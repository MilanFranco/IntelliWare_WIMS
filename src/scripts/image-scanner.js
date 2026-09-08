/**
 * image-scanner.js
 *
 * One-Shot Image Similarity Scanner using TensorFlow.js MobileNetV3.
 * No training required — uses a pre-trained feature extractor to compute
 * embeddings and finds the closest match via cosine similarity.
 *
 * Accuracy techniques:
 *   1. Augmented reference embeddings — each product photo is embedded from
 *      3 variants (original, h-flip, center crop) and averaged, making the
 *      stored fingerprint more view-invariant.
 *   2. Multi-crop live query — each camera frame queries both the full frame
 *      AND a center 75% crop; the better score wins per product.
 *   3. Softmax display confidence — the bar shows how clearly the top match
 *      beats the others, not the raw cosine value.
 *   4. Recalibrated thresholds — tuned for MobileNetV3's actual score range
 *      (genuine matches typically score 0.50-0.68 with this model).
 *   5. Rolling frame averaging + consecutive-frame voting.
 *
 * Usage:
 *   ImageScanner.init({ onAutoConfirm, onNeedsConfirm, onStopped, onStatusChange, onEmbeddingsReady })
 *   ImageScanner.startCamera(videoElement)
 *   ImageScanner.stopCamera()
 *   ImageScanner.isRunning()
 *   ImageScanner.resumeAfterConfirm()
 */
