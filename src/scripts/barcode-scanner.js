/**
 * IntelliWare Barcode Scanner — webcam scanning via html5-qrcode.
 * Supports EAN-13, UPC-A/E, Code 128, Code 39, and other common 1D formats.
 */
(function () {
    'use strict';

    var scanner = null;
    var isScanning = false;
    var isProcessingScan = false;
    var lastScanned = '';
    var lastScanTime = 0;
    var onScanCallback = null;
    var cameraPrefetchPromise = null;
    var focusAssistTimer = null;

    var DUPLICATE_COOLDOWN_MS = 2500;
    var SCAN_FPS = 25;
    var FOCUS_ASSIST_INTERVAL_MS = 2500;

    var overlay = null;
    var readerEl = null;
    var statusEl = null;

    var REAR_CAMERA_LABELS = ['back', 'rear', 'environment', 'rück', 'arrière', 'posteriore', 'traseira'];

    function getOverlay() {
        if (!overlay) overlay = document.getElementById('barcode-scanner-overlay');
        return overlay;
    }

    function getReaderEl() {
        if (!readerEl) readerEl = document.getElementById('barcode-scanner-reader');
        return readerEl;
    }

    function getStatusEl() {
        if (!statusEl) statusEl = document.getElementById('barcode-scanner-status');
        return statusEl;
    }

    function setStatus(msg) {
        var el = getStatusEl();
        if (el) el.textContent = msg;
    }

    function getSupportedFormats() {
        if (typeof Html5QrcodeSupportedFormats === 'undefined') return undefined;
        return [
            Html5QrcodeSupportedFormats.EAN_13,
            Html5QrcodeSupportedFormats.EAN_8,
            Html5QrcodeSupportedFormats.UPC_A,
            Html5QrcodeSupportedFormats.UPC_E,
            Html5QrcodeSupportedFormats.CODE_128,
            Html5QrcodeSupportedFormats.CODE_39,
            Html5QrcodeSupportedFormats.CODE_93,
            Html5QrcodeSupportedFormats.ITF
        ];
    }

    /** Visual scan frame — dimensions unchanged from original UI. */
    function getScanBox(viewfinderWidth, viewfinderHeight) {
        var width = Math.min(viewfinderWidth * 0.85, 400);
        var height = Math.min(viewfinderHeight * 0.4, 120);
        return { width: width, height: height };
    }

    function getScanConfig() {
        return {
            fps: SCAN_FPS,
            qrbox: getScanBox,
            formatsToSupport: getSupportedFormats(),
            disableFlip: true,
            rememberLastUsedCamera: true,
            experimentalFeatures: { useBarCodeDetectorIfSupported: true }
        };
    }

    function prefetchCameras() {
        if (cameraPrefetchPromise) return cameraPrefetchPromise;
        if (typeof Html5Qrcode === 'undefined') {
            cameraPrefetchPromise = Promise.resolve([]);
            return cameraPrefetchPromise;
        }

        cameraPrefetchPromise = Html5Qrcode.getCameras()
            .then(function (cameras) {
                return cameras || [];
            })
            .catch(function () {
                return [];
            });

        return cameraPrefetchPromise;
    }

    function selectRearCameraId(cameras) {
        if (!cameras || !cameras.length) return null;

        for (var i = 0; i < cameras.length; i++) {
            var label = (cameras[i].label || '').toLowerCase();
            for (var j = 0; j < REAR_CAMERA_LABELS.length; j++) {
                if (label.indexOf(REAR_CAMERA_LABELS[j]) !== -1) {
                    return cameras[i].id;
                }
            }
        }

        return cameras.length > 1 ? cameras[cameras.length - 1].id : cameras[0].id;
    }

    function destroyScanner() {
        stopFocusAssist();
        if (!scanner) {
            isScanning = false;
            return Promise.resolve();
        }

        var instance = scanner;
        scanner = null;
        isScanning = false;

        return instance.stop().catch(function () {
            /* ignore stop errors */
        }).then(function () {
            try {
                instance.clear();
            } catch (e) {
                /* ignore clear errors */
            }
        });
    }

    function createScannerInstance() {
        var reader = getReaderEl();
        if (!reader) return null;

        reader.innerHTML = '';
        scanner = new Html5Qrcode('barcode-scanner-reader', false);
        return scanner;
    }

    function stopFocusAssist() {
        if (focusAssistTimer) {
            clearInterval(focusAssistTimer);
            focusAssistTimer = null;
        }
    }

    function applyContinuousFocus() {
        if (!scanner || !isScanning || typeof scanner.applyVideoConstraints !== 'function') {
            return;
        }

        scanner.applyVideoConstraints({
            focusMode: 'continuous',
            advanced: [{ focusMode: 'continuous' }]
        }).catch(function () {
            /* focusMode not supported on this device */
        });
    }

    function applyHighResConstraints() {
        if (!scanner || !isScanning || typeof scanner.applyVideoConstraints !== 'function') {
            return;
        }

        scanner.applyVideoConstraints({
            width: { ideal: 1280, min: 640 },
            height: { ideal: 720, min: 480 },
            frameRate: { ideal: 30, min: 15 }
        }).catch(function () {
            /* resolution upgrade not supported */
        });
    }

    function startFocusAssist() {
        stopFocusAssist();
        applyContinuousFocus();
        applyHighResConstraints();
        focusAssistTimer = setInterval(applyContinuousFocus, FOCUS_ASSIST_INTERVAL_MS);
    }

    function stopScanner() {
        return destroyScanner();
    }

    function closeScanner() {
        stopScanner().then(function () {
            var el = getOverlay();
            if (el) el.classList.remove('active');
            onScanCallback = null;
            if (!document.querySelector('.modal-overlay.active')) {
                document.body.style.overflow = '';
            }
        });
    }

    function isDuplicateScan(code) {
        return code === lastScanned && (Date.now() - lastScanTime) < DUPLICATE_COOLDOWN_MS;
    }

    function handleScanSuccess(decodedText) {
        if (isProcessingScan) return;

        var code = (decodedText || '').trim();
        if (!code) return;
        if (isDuplicateScan(code)) return;

        isProcessingScan = true;
        lastScanned = code;
        lastScanTime = Date.now();

        setStatus('Barcode detected: ' + code);

        var callback = onScanCallback;
        closeScanner();

        if (typeof callback === 'function') {
            callback(code);
        }
    }

    function beginScanning(cameraConfig) {
        if (!scanner) {
            return Promise.reject(new Error('Scanner not initialized.'));
        }

        return scanner.start(
            cameraConfig,
            getScanConfig(),
            handleScanSuccess,
            function () {
                /* continuous mode — ignore per-frame decode misses */
            }
        ).then(function () {
            isScanning = true;
            startFocusAssist();
        });
    }

    function resolveCameraConfig(cameras) {
        if (cameras && cameras.length) {
            return selectRearCameraId(cameras);
        }
        return { facingMode: { ideal: 'environment' } };
    }

    function startScannerWithFallback() {
        if (typeof Html5Qrcode === 'undefined') {
            setStatus('Scanner library not loaded.');
            return Promise.resolve();
        }

        return destroyScanner().then(function () {
            if (!createScannerInstance()) {
                throw new Error('Scanner reader element not found.');
            }

            setStatus('Scanning… align barcode within the frame.');
            return prefetchCameras();
        }).then(function (cameras) {
            return beginScanning(resolveCameraConfig(cameras));
        }).catch(function () {
            return destroyScanner().then(function () {
                if (!createScannerInstance()) {
                    throw new Error('Scanner reader element not found.');
                }
                return beginScanning({ facingMode: { ideal: 'environment' } });
            });
        }).catch(function () {
            return destroyScanner().then(function () {
                if (!createScannerInstance()) {
                    throw new Error('Scanner reader element not found.');
                }
                return prefetchCameras().then(function (cameras) {
                    if (!cameras.length) {
                        throw new Error('No camera found. Please connect a webcam.');
                    }
                    return beginScanning(cameras[0].id);
                });
            });
        });
    }

    function startScanner() {
        startScannerWithFallback().catch(function (err) {
            setStatus('Camera error: ' + (err.message || err));
            isScanning = false;
            isProcessingScan = false;
        });
    }

    /**
     * Open the barcode scanner modal.
     * @param {Function} callback - Called with scanned barcode string.
     */
    function openScanner(callback) {
        onScanCallback = callback;
        isProcessingScan = false;

        var el = getOverlay();
        if (!el) {
            console.error('Barcode scanner modal not found on page.');
            return;
        }

        document.body.style.overflow = 'hidden';
        el.classList.add('active');
        setStatus('Initializing camera…');
        startScanner();
    }

    /**
     * Look up a product by barcode via API.
     * @param {string} barcode
     * @returns {Promise<object>}
     */
    function lookupBarcode(barcode) {
        return fetch('api/get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode))
            .then(function (r) { return r.json(); });
    }

    /**
     * Show duplicate barcode warning modal.
     * @param {object} product - Product object from API.
     */
    function showDuplicateWarning(product) {
        var overlay = document.getElementById('barcode-duplicate-overlay');
        var nameEl = document.getElementById('barcode-duplicate-product-name');
        var linkEl = document.getElementById('barcode-duplicate-view-link');

        if (!overlay) return;

        document.body.style.overflow = 'hidden';
        if (nameEl) nameEl.textContent = product.product_name || product.name || '';
        if (linkEl) linkEl.href = 'products.php?q=' + encodeURIComponent(product.barcode || '');

        overlay.classList.add('active');
    }

    function closeDuplicateWarning() {
        var overlay = document.getElementById('barcode-duplicate-overlay');
        if (overlay) overlay.classList.remove('active');
        if (!document.querySelector('.modal-overlay.active')) {
            document.body.style.overflow = '';
        }
    }

    function initModalListeners() {
        var closeBtn = document.getElementById('barcode-scanner-close');
        var cancelBtn = document.getElementById('barcode-scanner-cancel');
        var dismissBtn = document.getElementById('barcode-duplicate-dismiss');

        if (closeBtn && !closeBtn.dataset.barcodeInit) {
            closeBtn.dataset.barcodeInit = '1';
            closeBtn.addEventListener('click', closeScanner);
        }
        if (cancelBtn && !cancelBtn.dataset.barcodeInit) {
            cancelBtn.dataset.barcodeInit = '1';
            cancelBtn.addEventListener('click', closeScanner);
        }
        if (dismissBtn && !dismissBtn.dataset.barcodeInit) {
            dismissBtn.dataset.barcodeInit = '1';
            dismissBtn.addEventListener('click', closeDuplicateWarning);
        }

        var dupOverlay = document.getElementById('barcode-duplicate-overlay');
        if (dupOverlay && !dupOverlay.dataset.barcodeInit) {
            dupOverlay.dataset.barcodeInit = '1';
            dupOverlay.addEventListener('click', function (e) {
                if (e.target === dupOverlay) closeDuplicateWarning();
            });
        }

        var scanOverlay = document.getElementById('barcode-scanner-overlay');
        if (scanOverlay && !scanOverlay.dataset.barcodeInit) {
            scanOverlay.dataset.barcodeInit = '1';
            scanOverlay.addEventListener('click', function (e) {
                if (e.target === scanOverlay) closeScanner();
            });
        }
    }

    /**
     * Attach USB/Bluetooth HID keyboard-wedge scanner support to an input.
     * Scanners type characters quickly and suffix with Enter.
     * @param {HTMLInputElement} input
     * @param {Function} onScan - Called with (code, input) when a wedge scan completes.
     * @param {Object} [options]
     */
    function attachHidInput(input, onScan, options) {
        if (!input || input.dataset.hidAttached) return;

        options = options || {};
        var minLength = options.minLength || 3;
        var maxScanMs = options.maxScanMs || 700;
        var scanStart = 0;
        var lastKeyTime = 0;

        input.dataset.hidAttached = '1';
        input.dataset.barcodeScanner = '1';
        input.setAttribute('autocomplete', 'off');

        input.addEventListener('keydown', function (e) {
            var now = Date.now();

            if (e.key === 'Enter') {
                var code = input.value.trim();
                var elapsed = scanStart ? (now - scanStart) : (maxScanMs + 1);

                if (code.length >= minLength && elapsed <= maxScanMs) {
                    e.preventDefault();
                    if (isDuplicateScan(code)) return;

                    lastScanned = code;
                    lastScanTime = now;

                    if (typeof onScan === 'function') {
                        onScan(code, input);
                    }
                }
                scanStart = 0;
                return;
            }

            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                if (!scanStart || (now - lastKeyTime) > 120) {
                    scanStart = now;
                }
                lastKeyTime = now;
            }
        });
    }

    /**
     * Focus an input so a handheld scanner can wedge into it.
     * @param {HTMLInputElement|string} inputOrId
     */
    function focusHidInput(inputOrId) {
        var input = typeof inputOrId === 'string'
            ? document.getElementById(inputOrId)
            : inputOrId;
        if (input && typeof input.focus === 'function') {
            setTimeout(function () { input.focus(); }, 50);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalListeners);
    } else {
        initModalListeners();
    }

    window.BarcodeScanner = {
        open: openScanner,
        close: closeScanner,
        lookup: lookupBarcode,
        showDuplicate: showDuplicateWarning,
        closeDuplicate: closeDuplicateWarning,
        init: initModalListeners,
        attachHidInput: attachHidInput,
        focusHidInput: focusHidInput
    };
})();
