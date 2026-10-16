<?php
/**
 * src/partials/critical_stock_modal.php
 *
 * Reusable modal component for clicking and inspecting Critical Stock items on Dashboards.
 * Provides instant item details, stock metrics, full product navigation, and 1-click quick restock.
 */
?>
<!-- Critical Stock Details & Quick Restock Modal Overlay -->
<div id="critical-stock-modal" class="crit-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="crit-modal-title">
    <div class="crit-modal-box">
        <!-- Modal Header -->
        <div class="crit-modal-header">
            <div class="flex items-center gap-2.5">
                <span class="crit-modal-header-icon">
                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.268 16.5c-.77.833.192 2.5 1.732 2.5z"></path></svg>
                </span>
                <div>
                    <h3 class="crit-modal-heading" id="crit-modal-title">Critical Stock Inspection</h3>
                    <p class="crit-modal-subheading">Immediate view and replenishment for low or depleted inventory</p>
                </div>
            </div>
            <button type="button" class="crit-modal-close" onclick="closeCriticalStockModal()" title="Close (Esc)">&times;</button>
        </div>

        <!-- Modal Body Scroll -->
        <div class="crit-modal-body">
            
            <!-- Loading Indicator -->
            <div id="crit-modal-loading" style="display:flex; flex-direction:column; align-items:center; justify-content:center; padding:40px 20px;">
                <div class="crit-spinner"></div>
                <p class="text-sm text-[#9aa6d1] mt-3 font-onest">Loading item details…</p>
            </div>

            <!-- Content Container (Hidden while loading) -->
            <div id="crit-modal-content" style="display:none;" class="space-y-4">

                <!-- Product Hero Card -->
                <div class="crit-product-hero">
                    <div class="crit-img-wrap">
                        <img id="crit-modal-img" src="" alt="Product Photo" class="crit-img" style="display:none;">
                        <div id="crit-modal-img-fallback" class="crit-img-placeholder">
                            <svg class="w-8 h-8 text-[#9aa6d1]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span id="crit-modal-sku-badge" class="crit-pill crit-pill-sku">SKU: -</span>
                            <span id="crit-modal-cat-badge" class="crit-pill crit-pill-cat">Category</span>
                            <span id="crit-modal-loc-badge" class="crit-pill crit-pill-loc">Location</span>
                        </div>
                        <h4 id="crit-modal-prod-name" class="text-lg font-bold text-white font-onest leading-snug line-clamp-2">Product Name</h4>
                        <div class="flex items-center gap-3 mt-2 text-xs text-[#9aa6d1]">
                            <span>Brand/Supplier: <strong id="crit-modal-supplier" class="text-white">-</strong></span>
                            <span>&middot;</span>
                            <span>Unit Price: <strong id="crit-modal-price" class="text-[#51D55A]">₱0.00</strong></span>
                        </div>
                    </div>
                </div>

                <!-- Stock Urgency Banner -->
                <div id="crit-modal-alert-banner" class="crit-alert-banner">
                    <div class="flex items-start gap-2.5">
                        <div id="crit-modal-alert-dot" class="w-3 h-3 rounded-full mt-0.5 shrink-0 animate-ping"></div>
                        <div class="flex-1">
                            <div id="crit-modal-alert-title" class="text-xs font-bold uppercase tracking-wider">Alert Status</div>
                            <div id="crit-modal-alert-desc" class="text-xs text-[#e2e8f0] mt-0.5 leading-relaxed">Alert details</div>
                        </div>
                    </div>
                </div>

                <!-- Metrics Grid -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                    <div class="crit-metric-box">
                        <span class="crit-metric-label">Current Stock</span>
                        <div class="crit-metric-val" id="crit-modal-stock-qty">0</div>
                        <span class="crit-metric-sub" id="crit-modal-stock-unit">units remaining</span>
                    </div>
                    <div class="crit-metric-box">
                        <span class="crit-metric-label">Reorder Point</span>
                        <div class="crit-metric-val text-white" id="crit-modal-reorder-point">0</div>
                        <span class="crit-metric-sub">minimum safe level</span>
                    </div>
                    <div class="crit-metric-box">
                        <span class="crit-metric-label">Shortage Deficit</span>
                        <div class="crit-metric-val text-red-400" id="crit-modal-deficit">0</div>
                        <span class="crit-metric-sub">units needed to safety</span>
                    </div>
                    <div class="crit-metric-box">
                        <span class="crit-metric-label">Earliest Expiry</span>
                        <div class="crit-metric-val text-sm font-semibold text-white mt-1" id="crit-modal-expiry-text">None</div>
                        <span class="crit-metric-sub" id="crit-modal-batch-text">No active batch</span>
                    </div>
                </div>

                <!-- Quick Restock Panel -->
                <div class="crit-restock-panel">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-xs font-bold uppercase tracking-wider text-[#51D55A] flex items-center gap-1.5 font-onest">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            <span>Quick Stock In (Restock Item)</span>
                        </div>
                        <span class="text-[11px] text-[#9aa6d1]">Instant replenishment</span>
                    </div>

                    <form id="crit-restock-form" onsubmit="submitQuickRestock(event)">
                        <input type="hidden" id="crit-restock-product-id" value="">
                        
                        <div class="flex flex-wrap items-center gap-2 mb-2.5">
                            <span class="text-xs text-[#9aa6d1] font-onest">Quick Add:</span>
                            <button type="button" class="crit-chip-btn" onclick="setQuickRestockQty(5)">+5</button>
                            <button type="button" class="crit-chip-btn" onclick="setQuickRestockQty(10)">+10</button>
                            <button type="button" class="crit-chip-btn" onclick="setQuickRestockQty(25)">+25</button>
                            <button type="button" class="crit-chip-btn" onclick="setQuickRestockQty(50)">+50</button>
                            <button type="button" class="crit-chip-btn" onclick="setQuickRestockQty(100)">+100</button>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative flex-1 flex items-center">
                                <button type="button" class="crit-stepper-btn" onclick="stepQuickRestockQty(-5)" title="-5">&minus;</button>
                                <input type="number" id="crit-restock-qty-input" class="crit-num-input" min="1" step="1" value="10" placeholder="Qty" required>
                                <button type="button" class="crit-stepper-btn" onclick="stepQuickRestockQty(5)" title="+5">+</button>
                            </div>
                            <button type="submit" id="crit-restock-submit-btn" class="crit-submit-btn">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                                <span>Restock Now</span>
                            </button>
                        </div>
                    </form>

                    <!-- Restock Success Feedback -->
                    <div id="crit-restock-feedback" style="display:none;" class="crit-feedback-banner">
                        <span id="crit-restock-msg">Stock added successfully!</span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Modal Footer -->
        <div class="crit-modal-footer">
            <a id="crit-modal-view-product-btn" href="products.php" class="crit-footer-btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/></svg>
                <span>Manage in Inventory</span>
            </a>
            <div class="flex items-center gap-2">
                <a href="receive.php" class="crit-footer-btn-secondary" title="Go to Receive Inventory page">
                    Receive Page
                </a>
                <button type="button" class="crit-footer-btn-close" onclick="closeCriticalStockModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<style id="crit-modal-styles">
/* ══════════════════════════════════════════════════════════════
   Critical Stock Inspection Modal Styles
   ══════════════════════════════════════════════════════════════ */
.crit-modal-overlay {
    position: fixed;
    inset: 0;
    z-index: 99999;
    background: rgba(3, 7, 18, 0.78);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    animation: critFadeIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes critFadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}
.crit-modal-box {
    background: linear-gradient(170deg, #182859 0%, #0d163a 100%);
    border: 1px solid rgba(81, 213, 90, 0.28);
    box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.8), 0 0 35px rgba(81, 213, 90, 0.12);
    width: 100%;
    max-width: 600px;
    border-radius: 20px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: critZoomIn 0.24s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes critZoomIn {
    from { opacity: 0; transform: scale(0.95) translateY(10px); }
    to   { opacity: 1; transform: scale(1) translateY(0); }
}
.crit-modal-header {
    padding: 18px 24px;
    background: rgba(13, 22, 56, 0.65);
    border-bottom: 1px solid rgba(42, 68, 132, 0.45);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.crit-modal-header-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: rgba(239, 68, 68, 0.18);
    border: 1px solid rgba(239, 68, 68, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.crit-modal-heading {
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    font-family: 'Onest', sans-serif;
    margin: 0;
}
.crit-modal-subheading {
    font-size: 11.5px;
    color: #9aa6d1;
    margin: 2px 0 0 0;
    font-family: 'Onest', sans-serif;
}
.crit-modal-close {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: #cbd5e1;
    width: 32px;
    height: 32px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    transition: all 0.2s;
}
.crit-modal-close:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: rgba(239, 68, 68, 0.4);
    color: #fff;
}
.crit-modal-body {
    padding: 22px 24px;
    overflow-y: auto;
    max-height: calc(85vh - 140px);
}
.crit-spinner {
    width: 36px;
    height: 36px;
    border: 3px solid rgba(81, 213, 90, 0.2);
    border-top-color: #51D55A;
    border-radius: 50%;
    animation: critSpin 0.75s linear infinite;
}
@keyframes critSpin {
    to { transform: rotate(360deg); }
}

/* Product Hero */
.crit-product-hero {
    display: flex;
    align-items: center;
    gap: 16px;
    background: rgba(10, 16, 40, 0.45);
    border: 1px solid rgba(42, 68, 132, 0.35);
    border-radius: 14px;
    padding: 14px 16px;
}
.crit-img-wrap {
    width: 72px;
    height: 72px;
    border-radius: 12px;
    background: #080d22;
    border: 1px solid rgba(42, 68, 132, 0.5);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.crit-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.crit-img-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
}
.crit-pill {
    font-size: 11px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 6px;
    font-family: 'Onest', sans-serif;
}
.crit-pill-sku {
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #e2e8f0;
    font-family: monospace;
}
.crit-pill-cat {
    background: rgba(59, 130, 246, 0.15);
    border: 1px solid rgba(59, 130, 246, 0.35);
    color: #93c5fd;
}
.crit-pill-loc {
    background: rgba(168, 85, 247, 0.15);
    border: 1px solid rgba(168, 85, 247, 0.35);
    color: #d8b4fe;
}

/* Alert Banner */
.crit-alert-banner {
    padding: 12px 14px;
    border-radius: 12px;
    transition: all 0.2s;
}
.crit-alert-banner.out-of-stock {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.16) 0%, rgba(185, 28, 28, 0.08) 100%);
    border: 1px solid rgba(239, 68, 68, 0.45);
}
.crit-alert-banner.out-of-stock #crit-modal-alert-title { color: #f87171; }
.crit-alert-banner.out-of-stock #crit-modal-alert-dot { background: #ef4444; }

.crit-alert-banner.low-stock {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.16) 0%, rgba(217, 119, 6, 0.08) 100%);
    border: 1px solid rgba(245, 158, 11, 0.45);
}
.crit-alert-banner.low-stock #crit-modal-alert-title { color: #fbbf24; }
.crit-alert-banner.low-stock #crit-modal-alert-dot { background: #f59e0b; }

