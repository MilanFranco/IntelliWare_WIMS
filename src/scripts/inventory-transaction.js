/**
 * inventory-transaction.js
 *
 * POS-style inventory transaction workflow.
 * Supports:
 *   - Product search bar with AJAX dropdown
 *   - Cart management (add / qty change / remove)
 *   - Product info panel with reference image
 */
var InventoryTransaction = (function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────
    var cart = [];
    var txnMode = 'in';
    var defaultConfirmLabel = 'Confirm Receive';
    var catalogIndex = {};          // { productId -> rawProduct }
    var selectedProductId = null;

    // ── Search debounce ──────────────────────────────────────────────────────
    var _searchTimer = null;
    var _lastQuery = '';

    // ── Toast ────────────────────────────────────────────────────────────────

    function showToast(message, type) {
        var toast = document.getElementById('toast-container');
        var toastMsg = document.getElementById('toast-message');
        var toastIcon = document.getElementById('toast-icon');
        if (!toast || !toastMsg) return;

        toastMsg.innerText = message;
        toast.className = 'show ' + (type === 'success' ? 'success' : 'error');
        toastIcon.innerHTML = type === 'success'
            ? '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>'
            : '<svg style="width:18px;height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>';

        clearTimeout(toast._timer);
        toast._timer = setTimeout(function () { toast.classList.remove('show'); }, 3500);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    function getConfirmBaseLabel() {
        var btn = document.getElementById('inv-confirm-btn');
        if (btn && btn.getAttribute('data-base-label')) {
            return btn.getAttribute('data-base-label').trim();
        }
        if (defaultConfirmLabel) {
            return defaultConfirmLabel;
        }
        return txnMode === 'out' ? 'Confirm Release' : 'Confirm Receive';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
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

    function formatExpiryDate(value) {
        if (!value) return 'Not applicable';
        var date = new Date(value + 'T00:00:00');
        if (isNaN(date.getTime())) return value;
        return date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
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
        if (!raw) return null;
        if (raw._normalized) return raw;

        var qty = parseInt(raw.quantity != null ? raw.quantity : raw.qty || raw.currentStock || '0', 10);
        var reorderPoint = parseInt(raw.reorder_point != null ? raw.reorder_point : raw.reorder || raw.reorderPoint || '10', 10);
        var stockStatus = raw.stock_status || raw.stockStatus || computeStockStatus(qty, reorderPoint);

        return {
            _normalized: true,
            productId: parseInt(raw.product_id || raw.id || raw.productId || '0', 10),
            name: raw.product_name || raw.name || 'Unknown',
            category: raw.category || '',
            location: raw.location || '',
            currentStock: qty,
            reorderPoint: reorderPoint,
            expiryDate: raw.expiry_date || raw.expiryDate || null,
            batchNumber: raw.batch_number || raw.batchNumber || '',
            shelfLife: raw.shelf_life != null ? parseInt(raw.shelf_life, 10) : (raw.shelfLife != null ? parseInt(raw.shelfLife, 10) : null),
            stockStatus: stockStatus,
            stockStatusLabel: raw.stock_status_label || raw.stockStatusLabel || getStockStatusLabel(stockStatus),
            imageUrl: raw.image_url || raw.imageUrl || null,
            unit: raw.unit || raw.uom || '',
            price: parseFloat(raw.price != null ? raw.price : '0.00'),
        };
    }

    function cacheProduct(rawProduct) {
        if (!rawProduct) return;
        var id = rawProduct.product_id || rawProduct.id;
        if (id) catalogIndex[String(id)] = rawProduct;
    }

    function getProductDetails(productId) {
        var cached = catalogIndex[String(productId)];
        if (cached) return normalizeProduct(cached);

        var cartItem = findCartItem(productId);
        if (cartItem) {
            return normalizeProduct({
                id: cartItem.productId,
                name: cartItem.name,
                category: cartItem.category,
                quantity: cartItem.currentStock,
                unit: cartItem.unit,
                price: cartItem.price,
                image_url: cartItem.imageUrl,
            });
        }
        return null;
    }


    // ── Info panel ───────────────────────────────────────────────────────────

    function updateInfoActionButtons() {
        var removeBtn = document.getElementById('inv-info-remove');
        if (!removeBtn) return;
        var inCart = selectedProductId != null && findCartItem(selectedProductId) != null;
        removeBtn.disabled = !inCart;
    }

    function clearProductInfo() {
        selectedProductId = null;
        var emptyEl = document.getElementById('inv-info-empty');
        var contentEl = document.getElementById('inv-info-content');
        if (emptyEl) emptyEl.style.display = '';
        if (contentEl) contentEl.style.display = 'none';
        var wrap = document.getElementById('inv-existing-batches-wrap');
        if (wrap) wrap.style.display = 'none';
        var modeWrap = document.getElementById('inv-receive-mode-wrap');
        if (modeWrap) modeWrap.style.display = 'none';
        updateInfoActionButtons();
    }

    var _currentBatchesCache = [];

    function fetchProductBatchesForReceive(productId) {
        var wrap = document.getElementById('inv-existing-batches-wrap');
        var modeWrap = document.getElementById('inv-receive-mode-wrap');
        var countEl = document.getElementById('inv-batches-count');
        var listEl = document.getElementById('inv-batches-list');
        var batchEl = document.getElementById('inv-info-batch');
        var expiryEl = document.getElementById('inv-info-expiry');

        if (!wrap || !listEl) return;

        listEl.innerHTML = '<div style="padding:10px 12px; font-size:12px; color:#9aa6d1;">Loading active batches...</div>';
        wrap.style.display = 'block';

        fetch('api/get_product_batches.php?product_id=' + productId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (selectedProductId !== productId) return;
                if (data && data.success && Array.isArray(data.batches) && data.batches.length > 0) {
                    _currentBatchesCache = data.batches;
                    wrap.style.display = 'block';
                    if (modeWrap) modeWrap.style.display = 'block';
                    if (countEl) countEl.textContent = data.batches.length + (data.batches.length === 1 ? ' batch' : ' batches');
                    if (batchEl) batchEl.textContent = data.batches.length + (data.batches.length === 1 ? ' batch' : ' batches');
                    if (expiryEl && data.batches[0] && data.batches[0].expiry_formatted) {
                        expiryEl.textContent = data.batches[0].expiry_formatted;
                    }

                    var item = findCartItem(productId);
                    var activeTargetId = item ? (item.target_batch_id || null) : null;
                    var activeMode = item ? (item.receive_mode || 'new') : 'new';

                    var html = '';
                    data.batches.forEach(function (b, idx) {
                        var isSelected = activeMode === 'merge' && (activeTargetId === b.batch_id || (!activeTargetId && idx === 0));
                        var expText = b.expiry_formatted || 'No Expiry';
                        var expClass = 'pos-batch-expiry-ok';
                        if (b.expiry_date) {
                            var diffDays = Math.ceil((new Date(b.expiry_date + 'T00:00:00').getTime() - Date.now()) / (1000 * 3600 * 24));
                            if (diffDays < 0) expClass = 'pos-batch-expiry-crit';
                            else if (diffDays <= 90) expClass = 'pos-batch-expiry-warn';
                        } else {
                            expClass = 'pos-batch-expiry-none';
                        }

                        html += '<div class="pos-batch-row ' + (isSelected ? 'selected' : '') + '" '
                            + 'data-batch-id="' + b.batch_id + '" '
                            + 'data-batch-no="' + escapeHtml(b.batch_number || '') + '" '
                            + 'data-expiry="' + escapeHtml(b.expiry_date || '') + '" '
                            + 'onclick="window.selectBatchToReceive(this, ' + b.batch_id + ')">'
                            + '<div class="pos-batch-row-icon"></div>'
                            + '<div class="pos-batch-row-num">' + escapeHtml(b.batch_number || ('Batch ' + (idx + 1))) + '</div>'
                            + '<div class="pos-batch-row-qty">' + b.qty + ' <span style="font-size:10px; color:#9aa6d1;">' + escapeHtml((data.product && data.product.unit) || 'pcs') + '</span></div>'
                            + '<div class="pos-batch-row-expiry ' + expClass + '">Exp: ' + escapeHtml(expText) + '</div>'
                            + '</div>';
                    });
                    listEl.innerHTML = html;

                    window.setReceiveMode(activeMode, true);
                } else {
                    _currentBatchesCache = [];
                    wrap.style.display = 'none';
                    if (modeWrap) modeWrap.style.display = 'none';
                    if (batchEl) batchEl.textContent = 'None';
                    if (expiryEl) expiryEl.textContent = 'No Expiry';
                    window.setReceiveMode('new', true);
                }
            })
            .catch(function (err) {
                console.error('[Receive Batches] Error:', err);
                wrap.style.display = 'none';
                if (modeWrap) modeWrap.style.display = 'none';
            });
    }

    window.selectBatchToReceive = function(el, batchId) {
        if (!el) return;
        var list = document.getElementById('inv-batches-list');
        if (list) {
            list.querySelectorAll('.pos-batch-row').forEach(function (r) { r.classList.remove('selected'); });
        }
        el.classList.add('selected');

        var batchNo = el.getAttribute('data-batch-no') || '';
        var expiry = el.getAttribute('data-expiry') || '';

        var batchInput = document.getElementById('inv-receive-batch');
        var expiryInput = document.getElementById('inv-receive-expiry');
        if (batchInput) batchInput.value = batchNo;
        if (expiryInput) expiryInput.value = expiry;

        if (selectedProductId != null) {
            var item = findCartItem(selectedProductId);
            if (item) {
                item.receive_mode = 'merge';
                item.target_batch_id = batchId;
                item.batch_number = batchNo;
                item.expiry_date = expiry;
            }
        }

        window.setReceiveMode('merge', false, batchNo, expiry);
    };

    window.setReceiveMode = function(mode, skipFieldUpdate, batchNoVal, expiryVal) {
        var newBtn = document.getElementById('inv-mode-new-batch');
        var mergeBtn = document.getElementById('inv-mode-merge');
        var hintEl = document.getElementById('inv-receive-mode-hint');
        var mergeHint = document.getElementById('inv-expiry-merge-hint');
        var batchInput = document.getElementById('inv-receive-batch');
        var expiryInput = document.getElementById('inv-receive-expiry');

        if (mode === 'merge') {
            if (newBtn) newBtn.classList.remove('active');
            if (mergeBtn) mergeBtn.classList.add('active');

            var list = document.getElementById('inv-batches-list');
            var selectedRow = list ? list.querySelector('.pos-batch-row.selected') : null;
            if (!selectedRow && list) {
                selectedRow = list.querySelector('.pos-batch-row');
                if (selectedRow) selectedRow.classList.add('selected');
            }

            var bNo = batchNoVal || (selectedRow ? selectedRow.getAttribute('data-batch-no') : '');
            var exp = expiryVal || (selectedRow ? selectedRow.getAttribute('data-expiry') : '');
            var bId = selectedRow ? parseInt(selectedRow.getAttribute('data-batch-id'), 10) : null;

            if (!skipFieldUpdate) {
                if (batchInput && bNo) batchInput.value = bNo;
                if (expiryInput && exp) expiryInput.value = exp;
            }

            if (mergeHint) {
                mergeHint.style.display = 'inline';
                mergeHint.textContent = bNo ? 'Will merge into ' + bNo : 'Will merge with selected batch';
            }
            if (hintEl) {
                hintEl.textContent = 'Stock will be added into existing batch ' + (bNo || '') + (exp ? ' (expires ' + exp + ')' : '') + '.';
                hintEl.style.color = '#51D55A';
            }

            if (selectedProductId != null) {
                var item = findCartItem(selectedProductId);
                if (item) {
                    item.receive_mode = 'merge';
                    item.target_batch_id = bId;
                    if (bNo) item.batch_number = bNo;
                    if (exp) item.expiry_date = exp;
                }
            }
        } else {
            if (newBtn) newBtn.classList.add('active');
            if (mergeBtn) mergeBtn.classList.remove('active');

            var list2 = document.getElementById('inv-batches-list');
            if (list2) {
                list2.querySelectorAll('.pos-batch-row').forEach(function (r) { r.classList.remove('selected'); });
            }

            if (mergeHint) mergeHint.style.display = 'none';
            if (hintEl) {
                hintEl.textContent = 'Creates a new batch row with its own expiry date. Select a batch above to merge instead.';
                hintEl.style.color = '#9aa6d1';
            }

            if (!skipFieldUpdate) {
                if (batchInput && batchInput.value && _currentBatchesCache.some(function(b){ return b.batch_number === batchInput.value; })) {
                    batchInput.value = '';
                }
                if (expiryInput && expiryInput.value && _currentBatchesCache.some(function(b){ return b.expiry_date === expiryInput.value; })) {
                    expiryInput.value = '';
                }
            }

            if (selectedProductId != null) {
                var item2 = findCartItem(selectedProductId);
                if (item2) {
                    item2.receive_mode = 'new';
                    item2.target_batch_id = null;
                    if (batchInput) item2.batch_number = batchInput.value.trim();
                    if (expiryInput) item2.expiry_date = expiryInput.value.trim();
                }
            }
        }
        syncReceiveFieldsToCart();
    };

    function showProductInfo(rawProduct) {
        var product = normalizeProduct(rawProduct);
        if (!product || product.productId <= 0) { clearProductInfo(); return; }

        selectedProductId = product.productId;

        var emptyEl = document.getElementById('inv-info-empty');
        var contentEl = document.getElementById('inv-info-content');
        var imageWrap = document.getElementById('inv-info-image-wrap');
        var imageEl = document.getElementById('inv-info-image');
        var nameEl = document.getElementById('inv-info-name');
        var categoryEl = document.getElementById('inv-info-category');
        var stockEl = document.getElementById('inv-info-stock');
        var statusEl = document.getElementById('inv-info-status');
        var reorderEl = document.getElementById('inv-info-reorder');
        var expiryEl = document.getElementById('inv-info-expiry');
        var batchEl = document.getElementById('inv-info-batch');
        var locationEl = document.getElementById('inv-info-location');

        if (emptyEl) emptyEl.style.display = 'none';
        if (contentEl) contentEl.style.display = '';

        if (nameEl) nameEl.textContent = product.name;
        if (categoryEl) categoryEl.textContent = product.category || 'Uncategorized';
        if (locationEl) locationEl.textContent = product.location || '—';

        // Stock display — show remaining if release mode
        var stockText;
        if (txnMode === 'out') {
            var cartItem = findCartItem(product.productId);
            var pendingQty = cartItem ? cartItem.qty : 0;
            var effectiveStock = product.currentStock - pendingQty;
            stockText = String(effectiveStock);
            if (product.unit) stockText += ' ' + product.unit;
            stockText += ' remaining';
            if (pendingQty > 0) stockText += ' (' + product.currentStock + ' before release)';
        } else {
            stockText = String(product.currentStock);
            if (product.unit) stockText += ' ' + product.unit;
        }
        if (stockEl) stockEl.textContent = stockText;

        if (statusEl) {
            var displayStatus, displayStatusLabel;
            if (txnMode === 'out') {
                var cartItem2 = findCartItem(product.productId);
                var pendingQty2 = cartItem2 ? cartItem2.qty : 0;
                var effectiveStock2 = product.currentStock - pendingQty2;
                displayStatus = computeStockStatus(effectiveStock2, product.reorderPoint);
                displayStatusLabel = getStockStatusLabel(displayStatus);
            } else {
                displayStatus = product.stockStatus;
                displayStatusLabel = product.stockStatusLabel;
            }
            statusEl.innerHTML = '<span class="pos-status-badge pos-status-' + escapeHtml(displayStatus) + '">'
                + escapeHtml(displayStatusLabel) + '</span>';
        }

        if (reorderEl) reorderEl.textContent = String(product.reorderPoint);
        if (expiryEl) expiryEl.textContent = formatExpiryDate(product.expiryDate);
        if (batchEl) batchEl.textContent = product.batchNumber || '—';

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

        loadReceiveFieldsFromCart(product.productId);
        highlightSelectedCartRow();
        updateInfoActionButtons();

        if (txnMode === 'in') {
            fetchProductBatchesForReceive(product.productId);
        }
    }

    // ── Receive fields ───────────────────────────────────────────────────────

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

        var mergeBtn = document.getElementById('inv-mode-merge');
        if (mergeBtn && mergeBtn.classList.contains('active')) {
            item.receive_mode = 'merge';
            var selectedRow = document.querySelector('#inv-batches-list .pos-batch-row.selected');
            if (selectedRow) {
                item.target_batch_id = parseInt(selectedRow.getAttribute('data-batch-id'), 10) || null;
            }
        } else {
            item.receive_mode = 'new';
            item.target_batch_id = null;
        }
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
        if (item && item.receive_mode) {
            window.setReceiveMode(item.receive_mode, true);
        }
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

    // ── Cart ─────────────────────────────────────────────────────────────────

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
        if (!product || product.productId <= 0) {
            showToast('Invalid product.', 'error');
            return false;
        }

        cacheProduct(rawProduct);

        var existing = findCartItem(product.productId);
        if (existing) {
            if (!canIncreaseQty(existing)) {
                showToast('Insufficient stock for ' + existing.name + '. Available: ' + existing.currentStock + '.', 'error');
                return false;
            }
            existing.qty += 1;
            renderCart(flashExisting !== false ? product.productId : null);
            showProductInfo(product);
            return true;
        }

        if (txnMode === 'out' && product.currentStock <= 0) {
            showToast('Cannot release — ' + product.name + ' is out of stock.', 'error');
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
            category: product.category,
            location: product.location,
            currentStock: product.currentStock,
            unit: product.unit,
            price: product.price,
            imageUrl: product.imageUrl,
            productExpiryDate: product.expiryDate || '',
            batch_number: product.batchNumber || '',
            manufacturing_date: '',
            expiry_date: initialExpiry,
            shelf_life: product.shelfLife,
            qty: 1
        });

        renderCart(product.productId);
        showProductInfo(product);
        return true;
    }

    function changeQty(productId, delta) {
        var idx = findCartIndex(productId);
        if (idx === -1) return;
        var item = cart[idx];
        var newQty = item.qty + delta;

        if (newQty <= 0) { removeFromCart(productId); return; }
        if (txnMode === 'out' && newQty > item.currentStock) {
            showToast('Insufficient stock for ' + item.name + '. Available: ' + item.currentStock + '.', 'error');
            return;
        }

        item.qty = newQty;
        var inputEl = document.querySelector('.pos-qty-input[data-id="' + productId + '"]');
        if (inputEl) inputEl.value = String(newQty);
        updateItemQty(productId, newQty, true);
    }

    function updateItemQty(productId, newQty, enforce) {
        var idx = findCartIndex(productId);
        if (idx === -1) return;
        var item = cart[idx];

        if (txnMode === 'out' && newQty > item.currentStock) {
            showToast('Insufficient stock for ' + item.name + '. Available: ' + item.currentStock + '.', 'error');
            newQty = item.currentStock;
            var inputEl = document.querySelector('.pos-qty-input[data-id="' + productId + '"]');
            if (inputEl) inputEl.value = String(newQty);
        } else if (newQty < 1 && enforce) {
            newQty = 1;
            var inputEl2 = document.querySelector('.pos-qty-input[data-id="' + productId + '"]');
            if (inputEl2) inputEl2.value = '1';
        }

        item.qty = newQty;

        // Update row total price without rebuilding the table DOM
        var row = document.querySelector('#inv-cart-body tr[data-product-id="' + productId + '"]');
        if (row) {
            var totalCell = row.querySelector('.pos-cart-total-cell');
            if (totalCell) {
                totalCell.textContent = '₱' + (parseFloat(item.price || 0) * item.qty).toFixed(2);
            }
        }

        // Recalculate and update total quantity & grand total in summary & sticky hero button
        var totalQty = 0;
        var grandTotal = 0;
        for (var i = 0; i < cart.length; i++) {
            totalQty += cart[i].qty;
            grandTotal += (parseFloat(cart[i].price || 0) * cart[i].qty);
        }
        var summaryQt = document.getElementById('inv-summary-qty');
        if (summaryQt) summaryQt.textContent = String(totalQty);

        var summaryTot = document.getElementById('inv-summary-total');
        if (summaryTot) {
            summaryTot.textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        var confirmBtn = document.getElementById('inv-confirm-btn');
        if (confirmBtn && cart.length > 0) {
            var itemText = cart.length === 1 ? '1 item' : cart.length + ' items';
            var totalStr = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            confirmBtn.innerHTML = escapeHtml(defaultConfirmLabel) + ' · ' + itemText + ' · ' + totalStr;
        }

        // If product is currently displayed in the right panel, update remaining stock info
        if (selectedProductId === productId) {
            var details = getProductDetails(productId);
            if (details) showProductInfo(details);
        }
    }

    function removeFromCart(productId) {
        var idx = findCartIndex(productId);
        if (idx === -1) return;
        cart.splice(idx, 1);
        renderCart();

        if (selectedProductId === productId) {
            if (cart.length > 0) {
                var last = cart[cart.length - 1];
                var details = getProductDetails(last.productId);
                if (details) showProductInfo(details);
            } else {
                clearProductInfo();
            }
        }
    }

    function clearCart() {
        cart = [];
        renderCart();
        clearProductInfo();
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
        var summaryPr = document.getElementById('inv-summary-products');
        var summaryQt = document.getElementById('inv-summary-qty');
        var summaryTot = document.getElementById('inv-summary-total');
        var cartBadge = document.getElementById('inv-cart-badge');
        var clearBtn = document.getElementById('inv-clear-btn');

        if (!bodyEl) return;
        bodyEl.innerHTML = '';

        if (cart.length === 0) {
            if (emptyEl) emptyEl.style.display = '';
            if (tableEl) tableEl.style.display = 'none';
            if (clearBtn) clearBtn.style.display = 'none';
            if (cartBadge) cartBadge.textContent = '0';
            if (confirmBtn) {
                confirmBtn.disabled = true;
                confirmBtn.textContent = getConfirmBaseLabel();
            }
            if (summaryPr) summaryPr.textContent = '0';
            if (summaryQt) summaryQt.textContent = '0';
            if (summaryTot) summaryTot.textContent = '₱0.00';
            return;
        }

        if (emptyEl) emptyEl.style.display = 'none';
        if (tableEl) tableEl.style.display = '';
        if (clearBtn) clearBtn.style.display = 'inline-flex';
        if (cartBadge) cartBadge.textContent = String(cart.length);
        if (confirmBtn) confirmBtn.disabled = false;

        var totalQty = 0;
        var grandTotal = 0;
        cart.forEach(function (item) {
            totalQty += item.qty;
            var lineTotal = (parseFloat(item.price || 0) * item.qty);
            grandTotal += lineTotal;

            var tr = document.createElement('tr');
            tr.setAttribute('data-product-id', String(item.productId));
            if (flashProductId && item.productId === flashProductId) {
                tr.className = 'pos-cart-row-flash';
            }

            // Thumbnail cell
            var thumbTd = document.createElement('td');
            thumbTd.className = 'pos-cart-thumb-cell';
            if (item.imageUrl) {
                var thumbImg = document.createElement('img');
                thumbImg.className = 'pos-cart-thumb';
                thumbImg.src = item.imageUrl;
                thumbImg.alt = item.name;
                thumbImg.title = item.name;
                thumbImg.onerror = function () {
                    var ph = document.createElement('div');
                    ph.className = 'pos-cart-thumb-ph';
                    ph.textContent = '📦';
                    thumbImg.replaceWith(ph);
                };
                thumbTd.appendChild(thumbImg);
            } else {
                var thumbPh = document.createElement('div');
                thumbPh.className = 'pos-cart-thumb-ph';
                thumbPh.textContent = '📦';
                thumbTd.appendChild(thumbPh);
            }

            // Product name cell
            var nameTd = document.createElement('td');
            nameTd.innerHTML = '<div class="pos-cart-name">' + escapeHtml(item.name) + '</div>'
                + (item.unit ? '<div class="pos-cart-unit">Unit: ' + escapeHtml(item.unit) + '</div>' : '');

            // Current stock
            var stockTd = document.createElement('td');
            stockTd.innerHTML = '<span class="pos-cart-stock-chip">Stock: ' + escapeHtml(String(item.currentStock)) + '</span>';

            // Qty controls: [−] [ input ] [+]
            var qtyTd = document.createElement('td');
            qtyTd.style.textAlign = 'center';
            var qtyControls = document.createElement('div');
            qtyControls.className = 'pos-qty-controls';

            var minusBtn = document.createElement('button');
            minusBtn.type = 'button';
            minusBtn.className = 'pos-qty-btn';
            minusBtn.textContent = '−';
            minusBtn.setAttribute('data-action', 'decrease');
            minusBtn.setAttribute('data-id', String(item.productId));
            minusBtn.setAttribute('title', 'Decrease quantity');
            minusBtn.setAttribute('aria-label', 'Decrease quantity');

            var qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.className = 'pos-qty-input';
            qtyInput.min = '1';
            if (txnMode === 'out') {
                qtyInput.max = String(item.currentStock);
            }
            qtyInput.value = String(item.qty);
            qtyInput.setAttribute('data-id', String(item.productId));
            qtyInput.setAttribute('aria-label', 'Quantity for ' + item.name);

            qtyInput.addEventListener('focus', function () {
                this.select();
                var pid = parseInt(this.getAttribute('data-id') || '0', 10);
                if (pid > 0 && selectedProductId !== pid) {
                    var details = getProductDetails(pid);
                    if (details) showProductInfo(details);
                }
            });

            qtyInput.addEventListener('input', function () {
                var pid = parseInt(this.getAttribute('data-id') || '0', 10);
                var raw = this.value;
                if (raw === '') return;
                var val = parseInt(raw, 10);
                if (isNaN(val) || val < 1) return;
                updateItemQty(pid, val, false);
            });

            qtyInput.addEventListener('change', function () {
                var pid = parseInt(this.getAttribute('data-id') || '0', 10);
                var raw = this.value;
                var val = parseInt(raw, 10);
                if (isNaN(val) || val < 1) {
                    val = 1;
                    this.value = '1';
                }
                updateItemQty(pid, val, true);
            });

            qtyInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });

            var plusBtn = document.createElement('button');
            plusBtn.type = 'button';
            plusBtn.className = 'pos-qty-btn';
            plusBtn.textContent = '+';
            plusBtn.setAttribute('data-action', 'increase');
            plusBtn.setAttribute('data-id', String(item.productId));
            plusBtn.setAttribute('title', 'Increase quantity');
            plusBtn.setAttribute('aria-label', 'Increase quantity');

            qtyControls.appendChild(minusBtn);
            qtyControls.appendChild(qtyInput);
            qtyControls.appendChild(plusBtn);
            qtyTd.appendChild(qtyControls);

            var priceTd = document.createElement('td');
            priceTd.textContent = '₱' + parseFloat(item.price || 0).toFixed(2);

            var totalTd = document.createElement('td');
            totalTd.className = 'pos-cart-total-cell';
            totalTd.textContent = '₱' + lineTotal.toFixed(2);

            var actionTd = document.createElement('td');
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'pos-remove-btn';
            removeBtn.title = 'Remove product';
            removeBtn.innerHTML = '<svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>';
            removeBtn.setAttribute('data-action', 'remove');
            removeBtn.setAttribute('data-id', String(item.productId));
            actionTd.appendChild(removeBtn);

            tr.appendChild(thumbTd);
            tr.appendChild(nameTd);
            tr.appendChild(stockTd);
            tr.appendChild(qtyTd);
            tr.appendChild(priceTd);
            tr.appendChild(totalTd);
            tr.appendChild(actionTd);
            bodyEl.appendChild(tr);
        });

        if (summaryPr) summaryPr.textContent = String(cart.length);
        if (summaryQt) summaryQt.textContent = String(totalQty);
        if (summaryTot) {
            summaryTot.textContent = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        if (confirmBtn) {
            var itemText = cart.length === 1 ? '1 item' : cart.length + ' items';
            var totalFormatted = '₱' + grandTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            confirmBtn.innerHTML = escapeHtml(getConfirmBaseLabel()) + ' · ' + itemText + ' · ' + totalFormatted;
        }

        highlightSelectedCartRow();
        updateInfoActionButtons();
    }

    // ── Cart events ──────────────────────────────────────────────────────────

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
        if (e.target.closest('[data-action]') || e.target.closest('.pos-qty-input')) return;
        var row = e.target.closest('tr[data-product-id]');
        if (!row) return;
        var productId = parseInt(row.getAttribute('data-product-id') || '0', 10);
        if (productId <= 0) return;
        var details = getProductDetails(productId);
        if (details) showProductInfo(details);
    }

    // ── Product search ───────────────────────────────────────────────────────

    /**
     * Search products via the dedicated search API.
     * Returns Promise<Array> of product objects.
     */
    function searchProducts(query) {
        return fetch('api/search_products.php?q=' + encodeURIComponent(query) + '&limit=8')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.data) return data.data;
                return [];
            })
            .catch(function () { return []; });
    }

    function renderSearchDropdown(products, query) {
        var dropdown = document.getElementById('inv-search-dropdown');
        if (!dropdown) return;

        if (!products || products.length === 0) {
            dropdown.innerHTML = '<div class="pos-search-no-results">No products found for "' + escapeHtml(query) + '"</div>';
            dropdown.classList.add('open');
            return;
        }

        dropdown.innerHTML = '';
        products.forEach(function (p) {
            var id = parseInt(p.product_id || p.id || '0', 10);
            var name = p.product_name || p.name || 'Unknown';
            var category = p.category || '';
            var qty = parseInt(p.quantity || '0', 10);
            var rp = parseInt(p.reorder_point || '10', 10);
            var status = computeStockStatus(qty, rp);
            var imageUrl = p.image_url || null;

            var item = document.createElement('div');
            item.className = 'pos-search-item';
            item.setAttribute('data-product-id', String(id));

            // Thumbnail
            if (imageUrl) {
                var img = document.createElement('img');
                img.className = 'pos-search-item-thumb';
                img.src = imageUrl;
                img.alt = name;
                img.onerror = function () {
                    var ph = makePh();
                    img.replaceWith(ph);
                };
                item.appendChild(img);
            } else {
                item.appendChild(makePh());
            }

            // Info
            var info = document.createElement('div');
            info.className = 'pos-search-item-info';
            info.innerHTML = '<div class="pos-search-item-name">' + escapeHtml(name) + '</div>'
                + '<div class="pos-search-item-meta">' + escapeHtml(category || '—') + ' · ' + qty + ' in stock</div>';
            item.appendChild(info);

            // Stock badge
            var badge = document.createElement('span');
            badge.className = 'pos-search-item-stock-badge';
            if (status === 'out_of_stock') {
                badge.textContent = 'Out';
                badge.style.cssText = 'background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.25);';
            } else if (status === 'low_stock') {
                badge.textContent = 'Low';
                badge.style.cssText = 'background:rgba(245,158,11,0.1);color:#fbbf24;border:1px solid rgba(245,158,11,0.25);';
            } else {
                badge.textContent = 'OK';
                badge.style.cssText = 'background:rgba(81,213,90,0.1);color:#51D55A;border:1px solid rgba(81,213,90,0.25);';
            }
            item.appendChild(badge);

            // Click → add to cart
            item.addEventListener('click', function () {
                cacheProduct(p);
                addToCart(p);
                closeSearchDropdown();
                var searchInput = document.getElementById('inv-product-search');
                if (searchInput) { searchInput.value = ''; }
                updateSearchClearBtn();
            });

            dropdown.appendChild(item);
        });

        dropdown.classList.add('open');
    }

    function makePh() {
        var ph = document.createElement('div');
        ph.className = 'pos-search-item-placeholder';
        ph.textContent = '📦';
        return ph;
    }

    function closeSearchDropdown() {
        var dropdown = document.getElementById('inv-search-dropdown');
        if (dropdown) dropdown.classList.remove('open');
    }

    function updateSearchClearBtn() {
        var input = document.getElementById('inv-product-search');
        var clearBtn = document.getElementById('inv-search-clear');
        if (!input || !clearBtn) return;
        if (input.value.trim().length > 0) {
            clearBtn.classList.add('visible');
        } else {
            clearBtn.classList.remove('visible');
        }
    }

    function initSearch() {
        var searchInput = document.getElementById('inv-product-search');
        var clearBtn = document.getElementById('inv-search-clear');
        if (!searchInput) return;

        searchInput.addEventListener('input', function () {
            searchInput.classList.remove('error-border');
            var query = searchInput.value.trim();
            updateSearchClearBtn();

            if (query.length < 1) {
                closeSearchDropdown();
                _lastQuery = '';
                return;
            }

            if (query === _lastQuery) return;
            _lastQuery = query;

            clearTimeout(_searchTimer);
            _searchTimer = setTimeout(function () {
                searchProducts(query).then(function (products) {
                    renderSearchDropdown(products, query);
                });
            }, 250);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeSearchDropdown();
                searchInput.blur();
            }
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                searchInput.value = '';
                searchInput.classList.remove('error-border');
                updateSearchClearBtn();
                closeSearchDropdown();
                _lastQuery = '';
                searchInput.focus();
            });
        }

        // Close dropdown on outside click
        document.addEventListener('click', function (e) {
            var wrap = document.getElementById('inv-search-wrap');
            if (wrap && !wrap.contains(e.target)) {
                closeSearchDropdown();
            }
        });
    }



    // ── Form submit ──────────────────────────────────────────────────────────

    function validateAndSubmit(form) {
        if (cart.length === 0) {
            var searchInput = document.getElementById('inv-product-search');
            if (searchInput) {
                searchInput.classList.add('error-border');
                searchInput.focus();
            }
            showToast('Add at least one product to the transaction.', 'error');
            return false;
        }

        if (txnMode === 'out') {
            for (var i = 0; i < cart.length; i++) {
                if (cart[i].qty > cart[i].currentStock) {
                    showToast('Insufficient stock for ' + cart[i].name + '. Available: ' + cart[i].currentStock + '.', 'error');
                    return false;
                }
            }
        }

        var payload = cart.map(function (item) {
            var row = { product_id: item.productId, qty: item.qty };
            if (txnMode === 'in') {
                if (item.batch_number) row.batch_number = item.batch_number;
                if (item.expiry_date) row.expiry_date = item.expiry_date;
                if (item.manufacturing_date) row.manufacturing_date = item.manufacturing_date;
                if (item.location) row.location = item.location;
                if (item.category) row.category = item.category;
                if (item.target_batch_id) row.target_batch_id = item.target_batch_id;
                if (item.receive_mode) row.receive_mode = item.receive_mode;
            }
            return row;
        });

        document.getElementById('inv-items-json').value = JSON.stringify(payload);
        return true;
    }

    // ── Init ─────────────────────────────────────────────────────────────────

    function init() {
        var form = document.getElementById('inv-txn-form');
        if (!form) return;

        txnMode = form.getAttribute('data-txn-mode') || 'in';

        var confirmBtn = document.getElementById('inv-confirm-btn');
        if (confirmBtn) {
            defaultConfirmLabel = confirmBtn.getAttribute('data-base-label') || confirmBtn.textContent.trim() || (txnMode === 'out' ? 'Confirm Release' : 'Confirm Receive');
        } else {
            defaultConfirmLabel = txnMode === 'out' ? 'Confirm Release' : 'Confirm Receive';
        }

        renderCart();
        clearProductInfo();

        // Remarks collapsible drawer
        var remarksToggle = document.getElementById('inv-remarks-toggle');
        var remarksDrawer = document.getElementById('inv-remarks-drawer');
        var remarksInput = document.getElementById('inv-remarks-input');
        var remarksIcon = document.getElementById('inv-remarks-toggle-icon');
        var remarksText = document.getElementById('inv-remarks-toggle-text');

        if (remarksToggle && remarksDrawer) {
            remarksToggle.addEventListener('click', function () {
                var isHidden = remarksDrawer.style.display === 'none';
                if (isHidden) {
                    remarksDrawer.style.display = 'block';
                    if (remarksIcon) remarksIcon.textContent = '−';
                    if (remarksText) remarksText.textContent = 'Hide remarks';
                    if (remarksInput) remarksInput.focus();
                } else {
                    remarksDrawer.style.display = 'none';
                    if (remarksIcon) remarksIcon.textContent = '＋';
                    if (remarksText) remarksText.textContent = (remarksInput && remarksInput.value.trim()) ? 'Edit remarks' : 'Add remarks';
                }
            });

            if (remarksInput) {
                remarksInput.addEventListener('input', function () {
                    if (remarksText && remarksDrawer.style.display === 'none') {
                        remarksText.textContent = this.value.trim() ? 'Edit remarks' : 'Add remarks';
                    }
                });
            }
        }

        // Immediately display preloaded or requested product
        if (window.AUTOLOAD_PRODUCT && window.AUTOLOAD_PRODUCT.product_id) {
            addToCart(window.AUTOLOAD_PRODUCT);
            showProductInfo(window.AUTOLOAD_PRODUCT);
        } else {
            var urlParams = new URLSearchParams(window.location.search);
            var targetId = urlParams.get('product_id') || urlParams.get('id');
            var targetSku = urlParams.get('sku');
            var targetQ = urlParams.get('q');
            if (targetId) {
                fetch('api/get_product_by_id.php?id=' + encodeURIComponent(targetId))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success && res.found && res.product) {
                            addToCart(res.product);
                            showProductInfo(res.product);
                        }
                    })
                    .catch(function (e) { console.warn('Could not autoload product by ID:', e); });
            } else if (targetQ || targetSku) {
                fetch('api/search_products.php?q=' + encodeURIComponent(targetQ || targetSku))
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success && res.data && res.data.length > 0) {
                            addToCart(res.data[0]);
                            showProductInfo(res.data[0]);
                        }
                    })
                    .catch(function (e) { console.warn('Could not autoload product by query:', e); });
            }
        }

        // Search
        initSearch();


        // Info panel remove
        var infoRemove = document.getElementById('inv-info-remove');
        if (infoRemove) {
            infoRemove.addEventListener('click', function () {
                if (selectedProductId != null) removeFromCart(selectedProductId);
            });
        }

        // Cart body
        var cartBody = document.getElementById('inv-cart-body');
        if (cartBody) {
            cartBody.addEventListener('click', handleCartAction);
            cartBody.addEventListener('click', handleCartRowSelect);
        }

        // Clear transaction
        var clearBtn = document.getElementById('inv-clear-btn');
        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (cart.length === 0) return;
                clearCart();
            });
        }

        // Form submit
        form.addEventListener('submit', function (e) {
            syncReceiveFieldsToCart();
            if (!validateAndSubmit(form)) e.preventDefault();
        });

        // Receive field sync
        var receiveBatch = document.getElementById('inv-receive-batch');
        if (receiveBatch) {
            receiveBatch.addEventListener('input', syncReceiveFieldsToCart);
            receiveBatch.addEventListener('change', syncReceiveFieldsToCart);
        }

        var receiveMfg = document.getElementById('inv-receive-mfg');
        if (receiveMfg) {
            var mfgListener = function () { calculatePredictedExpiry(); syncReceiveFieldsToCart(); };
            receiveMfg.addEventListener('input', mfgListener);
            receiveMfg.addEventListener('change', mfgListener);
        }

        var receiveExpiry = document.getElementById('inv-receive-expiry');
        if (receiveExpiry) {
            receiveExpiry.addEventListener('input', syncReceiveFieldsToCart);
            receiveExpiry.addEventListener('change', syncReceiveFieldsToCart);
        }

        // Camera scanner
        initCameraScanner();
    }

    // ── Camera Scanner ───────────────────────────────────────────────────────
    var _cameraStream = null;
    var _cameraInterval = null;
    var _cameraBusy = false;
    var _catalogList = [];
    var _catalogLoaded = false;
    var _lastScannedId = null;
    var _lastScanTime = 0;

    function setCameraStatus(text, type) {
        var statusEl = document.getElementById('inv-camera-status-text');
        var dotEl = document.getElementById('inv-camera-dot');
        if (statusEl) statusEl.textContent = text;
        if (dotEl) {
            dotEl.style.background = type === 'success' ? '#51D55A' : (type === 'scanning' ? '#fbbf24' : '#64748b');
        }
    }

    function ensureCatalogLoaded() {
        if (_catalogLoaded && _catalogList.length > 0) return Promise.resolve(_catalogList);
        return fetch('api/get_all_products_for_scanner.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && Array.isArray(data.products)) {
                    _catalogList = data.products;
                    _catalogLoaded = true;
                    _catalogList.forEach(function (p) {
                        cacheProduct(p);
                    });
                }
                return _catalogList;
            })
            .catch(function (err) {
                console.warn('[CameraScanner] Catalog load error:', err);
                return [];
            });
    }

    function toggleCameraScanner() {
        var wrap = document.getElementById('inv-camera-scanner-wrap');
        if (!wrap) return;

        if (_cameraStream) {
            stopCameraScanner();
        } else {
            startCameraScanner();
        }
    }

    var _invFacingMode = 'environment';

    function flipCameraScanner() {
        _invFacingMode = (_invFacingMode === 'environment') ? 'user' : 'environment';
        if (_cameraStream) {
            _cameraStream.getTracks().forEach(function (t) { t.stop(); });
            _cameraStream = null;
        }
        startCameraScanner(_invFacingMode);
    }

    function startCameraScanner(requestedFacingMode) {
        if (requestedFacingMode) {
            _invFacingMode = requestedFacingMode;
        }
        var mode = _invFacingMode || 'environment';
        var wrap = document.getElementById('inv-camera-scanner-wrap');
        var video = document.getElementById('inv-camera-video');
        var btnText = document.getElementById('inv-camera-btn-text');
        var camBtn = document.getElementById('inv-camera-btn');
        if (!wrap || !video) return;

        wrap.style.display = 'block';
        if (btnText) btnText.textContent = 'Close Camera';
        if (camBtn) camBtn.classList.add('pos-camera-active');
        setCameraStatus('Starting camera (' + (mode === 'user' ? 'front' : 'rear') + ')…', 'scanning');

        ensureCatalogLoaded();

        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setCameraStatus('Camera not supported in this browser.', 'error');
            return;
        }

        navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: mode }, width: { ideal: 1280 }, height: { ideal: 720 } }
        }).catch(function () {
            return navigator.mediaDevices.getUserMedia({ video: true });
        }).then(function (stream) {
            _cameraStream = stream;
            video.srcObject = stream;
            setCameraStatus('Point camera at product', 'scanning');

            // Enable the Snap & Add button now that the camera is live
            var snapBtn = document.getElementById('inv-camera-snap-manual');
            if (snapBtn) snapBtn.disabled = false;

            clearInterval(_cameraInterval);
            _cameraInterval = setInterval(function () {
                if (!_cameraBusy && _cameraStream) {
                    processLiveFrame(false);
                }
            }, 1600);
        }).catch(function (err) {
            console.error('[CameraScanner] getUserMedia error:', err);
            setCameraStatus('Could not access camera: ' + (err.message || err), 'error');
        });
    }

    function stopCameraScanner() {
        clearInterval(_cameraInterval);
        _cameraInterval = null;
        _cameraBusy = false;

        if (_cameraStream) {
            _cameraStream.getTracks().forEach(function (t) { t.stop(); });
            _cameraStream = null;
        }

        var wrap = document.getElementById('inv-camera-scanner-wrap');
        var btnText = document.getElementById('inv-camera-btn-text');
        var video = document.getElementById('inv-camera-video');
        var camBtn = document.getElementById('inv-camera-btn');
        var snapBtn = document.getElementById('inv-camera-snap-manual');

        if (wrap) wrap.style.display = 'none';
        if (video) video.srcObject = null;
        if (btnText) btnText.textContent = 'Camera Scanner';
        if (camBtn) camBtn.classList.remove('pos-camera-active');
        if (snapBtn) snapBtn.disabled = true;  // Reset to grey until next session
    }

    function processLiveFrame(isManual) {
        var video = document.getElementById('inv-camera-video');
        var canvas = document.getElementById('inv-camera-canvas');
        if (!video || !canvas || video.readyState < 2) return;

        _cameraBusy = true;
        if (isManual) setCameraStatus('🔍 Analyzing product…', 'scanning');

        var vw = video.videoWidth || 640;
        var vh = video.videoHeight || 480;
        var scale = Math.min(1.0, 640 / vw);
        canvas.width = Math.round(vw * scale);
        canvas.height = Math.round(vh * scale);

        var ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        if (typeof ProductCameraScanner === 'undefined') {
            _cameraBusy = false;
            return;
        }

        // ── Priority 1: Cloud AI Vision for Manual Button Clicks ───────────
        if (isManual && typeof ProductCameraScanner.extractProductWithAiVision === 'function') {
            setCameraStatus('✨ Google Gemini Vision analyzing packaging…', 'scanning');
            ProductCameraScanner.extractProductWithAiVision(canvas).then(function (vRes) {
                if (vRes && vRes.success && vRes.data && vRes.data.product_name) {
                    var vName = vRes.data.product_name;
                    ensureCatalogLoaded().then(function (catalog) {
                        var matched = ProductCameraScanner.matchCatalogItem(vName, catalog);
                        if (matched && matched.item) {
                            _cameraBusy = false;
                            addToCart(matched.item);
                            ProductCameraScanner.playBeep(true);
                            setCameraStatus('✨ Added: ' + (matched.item.product_name || matched.item.name) + ' (Qty +1)', 'success');
                            showToast('✨ Added ' + (matched.item.product_name || matched.item.name) + ' to transaction!', 'success');
                            return;
                        }

                        // Search backend by the clean extracted product name
                        fetch('api/search_products.php?q=' + encodeURIComponent(vName))
                            .then(function (r) { return r.json(); })
                            .then(function (res) {
                                _cameraBusy = false;
                                if (res.success && res.data && res.data.length > 0) {
                                    var remoteItem = res.data[0];
                                    addToCart(remoteItem);
                                    ProductCameraScanner.playBeep(true);
                                    setCameraStatus('✨ Added: ' + remoteItem.product_name, 'success');
                                    showToast('✨ Added ' + remoteItem.product_name + ' to transaction!', 'success');
                                } else {
                                    showToast('AI detected: "' + vName + '". No matching product found in stock.', 'error');
                                    setCameraStatus('No matching product found. Try another item.', 'scanning');
                                }
                            }).catch(function () {
                                _cameraBusy = false;
                                runOcrFallback();
                            });
                    });
                    return;
                }
                runOcrFallback();
            }).catch(function () {
                runOcrFallback();
            });
            return;
        } else {
            runOcrFallback();
        }

        // ── Priority 2: Local OCR Pipeline ──────────────────────────────────
        function runOcrFallback() {
            ProductCameraScanner.runOcr(canvas, function (pct) {
                if (isManual) setCameraStatus('🔍 Reading label (' + pct + '%)…', 'scanning');
            }, true).then(function (res) {
                _cameraBusy = false;
                var rawText = (typeof res === 'object' && res.text != null) ? res.text : (res || '');

                if (!rawText || !rawText.trim()) {
                    if (isManual) showToast('No product detected. Move closer or try better lighting.', 'error');
                    setCameraStatus('Point camera at product', 'scanning');
                    return;
                }

                ensureCatalogLoaded().then(function (catalog) {
                    var matched = ProductCameraScanner.matchCatalogItem(rawText, catalog);
                    if (matched && matched.item) {
                        var now = Date.now();
                        var pId = matched.item.product_id || matched.item.productId || matched.item.id;

                        if (!isManual && _lastScannedId === pId && (now - _lastScanTime < 2500)) {
                            return;
                        }

                        _lastScannedId = pId;
                        _lastScanTime = now;

                        var added = addToCart(matched.item);
                        if (added) {
                            ProductCameraScanner.playBeep(true);
                            setCameraStatus('✨ Added: ' + (matched.item.product_name || matched.item.name) + ' (Qty +1)', 'success');
                            showToast('✨ Added ' + (matched.item.product_name || matched.item.name) + ' to transaction!', 'success');
                        }
                    } else if (isManual) {
                        var parsed = ProductCameraScanner.parseProductLabel(rawText);
                        var queryTerm = parsed.name || rawText.slice(0, 30).trim();
                        if (queryTerm) {
                            fetch('api/search_products.php?q=' + encodeURIComponent(queryTerm))
                                .then(function (r) { return r.json(); })
                                .then(function (res) {
                                    if (res.success && res.data && res.data.length > 0) {
                                        var remoteItem = res.data[0];
                                        addToCart(remoteItem);
                                        ProductCameraScanner.playBeep(true);
                                        setCameraStatus('✨ Added: ' + remoteItem.product_name, 'success');
                                        showToast('✨ Added ' + remoteItem.product_name + ' to transaction!', 'success');
                                    } else {
                                        showToast('Product label: "' + queryTerm + '". No matching item found in inventory.', 'error');
                                        setCameraStatus('No matching product found. Try another item.', 'scanning');
                                    }
                                });
                        } else {
                            showToast('No product recognized on packaging.', 'error');
                        }
                    }
                });
            }).catch(function (err) {
                _cameraBusy = false;
                console.warn('[CameraScanner] OCR error:', err);
                setCameraStatus('Point camera at product', 'scanning');
            });
        }
    }

    function initCameraScanner() {
        var camBtn = document.getElementById('inv-camera-btn');
        if (camBtn) camBtn.addEventListener('click', toggleCameraScanner);

        var camClose = document.getElementById('inv-camera-close-btn');
        if (camClose) camClose.addEventListener('click', stopCameraScanner);

        var camFlip = document.getElementById('inv-camera-flip-btn');
        if (camFlip) camFlip.addEventListener('click', flipCameraScanner);

        // Collapse / expand viewfinder
        var camCollapse = document.getElementById('inv-camera-collapse-btn');
        if (camCollapse) {
            camCollapse.addEventListener('click', function () {
                var vf = document.getElementById('inv-camera-viewfinder');
                var sb = document.getElementById('inv-camera-snap-bar');
                if (!vf) return;
                var hidden = vf.style.display === 'none';
                vf.style.display = hidden ? '' : 'none';
                if (sb) sb.style.display = hidden ? '' : 'none';
                // Rotate chevron
                var icon = camCollapse.querySelector('svg');
                if (icon) icon.style.transform = hidden ? '' : 'rotate(180deg)';
            });
        }

        // Torch toggle
        var camTorch = document.getElementById('inv-camera-torch-btn');
        var _torchOn = false;
        if (camTorch) {
            camTorch.addEventListener('click', function () {
                if (!_cameraStream) return;
                var track = _cameraStream.getVideoTracks()[0];
                if (!track) return;
                _torchOn = !_torchOn;
                track.applyConstraints({ advanced: [{ torch: _torchOn }] }).catch(function () {
                    showToast('Torch not supported on this device.', 'error');
                    _torchOn = false;
                });
                camTorch.classList.toggle('cam-torch-on', _torchOn);
            });
        }

        // Snap & Add
        var camSnap = document.getElementById('inv-camera-snap-manual');
        if (camSnap) {
            camSnap.addEventListener('click', function () {
                if (_cameraBusy) return;   // already processing — ignore double-tap
                if (!_cameraStream) return; // camera not running
                processLiveFrame(true);
            });
        }

        // Type code toggle
        var typeCodeBtn = document.getElementById('inv-camera-type-code-btn');
        var manualWrap = document.getElementById('inv-camera-manual-code-wrap');
        var manualInput = document.getElementById('inv-camera-manual-code-input');
        var manualSubmit = document.getElementById('inv-camera-manual-code-submit');
        var manualCancel = document.getElementById('inv-camera-manual-code-cancel');

        if (typeCodeBtn && manualWrap) {
            typeCodeBtn.addEventListener('click', function () {
                var open = manualWrap.style.display !== 'none';
                manualWrap.style.display = open ? 'none' : 'flex';
                typeCodeBtn.style.display = open ? '' : 'none';
                if (!open && manualInput) manualInput.focus();
            });
        }
        if (manualCancel && manualWrap) {
            manualCancel.addEventListener('click', function () {
                manualWrap.style.display = 'none';
                if (typeCodeBtn) typeCodeBtn.style.display = '';
                if (manualInput) manualInput.value = '';
            });
        }
        function submitManualCode() {
            if (!manualInput) return;
            var code = manualInput.value.trim();
            if (!code) return;
            setCameraStatus('Looking up "' + code + '"…', 'scanning');
            ensureCatalogLoaded().then(function (catalog) {
                var lc = code.toLowerCase();
                var found = catalog.find(function (p) {
                    return (p.product_id && String(p.product_id) === code) ||
                        (p.sku && p.sku.toLowerCase() === lc) ||
                        (p.barcode && p.barcode.toLowerCase() === lc) ||
                        (p.product_name && p.product_name.toLowerCase().includes(lc)) ||
                        (p.name && p.name.toLowerCase().includes(lc));
                });
                if (found) {
                    addToCart(found);
                    setCameraStatus('Added: ' + (found.product_name || found.name), 'success');
                    showToast('Added ' + (found.product_name || found.name) + ' to transaction!', 'success');
                    manualInput.value = '';
                    manualWrap.style.display = 'none';
                    if (typeCodeBtn) typeCodeBtn.style.display = '';
                } else {
                    fetch('api/search_products.php?q=' + encodeURIComponent(code))
                        .then(function (r) { return r.json(); })
                        .then(function (res) {
                            if (res.success && res.data && res.data.length > 0) {
                                addToCart(res.data[0]);
                                setCameraStatus('Added: ' + res.data[0].product_name, 'success');
                                showToast('Added ' + res.data[0].product_name + ' to transaction!', 'success');
                                manualInput.value = '';
                                manualWrap.style.display = 'none';
                                if (typeCodeBtn) typeCodeBtn.style.display = '';
                            } else {
                                showToast('No product found for "' + code + '".', 'error');
                                setCameraStatus('Point camera at product', 'scanning');
                            }
                        });
                }
            });
        }
        if (manualSubmit) manualSubmit.addEventListener('click', submitManualCode);
        if (manualInput) {
            manualInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') submitManualCode();
            });
        }
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        showToast: showToast,
        addToCart: addToCart,
        clearCart: clearCart,
        startCameraScanner: startCameraScanner,
        stopCameraScanner: stopCameraScanner,
        setReceiveMode: window.setReceiveMode,
        selectBatchToReceive: window.selectBatchToReceive,
    };
})();
