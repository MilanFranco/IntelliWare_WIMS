<!-- Barcode Scanner Modals (shared partial) -->
<style>
    #barcode-scanner-overlay,
    #barcode-duplicate-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 100003;
        align-items: center;
        justify-content: center;
        background: rgba(0, 0, 0, 0.85);
        backdrop-filter: blur(5px);
        padding: 20px;
        box-sizing: border-box;
    }
    #barcode-scanner-overlay.active,
    #barcode-duplicate-overlay.active {
        display: flex;
    }
    .barcode-modal-box {
        background: linear-gradient(170deg, #1D387B 0%, #11204a 100%);
        border: 1px solid rgba(81, 213, 90, 0.25);
        width: 100%;
        max-width: 650px;
        max-height: calc(100vh - 40px);
        border-radius: 20px;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        margin: auto;
    }
    .barcode-modal-header {
        padding: 24px 30px;
        background: rgba(13, 22, 56, 0.5);
        border-bottom: 1px solid rgba(66, 85, 135, 0.3);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .barcode-modal-title h3 {
        color: #fff;
        font-size: 22px;
        font-weight: 700;
        margin: 0 0 6px 0;
        font-family: 'Onest', sans-serif;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .barcode-modal-subtitle {
        color: #9aa6d1;
        font-size: 13px;
        font-family: 'Onest', sans-serif;
    }
    .barcode-modal-box .btn {
        height: 42px;
        padding: 0 20px;
        border-radius: 10px;
        font-family: 'Onest', sans-serif;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        border: none;
        color: #fff;
    }
    .barcode-modal-box .btn-primary {
        background: linear-gradient(135deg, #51D55A 0%, #4BC052 100%);
    }
    .barcode-modal-box .btn-secondary {
        background: #2A4484;
        border: 1px solid #355296;
    }
</style>

<div id="barcode-scanner-overlay" class="modal-overlay">
    <div class="barcode-modal-box" style="max-width: 520px;">
        <div class="barcode-modal-header">
            <div class="barcode-modal-title">
                <h3>Scan Barcode</h3>
                <div class="barcode-modal-subtitle">Point your camera at the barcode. Supports EAN-13, UPC, and Code 128.</div>
            </div>
            <button type="button" id="barcode-scanner-close" style="background:none; border:none; color:#9aa6d1; font-size:28px; cursor:pointer; line-height:1; padding:0;">&times;</button>
        </div>
        <div style="padding: 20px 30px;">
            <div id="barcode-scanner-reader" style="width:100%; border-radius:12px; overflow:hidden; background:#0B1438;"></div>
            <p id="barcode-scanner-status" style="color:#9aa6d1; font-size:13px; text-align:center; margin-top:12px;">Initializing camera…</p>
        </div>
        <div style="padding: 16px 30px 24px; border-top: 1px solid #2A4484; display:flex; justify-content:flex-end; background:rgba(13,22,56,0.3);">
            <button type="button" class="btn btn-secondary" id="barcode-scanner-cancel" style="background:transparent; border:1px solid #64748b;">Cancel</button>
        </div>
    </div>
</div>

<div id="barcode-duplicate-overlay" class="modal-overlay">
    <div class="barcode-modal-box" style="max-width: 460px; text-align:center; padding:40px;">
        <div style="margin-bottom:16px; display:flex; justify-content:center;">
            <div style="width:72px; height:72px; background:rgba(245,158,11,0.15); border-radius:50%; display:flex; align-items:center; justify-content:center;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            </div>
        </div>
        <h3 style="color:#fff; margin-bottom:10px;">Duplicate Barcode</h3>
        <p style="color:#9aa6d1; margin-bottom:8px;">This barcode already belongs to an existing product.</p>
        <p id="barcode-duplicate-product-name" style="color:#51D55A; font-weight:600; margin-bottom:24px;"></p>
        <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
            <button type="button" class="btn btn-secondary" id="barcode-duplicate-dismiss" style="background:transparent; border:1px solid #64748b;">Use Different Barcode</button>
            <a id="barcode-duplicate-view-link" href="#" class="btn btn-primary" style="text-decoration:none;">View Product</a>
        </div>
    </div>
</div>
