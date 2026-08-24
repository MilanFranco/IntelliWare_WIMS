/**
 * inventory-transaction.js
 *
 * POS-style inventory transaction workflow.
 * Supports:
 *   - Product search bar with AJAX dropdown
 *   - AI camera scanner (image similarity via ImageScanner module)
 *   - Confirmation modal for medium-confidence scan matches
 *   - Cart management (add / qty change / remove)
 *   - Product info panel with reference image
 */
var InventoryTransaction = (function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────
    var cart = [];
    var txnMode = 'in';
    var catalogIndex = {};          // { productId -> rawProduct }
    var selectedProductId = null;

    // ── Search debounce ──────────────────────────────────────────────────────
    var _searchTimer = null;
    var _lastQuery   = '';

    // ── Toast ────────────────────────────────────────────────────────────────

    function showToast(message, type) {
        var toast    = document.getElementById('toast-container');
        var toastMsg = document.getElementById('toast-message');
        var toastIcon= document.getElementById('toast-icon');
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

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function getStockStatusLabel(status) {
        switch (status) {
            case 'out_of_stock': return 'Out of Stock';
            case 'low_stock':    return 'Low Stock';
            default:             return 'In Stock';
        }
    }

    function computeStockStatus(qty, reorderPoint) {
        if (qty <= 0)              return 'out_of_stock';
        if (qty <= reorderPoint)   return 'low_stock';
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
            day   = '' + d.getDate(),
            year  = d.getFullYear();
        if (month.length < 2) month = '0' + month;
        if (day.length < 2)   day   = '0' + day;
        return [year, month, day].join('-');
    }

    function normalizeProduct(raw) {
        if (!raw) return null;
        if (raw._normalized) return raw;

        var qty          = parseInt(raw.quantity != null ? raw.quantity : raw.qty || raw.currentStock || '0', 10);
        var reorderPoint = parseInt(raw.reorder_point != null ? raw.reorder_point : raw.reorder || raw.reorderPoint || '10', 10);
        var stockStatus  = raw.stock_status || raw.stockStatus || computeStockStatus(qty, reorderPoint);

        return {
            _normalized      : true,
            productId        : parseInt(raw.product_id || raw.id || raw.productId || '0', 10),
            name             : raw.product_name || raw.name || 'Unknown',
            category         : raw.category || '',
            location         : raw.location || '',
            currentStock     : qty,
            reorderPoint     : reorderPoint,
            expiryDate       : raw.expiry_date || raw.expiryDate || null,
            batchNumber      : raw.batch_number || raw.batchNumber || '',
            shelfLife        : raw.shelf_life != null ? parseInt(raw.shelf_life, 10) : (raw.shelfLife != null ? parseInt(raw.shelfLife, 10) : null),
            stockStatus      : stockStatus,
            stockStatusLabel : raw.stock_status_label || raw.stockStatusLabel || getStockStatusLabel(stockStatus),
            imageUrl         : raw.image_url || raw.imageUrl || null,
            unit             : raw.unit || raw.uom || '',
            price            : parseFloat(raw.price != null ? raw.price : '0.00'),
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

    // ── Scanner badge ────────────────────────────────────────────────────────

    function updateScannerBadge(state, text) {
        var badge = document.getElementById('inv-scanner-badge');
        if (!badge) return;
        badge.textContent = text;
        badge.className = 'pos-scanner-status-badge ' + (state || 'idle');
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
        var emptyEl   = document.getElementById('inv-info-empty');
        var contentEl = document.getElementById('inv-info-content');
        if (emptyEl)   emptyEl.style.display   = '';
        if (contentEl) contentEl.style.display  = 'none';
        updateInfoActionButtons();
    }

    function showProductInfo(rawProduct) {
        var product = normalizeProduct(rawProduct);
        if (!product || product.productId <= 0) { clearProductInfo(); return; }

        selectedProductId = product.productId;

        var emptyEl     = document.getElementById('inv-info-empty');
        var contentEl   = document.getElementById('inv-info-content');
        var imageWrap   = document.getElementById('inv-info-image-wrap');
        var imageEl     = document.getElementById('inv-info-image');
        var nameEl      = document.getElementById('inv-info-name');
        var categoryEl  = document.getElementById('inv-info-category');
        var stockEl     = document.getElementById('inv-info-stock');
        var statusEl    = document.getElementById('inv-info-status');
        var reorderEl   = document.getElementById('inv-info-reorder');
        var expiryEl    = document.getElementById('inv-info-expiry');
        var batchEl     = document.getElementById('inv-info-batch');
        var locationEl  = document.getElementById('inv-info-location');

        if (emptyEl)   emptyEl.style.display   = 'none';
        if (contentEl) contentEl.style.display  = '';

        if (nameEl)     nameEl.textContent     = product.name;
        if (categoryEl) categoryEl.textContent = product.category || 'Uncategorized';
        if (locationEl) locationEl.textContent = product.location || '—';

        // Stock display — show remaining if release mode
        var stockText;
        if (txnMode === 'out') {
            var cartItem    = findCartItem(product.productId);
            var pendingQty  = cartItem ? cartItem.qty : 0;
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
                var cartItem2      = findCartItem(product.productId);
                var pendingQty2    = cartItem2 ? cartItem2.qty : 0;
                var effectiveStock2= product.currentStock - pendingQty2;
                displayStatus      = computeStockStatus(effectiveStock2, product.reorderPoint);
                displayStatusLabel = getStockStatusLabel(displayStatus);
            } else {
                displayStatus      = product.stockStatus;
                displayStatusLabel = product.stockStatusLabel;
            }
            statusEl.innerHTML = '<span class="pos-status-badge pos-status-' + escapeHtml(displayStatus) + '">'
                + escapeHtml(displayStatusLabel) + '</span>';
        }

        if (reorderEl) reorderEl.textContent = String(product.reorderPoint);
        if (expiryEl)  expiryEl.textContent  = formatExpiryDate(product.expiryDate);
        if (batchEl)   batchEl.textContent   = product.batchNumber || '—';

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
    }

    // ── Receive fields ───────────────────────────────────────────────────────

    function syncReceiveFieldsToCart() {
        if (txnMode !== 'in' || selectedProductId == null) return;
        var item = findCartItem(selectedProductId);
        if (!item) return;
        var batchInput  = document.getElementById('inv-receive-batch');
        var mfgInput    = document.getElementById('inv-receive-mfg');
        var expiryInput = document.getElementById('inv-receive-expiry');
        if (batchInput)  item.batch_number      = batchInput.value.trim();
        if (mfgInput)    item.manufacturing_date = mfgInput.value.trim();
        if (expiryInput) item.expiry_date        = expiryInput.value.trim();
    }

    function loadReceiveFieldsFromCart(productId) {
        var batchInput  = document.getElementById('inv-receive-batch');
        var mfgInput    = document.getElementById('inv-receive-mfg');
        var expiryInput = document.getElementById('inv-receive-expiry');
        if (!batchInput && !expiryInput && !mfgInput) return;

        var item = findCartItem(productId);
        if (batchInput)  batchInput.value  = item ? (item.batch_number || '') : '';
        if (mfgInput)    mfgInput.value    = item ? (item.manufacturing_date || '') : '';
        if (expiryInput) {
            expiryInput.value = item && item.expiry_date
                ? item.expiry_date
                : (item && item.productExpiryDate ? item.productExpiryDate : '');
        }
    }

    function calculatePredictedExpiry() {
        if (selectedProductId == null) return;
        var item = findCartItem(selectedProductId);
        if (!item) return;
        var mfgInput    = document.getElementById('inv-receive-mfg');
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
            productId           : product.productId,
            name                : product.name,
            category            : product.category,
            location            : product.location,
            currentStock        : product.currentStock,
            unit                : product.unit,
            price               : product.price,
            imageUrl            : product.imageUrl,
            productExpiryDate   : product.expiryDate || '',
            batch_number        : product.batchNumber || '',
            manufacturing_date  : '',
            expiry_date         : initialExpiry,
            shelf_life          : product.shelfLife,
            qty                 : 1
        });

        renderCart(product.productId);
        showProductInfo(product);
        return true;
    }

    function changeQty(productId, delta) {
        var idx = findCartIndex(productId);
        if (idx === -1) return;
        var item   = cart[idx];
        var newQty = item.qty + delta;

        if (newQty <= 0) { removeFromCart(productId); return; }
        if (txnMode === 'out' && newQty > item.currentStock) {
            showToast('Insufficient stock. Available: ' + item.currentStock + '.', 'error');
            return;
        }

        item.qty = newQty;
        renderCart(productId);

        if (selectedProductId === productId) {
            var details = getProductDetails(productId);
            if (details) showProductInfo(details);
        } else {
            updateInfoActionButtons();
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
        var emptyEl   = document.getElementById('inv-cart-empty');
        var tableEl   = document.getElementById('inv-cart-table');
        var bodyEl    = document.getElementById('inv-cart-body');
        var confirmBtn= document.getElementById('inv-confirm-btn');
        var summaryPr = document.getElementById('inv-summary-products');
        var summaryQt = document.getElementById('inv-summary-qty');

        if (!bodyEl) return;
        bodyEl.innerHTML = '';

        if (cart.length === 0) {
            if (emptyEl)   emptyEl.style.display    = '';
            if (tableEl)   tableEl.style.display    = 'none';
            if (confirmBtn) confirmBtn.disabled     = true;
            if (summaryPr)  summaryPr.textContent   = '0';
            if (summaryQt)  summaryQt.textContent   = '0';
            return;
        }

        if (emptyEl)   emptyEl.style.display   = 'none';
        if (tableEl)   tableEl.style.display   = '';
        if (confirmBtn) confirmBtn.disabled    = false;

        var totalQty = 0;
        cart.forEach(function (item) {
            totalQty += item.qty;

            var tr = document.createElement('tr');
            tr.setAttribute('data-product-id', String(item.productId));
            if (flashProductId && item.productId === flashProductId) {
                tr.className = 'pos-cart-row-flash';
            }

            // Product name cell
            var nameTd = document.createElement('td');
            nameTd.innerHTML = '<div class="font-semibold">' + escapeHtml(item.name) + '</div>'
                + (item.unit ? '<div class="text-[10px] text-[#9aa6d1]">' + escapeHtml(item.unit) + '</div>' : '');

            // Current stock
            var stockTd = document.createElement('td');
            stockTd.textContent = item.currentStock;

            // Qty controls
            var qtyTd       = document.createElement('td');
            var qtyControls = document.createElement('div');
            qtyControls.className = 'pos-qty-controls';

            var minusBtn = document.createElement('button');
            minusBtn.type = 'button'; minusBtn.className = 'pos-qty-btn';
            minusBtn.textContent = '−';
            minusBtn.setAttribute('data-action', 'decrease');
            minusBtn.setAttribute('data-id', String(item.productId));

            var qtySpan = document.createElement('span');
            qtySpan.className = 'pos-qty-value';
            qtySpan.textContent = String(item.qty);

            var plusBtn = document.createElement('button');
            plusBtn.type = 'button'; plusBtn.className = 'pos-qty-btn';
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

            var actionTd  = document.createElement('td');
            var removeBtn = document.createElement('button');
            removeBtn.type = 'button'; removeBtn.className = 'pos-remove-btn'; removeBtn.title = 'Remove';
            removeBtn.innerHTML = '<svg style="width:14px;height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>';
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

        if (summaryPr) summaryPr.textContent = String(cart.length);
        if (summaryQt) summaryQt.textContent = String(totalQty);
        highlightSelectedCartRow();
        updateInfoActionButtons();
    }

    // ── Cart events ──────────────────────────────────────────────────────────

    function handleCartAction(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        e.stopPropagation();
        var action    = btn.getAttribute('data-action');
        var productId = parseInt(btn.getAttribute('data-id') || '0', 10);
        if (productId <= 0) return;
        if (action === 'increase') changeQty(productId, 1);
        else if (action === 'decrease') changeQty(productId, -1);
        else if (action === 'remove')   removeFromCart(productId);
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
            var id       = parseInt(p.product_id || p.id || '0', 10);
            var name     = p.product_name || p.name || 'Unknown';
            var category = p.category || '';
            var qty      = parseInt(p.quantity || '0', 10);
            var rp       = parseInt(p.reorder_point || '10', 10);
            var status   = computeStockStatus(qty, rp);
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
        var clearBtn    = document.getElementById('inv-search-clear');
        if (!searchInput) return;

        searchInput.addEventListener('input', function () {
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

    // ── Scan Confirmation Modal ───────────────────────────────────────────────

    function openScanModal(candidates) {
        var backdrop  = document.getElementById('scan-modal-backdrop');
        var container = document.getElementById('scan-modal-candidates');
        if (!backdrop || !container) return;

        container.innerHTML = '';

        candidates.forEach(function (c) {
            var row = document.createElement('div');
            row.className = 'scan-modal-product-row';

            if (c.imageUrl) {
                var img = document.createElement('img');
                img.className = 'scan-modal-thumb';
                img.src = c.imageUrl;
                img.alt = c.name;
                img.onerror = function () { img.replaceWith(makeModalPh()); };
                row.appendChild(img);
            } else {
                row.appendChild(makeModalPh());
            }

            var info = document.createElement('div');
            info.className = 'scan-modal-info';
            info.innerHTML = '<div class="scan-modal-pname">' + escapeHtml(c.name) + '</div>'
                + '<div class="scan-modal-pcat">' + escapeHtml(c.category || '—') + '</div>'
                + '<div class="scan-modal-pconf">' + Math.round(c.score * 100) + '% match</div>';
            row.appendChild(info);

            row.addEventListener('click', function () {
                closeScanModal();
                addToCart(c);
                showToast('Added: ' + c.name, 'success');
                if (window.ImageScanner) ImageScanner.resumeAfterConfirm();
            });

            container.appendChild(row);
        });

        backdrop.classList.add('open');
    }

    function closeScanModal() {
        var backdrop = document.getElementById('scan-modal-backdrop');
        if (backdrop) backdrop.classList.remove('open');
    }

    function makeModalPh() {
        var ph = document.createElement('div');
        ph.className = 'scan-modal-thumb-ph';
        ph.textContent = '📦';
        return ph;
    }

    // ── Image Scanner integration ─────────────────────────────────────────────

    var _needsConfirmDebounce = null;

    function initImageScanner() {
        var videoEl      = document.getElementById('inv-camera-video');
        var cameraWrap   = document.getElementById('inv-camera-wrap');
        var toggleBtn    = document.getElementById('inv-camera-toggle-btn');
        var stopBtn      = document.getElementById('inv-camera-stop-btn');
        var cameraStatus = document.getElementById('inv-camera-status');
        var confFill     = document.getElementById('inv-conf-fill');
        var confLabel    = document.getElementById('inv-conf-label-text');
        var confPct      = document.getElementById('inv-conf-pct');

        if (!window.ImageScanner) return;

        ImageScanner.init({
            onStatusChange: function (state, text) {
                updateScannerBadge(state === 'scanning' ? 'scanning' : state === 'loading' || state === 'embedding' ? 'loading' : state === 'error' ? 'error' : 'idle', text);
                if (cameraStatus) cameraStatus.textContent = text;

                if (state === 'ready') {
                    if (toggleBtn) { toggleBtn.disabled = false; }
                }
                if (state === 'error') {
                    if (cameraWrap) cameraWrap.classList.remove('visible');
                    if (toggleBtn)  { toggleBtn.textContent = 'Start AI Scanner'; toggleBtn.classList.remove('is-active'); toggleBtn.disabled = false; }
                }
            },

            onEmbeddingsReady: function (count) {
                if (count === 0) {
                    updateScannerBadge('idle', 'No product images found');
                    showToast('Add reference photos to your products to enable scanning.', 'error');
                } else {
                    updateScannerBadge('idle', count + ' products ready to scan');
                }
            },

            onPredicting: function (topName, topScore, allSorted) {
                var pct = Math.round(topScore * 100);
                if (confFill) {
                    confFill.style.width = pct + '%';
                    confFill.className   = 'pos-conf-fill' + (topScore < 0.40 ? ' low' : topScore < 0.70 ? ' medium' : '');
                }
                if (confLabel) confLabel.textContent = topName;
                if (confPct)   confPct.textContent   = pct + '%';
            },

            onAutoConfirm: function (entry, score) {
                // entry = { productId, name, category, imageUrl, price, quantity, reorderPoint, unit, vec }
                if (cameraStatus) cameraStatus.textContent = 'Match found! Adding…';
                var raw = {
                    product_id   : entry.productId,
                    product_name : entry.name,
                    category     : entry.category,
                    image_url    : entry.imageUrl,
                    price        : entry.price,
                    quantity     : entry.quantity,
                    reorder_point: entry.reorderPoint,
                    unit         : entry.unit,
                };
                cacheProduct(raw);
                addToCart(raw);
                showToast('Scanned: ' + entry.name + ' (' + Math.round(score * 100) + '% match)', 'success');
                ImageScanner.resumeAfterConfirm();
            },

            onStopped: function () {
                updateScannerBadge('idle', 'AI Scanner idle');
                if (cameraWrap) cameraWrap.classList.remove('visible');
                if (confFill)   { confFill.style.width = '0%'; confFill.className = 'pos-conf-fill'; }
                if (confLabel)  confLabel.textContent = 'Waiting…';
                if (confPct)    confPct.textContent   = '—';
                if (cameraStatus) cameraStatus.textContent = 'Camera stopped.';
                if (toggleBtn) { toggleBtn.textContent = 'Start AI Scanner'; toggleBtn.classList.remove('is-active'); toggleBtn.disabled = false; }
                closeScanModal();
            },
        });

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                if (ImageScanner.isRunning()) {
                    ImageScanner.stopCamera();
                    return;
                }

                toggleBtn.disabled = true;
                updateScannerBadge('loading', 'Loading model…');
                if (cameraStatus) cameraStatus.textContent = 'Loading AI model…';

                ImageScanner.loadModel()
                    .then(function () {
                        if (!videoEl) throw new Error('Video element not found.');
                        return ImageScanner.startCamera(videoEl);
                    })
                    .then(function () {
                        if (cameraWrap) cameraWrap.classList.add('visible');
                        if (toggleBtn) { toggleBtn.textContent = 'Stop Camera'; toggleBtn.classList.add('is-active'); toggleBtn.disabled = false; }
                    })
                    .catch(function (err) {
                        toggleBtn.disabled = false;
                        showToast('Scanner error: ' + (err.message || err), 'error');
                    });
            });
        }

        if (stopBtn) {
            stopBtn.addEventListener('click', function () { ImageScanner.stopCamera(); });
        }

        // Modal cancel → dismiss and resume
        var cancelBtn = document.getElementById('scan-modal-cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                closeScanModal();
                if (window.ImageScanner) ImageScanner.resumeAfterConfirm();
            });
        }
    }

    // ── Form submit ──────────────────────────────────────────────────────────

    function validateAndSubmit(form) {
        if (cart.length === 0) {
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
                if (item.batch_number)       row.batch_number        = item.batch_number;
                if (item.expiry_date)        row.expiry_date         = item.expiry_date;
                if (item.manufacturing_date) row.manufacturing_date  = item.manufacturing_date;
                if (item.location)           row.location            = item.location;
                if (item.category)           row.category            = item.category;
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
        renderCart();
        clearProductInfo();

        // Search
        initSearch();

        // Camera scanner
        initImageScanner();

        // Info panel remove
        document.getElementById('inv-info-remove')?.addEventListener('click', function () {
            if (selectedProductId != null) removeFromCart(selectedProductId);
        });

        // Cart body
        var cartBody = document.getElementById('inv-cart-body');
        cartBody?.addEventListener('click', handleCartAction);
        cartBody?.addEventListener('click', handleCartRowSelect);

        // Clear transaction
        document.getElementById('inv-clear-btn')?.addEventListener('click', function () {
            if (cart.length === 0) return;
            clearCart();
        });

        // Form submit
        form.addEventListener('submit', function (e) {
            syncReceiveFieldsToCart();
            if (!validateAndSubmit(form)) e.preventDefault();
        });

        // Receive field sync
        document.getElementById('inv-receive-batch')?.addEventListener('input', syncReceiveFieldsToCart);
        document.getElementById('inv-receive-batch')?.addEventListener('change', syncReceiveFieldsToCart);

        var mfgListener = function () { calculatePredictedExpiry(); syncReceiveFieldsToCart(); };
        document.getElementById('inv-receive-mfg')?.addEventListener('input', mfgListener);
        document.getElementById('inv-receive-mfg')?.addEventListener('change', mfgListener);

        document.getElementById('inv-receive-expiry')?.addEventListener('input', syncReceiveFieldsToCart);
        document.getElementById('inv-receive-expiry')?.addEventListener('change', syncReceiveFieldsToCart);
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        showToast : showToast,
        addToCart : addToCart,
        clearCart : clearCart,
    };
})();
