/**
 * IntelliWare Teachable Machine Scanner Module (tm-scanner.js)
 *
 * Replaces the barcode-scanner.js module for product identification.
 * Uses Google Teachable Machine + TensorFlow.js to identify products
 * from a live camera feed.
 *
 * Global model is loaded on demand from:
 *   uploads/tm_models/global/model.json
 *
 * Class name → Product ID resolution:
 *   Regex /PRD-0*(\d+)/i extracts the integer from labels like
 *   "PRD-0002-ChiliMansi" → product_id = 2
 *
 * Confidence gating:
 *   None. Instantly picks the top-1 prediction on the first frame.
 *
 * Public API (window.TMScanner):
 *   TMScanner.init(config)             – wire up callbacks + DOM refs
 *   TMScanner.loadModel(categorySlug)  – fetch model from server
 *   TMScanner.startCamera()            – open camera stream
 *   TMScanner.stopCamera()             – release camera
 *   TMScanner.isRunning()              – boolean
 *   TMScanner.getCurrentSlug()         – currently loaded model slug
 */
(function (global) {
    'use strict';

    // ── Configuration constants ────────────────────────────────────────────
    var PREDICTION_FPS_LIMIT     = 8;     // max predictions per second
    var LOCK_DURATION_MS         = 3000;  // cooldown after a confirm before next scan
    var MODEL_BASE_PATH          = 'uploads/tm_models/global/';

    // ── State ──────────────────────────────────────────────────────────────
    var model         = null;
    var maxPredictions = 0;
    var currentSlug   = null;
    var stream        = null;
    var videoEl       = null;
    var rafId         = null;
    var isRunning     = false;
    var isLocked      = false;
    var lastFrameTime = 0;
    var frameDeltaMs  = Math.floor(1000 / PREDICTION_FPS_LIMIT);

    // ── Callbacks (set by init) ────────────────────────────────────────────
    var onAutoConfirm    = null;  // fn(productId, confidence, classLabel)
    var onPredicting     = null;  // fn(topLabel, topConf) — live update every frame
    var onModelLoaded    = null;  // fn(slug)
    var onModelError     = null;  // fn(errorMsg)
    var onCameraError    = null;  // fn(errorMsg)
    var onStopped        = null;  // fn()
    var onCameraStarted  = null;  // fn()

    // ── Class-name → product_id resolver ──────────────────────────────────
    /**
     * Extracts the integer product_id from a class label.
     * Handles:
     *   "PRD-0002-ChiliMansi"  → 2
     *   "PRD-002"              → 2
     *   "prd-0015"             → 15
     *   "15"                   → 15  (bare integer fallback)
     */
    function resolveProductId(className) {
        // Try PRD-NNNN... pattern first
        var match = className.match(/PRD-0*(\d+)/i);
        if (match) return parseInt(match[1], 10);

        // Try bare integer
        var bare = parseInt(className, 10);
        if (!isNaN(bare) && bare > 0) return bare;

        return null;
    }

    function loadModel() {
        var modelUrl    = MODEL_BASE_PATH + 'model.json';
        var metadataUrl = MODEL_BASE_PATH + 'metadata.json';

        // tmImage is from the Teachable Machine image CDN
        if (typeof tmImage === 'undefined') {
            var msg = 'Teachable Machine library not loaded. Check script tags.';
            if (onModelError) onModelError(msg);
            return Promise.reject(new Error(msg));
        }

        return tmImage.load(modelUrl, metadataUrl)
            .then(function (loadedModel) {
                model         = loadedModel;
                maxPredictions = model.getTotalClasses();
                currentSlug   = 'global';
                if (onModelLoaded) onModelLoaded('global');
                return loadedModel;
            })
            .catch(function (err) {
                var msg = 'Failed to load global model: ' + (err.message || err);
                if (onModelError) onModelError(msg);
                throw err;
            });
    }

    // ── Camera ─────────────────────────────────────────────────────────────
    function startCamera(videoElement) {
        if (!videoElement) {
            if (onCameraError) onCameraError('No video element provided.');
            return Promise.reject(new Error('No video element provided.'));
        }
        if (!model) {
            if (onCameraError) onCameraError('Model not loaded. Call loadModel() first.');
            return Promise.reject(new Error('Model not loaded.'));
        }
        videoEl = videoElement;

        var constraints = {
            video: {
                facingMode: { ideal: 'environment' },  // rear camera preferred
                width:  { ideal: 640 },
                height: { ideal: 480 },
            }
        };

        return navigator.mediaDevices.getUserMedia(constraints)
            .then(function (mediaStream) {
                stream = mediaStream;
                videoEl.srcObject = stream;
                return new Promise(function (resolve) {
                    videoEl.onloadedmetadata = function () {
                        videoEl.play();
                        resolve();
                    };
                });
            })
            .then(function () {
                isRunning    = true;
                isLocked     = false;
                lastFrameTime = 0;
                if (onCameraStarted) onCameraStarted();
                scheduleNextFrame();
            })
            .catch(function (err) {
                var msg = 'Camera access denied or unavailable: ' + (err.message || err);
                if (onCameraError) onCameraError(msg);
                throw err;
            });
    }

    function stopCamera() {
        isRunning = false;
        if (rafId) {
            cancelAnimationFrame(rafId);
            rafId = null;
        }
        if (stream) {
            stream.getTracks().forEach(function (t) { t.stop(); });
            stream = null;
        }
        if (videoEl) {
            videoEl.srcObject = null;
            videoEl = null;
        }
        isLocked     = false;
        if (onStopped) onStopped();
    }

    // ── Prediction loop ────────────────────────────────────────────────────
    function scheduleNextFrame() {
        if (!isRunning) return;
        rafId = requestAnimationFrame(function (timestamp) {
            if (!isRunning) return;
            if (timestamp - lastFrameTime >= frameDeltaMs) {
                lastFrameTime = timestamp;
                runPrediction();
            }
            scheduleNextFrame();
        });
    }

    function runPrediction() {
        if (!model || !videoEl || videoEl.readyState < 2) return;
        if (isLocked) return;

        model.predict(videoEl)
            .then(function (predictions) {
                if (!isRunning || isLocked) return;
                processPredictions(predictions);
            })
            .catch(function () { /* silently skip bad frames */ });
    }

    /**
     * Instantly picks the top-1 result and triggers auto confirm.
     * @param {Array<{className: string, probability: number}>} predictions
     */
    function processPredictions(predictions) {
        if (!predictions || predictions.length === 0) return;

        // Sort descending by probability
        var sorted = predictions.slice().sort(function (a, b) {
            return b.probability - a.probability;
        });

        var top1  = sorted[0];
        var topConf = top1.probability;

        // Emit live update
        if (onPredicting) {
            onPredicting(top1.className, topConf, [top1]);
        }

        // Instantly confirm top prediction
        triggerAutoConfirm(top1);
    }

    function triggerAutoConfirm(top1) {
        var productId = resolveProductId(top1.className);
        
        lockScanner();

        if (onAutoConfirm) {
            onAutoConfirm(productId, top1.probability, top1.className, currentSlug);
        }
    }


    function lockScanner() {
        isLocked     = true;
    }

    function unlockScanner() {
        isLocked     = false;
    }

    // ── Public API ─────────────────────────────────────────────────────────
    /**
     * Initialise callbacks and configuration overrides.
     * @param {Object} config
     *   config.onAutoConfirm(productId, confidence, classLabel, slug)
     *   config.onNeedsConfirm(candidates, slug)
     *   config.onPredicting(topLabel, topConf, allSorted)
     *   config.onModelLoaded(slug)
     *   config.onModelError(msg)
     *   config.onCameraError(msg)
     *   config.onCameraStarted()
     *   config.onStopped()
     *   config.autoConfirmThreshold  (default 0.80)
     *   config.ambiguityGapThreshold (default 0.15)
     *   config.stableFramesRequired  (default 4)
     *   config.predictionFpsLimit    (default 8)
     *   config.modelBasePath         (default 'uploads/tm_models/')
     */
    function init(config) {
        config = config || {};
        onAutoConfirm   = config.onAutoConfirm   || null;
        onPredicting    = config.onPredicting    || null;
        onModelLoaded   = config.onModelLoaded   || null;
        onModelError    = config.onModelError    || null;
        onCameraError   = config.onCameraError   || null;
        onCameraStarted = config.onCameraStarted || null;
        onStopped       = config.onStopped       || null;

        if (typeof config.predictionFpsLimit === 'number') {
            PREDICTION_FPS_LIMIT = config.predictionFpsLimit;
            frameDeltaMs = Math.floor(1000 / PREDICTION_FPS_LIMIT);
        }
        if (typeof config.modelBasePath === 'string') {
            MODEL_BASE_PATH = config.modelBasePath;
        }
    }

    /**
     * Call this after a manual-confirm tap so the scanner resumes.
     * Also called by the UI if the user dismisses the drawer without confirming.
     */
    function resumeAfterConfirm() {
        setTimeout(function () {
            unlockScanner();
        }, LOCK_DURATION_MS);
    }

    global.TMScanner = {
        init:               init,
        loadModel:          loadModel,
        startCamera:        startCamera,
        stopCamera:         stopCamera,
        isRunning:          function () { return isRunning; },
        getCurrentSlug:     function () { return currentSlug; },
        resolveProductId:   resolveProductId,
        resumeAfterConfirm: resumeAfterConfirm,
        unlockScanner:      unlockScanner,
        lockScanner:        lockScanner,
    };

}(window));