.crit-alert-banner.healthy-stock {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.16) 0%, rgba(5, 150, 105, 0.08) 100%);
    border: 1px solid rgba(16, 185, 129, 0.45);
}
.crit-alert-banner.healthy-stock #crit-modal-alert-title { color: #34d399; }
.crit-alert-banner.healthy-stock #crit-modal-alert-dot { background: #10b981; }

/* Metric Box */
.crit-metric-box {
    background: rgba(10, 16, 40, 0.45);
    border: 1px solid rgba(42, 68, 132, 0.3);
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
}
.crit-metric-label {
    font-size: 10.5px;
    font-weight: 600;
    color: #9aa6d1;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    font-family: 'Onest', sans-serif;
}
.crit-metric-val {
    font-size: 20px;
    font-weight: 800;
    font-family: monospace;
    margin-top: 2px;
    line-height: 1.2;
}
.crit-metric-sub {
    font-size: 10px;
    color: #64748b;
    margin-top: 2px;
    font-family: 'Onest', sans-serif;
}

/* Quick Restock Panel */
.crit-restock-panel {
    background: rgba(19, 39, 91, 0.35);
    border: 1px solid rgba(81, 213, 90, 0.25);
    border-radius: 14px;
    padding: 14px 16px;
}
.crit-chip-btn {
    background: rgba(81, 213, 90, 0.12);
    border: 1px solid rgba(81, 213, 90, 0.3);
    color: #51D55A;
    font-size: 11.5px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.15s;
}
.crit-chip-btn:hover {
    background: rgba(81, 213, 90, 0.24);
    transform: translateY(-1px);
}
.crit-stepper-btn {
    width: 36px;
    height: 40px;
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(42, 68, 132, 0.6);
    color: #cbd5e1;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.crit-stepper-btn:first-child { border-radius: 10px 0 0 10px; border-right: none; }
.crit-stepper-btn:last-child { border-radius: 0 10px 10px 0; border-left: none; }
.crit-stepper-btn:hover { background: rgba(255, 255, 255, 0.12); color: #fff; }

.crit-num-input {
    height: 40px;
    background: rgba(6, 11, 28, 0.7);
    border: 1px solid rgba(42, 68, 132, 0.6);
    color: #fff;
    font-size: 15px;
    font-weight: 700;
    font-family: monospace;
    text-align: center;
    width: 100%;
    outline: none;
}
.crit-num-input:focus {
    border-color: #51D55A;
    box-shadow: 0 0 10px rgba(81, 213, 90, 0.25);
}

.crit-submit-btn {
    height: 40px;
    padding: 0 18px;
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border: none;
    border-radius: 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    font-family: 'Onest', sans-serif;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
    transition: all 0.15s;
    white-space: nowrap;
}
.crit-submit-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.45);
}
.crit-submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

