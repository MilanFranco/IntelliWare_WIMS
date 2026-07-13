/**
 * POS-style inventory transaction workflow (Receive / Release pages).
 * Scanner-first: barcode wedge or webcam → cart → confirm.
 */
var InventoryTransaction = (function () {
    'use strict';

    var cart = [];
    var txnMode = 'in';
    var catalogIndex = {};
    var selectedProductId = null;

    function showToast(message, type) {
        var toast = document.getElementById('toast-container');
        var toastMsg = document.getElementById('toast-message');
        var toastIcon = document.getElementById('toast-icon');
        if (!toast || !toastMsg) return;

        toastMsg.innerText = message;
        toast.className = 'show ' + (type === 'success' ? 'success' : 'error');
        toastIcon.innerHTML = type === 'success'
            ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>';

        setTimeout(function () { toast.classList.remove('show'); }, 3500);
    }

    function focusWedgeInput() {
        var wedge = document.getElementById('inv-wedge-input');
        if (wedge) {
            setTimeout(function () { wedge.focus(); }, 50);
        }
    }

    function focusBarcodeInput() {
        if (window.BarcodeScanner && BarcodeScanner.isInlineActive && BarcodeScanner.isInlineActive()) {
            focusWedgeInput();
            return;
        }
        if (window.BarcodeScanner && BarcodeScanner.isUsbScannerConnected()) {
            focusWedgeInput();
            return;
        }
        var input = document.getElementById('inv-barcode-input');
        if (input) {
            setTimeout(function () {
                input.focus();
                input.select();
            }, 50);
        }
    }

    function updateScannerUi(connected) {
        var badge = document.getElementById('inv-scanner-badge');
        var hint = document.getElementById('inv-scanner-hint');
        if (badge) {
            badge.textContent = connected ? 'Scanner ready' : 'No barcode scanner detected.';
            badge.classList.toggle('pos-scanner-badge--off', !connected);
        }
        if (hint) {
            hint.innerHTML = connected
                ? '<strong>Scan the barcode</strong> printed on product packaging. USB scanners work automatically. If product not found, register it in Products page first.'
                : 'No barcode scanner detected. Type barcodes manually and press Enter. Connect a USB scanner to enable automatic scanning.';
        }
    }

    function updateInfoActionButtons() {
        var increaseBtn = document.getElementById('inv-info-increase');
        var decreaseBtn = document.getElementById('inv-info-decrease');
        var removeBtn = document.getElementById('inv-info-remove');

        if (!increaseBtn || !decreaseBtn || !removeBtn) return;

        var inCart = selectedProductId != null && findCartItem(selectedProductId) != null;
        var disabled = !inCart;

        increaseBtn.disabled = disabled;
        decreaseBtn.disabled = disabled;
        removeBtn.disabled = disabled;

        if (inCart) {
            var item = findCartItem(selectedProductId);
            increaseBtn.disabled = !canIncreaseQty(item);
        }
    }

    function cacheProduct(rawProduct) {
        if (!rawProduct) return;

        var id = rawProduct.product_id || rawProduct.id;
        if (id) {
            catalogIndex[String(id)] = rawProduct;
        }
        if (rawProduct.barcode) {
            catalogIndex['bc:' + rawProduct.barcode] = rawProduct;
        }
    }

    function getStockStatusLabel(status) {
        switch (status) {
            case 'out_of_stock': return 'Out of Stock';
            case 'low_stock': return 'Low Stock';
            default: return 'In Stock';
        }
    }

    function computeStockStatus(qty, reorderPoint) {
        if (qty <= 0) return 'out_of_stock';
        if (qty <= reorderPoint) return 'low_stock';
        return 'in_stock';
    }

    function formatDateYMD(date) {
        var d = new Date(date);
        if (isNaN(d.getTime())) return '';
        var month = '' + (d.getMonth() + 1),
            day = '' + d.getDate(),
            year = d.getFullYear();

        if (month.length < 2) month = '0' + month;
        if (day.length < 2) day = '0' + day;

        return [year, month, day].join('-');
    }

    function normalizeProduct(raw) {
        var qty = parseInt(raw.quantity != null ? raw.quantity : raw.qty || '0', 10);
        var reorderPoint = parseInt(
            raw.reorder_point != null ? raw.reorder_point : raw.reorder || '10',
            10
        );
        var stockStatus = raw.stock_status || computeStockStatus(qty, reorderPoint);

        return {
            productId: parseInt(raw.product_id || raw.id || '0', 10),
            name: raw.product_name || raw.name || 'Unknown',
            barcode: raw.barcode || '',
            category: raw.category || '',
            location: raw.location || '',
            currentStock: qty,
            reorderPoint: reorderPoint,
            expiryDate: raw.expiry_date || null,
            batchNumber: raw.batch_number || '',
            shelfLife: raw.shelf_life != null ? parseInt(raw.shelf_life, 10) : null,
            stockStatus: stockStatus,
            stockStatusLabel: raw.stock_status_label || getStockStatusLabel(stockStatus),
            imageUrl: raw.image_url || null,
            unit: raw.unit || raw.uom || '',
            sku: raw.sku || ''
        };
    }

    function syncReceiveFieldsToCart() {
        if (txnMode !== 'in' || selectedProductId == null) return;

        var item = findCartItem(selectedProductId);
        if (!item) return;

        var batchInput = document.getElementById('inv-receive-batch');
        var mfgInput = document.getElementById('inv-receive-mfg');
        var expiryInput = document.getElementById('inv-receive-expiry');
        if (batchInput) item.batch_number = batchInput.value.trim();
        if (mfgInput) item.manufacturing_date = mfgInput.value.trim();
        if (expiryInput) item.expiry_date = expiryInput.value.trim();
    }

    function loadReceiveFieldsFromCart(productId) {
        var batchInput = document.getElementById('inv-receive-batch');
        var mfgInput = document.getElementById('inv-receive-mfg');
        var expiryInput = document.getElementById('inv-receive-expiry');
        if (!batchInput && !expiryInput && !mfgInput) return;

        var item = findCartItem(productId);
        if (batchInput) batchInput.value = item ? (item.batch_number || '') : '';
        if (mfgInput) mfgInput.value = item ? (item.manufacturing_date || '') : '';
        if (expiryInput) {
            expiryInput.value = item && item.expiry_date
                ? item.expiry_date
                : (item && item.productExpiryDate ? item.productExpiryDate : '');
        }
    }

    function getProductDetails(productId) {
        var cached = catalogIndex[String(productId)];
        if (cached) {
            return normalizeProduct(cached);
        }

        var cartItem = findCartItem(productId);
        if (cartItem) {
            return normalizeProduct({
                id: cartItem.productId,
                name: cartItem.name,
                barcode: cartItem.barcode,
                quantity: cartItem.currentStock,
                unit: cartItem.unit
            });
        }

        return null;
    }

    function formatExpiryDate(value) {
        if (!value) return 'Not applicable';
        var date = new Date(value + 'T00:00:00');
        if (isNaN(date.getTime())) return value;
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function clearProductInfo() {
        selectedProductId = null;
        var emptyEl = document.getElementById('inv-info-empty');
        var contentEl = document.getElementById('inv-info-content');
        if (emptyEl) emptyEl.style.display = '';
        if (contentEl) contentEl.style.display = 'none';
        updateInfoActionButtons();
    }

    function showProductInfo(rawProduct) {
        var product = normalizeProduct(rawProduct);
        if (product.productId <= 0) {
            clearProductInfo();
            return;
        }

        selectedProductId = product.productId;

        var emptyEl = document.getElementById('inv-info-empty');
        var contentEl = document.getElementById('inv-info-content');
        var imageWrap = document.getElementById('inv-info-image-wrap');
        var imageEl = document.getElementById('inv-info-image');
        var nameEl = document.getElementById('inv-info-name');
        var barcodeEl = document.getElementById('inv-info-barcode');
        var categoryEl = document.getElementById('inv-info-category');
        var stockEl = document.getElementById('inv-info-stock');
        var statusEl = document.getElementById('inv-info-status');
        var reorderEl = document.getElementById('inv-info-reorder');
        var expiryEl = document.getElementById('inv-info-expiry');
        var batchEl = document.getElementById('inv-info-batch');

        if (emptyEl) emptyEl.style.display = 'none';
        if (contentEl) contentEl.style.display = '';

        if (nameEl) nameEl.textContent = product.name;
        if (barcodeEl) barcodeEl.textContent = product.barcode || '—';
        if (categoryEl) categoryEl.textContent = product.category || 'Uncategorized';
        var locationEl = document.getElementById('inv-info-location');
        if (locationEl) locationEl.textContent = product.location || '—';

        var stockText = String(product.currentStock);
        if (product.unit) stockText += ' ' + product.unit;
        if (stockEl) stockEl.textContent = stockText;

        if (statusEl) {
            statusEl.innerHTML = '<span class="pos-status-badge pos-status-' + escapeHtml(product.stockStatus) + '">'
                + escapeHtml(product.stockStatusLabel) + '</span>';
        }

        if (reorderEl) reorderEl.textContent = String(product.reorderPoint);
        if (expiryEl) expiryEl.textContent = formatExpiryDate(product.expiryDate);
        if (batchEl) batchEl.textContent = product.batchNumber || '—';

        loadReceiveFieldsFromCart(product.productId);

        if (imageWrap && imageEl) {
            if (product.imageUrl) {
                imageWrap.classList.add('has-image');
                imageEl.src = product.imageUrl;
                imageEl.alt = product.name;
            } else {
                imageWrap.classList.remove('has-image');
                imageEl.removeAttribute('src');
                imageEl.alt = 'No product image';
            }
        }

        highlightSelectedCartRow();
        updateInfoActionButtons();
    }

    function findCartItem(productId) {
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].productId === productId) return cart[i];
        }
        return null;
    }

    function findCartIndex(productId) {
        for (var i = 0; i < cart.length; i++) {
            if (cart[i].productId === productId) return i;
        }
        return -1;
    }

    function canIncreaseQty(item) {
        if (txnMode !== 'out') return true;
        return item.qty < item.currentStock;
    }

    function addToCart(rawProduct, flashExisting) {
        var product = normalizeProduct(rawProduct);
        if (product.productId <= 0) {
            showToast('Invalid product.', 'error');
            return false;
        }

        cacheProduct(rawProduct);

        var existing = findCartItem(product.productId);
        if (existing) {
            if (!canIncreaseQty(existing)) {
                showToast('Insufficient stock for ' + existing.name + '. Available: ' + existing.currentStock + '.', 'error');
                focusBarcodeInput();
                return false;
            }
            existing.qty += 1;
            renderCart(flashExisting !== false ? product.productId : null);
            showProductInfo(product);
            focusBarcodeInput();
            return true;
        }

        if (txnMode === 'out' && product.currentStock <= 0) {
            showToast('Cannot release — ' + product.name + ' is out of stock.', 'error');
            focusBarcodeInput();
            return false;
        }

        var initialExpiry = '';
        if (product.shelfLife != null && !isNaN(product.shelfLife)) {
            var date = new Date();
            date.setDate(date.getDate() + product.shelfLife);
            initialExpiry = formatDateYMD(date);
        }

        cart.push({
            productId: product.productId,
            name: product.name,
            barcode: product.barcode,
            category: product.category,
            location: product.location,
            currentStock: product.currentStock,
            unit: product.unit,
            productExpiryDate: product.expiryDate || '',
            batch_number: product.batchNumber || '',
            manufacturing_date: '',
            expiry_date: initialExpiry,
            shelf_life: product.shelfLife,
            qty: 1
        });

        renderCart(product.productId);
        showProductInfo(product);
        focusBarcodeInput();
        return true;
    }

    function changeQty(productId, delta) {
        var idx = findCartIndex(productId);
        if (idx === -1) return;

        var item = cart[idx];
        var newQty = item.qty + delta;

        if (newQty <= 0) {
            removeFromCart(productId);
            return;
        }

        if (txnMode === 'out' && newQty > item.currentStock) {
            showToast('Insufficient stock. Available: ' + item.currentStock + '.', 'error');
            return;
        }

        item.qty = newQty;
        renderCart(productId);

        var details = getProductDetails(productId);
        if (details && selectedProductId === productId) {
            showProductInfo(details);
        } else {
            updateInfoActionButtons();
        }

        focusBarcodeInput();
    }

    function removeFromCart(productId) {
        var idx = findCartIndex(productId);
        if (idx === -1) return;
        cart.splice(idx, 1);
        renderCart();

        if (selectedProductId === productId) {
            if (cart.length > 0) {
                var lastItem = cart[cart.length - 1];
                var details = getProductDetails(lastItem.productId);
                if (details) showProductInfo(details);
            } else {
                clearProductInfo();
            }
        }

        focusBarcodeInput();
    }

    function clearCart() {
        cart = [];
        renderCart();
        clearProductInfo();
        focusBarcodeInput();
    }

    function highlightSelectedCartRow() {
        var rows = document.querySelectorAll('#inv-cart-body tr[data-product-id]');
        rows.forEach(function (row) {
            var id = parseInt(row.getAttribute('data-product-id') || '0', 10);
            row.classList.toggle('pos-cart-row-selected', id === selectedProductId);
        });
    }

    function renderCart(flashProductId) {
        var emptyEl = document.getElementById('inv-cart-empty');
        var tableEl = document.getElementById('inv-cart-table');
        var bodyEl = document.getElementById('inv-cart-body');
        var confirmBtn = document.getElementById('inv-confirm-btn');
        var summaryProducts = document.getElementById('inv-summary-products');
        var summaryQty = document.getElementById('inv-summary-qty');

        if (!bodyEl) return;

        bodyEl.innerHTML = '';

        if (cart.length === 0) {
            if (emptyEl) emptyEl.style.display = '';
            if (tableEl) tableEl.style.display = 'none';
            if (confirmBtn) confirmBtn.disabled = true;
            if (summaryProducts) summaryProducts.textContent = '0';
            if (summaryQty) summaryQty.textContent = '0';
            return;
        }

        if (emptyEl) emptyEl.style.display = 'none';
        if (tableEl) tableEl.style.display = '';
        if (confirmBtn) confirmBtn.disabled = false;

        var totalQty = 0;

        cart.forEach(function (item) {
            totalQty += item.qty;

            var tr = document.createElement('tr');
            tr.setAttribute('data-product-id', String(item.productId));

            if (flashProductId && item.productId === flashProductId) {
                tr.className = 'pos-cart-row-flash';
            }

            var nameTd = document.createElement('td');
            nameTd.innerHTML = '<div class="font-semibold">' + escapeHtml(item.name) + '</div>'
                + (item.unit ? '<div class="text-[10px] text-[#9aa6d1]">' + escapeHtml(item.unit) + '</div>' : '');

            var qtyTd = document.createElement('td');
            var qtyControls = document.createElement('div');
            qtyControls.className = 'pos-qty-controls';

            var minusBtn = document.createElement('button');
            minusBtn.type = 'button';
            minusBtn.className = 'pos-qty-btn';
            minusBtn.textContent = '−';
            minusBtn.setAttribute('data-action', 'decrease');
            minusBtn.setAttribute('data-id', String(item.productId));

            var qtySpan = document.createElement('span');
            qtySpan.className = 'pos-qty-value';
            qtySpan.textContent = String(item.qty);

            var plusBtn = document.createElement('button');
            plusBtn.type = 'button';
            plusBtn.className = 'pos-qty-btn';
            plusBtn.textContent = '+';
            plusBtn.setAttribute('data-action', 'increase');
            plusBtn.setAttribute('data-id', String(item.productId));

            qtyControls.appendChild(minusBtn);
            qtyControls.appendChild(qtySpan);
            qtyControls.appendChild(plusBtn);
            qtyTd.appendChild(qtyControls);

            var actionTd = document.createElement('td');
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'pos-remove-btn';
            removeBtn.title = 'Remove';
            removeBtn.innerHTML = '&times;';
            removeBtn.setAttribute('data-action', 'remove');
            removeBtn.setAttribute('data-id', String(item.productId));
            actionTd.appendChild(removeBtn);

            tr.appendChild(nameTd);
            tr.appendChild(qtyTd);
            tr.appendChild(actionTd);
            bodyEl.appendChild(tr);
        });

        if (summaryProducts) summaryProducts.textContent = String(cart.length);
        if (summaryQty) summaryQty.textContent = String(totalQty);
        highlightSelectedCartRow();
        updateInfoActionButtons();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function handleCartAction(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;

        e.stopPropagation();

        var action = btn.getAttribute('data-action');
        var productId = parseInt(btn.getAttribute('data-id') || '0', 10);
        if (productId <= 0) return;

        if (action === 'increase') changeQty(productId, 1);
        else if (action === 'decrease') changeQty(productId, -1);
        else if (action === 'remove') removeFromCart(productId);
    }

    function handleCartRowSelect(e) {
        if (e.target.closest('[data-action]')) return;

        var row = e.target.closest('tr[data-product-id]');
        if (!row) return;

        var productId = parseInt(row.getAttribute('data-product-id') || '0', 10);
        if (productId <= 0) return;

        var details = getProductDetails(productId);
        if (details) showProductInfo(details);
    }

    function barcodeLookupVariants(code) {
        var variants = [];
        var seen = {};

        function add(value) {
            value = (value || '').trim();
            if (!value || seen[value]) return;
            seen[value] = true;
            variants.push(value);
        }

        add(code);

        if (/^\d+$/.test(code)) {
            if (code.length === 12) {
                add('0' + code);
            } else if (code.length === 13 && code.charAt(0) === '0') {
                add(code.substring(1));
            } else if (code.length < 13) {
                add(code.padStart(13, '0'));
            }
        }

        return variants;
    }

    function findInCatalog(code) {
        var variants = barcodeLookupVariants(code);
        for (var i = 0; i < variants.length; i++) {
            var hit = catalogIndex['bc:' + variants[i]];
            if (hit) return hit;
        }
        return null;
    }

    function lookupProductByBarcode(code) {
        if (!window.BarcodeScanner) {
            return Promise.reject(new Error('Barcode scanner module not loaded.'));
        }

        var variants = barcodeLookupVariants(code);
        var index = 0;

        function tryNext() {
            if (index >= variants.length) {
                return Promise.resolve({ found: false, barcode: code });
            }

            var candidate = variants[index];
            index += 1;

            return BarcodeScanner.lookup(candidate).then(function (data) {
                if (data.found && data.product) {
                    return data;
                }
                return tryNext();
            });
        }

        return tryNext();
    }

    function addProductByBarcode(barcode) {
        var code = (barcode || '').trim();
        if (!code) return;

        var cached = findInCatalog(code);
        if (cached) {
            addToCart(cached);
            return;
        }

        lookupProductByBarcode(code).then(function (data) {
            if (data.found && data.product) {
                var product = data.product;
                if (product.product_id && !product.id) {
                    product.id = product.product_id;
                }
                if (product.quantity != null && product.qty == null) {
                    product.qty = product.quantity;
                }
                cacheProduct(product);
                addToCart(product);
            } else {
                showToast('Product not found. Please register this product first.', 'error');
                focusBarcodeInput();
            }
        }).catch(function () {
            showToast('Failed to look up barcode.', 'error');
            focusBarcodeInput();
        });
    }

    function processBarcodeInput() {
        var input = document.getElementById('inv-barcode-input');
        if (!input) return;

        var code = input.value.trim();
        input.value = '';

        if (code) {
            addProductByBarcode(code);
        } else {
            focusBarcodeInput();
        }
    }

    function openInlineCamera() {
        var barcodeMode = document.getElementById('inv-barcode-mode');
        var cameraMode = document.getElementById('inv-camera-mode');
        var scanBtn = document.getElementById('inv-scan-btn');

        if (!barcodeMode || !cameraMode || !window.BarcodeScanner) return;

        barcodeMode.style.display = 'none';
        cameraMode.style.display = '';
        if (scanBtn) scanBtn.classList.add('is-active');

        focusWedgeInput();

        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                BarcodeScanner.openInline('inv-inline-scanner-reader', function (code) {
                    addProductByBarcode(code);
                }, { skipDuplicate: true, statusId: 'inv-inline-scanner-status' });
            });
        });
    }

    function closeInlineCamera() {
        var barcodeMode = document.getElementById('inv-barcode-mode');
        var cameraMode = document.getElementById('inv-camera-mode');
        var scanBtn = document.getElementById('inv-scan-btn');

        if (!window.BarcodeScanner) return;

        BarcodeScanner.closeInline().then(function () {
            if (cameraMode) cameraMode.style.display = 'none';
            if (barcodeMode) barcodeMode.style.display = '';
            if (scanBtn) scanBtn.classList.remove('is-active');
            focusBarcodeInput();
        });
    }

    function handleInfoAction(action) {
        if (selectedProductId == null) return;

        if (action === 'increase') changeQty(selectedProductId, 1);
        else if (action === 'decrease') changeQty(selectedProductId, -1);
        else if (action === 'remove') removeFromCart(selectedProductId);
    }
    function validateAndSubmit(form) {
        if (cart.length === 0) {
            showToast('Add at least one product to the transaction.', 'error');
            focusBarcodeInput();
            return false;
        }

        if (txnMode === 'out') {
            for (var i = 0; i < cart.length; i++) {
                if (cart[i].qty > cart[i].currentStock) {
                    showToast(
                        'Insufficient stock for ' + cart[i].name + '. Available: ' + cart[i].currentStock + '.',
                        'error'
                    );
                    return false;
                }
            }
        }

        var payload = cart.map(function (item) {
            var row = { product_id: item.productId, qty: item.qty };
            if (txnMode === 'in') {
                var metadata = {};
                if (item.batch_number) metadata.batch_number = item.batch_number;
                if (item.expiry_date) metadata.expiry_date = item.expiry_date;
                if (item.manufacturing_date) metadata.manufacturing_date = item.manufacturing_date;
                if (item.location) metadata.location = item.location;
                if (item.category) metadata.category = item.category;

                if (metadata.batch_number) row.batch_number = metadata.batch_number;
                if (metadata.expiry_date) row.expiry_date = metadata.expiry_date;
                if (metadata.manufacturing_date) row.manufacturing_date = metadata.manufacturing_date;
                if (metadata.location) row.location = metadata.location;
                if (metadata.category) row.category = metadata.category;
            }
            return row;
        });

        document.getElementById('inv-items-json').value = JSON.stringify(payload);
        return true;
    }

    function calculatePredictedExpiry() {
        if (selectedProductId == null) return;
        var item = findCartItem(selectedProductId);
        if (!item) return;

        var mfgInput = document.getElementById('inv-receive-mfg');
        var expiryInput = document.getElementById('inv-receive-expiry');
        if (!mfgInput || !expiryInput) return;

        if (item.shelf_life != null && !isNaN(item.shelf_life)) {
            var refDate = mfgInput.value ? new Date(mfgInput.value + 'T00:00:00') : new Date();
            if (!isNaN(refDate.getTime())) {
                refDate.setDate(refDate.getDate() + item.shelf_life);
                expiryInput.value = formatDateYMD(refDate);
            }
        }
    }

    function init() {
        var form = document.getElementById('inv-txn-form');
        if (!form) return;

        txnMode = form.getAttribute('data-txn-mode') || 'in';
        renderCart();
        clearProductInfo();

        var barcodeInput = document.getElementById('inv-barcode-input');
        var wedgeInput = document.getElementById('inv-wedge-input');

        function onWedgeScan(code) {
            if (barcodeInput) barcodeInput.value = '';
            if (wedgeInput) wedgeInput.value = '';
            addProductByBarcode(code);
        }

        if (barcodeInput) {
            barcodeInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    processBarcodeInput();
                }
            });

            if (window.BarcodeScanner) {
                BarcodeScanner.attachHidInput(barcodeInput, function (code) {
                    if (!BarcodeScanner.isUsbScannerConnected()) return;
                    onWedgeScan(code);
                }, { minLength: 3, maxScanMs: 700, skipDuplicate: true });
            }
        }

        if (wedgeInput && window.BarcodeScanner) {
            BarcodeScanner.attachHidInput(wedgeInput, function (code) {
                if (!BarcodeScanner.isUsbScannerConnected()) return;
                onWedgeScan(code);
            }, { minLength: 3, maxScanMs: 700, skipDuplicate: true });

            updateScannerUi(BarcodeScanner.isUsbScannerConnected());
            BarcodeScanner.onStatusChange(updateScannerUi);
        }

        if (window.BarcodeScanner) {
            document.getElementById('inv-scan-btn')?.addEventListener('click', function () {
                if (BarcodeScanner.isInlineActive && BarcodeScanner.isInlineActive()) {
                    closeInlineCamera();
                } else {
                    openInlineCamera();
                }
            });

            document.getElementById('inv-close-camera-btn')?.addEventListener('click', closeInlineCamera);
        }

        document.getElementById('inv-info-increase')?.addEventListener('click', function () {
            handleInfoAction('increase');
        });
        document.getElementById('inv-info-decrease')?.addEventListener('click', function () {
            handleInfoAction('decrease');
        });
        document.getElementById('inv-info-remove')?.addEventListener('click', function () {
            handleInfoAction('remove');
        });

        var cartBody = document.getElementById('inv-cart-body');
        cartBody?.addEventListener('click', handleCartAction);
        cartBody?.addEventListener('click', handleCartRowSelect);

        document.getElementById('inv-clear-btn')?.addEventListener('click', function () {
            if (cart.length === 0) {
                focusBarcodeInput();
                return;
            }
            clearCart();
        });

        form.addEventListener('submit', function (e) {
            syncReceiveFieldsToCart();
            if (!validateAndSubmit(form)) {
                e.preventDefault();
            }
        });

        document.getElementById('inv-receive-batch')?.addEventListener('input', syncReceiveFieldsToCart);
        document.getElementById('inv-receive-batch')?.addEventListener('change', syncReceiveFieldsToCart);

        var mfgListener = function () {
            calculatePredictedExpiry();
            syncReceiveFieldsToCart();
        };
        document.getElementById('inv-receive-mfg')?.addEventListener('input', mfgListener);
        document.getElementById('inv-receive-mfg')?.addEventListener('change', mfgListener);

        document.getElementById('inv-receive-expiry')?.addEventListener('input', syncReceiveFieldsToCart);
        document.getElementById('inv-receive-expiry')?.addEventListener('change', syncReceiveFieldsToCart);

        document.getElementById('inv-scanner-hint')?.addEventListener('click', focusBarcodeInput);

        focusBarcodeInput();
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        showToast: showToast,
        addProductByBarcode: addProductByBarcode,
        addToCart: addToCart,
        clearCart: clearCart
    };
})();
