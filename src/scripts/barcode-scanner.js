/**
 * IntelliWare Barcode Scanner Module
 * 
 * This module handles reading/scanning manufacturer-assigned barcodes from product packaging
 * using webcam-based scanning (html5-qrcode library). It supports:
 *   - EAN-13, EAN-8 (retail barcodes)
 *   - UPC-A, UPC-E (North American retail)
 *   - Code 128, Code 39, Code 93 (industrial/logistics)
 *   - ITF (Interleaved 2 of 5)
 * 
 * IMPORTANT: This module reads existing barcodes only. It does NOT:
 *   - Generate new barcodes
 *   - Assign barcodes to products
 *   - Create barcode images
 *   - Print barcodes
 * 
 * Barcodes must be pre-registered in the system before use in transactions.
 */
(function () {
    'use strict';

    var scanner = null;
    var isScanning = false;
    var isProcessingScan = false;
    var lastScanned = '';
    var lastScanTime = 0;
    var onScanCallback = null;
    var scanOptions = {};
    var cameraPrefetchPromise = null;
    var focusAssistTimer = null;

    var DUPLICATE_COOLDOWN_MS = 2500;
    var SCAN_FPS = 10;
    var INLINE_SCAN_DEBOUNCE_MS = 900;
    var FOCUS_ASSIST_INTERVAL_MS = 2500;

    var overlay = null;
    var readerEl = null;
    var statusEl = null;
    var inlineMode = false;
    var inlineReaderId = null;
    var inlineStatusId = null;

    var REAR_CAMERA_LABELS = ['back', 'rear', 'environment', 'rück', 'arrière', 'posteriore', 'traseira'];

    function getOverlay() {
        if (!overlay) overlay = document.getElementById('barcode-scanner-overlay');
        return overlay;
    }

    function getReaderEl() {
        if (inlineMode && inlineReaderId) {
            return document.getElementById(inlineReaderId);
        }
        if (!readerEl) readerEl = document.getElementById('barcode-scanner-reader');
        return readerEl;
    }

    function getReaderElementId() {
        if (inlineMode && inlineReaderId) return inlineReaderId;
        return 'barcode-scanner-reader';
    }

    function getStatusEl() {
        if (inlineMode && inlineStatusId) {
            return document.getElementById(inlineStatusId);
        }
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

    /**
     * Wide scan region for 1D barcodes (EAN, UPC, Code 128/39).
     * html5-qrcode only decodes inside qrbox when set; keep it large.
     */
    function getScanBox(viewfinderWidth, viewfinderHeight) {
        var width = Math.min(Math.floor(viewfinderWidth * 0.92), 640);
        var height = Math.min(Math.floor(viewfinderHeight * 0.55), 220);
        if (height < 100) {
            height = Math.min(160, Math.floor(viewfinderHeight * 0.75));
        }
        return { width: Math.max(width, 240), height: Math.max(height, 100) };
    }

    function getScannerConstructorConfig() {
        var config = { verbose: false };
        var formats = getSupportedFormats();
        if (formats) {
            config.formatsToSupport = formats;
        }
        config.experimentalFeatures = { useBarCodeDetectorIfSupported: false };
        return config;
    }

    function getScanConfig() {
        return {
            fps: SCAN_FPS,
            qrbox: getScanBox,
            aspectRatio: 1.777778,
            disableFlip: false,
            rememberLastUsedCamera: true
        };
    }

    function waitForReaderLayout(readerId) {
        return new Promise(function (resolve) {
            var attempts = 0;
            var maxAttempts = 30;

            function tick() {
                var el = document.getElementById(readerId);
                if (el && el.offsetWidth > 0 && el.offsetHeight > 0) {
                    resolve();
                    return;
                }
                attempts += 1;
                if (attempts >= maxAttempts) {
                    resolve();
                    return;
                }
                requestAnimationFrame(tick);
            }

            requestAnimationFrame(function () {
                requestAnimationFrame(tick);
            });
        });
    }

    function resolveApiUrl(path) {
        if (/^https?:\/\//i.test(path)) return path;
        var base = window.location.href.replace(/[#?].*$/, '').replace(/[^/]+$/, '');
        return base + path.replace(/^\//, '');
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
        scanner = new Html5Qrcode(getReaderElementId(), getScannerConstructorConfig());
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

    function isInlineScanDebounced(code) {
        return code === lastScanned && (Date.now() - lastScanTime) < INLINE_SCAN_DEBOUNCE_MS;
    }

    function handleScanSuccess(decodedText, skipDuplicate) {
        if (isProcessingScan) return;

        var code = (decodedText || '').trim();
        if (!code) return;
        if (!skipDuplicate && isDuplicateScan(code)) return;
        if (skipDuplicate && inlineMode && isInlineScanDebounced(code)) return;

        isProcessingScan = true;
        lastScanned = code;
        lastScanTime = Date.now();

        setStatus('Barcode detected: ' + code);

        var callback = onScanCallback;

        if (inlineMode) {
            if (typeof callback === 'function') {
                callback(code);
            }
            setTimeout(function () {
                isProcessingScan = false;
                setStatus('Scanning… align barcode within the frame.');
            }, INLINE_SCAN_DEBOUNCE_MS);
            return;
        }

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
            function (decodedText) {
                handleScanSuccess(decodedText, scanOptions.skipDuplicate);
            },
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
            console.error('Barcode scanner error:', err);
        });
    }

    /**
     * Open inline scanner (embedded in page, stays open for continuous scanning).
     * @param {string} readerId - DOM id of the reader container element.
     * @param {Function} callback - Called with scanned barcode string.
     * @param {Object} [options] - { skipDuplicate: boolean, statusId: string }
     */
    function openInlineScanner(readerId, callback, options) {
        options = options || {};
        inlineMode = true;
        inlineReaderId = readerId;
        inlineStatusId = options.statusId || null;
        onScanCallback = callback;
        scanOptions = options;
        isProcessingScan = false;
        lastScanned = '';
        lastScanTime = 0;

        setStatus('Initializing camera…');
        waitForReaderLayout(readerId).then(function () {
            startScanner();
        });
    }

    /**
     * Close inline scanner and return to normal input mode.
     */
    function closeInlineScanner() {
        inlineMode = false;
        inlineReaderId = null;
        inlineStatusId = null;

        return stopScanner().then(function () {
            onScanCallback = null;
        });
    }

    function isInlineActive() {
        return inlineMode && isScanning;
    }

    /**
     * Open the barcode scanner modal.
     * @param {Function} callback - Called with scanned barcode string.
     * @param {Object} [options] - { skipDuplicate: boolean }
     */
    function openScanner(callback, options) {
        onScanCallback = callback;
        scanOptions = options || {};
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
        var url = resolveApiUrl('api/get_product_by_barcode.php?barcode=' + encodeURIComponent(barcode));
        return fetch(url).then(function (r) {
            if (!r.ok) {
                throw new Error('Lookup failed (' + r.status + ')');
            }
            return r.json();
        });
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
        var skipDuplicate = options.skipDuplicate || false;
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
                    if (!skipDuplicate && isDuplicateScan(code)) return;

                    if (!skipDuplicate) {
                        lastScanned = code;
                        lastScanTime = now;
                    }

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

    // --------------------------------------------------------
    // USB BARCODE SCANNER DETECTION (WebHID + keyboard wedge)
    // --------------------------------------------------------

    var headerStatusEl = null;
    var headerStatusTextEl = null;
    var hidDeviceCount = 0;
    var headerStatusInitialized = false;
    var usbScannerConnected = false;
    var globalWedgeEnabled = false;
    var globalWedgeBlurHandler = null;
    var statusChangeListeners = [];

    function getHeaderStatusEl() {
        if (!headerStatusEl) headerStatusEl = document.getElementById('header-scanner-status');
        return headerStatusEl;
    }

    function getHeaderStatusTextEl() {
        if (!headerStatusTextEl) {
            var el = getHeaderStatusEl();
            headerStatusTextEl = el ? el.querySelector('.header-scanner-status__text') : null;
        }
        return headerStatusTextEl;
    }

    function isLikelyBarcodeScanner(device) {
        if (!device) return false;

        var collections = device.collections || [];
        for (var i = 0; i < collections.length; i++) {
            var col = collections[i];
            if (col.usagePage === 0x8C) return true;
        }

        var label = [
            device.productName,
            device.manufacturerName
        ].filter(Boolean).join(' ').toLowerCase();

        return /barcode|scanner|symbol|honeywell|zebra|datalogic|code reader|handheld|scan/i.test(label);
    }

    function setHeaderScannerStatus(state) {
        var el = getHeaderStatusEl();
        var textEl = getHeaderStatusTextEl();
        if (!el) return;

        el.classList.remove(
            'header-scanner-status--ready',
            'header-scanner-status--disconnected',
            'header-scanner-status--checking'
        );

        if (state === 'ready') {
            el.classList.add('header-scanner-status--ready');
            if (textEl) textEl.textContent = 'Scanner ready';
            el.title = 'Barcode scanner is connected and ready to scan.';
            return;
        }

        if (state === 'checking') {
            el.classList.add('header-scanner-status--checking');
            if (textEl) textEl.textContent = 'Checking scanner…';
            el.title = 'Detecting barcode scanner connection.';
            return;
        }

        el.classList.add('header-scanner-status--disconnected');
        if (textEl) textEl.textContent = 'No barcode scanner detected.';
        el.title = 'Connect a USB barcode scanner to enable automatic scanning. Manual entry still works.';
    }

    function notifyStatusChange() {
        statusChangeListeners.forEach(function (fn) {
            try { fn(usbScannerConnected); } catch (e) { /* ignore listener errors */ }
        });
    }

    function setUsbScannerConnected(connected) {
        if (usbScannerConnected === connected) {
            if (connected) setHeaderScannerStatus('ready');
            return;
        }

        usbScannerConnected = connected;

        if (connected) {
            setHeaderScannerStatus('ready');
            enableGlobalWedge();
        } else {
            setHeaderScannerStatus('disconnected');
            disableGlobalWedge();
        }

        notifyStatusChange();
    }

    function isUsbScannerConnected() {
        return usbScannerConnected;
    }

    function onScannerStatusChange(callback) {
        if (typeof callback === 'function') {
            statusChangeListeners.push(callback);
        }
    }

    function markScannerReady() {
        setUsbScannerConnected(true);
    }

    function markScannerDisconnected(force) {
        if (!force && hidDeviceCount > 0) return;
        setUsbScannerConnected(false);
    }

    function refreshHidDeviceStatus() {
        if (!navigator.hid || typeof navigator.hid.getDevices !== 'function') {
            return Promise.resolve(false);
        }

        return navigator.hid.getDevices().then(function (devices) {
            var scanners = devices.filter(isLikelyBarcodeScanner);
            hidDeviceCount = scanners.length;

            if (hidDeviceCount > 0) {
                setUsbScannerConnected(true);
                return true;
            }

            return false;
        }).catch(function () {
            return false;
        });
    }

    function initWebHidStatus() {
        if (!navigator.hid) return;

        navigator.hid.addEventListener('connect', function (e) {
            if (isLikelyBarcodeScanner(e.device)) {
                hidDeviceCount += 1;
                setUsbScannerConnected(true);
            }
        });

        navigator.hid.addEventListener('disconnect', function (e) {
            if (!isLikelyBarcodeScanner(e.device)) return;

            hidDeviceCount = Math.max(0, hidDeviceCount - 1);
            if (hidDeviceCount === 0) {
                markScannerDisconnected(true);
            }
        });

        refreshHidDeviceStatus();
    }

    function enableGlobalWedge() {
        var input = document.getElementById('global-scanner-wedge-input');
        if (!input || globalWedgeEnabled) return;

        globalWedgeEnabled = true;
        input.disabled = false;
        originalAttachHidInput(input, null, { minLength: 3, maxScanMs: 700, skipDuplicate: true });

        globalWedgeBlurHandler = function () {
            if (!usbScannerConnected) return;
            var active = document.activeElement;
            if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable)) {
                return;
            }
            setTimeout(function () {
                if (!usbScannerConnected || document.activeElement === input) return;
                active = document.activeElement;
                if (active && (active.tagName === 'INPUT' || active.tagName === 'TEXTAREA' || active.isContentEditable)) {
                    return;
                }
                input.focus({ preventScroll: true });
            }, 120);
        };

        input.addEventListener('blur', globalWedgeBlurHandler);

        setTimeout(function () {
            if (usbScannerConnected) {
                input.focus({ preventScroll: true });
            }
        }, 300);
    }

    function disableGlobalWedge() {
        var input = document.getElementById('global-scanner-wedge-input');
        if (!input) return;

        globalWedgeEnabled = false;
        input.disabled = true;
        input.value = '';
        input.blur();

        if (globalWedgeBlurHandler) {
            input.removeEventListener('blur', globalWedgeBlurHandler);
            globalWedgeBlurHandler = null;
        }
    }

    function initHeaderScannerStatus() {
        if (headerStatusInitialized) return;
        headerStatusInitialized = true;

        if (!getHeaderStatusEl()) return;

        setHeaderScannerStatus('checking');
        disableGlobalWedge();
        initWebHidStatus();

        refreshHidDeviceStatus().then(function (found) {
            if (!found) {
                setUsbScannerConnected(false);
            }
        }).catch(function () {
            setUsbScannerConnected(false);
        });
    }

    var originalAttachHidInput = attachHidInput;
    attachHidInput = function (input, onScan, options) {
        return originalAttachHidInput(input, function (code, inputEl) {
            setUsbScannerConnected(true);
            if (typeof onScan === 'function') {
                onScan(code, inputEl);
            }
        }, options);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalListeners);
        document.addEventListener('DOMContentLoaded', initHeaderScannerStatus);
    } else {
        initModalListeners();
        initHeaderScannerStatus();
    }

    window.BarcodeScanner = {
        open: openScanner,
        close: closeScanner,
        openInline: openInlineScanner,
        closeInline: closeInlineScanner,
        isInlineActive: isInlineActive,
        lookup: lookupBarcode,
        showDuplicate: showDuplicateWarning,
        closeDuplicate: closeDuplicateWarning,
        init: initModalListeners,
        attachHidInput: attachHidInput,
        focusHidInput: focusHidInput,
        initHeaderStatus: initHeaderScannerStatus,
        setHeaderStatus: setHeaderScannerStatus,
        markReady: markScannerReady,
        isUsbScannerConnected: isUsbScannerConnected,
        onStatusChange: onScannerStatusChange
    };
})();
