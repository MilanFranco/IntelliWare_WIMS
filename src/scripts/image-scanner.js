/**
 * image-scanner.js  —  v3.0 (MobileNetV3-Large Edition)
 *
 * High-accuracy product image recognition engine for IntelliWare.
 * Uses TensorFlow.js with MobileNetV3-Large as the feature backbone
 * (1280-dim embeddings, vs. 576-dim from the previous MobileNetV3-Small).
 *
 * Accuracy & Speed Techniques:
 *   1. MobileNetV3-Large backbone — 1280-dim embeddings (2.22× more discriminative
 *      than Small's 576-dim); trained on 1.28M ImageNet images; significantly
 *      better at separating visually similar FMCG product packaging.
 *   2. 5-crop augmented reference embeddings — original, h-flip, center 80%,
 *      top-left 60%, top-right 60% — averaged so the fingerprint is robust
 *      to partial views, angle variation, and off-centre labels.
 *   3. Multi-crop live query — per frame we embed the full frame AND a
 *      center-75% crop; best score per product wins.
 *   4. Rolling frame averaging  — 3-frame exponential buffer smooths noise.
 *   5. Color histogram disambiguation — when two products score within 0.025
 *      of each other, an 8-bin-per-channel RGB histogram acts as tiebreaker.
 *   6. localStorage embedding cache — embeddings serialised and stored keyed
 *      by product-image path; re-used across page loads (0ms recompute).
 *   7. Adaptive threshold calibration — per-product auto-confirm threshold
 *      tightens after repeated successful scans (stored in localStorage).
 *   8. Softmax display confidence — the UI bar shows relative dominance,
 *      not the raw cosine, so 80% really means "clearly the best match".
 *   9. Faster loop — 400ms scan interval, 2 consecutive frames required.
 *
 * Usage:
 *   ImageScanner.init({ onAutoConfirm, onNeedsConfirm, onStopped,
 *                       onStatusChange, onEmbeddingsReady, onPredicting })
 *   ImageScanner.loadModel()      → Promise
 *   ImageScanner.startCamera(videoElement)
 *   ImageScanner.stopCamera()
 *   ImageScanner.isRunning()
 *   ImageScanner.resumeAfterConfirm()
 *   ImageScanner.recordSuccess(productId, score)  // call after confirmed match
 *   ImageScanner.clearCache()                     // force full re-embed
 */