.crit-feedback-banner {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 8px;
    background: rgba(16, 185, 129, 0.15);
    border: 1px solid rgba(16, 185, 129, 0.4);
    color: #6ee7b7;
    font-size: 12px;
    font-family: 'Onest', sans-serif;
    animation: critFadeIn 0.2s;
}

/* Footer */
.crit-modal-footer {
    padding: 16px 24px;
    background: rgba(13, 22, 56, 0.65);
    border-top: 1px solid rgba(42, 68, 132, 0.45);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}
.crit-footer-btn-primary {
    height: 38px;
    padding: 0 16px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 10px;
    color: #fff;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
    box-shadow: 0 4px 14px rgba(59, 130, 246, 0.3);
    transition: all 0.15s;
}
.crit-footer-btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(59, 130, 246, 0.45);
    color: #fff;
}
.crit-footer-btn-secondary {
    height: 38px;
    padding: 0 14px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    color: #9aa6d1;
    font-size: 12.5px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    display: inline-flex;
    align-items: center;
    text-decoration: none;
    transition: all 0.15s;
}
.crit-footer-btn-secondary:hover {
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
}
.crit-footer-btn-close {
    height: 38px;
    padding: 0 16px;
    background: rgba(42, 68, 132, 0.3);
    border: 1px solid rgba(42, 68, 132, 0.5);
    border-radius: 10px;
    color: #cbd5e1;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    cursor: pointer;
    transition: all 0.15s;
}
.crit-footer-btn-close:hover {
    background: rgba(42, 68, 132, 0.6);
    color: #fff;
}
</style>

