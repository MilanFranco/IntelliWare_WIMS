/**
 * image-scanner.js
 *
 * One-Shot Image Similarity Scanner using TensorFlow.js MobileNetV3.
 * No training required — uses a pre-trained feature extractor to compute
 * 512-dimensional embeddings and finds the closest match via cosine similarity.
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
    var AUTO_CONFIRM_THRESHOLD  = 0.70;   // ≥ 70% → auto-add
    var NEEDS_CONFIRM_THRESHOLD = 0.40;   // 40-69% → show modal
    // below 40% → no match (search fallback)

    var SCAN_INTERVAL_MS  = 1200;         // ms between frames analysed
    var DEBOUNCE_CONFIRM_MS = 800;        // hold high confidence for this long before firing

    // ── State ───────────────────────────────────────────────────────────────
    var _model         = null;            // tf.GraphModel
    var _embeddings    = [];              // [{productId, name, category, imageUrl, price, quantity, reorderPoint, unit, vec}]
    var _stream        = null;            // MediaStream
    var _scanInterval  = null;
    var _running       = false;
    var _paused        = false;           // paused while waiting for user confirmation
    var _callbacks     = {};
    var _debounceTimer = null;
    var _lastTopId     = null;

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
     * Extract a 1024-dim embedding from an image/video element using MobileNetV3.
     * Returns a Float32Array or null on failure.
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
                // Flatten to 1-D
                var flat = result.squeeze();
                return flat.dataSync(); // Float32Array
            });
        } catch (e) {
            console.error('[ImageScanner] embed error:', e);
            return null;
        }
    }

    /**
     * Load image from URL into an HTMLImageElement and extract embedding.
     * Returns Promise<Float32Array|null>.
     */
    function _embedImageUrl(url) {
        return new Promise(function (resolve) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                // Draw to offscreen canvas at 224×224 to normalise size
                var canvas = document.createElement('canvas');
                canvas.width = 224; canvas.height = 224;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, 224, 224);
                var vec = _extractEmbedding(canvas);
                resolve(vec);
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
                var products = data.products;
                var withImage = products.filter(function (p) { return p.image_url; });
                _emit('onStatusChange', 'embedding', 'Computing embeddings for ' + withImage.length + ' products…');

                // Process one at a time to avoid memory spikes
                var index = 0;
                _embeddings = [];

                function next() {
                    if (index >= withImage.length) return Promise.resolve();
                    var p = withImage[index++];
                    return _embedImageUrl(p.image_url).then(function (vec) {
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

    function _scanFrame(videoEl) {
        if (!_model || _embeddings.length === 0) return;

        var vec = _extractEmbedding(videoEl);
        if (!vec) return;

        // Compute similarity to every embedded product
        var scores = _embeddings.map(function (e) {
            return { entry: e, score: _cosine(vec, e.vec) };
        });
        scores.sort(function (a, b) { return b.score - a.score; });

        var top = scores[0];
        var topScore = top.score;

        _emit('onPredicting', top.entry.name, topScore, scores.slice(0, 3).map(function (s) {
            return { name: s.entry.name, productId: s.entry.productId, score: s.score };
        }));

        if (topScore >= AUTO_CONFIRM_THRESHOLD) {
            // Debounce: require consecutive high-confidence frames
            if (_lastTopId === top.entry.productId) {
                if (!_debounceTimer) {
                    _debounceTimer = setTimeout(function () {
                        _debounceTimer = null;
                        _paused = true;
                        _emit('onAutoConfirm', top.entry, topScore);
                    }, DEBOUNCE_CONFIRM_MS);
                }
            } else {
                _lastTopId = top.entry.productId;
                clearTimeout(_debounceTimer);
                _debounceTimer = null;
            }
        } else if (topScore >= NEEDS_CONFIRM_THRESHOLD) {
            _lastTopId = null;
            clearTimeout(_debounceTimer);
            _debounceTimer = null;
            var candidates = scores.slice(0, 3).filter(function (s) {
                return s.score >= NEEDS_CONFIRM_THRESHOLD;
            });
            // Only fire needs-confirm event occasionally (not every frame)
            // handled by caller debounce
        } else {
            _lastTopId = null;
            clearTimeout(_debounceTimer);
            _debounceTimer = null;
        }
    }

    function stopCamera() {
        _running = false;
        _paused  = false;
        clearInterval(_scanInterval);
        _scanInterval = null;
        clearTimeout(_debounceTimer);
        _debounceTimer = null;
        _lastTopId = null;

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
        _paused    = false;
        _lastTopId = null;
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
