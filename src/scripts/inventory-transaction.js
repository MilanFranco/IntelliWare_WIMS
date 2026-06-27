/**
 * Barcode-first inventory transaction workflow (Receive / Release pages).
 */
var InventoryTransaction = (function () {
    'use strict';

    var currentStock = 0;
    var txnMode = 'in';

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

    function populateProduct(optionEl) {
        if (!optionEl || !optionEl.value) {
            clearProduct();
            return;
        }

        var name = optionEl.getAttribute('data-name') || optionEl.textContent.trim();
        var barcode = optionEl.getAttribute('data-barcode') || '—';
        var qty = parseInt(optionEl.getAttribute('data-qty') || '0', 10);
        var uom = optionEl.getAttribute('data-uom') || '';

        currentStock = qty;
        document.getElementById('inv-product-id').value = optionEl.value;
        document.getElementById('inv-display-name').textContent = name;
        document.getElementById('inv-display-barcode').textContent = barcode || '—';
        document.getElementById('inv-display-stock').textContent = qty + (uom ? ' ' + uom : '');
        document.getElementById('inv-product-panel').classList.add('visible');

        var qtyInput = document.getElementById('inv-qty-input');
        qtyInput.value = '1';
        qtyInput.focus();
        qtyInput.select();
    }

    function clearProduct() {
        currentStock = 0;
        document.getElementById('inv-product-id').value = '';
        document.getElementById('inv-product-panel').classList.remove('visible');
    }

    function selectProductByBarcode(barcode) {
        if (!window.BarcodeScanner) return;

        BarcodeScanner.lookup(barcode).then(function (data) {
            if (data.found && data.product) {
                var p = data.product;
                var select = document.getElementById('inv-product-select');

                currentStock = parseInt(p.quantity || '0', 10);
                document.getElementById('inv-product-id').value = p.product_id;
                document.getElementById('inv-display-name').textContent = p.product_name;
                document.getElementById('inv-display-barcode').textContent = p.barcode || '—';
                document.getElementById('inv-display-stock').textContent = currentStock + (p.unit ? ' ' + p.unit : '');
                document.getElementById('inv-product-panel').classList.add('visible');

                if (select && window.jQuery) {
                    jQuery(select).val(String(p.product_id)).trigger('change.select2');
                } else if (select) {
                    select.value = String(p.product_id);
                }

                var qtyInput = document.getElementById('inv-qty-input');
                qtyInput.value = '1';
                qtyInput.focus();
                qtyInput.select();

                showToast('Product found: ' + p.product_name, 'success');
            } else {
                showToast('No product found for barcode: ' + barcode, 'error');
            }
        }).catch(function () {
            showToast('Failed to look up barcode.', 'error');
        });
    }

    function populateFromSelect(select) {
        var option = select.options[select.selectedIndex];
        populateProduct(option);
    }

    function validateForm(form) {
        var productId = parseInt(document.getElementById('inv-product-id').value || '0', 10);
        var qty = parseInt(document.getElementById('inv-qty-input').value || '0', 10);

        if (productId <= 0) {
            showToast('Please scan or select a product first.', 'error');
            return false;
        }
        if (qty <= 0) {
            showToast('Quantity must be greater than zero.', 'error');
            return false;
        }
        if (txnMode === 'out' && qty > currentStock) {
            showToast('Insufficient stock. Available: ' + currentStock + '.', 'error');
            return false;
        }
        return true;
    }

    function init() {
        var form = document.getElementById('inv-txn-form');
        if (!form) return;

        txnMode = form.getAttribute('data-txn-mode') || 'in';

        if (window.jQuery) {
            jQuery('#inv-product-select').select2({
                placeholder: '-- Scan barcode or search product --',
                allowClear: true,
                width: '100%',
                dropdownParent: jQuery(document.body)
            }).on('select2:select', function () {
                populateFromSelect(document.getElementById('inv-product-select'));
            }).on('select2:clear', function () {
                clearProduct();
            });
        }

        if (window.BarcodeScanner) {
            BarcodeScanner.init();

            document.getElementById('inv-scan-btn')?.addEventListener('click', function () {
                BarcodeScanner.open(selectProductByBarcode);
            });

            var wedgeInput = document.getElementById('inv-hid-barcode-input');
            if (wedgeInput) {
                BarcodeScanner.attachHidInput(wedgeInput, function (code) {
                    wedgeInput.value = '';
                    selectProductByBarcode(code);
                });
            }

            document.getElementById('inv-hid-focus-hint')?.addEventListener('click', function () {
                if (wedgeInput) BarcodeScanner.focusHidInput(wedgeInput);
            });
        }

        form.addEventListener('submit', function (e) {
            if (!validateForm(form)) {
                e.preventDefault();
            }
        });

        document.getElementById('inv-qty-input')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (validateForm(form)) {
                    form.submit();
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', init);

    return {
        showToast: showToast,
        selectProductByBarcode: selectProductByBarcode
    };
})();