<script>
/* ══════════════════════════════════════════════════════════════
   Critical Stock Modal JavaScript Controller
   ══════════════════════════════════════════════════════════════ */
let _activeCriticalProduct = null;

function openCriticalStockModal(productId) {
    if (!productId) return;

    const overlay = document.getElementById('critical-stock-modal');
    const loading = document.getElementById('crit-modal-loading');
    const content = document.getElementById('crit-modal-content');
    const feedback = document.getElementById('crit-restock-feedback');

    if (!overlay) return;

    // Reset view
    overlay.style.display = 'flex';
    if (loading) loading.style.display = 'flex';
    if (content) content.style.display = 'none';
    if (feedback) feedback.style.display = 'none';
    document.body.style.overflow = 'hidden';

    // Fetch product details
    fetch('api/get_product_by_id.php?id=' + encodeURIComponent(productId))
        .then(res => res.json())
        .then(data => {
            if (!data || !data.success || !data.found || !data.product) {
                alert('Could not find product details.');
                closeCriticalStockModal();
                return;
            }

            const p = data.product;
            _activeCriticalProduct = p;

            // Populate fields
            document.getElementById('crit-restock-product-id').value = p.product_id;
            document.getElementById('crit-modal-prod-name').textContent = p.product_name;
            document.getElementById('crit-modal-sku-badge').textContent = p.sku;
            document.getElementById('crit-modal-cat-badge').textContent = p.category || 'General';
            document.getElementById('crit-modal-loc-badge').textContent = p.location ? ('📍 ' + p.location) : '📍 Unassigned';
            document.getElementById('crit-modal-supplier').textContent = p.supplier || 'Not specified';
            document.getElementById('crit-modal-price').textContent = '₱' + parseFloat(p.price || 0).toFixed(2);

            // Image handling
            const imgEl = document.getElementById('crit-modal-img');
            const fallbackEl = document.getElementById('crit-modal-img-fallback');
            if (p.image_url) {
                imgEl.src = p.image_url;
                imgEl.style.display = 'block';
                if (fallbackEl) fallbackEl.style.display = 'none';
            } else {
                imgEl.style.display = 'none';
                if (fallbackEl) fallbackEl.style.display = 'flex';
            }

            // Metrics
            const qty = parseInt(p.quantity, 10) || 0;
            const rp = parseInt(p.reorder_point, 10) || 0;
            const unit = p.unit || 'units';

            const stockValEl = document.getElementById('crit-modal-stock-qty');
            stockValEl.textContent = qty;
            document.getElementById('crit-modal-stock-unit').textContent = unit + ' remaining';
            document.getElementById('crit-modal-reorder-point').textContent = rp;

            const deficit = Math.max(0, rp - qty);
            document.getElementById('crit-modal-deficit').textContent = (deficit > 0 ? ('-' + deficit) : '0');

            document.getElementById('crit-modal-expiry-text').textContent = p.expiry_date || 'None tracked';
            document.getElementById('crit-modal-batch-text').textContent = p.batch_number || 'No active batch';

            // Alert banner state
            const banner = document.getElementById('crit-modal-alert-banner');
            const alertTitle = document.getElementById('crit-modal-alert-title');
            const alertDesc = document.getElementById('crit-modal-alert-desc');

            banner.className = 'crit-alert-banner';
            if (qty <= 0) {
                banner.classList.add('out-of-stock');
                stockValEl.className = 'crit-metric-val text-red-400';
                alertTitle.textContent = '🚨 Completely Out of Stock';
                alertDesc.textContent = `Stock is depleted (0 ${unit}). Reorder point is ${rp} ${unit}. Immediate replenishment is recommended.`;
            } else if (qty <= rp) {
                banner.classList.add('low-stock');
                stockValEl.className = 'crit-metric-val text-amber-400';
                alertTitle.textContent = '⚠️ Critical Low Stock Alert';
                alertDesc.textContent = `Current inventory (${qty} ${unit}) is at or below the reorder point (${rp} ${unit}). Shortage risk is active.`;
            } else {
                banner.classList.add('healthy-stock');
                stockValEl.className = 'crit-metric-val text-emerald-400';
                alertTitle.textContent = '✓ Stock Healthy';
                alertDesc.textContent = `Current inventory (${qty} ${unit}) is above the reorder point (${rp} ${unit}).`;
            }

            // Manage in Inventory link
            const viewBtn = document.getElementById('crit-modal-view-product-btn');
            if (viewBtn) {
                viewBtn.href = 'products.php?view_id=' + encodeURIComponent(p.product_id);
            }

            // Recommended restock quantity
            const recRestock = deficit > 0 ? (Math.ceil(deficit / 10) * 10) : 10;
            document.getElementById('crit-restock-qty-input').value = Math.max(10, recRestock);

            // Switch to content
            if (loading) loading.style.display = 'none';
            if (content) content.style.display = 'block';
        })
        .catch(err => {
            console.error('[Critical Stock Modal] Error fetching product:', err);
            alert('Failed to load product details.');
            closeCriticalStockModal();
        });
}