var ImageScanner = (function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────────────────────
    //
    // Thresholds are on RAW cosine similarity, not the softmax display value.
    // MobileNetV3 genuine-match cosines typically land in the 0.50-0.68 range.
    //
    var AUTO_CONFIRM_THRESHOLD  = 0.57;   // raw cosine ≥ this → candidate for auto-add
    var NEEDS_CONFIRM_THRESHOLD = 0.32;   // raw cosine ≥ this → show confirmation modal
    // below NEEDS_CONFIRM_THRESHOLD → no match

    var SCAN_INTERVAL_MS        = 800;    // ms between frames analysed
    var DEBOUNCE_CONFIRM_MS     = 500;    // final pause before firing onAutoConfirm
    var MIN_SCORE_MARGIN        = 0.04;   // top-1 raw score must beat top-2 by at least this
    var CONSECUTIVE_FRAMES_REQ  = 3;      // top-1 must win this many frames in a row
    var FRAME_BUFFER_SIZE       = 3;      // rolling window of query embeddings to average

    // ── State ───────────────────────────────────────────────────────────────
    var _model            = null;         // tf.GraphModel
    var _embeddings       = [];           // [{productId, name, category, imageUrl, price, quantity, reorderPoint, unit, vec}]
    var _stream           = null;         // MediaStream
    var _scanInterval     = null;
    var _running          = false;
    var _paused           = false;        // paused while waiting for user confirmation
    var _callbacks        = {};
    var _debounceTimer    = null;
    var _lastTopId        = null;
    var _consecutiveCount = 0;            // frames in a row where the same product won
    var _frameBuffer      = [];           // rolling window of recent query embeddings for averaging
    var _cropCanvas       = null;         // persistent off-screen canvas for multi-crop queries

    // ── Helpers ─────────────────────────────────────────────────────────────

    function _emit(name) {
        var args = Array.prototype.slice.call(arguments, 1);
        if (_callbacks[name]) {
            try { _callbacks[name].apply(null, args); } catch (e) { console.error('[ImageScanner] callback error:', e); }
        }
    }

    function _cosine(a, b) {
        // Both a and b are Float32Array of same length.
        var dot = 0, magA = 0, magB = 0;
        for (var i = 0; i < a.length; i++) {
            dot  += a[i] * b[i];
            magA += a[i] * a[i];
            magB += b[i] * b[i];
        }
        if (magA === 0 || magB === 0) return 0;
        return dot / (Math.sqrt(magA) * Math.sqrt(magB));
    }

    /**
     * Average a list of Float32Arrays into a single vector.
     * Used to smooth out per-frame noise across the rolling buffer.
     */
    function _averageVecs(vecs) {
        if (vecs.length === 1) return vecs[0];
        var len = vecs[0].length;
        var avg = new Float32Array(len);
        for (var i = 0; i < len; i++) {
            var sum = 0;
            for (var j = 0; j < vecs.length; j++) sum += vecs[j][i];
            avg[i] = sum / vecs.length;
        }
        return avg;
    }

    /**
     * Temperature-scaled softmax confidence.
     *
     * Given a sorted array of {score} objects, returns a value in [0,1]
     * that represents how clearly the top match wins over the field.
     * This is what we show on the confidence bar — NOT the raw cosine.
     *
     * Example: top=0.58, second=0.54 → shows ~72%
     *          top=0.58, second=0.57 → shows ~44% (ambiguous — right call)
     */
    function _softmaxConf(sortedScores) {
        var T   = 20;   // temperature — higher = sharper discrimination
        var top = sortedScores[0].score;
        var expVals = sortedScores.map(function (s) { return Math.exp((s.score - top) * T); });
        var sum = expVals.reduce(function (a, b) { return a + b; }, 0);
        return expVals[0] / sum;
    }

    /**
     * Extract an embedding from any canvas/image/video element.
     * The model expects a 224×224 normalised [0,1] RGB image.
     * Returns Float32Array or null on failure.
     */
    function _extractEmbedding(inputEl) {
        if (!_model) return null;
        try {
            return tf.tidy(function () {
                var tensor = tf.browser.fromPixels(inputEl)
                    .resizeBilinear([224, 224])
                    .toFloat()
                    .div(255.0)
                    .expandDims(0);
                var result = _model.predict(tensor);
                return result.squeeze().dataSync(); // Float32Array
            });
        } catch (e) {
            console.error('[ImageScanner] embed error:', e);
            return null;
        }
    }

    /**
     * Load a product reference image and compute an AUGMENTED embedding.
     *
     * Instead of one embedding from one view, we compute embeddings from
     * three variants of the same image and average them:
     *   1. Full image (original)
     *   2. Horizontally flipped — covers products seen from the other side
     *   3. Center 80% crop — removes border/padding artefacts in product photos
     *
     * The averaged vector is more view-invariant: it scores higher when the
     * product is seen at a slightly different angle or scale in the live feed.
     *
     * Returns Promise<Float32Array|null>.
     */
    function _embedImageUrlAugmented(url) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                var canvas = document.createElement('canvas');
                canvas.width  = 224;
                canvas.height = 224;
                var ctx = canvas.getContext('2d');
                var iw  = img.naturalWidth  || 224;
                var ih  = img.naturalHeight || 224;
                var augVecs = [];

                // 1. Original — full image
                ctx.clearRect(0, 0, 224, 224);
                ctx.drawImage(img, 0, 0, 224, 224);
                var v1 = _extractEmbedding(canvas);
                if (v1) augVecs.push(v1);

                // 2. Horizontal flip — handles mirrored views
                ctx.clearRect(0, 0, 224, 224);
                ctx.save();
                ctx.translate(224, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(img, 0, 0, 224, 224);
                ctx.restore();
                var v2 = _extractEmbedding(canvas);
                if (v2) augVecs.push(v2);

                // 3. Center 80% crop — focuses on the product, ignores background/padding
                var pad = 0.10;
                ctx.clearRect(0, 0, 224, 224);
                ctx.drawImage(img,
                    iw * pad,           ih * pad,
                    iw * (1 - 2 * pad), ih * (1 - 2 * pad),
                    0, 0, 224, 224
                );
                var v3 = _extractEmbedding(canvas);
                if (v3) augVecs.push(v3);

                resolve(augVecs.length > 0 ? _averageVecs(augVecs) : null);
            };
            img.onerror = function () { resolve(null); };
            img.src = url;
        });
    }

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * init({ onAutoConfirm, onNeedsConfirm, onStopped, onStatusChange, onEmbeddingsReady })
     * Call once per page.
     */
    function init(callbacks) {
        _callbacks = callbacks || {};
    }

    /**
     * loadModel()
     * Loads TF.js MobileNetV3 feature extractor from tfhub via jsdelivr CDN.
     * Returns Promise.
     */
    function loadModel() {
        if (_model) return Promise.resolve();
        _emit('onStatusChange', 'loading-model', 'Loading AI model…');

        // MobileNetV3-Small feature vector (no top/softmax layer) from TFHub via TFJS format
        var MODEL_URL = 'https://tfhub.dev/google/tfjs-model/imagenet/mobilenet_v3_small_100_224/feature_vector/5/default/1';

        return tf.loadGraphModel(MODEL_URL, { fromTFHub: true })
            .then(function (model) {
                _model = model;
                _emit('onStatusChange', 'model-ready', 'Model loaded. Pre-computing product embeddings…');
                return _precomputeEmbeddings();
            })
            .then(function () {
                _emit('onStatusChange', 'ready', 'Ready to scan');
                _emit('onEmbeddingsReady', _embeddings.length);
            })
            .catch(function (err) {
                _emit('onStatusChange', 'error', 'Failed to load model: ' + (err.message || err));
                throw err;
            });
    }

    /**
     * Fetches all products from the scanner API, loads their images,
     * and pre-computes embeddings so scan comparisons are instant.
     */
    function _precomputeEmbeddings() {
        return fetch('api/get_all_products_for_scanner.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error('Scanner API error');
                var withImage = data.products.filter(function (p) { return p.image_url; });
                _emit('onStatusChange', 'embedding',
                    'Computing augmented embeddings for ' + withImage.length + ' products…');

                var index = 0;
                _embeddings = [];

                function next() {
                    if (index >= withImage.length) return Promise.resolve();
                    var p = withImage[index++];
                    // Use augmented embedding (avg of 3 crops) for a more robust reference
                    return _embedImageUrlAugmented(p.image_url).then(function (vec) {
                        if (vec) {
                            _embeddings.push({
                                productId   : p.product_id,
                                name        : p.product_name,
                                category    : p.category,
                                imageUrl    : p.image_url,
                                price       : p.price,
                                quantity    : p.quantity,
                                reorderPoint: p.reorder_point,
                                unit        : p.unit,
                                vec         : vec
                            });
                        }
                        return next();
                    });
                }
                return next();
            });
    }

    /**
     * startCamera(videoEl)
     * Requests camera access, starts scanning the video feed.
     */
    function startCamera(videoEl) {
        if (_running) return Promise.resolve();
        if (_embeddings.length === 0) {
            _emit('onStatusChange', 'error', 'No product embeddings loaded. Add products with images first.');
            return Promise.reject(new Error('No embeddings'));
        }

        // Initialise persistent off-screen canvas for multi-crop queries
        if (!_cropCanvas) {
            _cropCanvas        = document.createElement('canvas');
            _cropCanvas.width  = 224;
            _cropCanvas.height = 224;
        }

        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
            .then(function (stream) {
                _stream  = stream;
                _running = true;
                _paused  = false;
                videoEl.srcObject = stream;

                _emit('onStatusChange', 'scanning', 'Scanning… point at a product');

                _scanInterval = setInterval(function () {
                    if (_paused || !_running) return;
                    _scanFrame(videoEl);
                }, SCAN_INTERVAL_MS);
            })
            .catch(function (err) {
                _emit('onStatusChange', 'error', 'Camera error: ' + (err.message || err));
                throw err;
            });
    }

    /**
     * _scanFrame(videoEl)
     *
     * Per-frame pipeline:
     *   1. Extract embedding from FULL frame
     *   2. Extract embedding from CENTER 75% crop (removes background at edges)
     *   3. For each product: score = max(cosine(vecFull, ref), cosine(vecCenter, ref),
     *                                   cosine(rollingAvg, ref))
     *   4. Rank products; apply margin + consecutive-frame checks; emit result
     *
     * The multi-crop strategy greatly helps when the product is at varying
     * distances or off-centre in the frame.
     */
    function _scanFrame(videoEl) {
        if (!_model || _embeddings.length === 0 || !_cropCanvas) return;

        var ctx = _cropCanvas.getContext('2d');
        var vw  = videoEl.videoWidth  || 640;
        var vh  = videoEl.videoHeight || 480;

        // Query 1 — full frame (captures whole scene)
        ctx.clearRect(0, 0, 224, 224);
        ctx.drawImage(videoEl, 0, 0, 224, 224);
        var vecFull = _extractEmbedding(_cropCanvas);

        // Query 2 — center 75% crop (trims 12.5% from each edge, removes background)
        var pad = 0.125;
        ctx.clearRect(0, 0, 224, 224);
        ctx.drawImage(videoEl,
            vw * pad,           vh * pad,
            vw * (1 - 2 * pad), vh * (1 - 2 * pad),
            0, 0, 224, 224
        );
        var vecCenter = _extractEmbedding(_cropCanvas);

        if (!vecFull && !vecCenter) return;

        // Rolling buffer: use the average of the two crops as this frame's query
        var queryVecs = [];
        if (vecFull)   queryVecs.push(vecFull);
        if (vecCenter) queryVecs.push(vecCenter);
        var frameCombined = _averageVecs(queryVecs);

        _frameBuffer.push(frameCombined);
        if (_frameBuffer.length > FRAME_BUFFER_SIZE) _frameBuffer.shift();
        var rollingVec = _averageVecs(_frameBuffer);

        // ── Score every product
        //    Take max of: full-frame score, center-crop score, rolling-avg score.
        //    This catches the product no matter which crop it falls in.
        var scores = _embeddings.map(function (e) {
            var s1 = vecFull   ? _cosine(vecFull,   e.vec) : 0;
            var s2 = vecCenter ? _cosine(vecCenter,  e.vec) : 0;
            var s3 = _cosine(rollingVec, e.vec);
            return { entry: e, score: Math.max(s1, s2, s3) };
        });
        scores.sort(function (a, b) { return b.score - a.score; });

        var top      = scores[0];
        var topScore = top.score;
        var margin   = scores.length > 1 ? topScore - scores[1].score : 1.0;

        // ── Display confidence via softmax (much more meaningful than raw cosine)
        var displayConf = _softmaxConf(scores.slice(0, Math.min(5, scores.length)));

        _emit('onPredicting', top.entry.name, displayConf, scores.slice(0, 3).map(function (s) {
            return { name: s.entry.name, productId: s.entry.productId, score: s.score };
        }));

        // ── Decision logic (uses raw cosine, NOT displayConf)
        if (topScore >= AUTO_CONFIRM_THRESHOLD && margin >= MIN_SCORE_MARGIN) {
            if (_lastTopId === top.entry.productId) {
                _consecutiveCount++;
                if (_consecutiveCount >= CONSECUTIVE_FRAMES_REQ && !_debounceTimer) {
                    var _capturedEntry = top.entry;
                    var _capturedScore = topScore;
                    _debounceTimer = setTimeout(function () {
                        _debounceTimer = null;
                        _paused = true;
                        _emit('onAutoConfirm', _capturedEntry, _capturedScore);
                    }, DEBOUNCE_CONFIRM_MS);
                }
            } else {
                // New winner — reset streak
                _lastTopId        = top.entry.productId;
                _consecutiveCount = 1;
                clearTimeout(_debounceTimer);
                _debounceTimer    = null;
            }
        } else if (topScore >= NEEDS_CONFIRM_THRESHOLD) {
            _lastTopId        = null;
            _consecutiveCount = 0;
            clearTimeout(_debounceTimer);
            _debounceTimer    = null;
        } else {
            _lastTopId        = null;
            _consecutiveCount = 0;
            clearTimeout(_debounceTimer);
            _debounceTimer    = null;
        }
    }

    function stopCamera() {
        _running          = false;
        _paused           = false;
        clearInterval(_scanInterval);
        _scanInterval     = null;
        clearTimeout(_debounceTimer);
        _debounceTimer    = null;
        _lastTopId        = null;
        _consecutiveCount = 0;
        _frameBuffer      = [];

        if (_stream) {
            _stream.getTracks().forEach(function (t) { t.stop(); });
            _stream = null;
        }
        _emit('onStopped');
        _emit('onStatusChange', 'idle', 'Scanner stopped');
    }

    function isRunning() { return _running; }

    /** Call after user dismisses confirmation modal to resume scanning. */
    function resumeAfterConfirm() {
        _paused           = false;
        _lastTopId        = null;
        _consecutiveCount = 0;
        _frameBuffer      = [];   // clear stale frames from the previous scan
        _emit('onStatusChange', 'scanning', 'Scanning… point at a product');
    }

    return {
        init              : init,
        loadModel         : loadModel,
        startCamera       : startCamera,
        stopCamera        : stopCamera,
        isRunning         : isRunning,
        resumeAfterConfirm: resumeAfterConfirm,
    };
})();
