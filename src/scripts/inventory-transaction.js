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
        // No barcode input in AI scanner mode — noop retained for internal compatibility
    }

    function updateScannerBadge(state, text) {
        var badge = document.getElementById('inv-scanner-badge');
        if (!badge) return;
        badge.textContent = text;
        badge.className = 'pos-scanner-badge hidden sm:inline pos-scanner-badge--' + (state || 'idle');
    }

    function updateInfoActionButtons() {
        var removeBtn = document.getElementById('inv-info-remove');
        if (!removeBtn) return;

        var inCart = selectedProductId != null && findCartItem(selectedProductId) != null;
        removeBtn.disabled = !inCart;
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
        if (raw._normalized) return raw;

        var qty = parseInt(raw.quantity != null ? raw.quantity : raw.qty || raw.currentStock || '0', 10);
        var reorderPoint = parseInt(
            raw.reorder_point != null ? raw.reorder_point : raw.reorder || raw.reorderPoint || '10',
            10
        );
        var stockStatus = raw.stock_status || raw.stockStatus || computeStockStatus(qty, reorderPoint);

        return {
            _normalized: true,
            productId: parseInt(raw.product_id || raw.id || raw.productId || '0', 10),
            name: raw.product_name || raw.name || 'Unknown',
            barcode: raw.barcode || '',
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
                unit: cartItem.unit,
                price: cartItem.price
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

        var stockText;
        if (txnMode === 'out') {
            // Show how many will be left after the current pending release
            var cartItem = findCartItem(product.productId);
            var pendingQty = cartItem ? cartItem.qty : 0;
            var effectiveStock = product.currentStock - pendingQty;
            stockText = String(effectiveStock);
            if (product.unit) stockText += ' ' + product.unit;
            stockText += ' remaining';
            if (pendingQty > 0) {
                stockText += ' (' + product.currentStock + ' before release)';
            }
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
            price: product.price,
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

        // Refresh the info panel so "Current Stock" shows live remaining
        if (selectedProductId === productId) {
            var details = getProductDetails(productId);
            if (details) showProductInfo(details);
        } else {
            var details2 = getProductDetails(productId);
            if (details2 && selectedProductId === productId) showProductInfo(details2);
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

            var barcodeTd = document.createElement('td');
            barcodeTd.textContent = item.barcode || '—';

            var stockTd = document.createElement('td');
            stockTd.textContent = item.currentStock;

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

            var priceTd = document.createElement('td');
            priceTd.textContent = '₱' + parseFloat(item.price || 0).toFixed(2);

            var totalTd = document.createElement('td');
            totalTd.textContent = '₱' + (parseFloat(item.price || 0) * item.qty).toFixed(2);

            var actionTd = document.createElement('td');
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'pos-remove-btn';
            removeBtn.title = 'Remove';
            removeBtn.innerHTML = '<svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>';
            removeBtn.setAttribute('data-action', 'remove');
            removeBtn.setAttribute('data-id', String(item.productId));
            actionTd.appendChild(removeBtn);

            tr.appendChild(nameTd);
            tr.appendChild(stockTd);
            tr.appendChild(qtyTd);
            tr.appendChild(priceTd);
            tr.appendChild(totalTd);
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

    // ── AI Scanner: product lookup by integer ID ──────────────────────────────

    /**
     * Called by TMScanner's onAutoConfirm. Fetches product via get_product_by_id.php
     * and adds it to the cart. Also updates the info panel.
     * @param {number} productId
     * @param {number} confidence   0-1
     * @param {string} classLabel
     * @param {string} categorySlug
     */
    function addProductById(productId, confidence, classLabel, categorySlug) {
        productId = parseInt(productId, 10);
        if (productId <= 0) {
            showToast('Could not resolve product from scan.', 'error');
            if (window.TMScanner) TMScanner.resumeAfterConfirm();
            return;
        }

        // Check cache first
        var cached = catalogIndex[String(productId)];
        if (cached) {
            addToCart(cached);
            showToast('Auto-identified: ' + (cached.product_name || cached.name || 'Product') + ' (' + Math.round(confidence * 100) + '% confidence)', 'success');
            if (window.TMScanner) TMScanner.resumeAfterConfirm();
            return;
        }

        fetch('api/get_product_by_id.php?id=' + productId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.found && data.product) {
                    cacheProduct(data.product);
                    addToCart(data.product);
                    showToast(
                        'Auto-identified: ' + (data.product.product_name || 'Product') +
                        ' (' + Math.round(confidence * 100) + '% confidence)',
                        'success'
                    );
                } else {
                    showToast('Product ID ' + productId + ' not found in database.', 'error');
                }
                if (window.TMScanner) TMScanner.resumeAfterConfirm();
            })
            .catch(function () {
                showToast('Failed to fetch product details.', 'error');
                if (window.TMScanner) TMScanner.resumeAfterConfirm();
            });
    }

    /**
     * Called by TMScanner's onNeedsConfirm.
     * Fetches product details for each candidate, then populates
     * the amber candidate drawer so staff can tap to confirm.
     * @param {Array<{productId, label, confidence}>} candidates
     * @param {string} categorySlug
     */
    function showCandidateDrawer(candidates, categorySlug) {
        var drawer = document.getElementById('tm-candidate-drawer');
        var list   = document.getElementById('tm-candidate-list');
        if (!drawer || !list) {
            // Drawer not in DOM — fall back to auto with top candidate
            if (candidates.length > 0) addProductById(candidates[0].productId, candidates[0].confidence, candidates[0].label, categorySlug);
            return;
        }

        list.innerHTML = '<div style="padding:12px;text-align:center;color:#9aa6d1;font-size:12px;">Loading candidates…</div>';
        drawer.classList.add('is-open');

        // Fetch product names/images for each candidate in parallel
        var fetches = candidates.map(function (c) {
            var cached = catalogIndex[String(c.productId)];
            if (cached) {
                return Promise.resolve({ found: true, product: cached, confidence: c.confidence, label: c.label });
            }
            return fetch('api/get_product_by_id.php?id=' + c.productId)
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.found && data.product) {
                        cacheProduct(data.product);
                    }
                    return { found: data.found, product: data.product, confidence: c.confidence, label: c.label };
                })
                .catch(function () {
                    return { found: false, product: null, confidence: c.confidence, label: c.label };
                });
        });

        Promise.all(fetches).then(function (results) {
            list.innerHTML = '';
            var validCount = 0;

            results.forEach(function (res) {
                if (!res.found || !res.product) return;
                validCount++;

                var p = res.product;
                var pId = parseInt(p.product_id || p.id || '0', 10);
                var pName = p.product_name || p.name || 'Unknown Product';
                var pConf = Math.round(res.confidence * 100);
                var pImg  = p.image_url || null;

                var item = document.createElement('div');
                item.className = 'tm-candidate-item';

                // Thumbnail
                if (pImg) {
                    var img = document.createElement('img');
                    img.className = 'tm-candidate-thumb';
                    img.src = pImg;
                    img.alt = pName;
                    img.onerror = function () {
                        img.replaceWith(makePlaceholder());
                    };
                    item.appendChild(img);
                } else {
                    item.appendChild(makePlaceholder());
                }

                // Info block
                var info = document.createElement('div');
                info.className = 'tm-candidate-info';
                info.innerHTML =
                    '<div class="tm-candidate-name">' + escapeHtml(pName) + '</div>' +
                    '<div class="tm-candidate-conf">' + pConf + '% confidence</div>';
                item.appendChild(info);

                // Pick button
                var pickBtn = document.createElement('button');
                pickBtn.type = 'button';
                pickBtn.className = 'tm-candidate-pick-btn';
                pickBtn.textContent = 'Select';
                pickBtn.setAttribute('data-product-id', String(pId));
                pickBtn.addEventListener('click', function () {
                    closeCandidateDrawer();
                    addToCart(p);
                    showToast('Manually confirmed: ' + pName, 'success');
                    if (window.TMScanner) TMScanner.resumeAfterConfirm();
                });
                item.appendChild(pickBtn);

                list.appendChild(item);
            });

            if (validCount === 0) {
                list.innerHTML = '<div style="padding:12px;text-align:center;color:#f87171;font-size:12px;">No recognizable products found. Try again.</div>';
            }
        });
    }

    function makePlaceholder() {
        var ph = document.createElement('div');
        ph.className = 'tm-candidate-thumb-placeholder';
        ph.innerHTML = '&#128722;'; // shopping bag emoji
        return ph;
    }

    function closeCandidateDrawer() {
        var drawer = document.getElementById('tm-candidate-drawer');
        if (drawer) drawer.classList.remove('is-open');
    }

    // ── End AI Scanner helpers ─────────────────────────────────────────

    function handleInfoAction(action) {
        if (selectedProductId == null) return;
        if (action === 'remove') removeFromCart(selectedProductId);
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

    function initTMScanner() {
        if (!window.TMScanner) return;

        var videoEl        = document.getElementById('tm-video');
        var cameraWrap     = document.getElementById('tm-camera-wrap');
        var toggleBtn      = document.getElementById('tm-toggle-btn');
        var stopBtn        = document.getElementById('tm-stop-btn');
        var cameraStatus   = document.getElementById('tm-camera-status');
        var confFill       = document.getElementById('tm-conf-fill');
        var confLabelText  = document.getElementById('tm-conf-label-text');
        var confPct        = document.getElementById('tm-conf-pct');

        updateScannerBadge('idle', 'AI Scanner ready');
        if (toggleBtn) toggleBtn.disabled = false;

        // ─ TMScanner callbacks ───────────────────────────────────
        TMScanner.init({
            onModelLoaded: function (slug) {
                updateScannerBadge('idle', 'Model loaded: ' + slug);
                if (cameraStatus) cameraStatus.textContent = 'Model loaded. Starting camera…';
            },
            onModelError: function (msg) {
                updateScannerBadge('off', 'Model error');
                showToast('Model error: ' + msg, 'error');
            },
            onCameraStarted: function () {
                updateScannerBadge('scanning', 'Scanning…');
                if (cameraStatus) cameraStatus.textContent = 'Scanning… hold product in view';
                if (cameraWrap) cameraWrap.style.display = '';
                if (toggleBtn) { toggleBtn.textContent = 'Stop Camera'; toggleBtn.classList.add('is-active'); }
            },
            onCameraError: function (msg) {
                updateScannerBadge('off', 'Camera error');
                showToast('Camera error: ' + msg, 'error');
                if (cameraWrap) cameraWrap.style.display = 'none';
                if (toggleBtn) { toggleBtn.textContent = 'Start Camera'; toggleBtn.classList.remove('is-active'); toggleBtn.disabled = false; }
            },
            onStopped: function () {
                updateScannerBadge('idle', 'AI Scanner idle');
                if (cameraWrap) cameraWrap.style.display = 'none';
                if (confFill) { confFill.style.width = '0%'; confFill.className = 'tm-conf-fill'; }
                if (confLabelText) confLabelText.textContent = 'Waiting…';
                if (confPct) confPct.textContent = '—';
                if (cameraStatus) cameraStatus.textContent = 'Camera stopped.';
                if (toggleBtn) { toggleBtn.textContent = 'Start AI Scanner'; toggleBtn.classList.remove('is-active'); toggleBtn.disabled = false; }
            },
            onPredicting: function (topLabel, topConf, allSorted) {
                if (!confFill) return;
                var pct = Math.round(topConf * 100);
                confFill.style.width = pct + '%';
                confFill.className = 'tm-conf-fill' +
                    (topConf < 0.5 ? ' is-low' : topConf < 0.80 ? ' is-medium' : '');
                if (confLabelText) confLabelText.textContent = topLabel;
                if (confPct) confPct.textContent = pct + '%';
            },
            onAutoConfirm: function (productId, confidence, classLabel) {
                if (cameraStatus) cameraStatus.textContent = 'Identified! Adding to transaction…';
                addProductById(productId, confidence, classLabel, 'global');
            }
        });

        // ─ Toggle camera button ───────────────────────────────────
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (TMScanner.isRunning()) {
                    TMScanner.stopCamera();
                    return;
                }

                toggleBtn.disabled = true;
                updateScannerBadge('loading', 'Loading model…');
                if (cameraStatus) cameraStatus.textContent = 'Loading AI model…';

                var loadPromise = TMScanner.loadModel();

                loadPromise
                    .then(function () {
                        if (!videoEl) {
                            showToast('Video element not found.', 'error');
                            toggleBtn.disabled = false;
                            return;
                        }
                        return TMScanner.startCamera(videoEl);
                    })
                    .catch(function (err) {
                        toggleBtn.disabled = false;
                    });
            });
        }

        // Stop button (inside camera footer)
        if (stopBtn) {
            stopBtn.addEventListener('click', function () {
                TMScanner.stopCamera();
            });
        }

    }

    function init() {
        var form = document.getElementById('inv-txn-form');
        if (!form) return;

        txnMode = form.getAttribute('data-txn-mode') || 'in';
        renderCart();
        clearProductInfo();

        // ─ Wire up TM Scanner ───────────────────────────────────
        initTMScanner();

        // ─ Info panel / Cart events ──────────────────────────────
        document.getElementById('inv-info-remove')?.addEventListener('click', function () {
            handleInfoAction('remove');
        });

        var cartBody = document.getElementById('inv-cart-body');
        cartBody?.addEventListener('click', handleCartAction);
        cartBody?.addEventListener('click', handleCartRowSelect);

        document.getElementById('inv-clear-btn')?.addEventListener('click', function () {
            if (cart.length === 0) return;
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
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        showToast:         showToast,
        addProductById:    addProductById,
        addToCart:         addToCart,
        clearCart:         clearCart,
        showCandidateDrawer: showCandidateDrawer,
    };
})();