function closeCriticalStockModal() {
    const overlay = document.getElementById('critical-stock-modal');
    if (overlay) overlay.style.display = 'none';
    document.body.style.overflow = '';
    _activeCriticalProduct = null;
}

function setQuickRestockQty(amount) {
    const input = document.getElementById('crit-restock-qty-input');
    if (input) input.value = amount;
}

function stepQuickRestockQty(delta) {
    const input = document.getElementById('crit-restock-qty-input');
    if (!input) return;
    let cur = parseInt(input.value, 10) || 0;
    cur = Math.max(1, cur + delta);
    input.value = cur;
}

function submitQuickRestock(e) {
    if (e && e.preventDefault) e.preventDefault();

    const productId = document.getElementById('crit-restock-product-id')?.value;
    const qtyInput = document.getElementById('crit-restock-qty-input');
    const qty = parseInt(qtyInput ? qtyInput.value : '0', 10);
    const submitBtn = document.getElementById('crit-restock-submit-btn');
    const feedback = document.getElementById('crit-restock-feedback');
    const msgEl = document.getElementById('crit-restock-msg');

    if (!productId || qty <= 0) {
        alert('Please specify a restock quantity of at least 1.');
        return;
    }

    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="crit-spinner" style="width:16px;height:16px;border-width:2px;display:inline-block;margin-right:6px;"></span> Restocking…';
    }

    fetch('api/quick_restock.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            product_id: parseInt(productId, 10),
            quantity: qty
        })
    })
    .then(res => res.json())
    .then(data => {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg><span>Restock Now</span>';
        }

        if (data && data.success) {
            // Update modal stock display live
            const stockValEl = document.getElementById('crit-modal-stock-qty');
            if (stockValEl) {
                stockValEl.textContent = data.new_quantity;
                stockValEl.className = data.is_healthy ? 'crit-metric-val text-emerald-400' : 'crit-metric-val text-amber-400';
            }

            const deficit = Math.max(0, data.reorder_point - data.new_quantity);
            const deficitEl = document.getElementById('crit-modal-deficit');
            if (deficitEl) deficitEl.textContent = deficit > 0 ? ('-' + deficit) : '0';

            // Update Alert banner
            const banner = document.getElementById('crit-modal-alert-banner');
            const alertTitle = document.getElementById('crit-modal-alert-title');
            const alertDesc = document.getElementById('crit-modal-alert-desc');
            if (banner && alertTitle && alertDesc) {
                banner.className = 'crit-alert-banner ' + (data.is_healthy ? 'healthy-stock' : 'low-stock');
                alertTitle.textContent = data.is_healthy ? '✓ Stock Healthy' : '⚠️ Critical Low Stock Alert';
                alertDesc.textContent = `New stock is ${data.new_quantity} ${data.unit} (Reorder point: ${data.reorder_point} ${data.unit}).`;
            }

            // Show feedback
            if (feedback && msgEl) {
                msgEl.textContent = data.message;
                feedback.style.display = 'block';
            }

            // Update the quantity displayed on the dashboard card in real time!
            const dashCardQty = document.getElementById('crit-stock-qty-' + data.product_id);
            if (dashCardQty) {
                dashCardQty.textContent = data.new_quantity;
                dashCardQty.className = data.new_quantity <= 0 
                    ? 'text-xl font-bold font-onest leading-none text-[#DC2626]' 
                    : (data.is_healthy 
                        ? 'text-xl font-bold font-onest leading-none text-[#059669]' 
                        : 'text-xl font-bold font-onest leading-none text-[#D97706]');
            }

            const dashCardBadge = document.getElementById('crit-stock-badge-' + data.product_id);
            if (dashCardBadge) {
                if (data.new_quantity <= 0) {
                    dashCardBadge.textContent = 'OUT';
                    dashCardBadge.className = 'pos-crit-badge pos-crit-badge-out';
                } else if (!data.is_healthy) {
                    dashCardBadge.textContent = 'LOW';
                    dashCardBadge.className = 'pos-crit-badge pos-crit-badge-low';
                } else {
                    dashCardBadge.textContent = 'OK';
                    dashCardBadge.className = 'pos-crit-badge pos-quick-badge-in';
                }
            }

            const dashCardTile = document.getElementById('crit-stock-tile-' + data.product_id);
            if (dashCardTile) {
                if (data.new_quantity <= 0) {
                    dashCardTile.className = 'pos-crit-tile pos-crit-tile-out';
                    dashCardTile.innerHTML = '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
                } else if (!data.is_healthy) {
                    dashCardTile.className = 'pos-crit-tile pos-crit-tile-low';
                    dashCardTile.innerHTML = '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
                } else {
                    dashCardTile.className = 'pos-crit-tile pos-quick-icon-receive';
                    dashCardTile.innerHTML = '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m9 12 2 2 4-4"/></svg>';
                }
            }

            if (typeof showToast === 'function') {
                showToast(data.message, 'success');
            }
        } else {
            alert(data.error || 'Failed to restock item.');
        }
    })
    .catch(err => {
        console.error('[Quick Restock] Error:', err);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Restock Now';
        }
        alert('Server connection error during restock.');
    });
}

// Close on backdrop click & Escape key
document.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('critical-stock-modal');
    if (overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) closeCriticalStockModal();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const overlay = document.getElementById('critical-stock-modal');
            if (overlay && overlay.style.display !== 'none') {
                closeCriticalStockModal();
            }
        }
    });
});
</script>