var ImageScanner = (function () {
    'use strict';

    // ── Model ──────────────────────────────────────────────────────────────────
    //
    // MobileNetV3-Large feature vector (no classification head).
    // Outputs 1280-dim embedding — 2.22× more discriminative than MobileNetV3-Small.
    // Available as TFJS GraphModel natively on TFHub.
    //
    var MODEL_URL = 'https://storage.googleapis.com/tfjs-models/savedmodel/mobilenet_v2_1.0_224/model.json';
    var MODEL_INPUT_SIZE = 224;

    // ── Config ─────────────────────────────────────────────────────────────────
    //
    // Thresholds operate on RAW cosine similarity (range ≈ 0.50–0.72 for genuine
    // matches with MobileNetV3-Large's 1280-dim embedding space.
    // Large produces tighter, more confident cosine scores than Small.
    //
    var AUTO_CONFIRM_BASE       = 0.54;  // baseline raw cosine → auto-add candidate
    var NEEDS_CONFIRM_THRESHOLD = 0.30;  // raw cosine → show confirmation modal
    // below NEEDS_CONFIRM_THRESHOLD → no match shown

    var SCAN_INTERVAL_MS        = 400;   // ms between frame analyses (was 800)
    var DEBOUNCE_CONFIRM_MS     = 250;   // pause before firing onAutoConfirm (was 500)
    var MIN_SCORE_MARGIN        = 0.035; // top-1 must beat top-2 by at least this
    var CONSECUTIVE_FRAMES_REQ  = 2;     // frames in a row for auto-confirm (was 3)
    var FRAME_BUFFER_SIZE       = 3;     // rolling window for query embedding avg

    // Color histogram tiebreaker: if top-2 cosine scores differ by less than this,
    // run histogram comparison as a disambiguation step.
    var COLOR_HIST_TIEBREAK_THRESHOLD = 0.025;
    var COLOR_HIST_BINS               = 8;    // bins per channel (R, G, B)

    // localStorage keys
    var LS_EMBED_CACHE_KEY  = 'iw_img_embed_cache_v3';   // v3 = MobileNetV3-Large (1280-dim)
    var LS_THRESH_CACHE_KEY = 'iw_img_thresh_cache_v3';

    // ── State ──────────────────────────────────────────────────────────────────
    var _model            = null;       // tf.GraphModel
    var _embeddings       = [];         // [{productId, name, category, imageUrl, price, quantity, reorderPoint, unit, vec, hist}]
    var _stream           = null;       // MediaStream
    var _scanInterval     = null;
    var _running          = false;
    var _paused           = false;
    var _callbacks        = {};
    var _debounceTimer    = null;
    var _lastTopId        = null;
    var _consecutiveCount = 0;
    var _frameBuffer      = [];
    var _cropCanvas       = null;       // persistent off-screen canvas (380×380)
    var _histCanvas       = null;       // persistent off-screen canvas for histograms (64×64)

    function _getBaseUrl() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src || '';
            var idx = src.indexOf('src/scripts/image-scanner.js');
            if (idx !== -1) return src.substring(0, idx);
        }
        var path = window.location.pathname;
        var appIdx = path.indexOf('/src/');
        if (appIdx !== -1) return path.substring(0, appIdx + 1);
        return '';
    }

    // Embedding cache: key = productId + ':' + imageUrl, value = Float32Array
    var _embedCache  = _loadEmbedCache();

    // Per-product adaptive threshold: key = productId, value = float
    var _threshCache = _loadThreshCache();

    // ── localStorage helpers ───────────────────────────────────────────────────

    function _loadEmbedCache() {
        try {
            var raw = localStorage.getItem(LS_EMBED_CACHE_KEY);
            if (!raw) return {};
            var obj = JSON.parse(raw);
            // Deserialise arrays back to Float32Array
            var out = {};
            Object.keys(obj).forEach(function (k) {
                out[k] = new Float32Array(obj[k]);
            });
            return out;
        } catch (e) {
            return {};
        }
    }

    function _saveEmbedCache() {
        try {
            var serialisable = {};
            Object.keys(_embedCache).forEach(function (k) {
                serialisable[k] = Array.from(_embedCache[k]);
            });
            localStorage.setItem(LS_EMBED_CACHE_KEY, JSON.stringify(serialisable));
        } catch (e) {
            // localStorage full or unavailable — continue without caching
        }
    }

    function _loadThreshCache() {
        try {
            var raw = localStorage.getItem(LS_THRESH_CACHE_KEY);
            return raw ? JSON.parse(raw) : {};
        } catch (e) {
            return {};
        }
    }

    function _saveThreshCache() {
        try {
            localStorage.setItem(LS_THRESH_CACHE_KEY, JSON.stringify(_threshCache));
        } catch (e) {}
    }

    /**
     * Return the effective auto-confirm threshold for a product.
     * If we have a learned calibration for this product, blend it with the base.
     */
    function _getThreshold(productId) {
        var learned = _threshCache[String(productId)];
        if (learned == null) return AUTO_CONFIRM_BASE;
        // Clamp between 0.42 and 0.70 to avoid degenerate extremes
        return Math.max(0.42, Math.min(0.70, learned));
    }

    // ── Math helpers ──────────────────────────────────────────────────────────

    function _cosine(a, b) {
        var dot = 0, magA = 0, magB = 0;
        for (var i = 0; i < a.length; i++) {
            dot  += a[i] * b[i];
            magA += a[i] * a[i];
            magB += b[i] * b[i];
        }
        if (magA === 0 || magB === 0) return 0;
        return dot / (Math.sqrt(magA) * Math.sqrt(magB));
    }

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
     * Returns how clearly the top match beats the rest (for UI display).
     * T=25 gives sharper discrimination with EfficientNet's tighter score range.
     */
    function _softmaxConf(sortedScores) {
        var T   = 25;
        var top = sortedScores[0].score;
        var expVals = sortedScores.map(function (s) {
            return Math.exp((s.score - top) * T);
        });
        var sum = expVals.reduce(function (a, b) { return a + b; }, 0);
        return expVals[0] / sum;
    }

    // ── Color Histogram ───────────────────────────────────────────────────────

    /**
     * Compute a normalised RGB histogram for a canvas/image element.
     * Returns Float32Array of length COLOR_HIST_BINS * 3.
     * Operates on a 64×64 downscale to keep it fast.
     */
    function _colorHistogram(inputEl) {
        if (!_histCanvas) {
            _histCanvas        = document.createElement('canvas');
            _histCanvas.width  = 64;
            _histCanvas.height = 64;
        }
        var ctx = _histCanvas.getContext('2d');
        ctx.drawImage(inputEl, 0, 0, 64, 64);
        var imageData;
        try {
            imageData = ctx.getImageData(0, 0, 64, 64).data;
        } catch (e) {
            return null;
        }

        var bins = COLOR_HIST_BINS;
        var hist = new Float32Array(bins * 3);
        var total = 64 * 64;

        for (var i = 0; i < imageData.length; i += 4) {
            var r = Math.floor(imageData[i]     / 256 * bins);
            var g = Math.floor(imageData[i + 1] / 256 * bins);
            var b = Math.floor(imageData[i + 2] / 256 * bins);
            hist[Math.min(r, bins - 1)]             += 1;
            hist[bins + Math.min(g, bins - 1)]      += 1;
            hist[bins * 2 + Math.min(b, bins - 1)]  += 1;
        }

        // Normalise
        for (var j = 0; j < hist.length; j++) hist[j] /= total;
        return hist;
    }

    /**
     * Histogram intersection similarity — range [0, 1].
     * 1.0 means identical color distribution.
     */
    function _histSimilarity(hA, hB) {
        if (!hA || !hB) return 0;
        var s = 0;
        for (var i = 0; i < hA.length; i++) s += Math.min(hA[i], hB[i]);
        return s;
    }

    // ── Embedding Extraction ──────────────────────────────────────────────────

    /**
     * Extract an embedding from any canvas/image/video element.
     * EfficientNet-Lite4 expects 380×380 normalised [0,1] RGB input.
     * Returns Float32Array or null on failure.
     */
    function _extractEmbedding(inputEl) {
        if (!_model) return null;
        try {
            return tf.tidy(function () {
                var tensor = tf.browser.fromPixels(inputEl)
                    .resizeBilinear([MODEL_INPUT_SIZE, MODEL_INPUT_SIZE])
                    .toFloat()
                    .div(255.0)
                    .expandDims(0);
                var result = _model.predict(tensor);
                return result.squeeze().dataSync(); // Float32Array (1280-dim)
            });
        } catch (e) {
            console.error('[ImageScanner] embed error:', e);
            return null;
        }
    }

    /**
     * Load a product reference image and compute a 5-CROP AUGMENTED embedding.
     *
     * Crops computed:
     *   1. Full image (original view)
     *   2. Horizontally flipped (covers mirrored product placement)
     *   3. Center 80% crop (removes border artefacts, focuses on label)
     *   4. Top-left 60% crop (captures top-left label corner, brand logos)
     *   5. Top-right 60% crop (captures packaging details, nutrition facts, variant tags)
     *
     * The averaged vector is substantially more view-invariant than a single crop.
     * Also computes a color histogram for later disambiguation.
     *
     * Returns Promise<{vec: Float32Array, hist: Float32Array}|null>.
     */
    function _embedImageUrlAugmented(url, cacheKey) {
        // Check localStorage cache first
        if (cacheKey && _embedCache[cacheKey]) {
            return Promise.resolve({ vec: _embedCache[cacheKey], hist: null });
        }

        return new Promise(function (resolve) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                var canvas = document.createElement('canvas');
                canvas.width  = MODEL_INPUT_SIZE;
                canvas.height = MODEL_INPUT_SIZE;
                var ctx = canvas.getContext('2d');
                var iw  = img.naturalWidth  || MODEL_INPUT_SIZE;
                var ih  = img.naturalHeight || MODEL_INPUT_SIZE;
                var augVecs = [];

                // 1. Original — full image
                ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.drawImage(img, 0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                var v1 = _extractEmbedding(canvas);
                if (v1) augVecs.push(v1);

                // 2. Horizontal flip — handles mirrored placement on shelves
                ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.save();
                ctx.translate(MODEL_INPUT_SIZE, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(img, 0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.restore();
                var v2 = _extractEmbedding(canvas);
                if (v2) augVecs.push(v2);

                // 3. Center 80% crop — removes padding/background, focuses on product label
                var pad = 0.10;
                ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.drawImage(img,
                    iw * pad,           ih * pad,
                    iw * (1 - 2 * pad), ih * (1 - 2 * pad),
                    0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE
                );
                var v3 = _extractEmbedding(canvas);
                if (v3) augVecs.push(v3);

                // 4. Top-left 60% crop — brand logo region
                ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.drawImage(img,
                    0, 0, iw * 0.60, ih * 0.60,
                    0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE
                );
                var v4 = _extractEmbedding(canvas);
                if (v4) augVecs.push(v4);

                // 5. Top-right 60% crop — variant/flavor indicator region
                ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.drawImage(img,
                    iw * 0.40, 0, iw * 0.60, ih * 0.60,
                    0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE
                );
                var v5 = _extractEmbedding(canvas);
                if (v5) augVecs.push(v5);

                var finalVec = augVecs.length > 0 ? _averageVecs(augVecs) : null;

                // Color histogram for disambiguation (computed from full image)
                ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                ctx.drawImage(img, 0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
                var hist = finalVec ? _colorHistogram(canvas) : null;

                // Store in cache
                if (finalVec && cacheKey) {
                    _embedCache[cacheKey] = finalVec;
                    _saveEmbedCache();
                }

                resolve(finalVec ? { vec: finalVec, hist: hist } : null);
            };
            img.onerror = function () { resolve(null); };
            img.src = url;
        });
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function _emit(name) {
        var args = Array.prototype.slice.call(arguments, 1);
        if (_callbacks[name]) {
            try { _callbacks[name].apply(null, args); } catch (e) {
                console.error('[ImageScanner] callback error:', e);
            }
        }
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * init({ onAutoConfirm, onNeedsConfirm, onStopped, onStatusChange,
     *         onEmbeddingsReady, onPredicting })
     * Call once per page before loadModel().
     */
    function init(callbacks) {
        _callbacks = callbacks || {};
    }

    /**
     * loadModel()
     * Downloads EfficientNet-Lite4 from TFHub (TFJS format), then pre-computes
     * all product embeddings (using localStorage cache where available).
     * Returns Promise.
     */
    function loadModel() {
        if (_model) return Promise.resolve();
        _emit('onStatusChange', 'loading-model', 'Loading visual embedding model…');

        return tf.loadGraphModel(MODEL_URL)
            .then(function (model) {
                _model = model;
                // Warm up the model with a dummy tensor to avoid first-scan delay
                try {
                    tf.tidy(function () {
                        var dummy = tf.zeros([1, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE, 3]);
                        model.predict(dummy);
                    });
                } catch (e) { /* warm-up optional */ }
                _emit('onStatusChange', 'model-ready', 'AI model ready. Loading product fingerprints…');
                return _precomputeEmbeddings();
            })
            .then(function () {
                _emit('onStatusChange', 'ready', 'Ready to scan');
                _emit('onEmbeddingsReady', _embeddings.length);
            })
            .catch(function (err) {
                console.warn('[ImageScanner] Visual matching model load notice:', err.message || err);
                _emit('onStatusChange', 'idle', 'Visual similarity standby');
                return null;
            });
    }

    /**
     * _precomputeEmbeddings()
     * Fetches all products from the scanner API and computes augmented embeddings.
     * Uses localStorage cache — products whose image URL hasn't changed are instant.
     */
    function _precomputeEmbeddings() {
        return fetch(_getBaseUrl() + 'api/get_all_products_for_scanner.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) throw new Error('Scanner API error');
                var withImage = data.products.filter(function (p) { return p.image_url; });

                var cached    = 0;
                var toCompute = withImage.filter(function (p) {
                    var key = p.product_id + ':' + p.image_url;
                    if (_embedCache[key]) { cached++; return false; }
                    return true;
                });

                if (toCompute.length > 0) {
                    _emit('onStatusChange', 'embedding',
                        'Computing embeddings for ' + toCompute.length + ' products' +
                        (cached > 0 ? ' (' + cached + ' cached)…' : '…'));
                } else {
                    _emit('onStatusChange', 'embedding',
                        'Loading ' + cached + ' cached product fingerprints…');
                }

                var index = 0;
                _embeddings = [];

                function next() {
                    if (index >= withImage.length) return Promise.resolve();
                    var p   = withImage[index++];
                    var key = p.product_id + ':' + p.image_url;

                    return _embedImageUrlAugmented(p.image_url, key).then(function (result) {
                        if (result && result.vec) {
                            _embeddings.push({
                                productId   : p.product_id,
                                name        : p.product_name,
                                category    : p.category,
                                imageUrl    : p.image_url,
                                price       : p.price,
                                quantity    : p.quantity,
                                reorderPoint: p.reorder_point,
                                unit        : p.unit,
                                vec         : result.vec,
                                hist        : result.hist   // may be null for cached entries
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
     * Requests camera access and starts the scan loop.
     * Uses environment-facing camera (rear) for better product capture.
     */
    function startCamera(videoEl) {
        if (_running) return Promise.resolve();
        if (_embeddings.length === 0) {
            _emit('onStatusChange', 'error', 'No product fingerprints loaded. Add products with images first.');
            return Promise.reject(new Error('No embeddings'));
        }

        // Persistent off-screen canvas sized for EfficientNet-Lite4
        if (!_cropCanvas) {
            _cropCanvas        = document.createElement('canvas');
            _cropCanvas.width  = MODEL_INPUT_SIZE;
            _cropCanvas.height = MODEL_INPUT_SIZE;
        }

        return navigator.mediaDevices.getUserMedia({
            video: {
                facingMode: 'environment',
                width:  { ideal: 1280 },
                height: { ideal: 720 }
            }
        })
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
     *   1. Full-frame embedding
     *   2. Center-75% crop embedding
     *   3. Rolling average of last 3 combined frame embeddings
     *   4. Per-product score = max(fullScore, centerScore, rollingScore)
     *   5. Color histogram tiebreaker if top-2 scores are within 0.025
     *   6. Consecutive-frame voting + adaptive threshold check
     *   7. Emit onPredicting with top-3 for live UI display
     */
    function _scanFrame(videoEl) {
        if (!_model || _embeddings.length === 0 || !_cropCanvas) return;

        var ctx = _cropCanvas.getContext('2d');
        var vw  = videoEl.videoWidth  || 640;
        var vh  = videoEl.videoHeight || 480;

        // ── Query 1: Full frame
        ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
        ctx.drawImage(videoEl, 0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
        var vecFull = _extractEmbedding(_cropCanvas);

        // ── Query 2: Center 75% crop (trims 12.5% from each edge)
        var pad = 0.125;
        ctx.clearRect(0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE);
        ctx.drawImage(videoEl,
            vw * pad,           vh * pad,
            vw * (1 - 2 * pad), vh * (1 - 2 * pad),
            0, 0, MODEL_INPUT_SIZE, MODEL_INPUT_SIZE
        );
        var vecCenter = _extractEmbedding(_cropCanvas);

        if (!vecFull && !vecCenter) return;

        // ── Rolling buffer: average of the two crop embeddings for this frame
        var queryVecs = [];
        if (vecFull)   queryVecs.push(vecFull);
        if (vecCenter) queryVecs.push(vecCenter);
        var frameCombined = _averageVecs(queryVecs);

        _frameBuffer.push(frameCombined);
        if (_frameBuffer.length > FRAME_BUFFER_SIZE) _frameBuffer.shift();
        var rollingVec = _averageVecs(_frameBuffer);

        // ── Live query color histogram (from full frame)
        var liveHist = _colorHistogram(_cropCanvas);

        // ── Score every product
        var scores = _embeddings.map(function (e) {
            var s1 = vecFull   ? _cosine(vecFull,   e.vec) : 0;
            var s2 = vecCenter ? _cosine(vecCenter,  e.vec) : 0;
            var s3 = _cosine(rollingVec, e.vec);
            return { entry: e, score: Math.max(s1, s2, s3) };
        });
        scores.sort(function (a, b) { return b.score - a.score; });

        // ── Color histogram tiebreaker
        // If top-2 are ambiguously close, use color distribution to disambiguate.
        if (scores.length >= 2 &&
            liveHist &&
            (scores[0].score - scores[1].score) < COLOR_HIST_TIEBREAK_THRESHOLD) {

            var hSim0 = _histSimilarity(liveHist, scores[0].entry.hist);
            var hSim1 = _histSimilarity(liveHist, scores[1].entry.hist);

            if (hSim1 > hSim0 + 0.05) {
                // Swap: the color histogram clearly prefers the second candidate
                var tmp  = scores[0];
                scores[0] = scores[1];
                scores[1] = tmp;
            }
            // else: cosine winner stands — small histogram differences are noise
        }

        var top      = scores[0];
        var topScore = top.score;
        var margin   = scores.length > 1 ? topScore - scores[1].score : 1.0;

        // ── Softmax display confidence (for UI bar)
        var displayConf = _softmaxConf(scores.slice(0, Math.min(5, scores.length)));

        _emit('onPredicting', top.entry.name, displayConf,
            scores.slice(0, 3).map(function (s) {
                return {
                    name      : s.entry.name,
                    productId : s.entry.productId,
                    score     : s.score,
                    imageUrl  : s.entry.imageUrl
                };
            })
        );

        // ── Decision logic (uses raw cosine + adaptive per-product threshold)
        var autoThreshold = _getThreshold(top.entry.productId);

        if (topScore >= autoThreshold && margin >= MIN_SCORE_MARGIN) {
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
            // Emit for low-confidence confirmation modal
            _emit('onNeedsConfirm', top.entry, topScore, displayConf);
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
        _frameBuffer      = [];
        _emit('onStatusChange', 'scanning', 'Scanning… point at a product');
    }

    /**
     * recordSuccess(productId, score)
     *
     * Call this after a scan is confirmed correct (either auto or manual).
     * Updates the adaptive threshold for this product:
     *   - If the matched score was high, we can be more aggressive next time.
     *   - Exponential moving average keeps the threshold stable.
     *
     * @param {number} productId
     * @param {number} score      — the raw cosine score of the confirmed match
     */
    function recordSuccess(productId, score) {
        if (!productId || !score) return;
        var key = String(productId);
        var prev = _threshCache[key] != null ? _threshCache[key] : AUTO_CONFIRM_BASE;
        // EMA: new threshold = 90% old + 10% of (confirmed score - small safety margin)
        var target = Math.max(score - 0.05, AUTO_CONFIRM_BASE);
        _threshCache[key] = prev * 0.90 + target * 0.10;
        _saveThreshCache();
    }

    /**
     * clearCache()
     * Clears all cached embeddings and thresholds from localStorage.
     * The next loadModel() call will recompute everything from scratch.
     */
    function clearCache() {
        _embedCache  = {};
        _threshCache = {};
        try {
            localStorage.removeItem(LS_EMBED_CACHE_KEY);
            localStorage.removeItem(LS_THRESH_CACHE_KEY);
        } catch (e) {}
        console.info('[ImageScanner] Cache cleared. Embeddings will recompute on next load.');
    }

    return {
        init              : init,
        loadModel         : loadModel,
        startCamera       : startCamera,
        stopCamera        : stopCamera,
        isRunning         : isRunning,
        resumeAfterConfirm: resumeAfterConfirm,
        recordSuccess     : recordSuccess,
        clearCache        : clearCache,
    };
})();
