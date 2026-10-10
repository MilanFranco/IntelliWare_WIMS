<?php
/**
 * Partial: Philippine Market & Seasonal Demand Recommendations
 * ------------------------------------------------------------
 * Renders:
 *   1. A floating action button (FAB) fixed to the bottom-right corner
 *   2. The full Philippine Seasonal Recommendations modal overlay with CSS.
 *
 * Required in scope from insights.php:
 *   $philippineProfiles, $activeMonthProfile, $selectedMonth,
 *   $allSeasonalMonthsData, $currentHorizon (default 'monthly')
 */
$_psi_selectedMonth  = $selectedMonth   ?? (int)date('n');
$_psi_activeProfile  = $activeMonthProfile ?? [];
$_psi_currentHorizon = $currentHorizon ?? 'monthly';
if (!function_exists('formatSafeSeasonalHtml')) {
    function formatSafeSeasonalHtml(?string $str): string {
        if ($str === null || $str === '') {
            return '';
        }
        $str = str_ireplace(
            ['&lt;strong&gt;', '&lt;/strong&gt;', '&lt;b&gt;', '&lt;/b&gt;', '&lt;em&gt;', '&lt;/em&gt;'],
            ['<strong>', '</strong>', '<b>', '</b>', '<em>', '</em>'],
            $str
        );
        $escaped = htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
        return str_ireplace(
            ['&lt;strong&gt;', '&lt;/strong&gt;', '&lt;b&gt;', '&lt;/b&gt;', '&lt;em&gt;', '&lt;/em&gt;'],
            ['<strong>', '</strong>', '<b>', '</b>', '<em>', '</em>'],
            $escaped
        );
    }
}
?>

<style id="ph-seasonal-modal-styles">
/* ══════════════════════════════════════════════════════════════
   PH Seasonal FAB — Premium Glassmorphic Floating Action Button
   ══════════════════════════════════════════════════════════════ */

/* Entry animation */
@keyframes phFabSlideIn {
    from { opacity: 0; transform: translateY(18px) scale(0.92); }
    to   { opacity: 1; transform: translateY(0)   scale(1); }
}
/* Ambient glow pulse for live status dot */
@keyframes phFabDotPulse {
    0%, 100% {
        opacity: 1;
        box-shadow: 0 0 6px rgba(81, 213, 90, 0.85), 0 0 12px rgba(81, 213, 90, 0.45);
    }
    50% {
        opacity: 0.65;
        box-shadow: 0 0 3px rgba(81, 213, 90, 0.5);
    }
}
/* Slow spin for the sun icon on hover */
@keyframes phSunSpin {
    from { transform: rotate(0deg);   }
    to   { transform: rotate(360deg); }
}
/* Gradient border shimmer */
@keyframes phBorderShimmer {
    0%   { background-position: 0%   50%; }
    50%  { background-position: 100% 50%; }
    100% { background-position: 0%   50%; }
}

/* ── Main pill ── */
#phSeasonalFab {
    position: fixed !important;
    bottom: 24px !important;
    right: 24px !important;
    z-index: 999990 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 12px !important;
    padding: 7px 9px 7px 15px !important;
    border-radius: 999px !important;
    cursor: pointer !important;
    outline: none !important;
    user-select: none !important;
    -webkit-tap-highlight-color: transparent !important;
    /* Glassmorphic dark pill */
    background: linear-gradient(135deg, rgba(10, 22, 55, 0.96) 0%, rgba(14, 30, 72, 0.94) 100%) !important;
    backdrop-filter: blur(18px) saturate(1.6) !important;
    -webkit-backdrop-filter: blur(18px) saturate(1.6) !important;
    border: 1.5px solid rgba(81, 213, 90, 0.5) !important;
    box-shadow:
        0 12px 36px rgba(0, 0, 0, 0.5),
        0 4px 14px rgba(16, 185, 129, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
    /* Smooth spring lift */
    transition:
        transform 0.26s cubic-bezier(0.34, 1.56, 0.64, 1),
        box-shadow 0.26s ease,
        border-color 0.22s ease,
        background 0.22s ease !important;
    animation: phFabSlideIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both !important;
}
#phSeasonalFab:hover {
    transform: translateY(-4px) scale(1.03) !important;
    border-color: rgba(81, 213, 90, 0.85) !important;
    background: linear-gradient(135deg, rgba(12, 26, 65, 0.98) 0%, rgba(20, 46, 100, 0.97) 100%) !important;
    box-shadow:
        0 16px 44px rgba(0, 0, 0, 0.55),
        0 6px 20px rgba(16, 185, 129, 0.35),
        0 0 0 3px rgba(81, 213, 90, 0.14),
        inset 0 1px 0 rgba(255, 255, 255, 0.15) !important;
}
#phSeasonalFab:active {
    transform: translateY(-1px) scale(0.98) !important;
    box-shadow:
        0 6px 18px rgba(0, 0, 0, 0.4),
        0 2px 10px rgba(16, 185, 129, 0.2) !important;
}
#phSeasonalFab:focus-visible {
    outline: 2px solid #51D55A !important;
    outline-offset: 3px !important;
}

/* ── Left text area ── */
.ph-fab-text-col {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-start;
    gap: 4px;
}
.ph-fab-title {
    font-family: 'Onest', -apple-system, sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    color: #ffffff;
    white-space: nowrap;
    letter-spacing: 0.15px;
    line-height: 1.15;
}
.ph-fab-subtitle {
    font-family: 'Onest', -apple-system, sans-serif;
    font-size: 10px;
    font-weight: 500;
    color: rgba(154, 166, 209, 0.75);
    white-space: nowrap;
    letter-spacing: 0.2px;
    line-height: 1.2;
}

/* ── Month pill badge (Enhanced alignment & typography) ── */
.ph-fab-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    background: rgba(16, 185, 129, 0.16) !important;
    color: #4ade80 !important;
    font-family: 'Onest', -apple-system, sans-serif !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    padding: 3px 10px 3px 8px !important;
    border-radius: 999px !important;
    border: 1px solid rgba(81, 213, 90, 0.4) !important;
    text-transform: none !important;
    letter-spacing: 0.3px !important;
    line-height: 1 !important;
    align-self: flex-start;
    box-sizing: border-box;
}
.ph-fab-badge::before {
    content: '';
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #51D55A;
    display: inline-block;
    flex-shrink: 0;
    box-shadow: 0 0 6px rgba(81, 213, 90, 0.8);
    animation: phFabDotPulse 2s ease-in-out infinite;
}

/* ── Right icon disc ── */
.ph-fab-icon-disc {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    background: linear-gradient(135deg, #10b981 0%, #059669 60%, #047857 100%) !important;
    border: 2px solid rgba(255, 255, 255, 0.25) !important;
    box-shadow:
        0 2px 10px rgba(5, 150, 105, 0.4),
        inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
    transition: transform 0.25s ease, box-shadow 0.25s ease;
    position: relative;
    overflow: hidden;
}
.ph-fab-icon-disc::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: linear-gradient(130deg, rgba(255,255,255,0.25) 0%, transparent 60%);
    opacity: 0;
    transition: opacity 0.25s ease;
}
#phSeasonalFab:hover .ph-fab-icon-disc {
    transform: rotate(15deg) scale(1.08);
    box-shadow:
        0 4px 16px rgba(5, 150, 105, 0.6),
        inset 0 1px 0 rgba(255, 255, 255, 0.35) !important;
}
#phSeasonalFab:hover .ph-fab-icon-disc::after {
    opacity: 1;
}
.ph-fab-icon-disc svg {
    width: 20px;
    height: 20px;
    color: #ffffff;
    flex-shrink: 0;
    display: block;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.25));
}

/* ── Light theme overrides ── */
[data-theme="light"] #phSeasonalFab {
    background: linear-gradient(135deg, rgba(255,255,255,0.97) 0%, rgba(240,248,255,0.95) 100%) !important;
    border: 1.5px solid rgba(16, 185, 129, 0.3) !important;
    box-shadow:
        0 8px 32px rgba(0, 0, 0, 0.1),
        0 3px 12px rgba(16, 185, 129, 0.15),
        inset 0 1px 0 rgba(255,255,255,0.9) !important;
}
[data-theme="light"] #phSeasonalFab:hover {
    border-color: rgba(16, 185, 129, 0.65) !important;
    box-shadow:
        0 14px 40px rgba(0, 0, 0, 0.14),
        0 5px 18px rgba(16, 185, 129, 0.3),
        0 0 0 4px rgba(16, 185, 129, 0.08) !important;
}
[data-theme="light"] .ph-fab-title {
    color: #0c1a3a;
}
[data-theme="light"] .ph-fab-subtitle {
    color: #64748b;
}
[data-theme="light"] .ph-fab-badge {
    background: rgba(16, 185, 129, 0.1);
    color: #047857;
    border-color: rgba(16, 185, 129, 0.28);
}
[data-theme="light"] .ph-fab-badge::before {
    background: #059669;
}
[data-theme="light"] .ph-fab-icon-disc {
    background: linear-gradient(145deg, #10b981 0%, #059669 50%, #047857 100%);
    box-shadow:
        0 0 0 3px rgba(16, 185, 129, 0.18),
        0 4px 14px rgba(5, 150, 105, 0.35),
        inset 0 1px 0 rgba(255,255,255,0.25);
}
[data-theme="light"] .ph-fab-divider {
    background: rgba(0, 0, 0, 0.12) !important;
}
@media (max-width: 580px) {
    #phSeasonalFab {
        bottom: 18px !important;
        right: 16px !important;
        padding: 5px 6px 5px 12px !important;
        gap: 8px !important;
    }
    .ph-fab-text-col {
        display: flex !important;
        flex-direction: column !important;
        gap: 1px !important;
    }
    .ph-fab-title {
        font-size: 11.5px !important;
        line-height: 1.15 !important;
    }
    .ph-fab-badge {
        display: inline-flex !important;
        font-size: 9px !important;
        padding: 1px 5px !important;
        line-height: 1.1 !important;
    }
    .ph-fab-divider {
        height: 22px !important;
    }
    .ph-fab-icon-disc {
        width: 36px !important;
        height: 36px !important;
    }
    .ph-fab-icon-disc svg {
        width: 19px !important;
        height: 19px !important;
    }
}

/* ── Modal (Optimized 60fps Smooth Scroll, No Lag) ────────── */
.ph-seasonal-modal-overlay {
    position: fixed !important; top: 0 !important; left: 0 !important;
    right: 0 !important; bottom: 0 !important;
    width: 100vw !important; height: 100vh !important;
    display: none; align-items: center !important; justify-content: center !important;
    z-index: 9999999 !important; padding: 20px !important;
    box-sizing: border-box !important; opacity: 0; transition: opacity 0.2s ease;
    background: rgba(4, 9, 20, 0.82) !important;
}
.ph-seasonal-modal-overlay.open {
    display: flex !important; opacity: 1 !important; visibility: visible !important;
}
.ph-seasonal-modal-dialog {
    position: relative; z-index: 1;
    background: #0d1b3e !important; /* Fully opaque to prevent GPU blend lag on scroll */
    border: 1px solid var(--insights-border, rgba(42, 68, 132, 0.7));
    border-radius: 20px; width: 100%; max-width: 1100px; max-height: 90vh;
    min-height: 0 !important;
    display: flex; flex-direction: column;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.65); overflow: hidden;
    transform: translateZ(0); /* Hardware accelerated */
    isolation: isolate;
    animation: phModalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
    font-family: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}
.ph-seasonal-modal-dialog,
.ph-seasonal-modal-dialog button,
.ph-seasonal-modal-dialog input,
.ph-seasonal-modal-dialog select,
.ph-seasonal-modal-dialog textarea,
.ph-seasonal-modal-dialog span,
.ph-seasonal-modal-dialog p,
.ph-seasonal-modal-dialog div,
.ph-seasonal-modal-dialog h1,
.ph-seasonal-modal-dialog h2,
.ph-seasonal-modal-dialog h3,
.ph-seasonal-modal-dialog h4,
.ph-seasonal-modal-dialog th,
.ph-seasonal-modal-dialog td {
    font-family: 'Onest', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
}
[data-theme="light"] .ph-seasonal-modal-dialog {
    background: #ffffff !important;
    border-color: #cbd5e1;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.18);
}
@keyframes phModalPopIn {
    from { transform: scale(0.96) translateY(10px); opacity: 0; }
    to   { transform: none; opacity: 1; }
}
.ph-seasonal-modal-header {
    padding: 18px 24px;
    border-bottom: 1px solid var(--insights-border, rgba(42, 68, 132, 0.6));
    display: flex; justify-content: space-between; align-items: center;
    background: #11224d; flex-shrink: 0;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
}
[data-theme="light"] .ph-seasonal-modal-header {
    background: #f8fafc;
    border-color: #e2e8f0;
    border-top-left-radius: 20px;
    border-top-right-radius: 20px;
}
.ph-seasonal-modal-body {
    padding: 24px 24px 36px 24px;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1 1 auto;
    min-height: 0 !important;
    overscroll-behavior: contain;
    border-bottom-left-radius: 20px;
    border-bottom-right-radius: 20px;
    -webkit-overflow-scrolling: touch;
    transform: translateZ(0); /* Clean compositor layer without will-change/contain:paint jank */
}
/* High performance custom scrollbar */
.ph-seasonal-modal-body::-webkit-scrollbar {
    width: 7px;
}
.ph-seasonal-modal-body::-webkit-scrollbar-track {
    background: rgba(19, 39, 91, 0.25);
    border-radius: 4px;
}
.ph-seasonal-modal-body::-webkit-scrollbar-thumb {
    background: #2a4484;
    border-radius: 4px;
}
.ph-seasonal-modal-body::-webkit-scrollbar-thumb:hover {
    background: #51D55A;
}
[data-theme="light"] .ph-seasonal-modal-body::-webkit-scrollbar-track {
    background: #f1f5f9;
}
[data-theme="light"] .ph-seasonal-modal-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
}
[data-theme="light"] .ph-seasonal-modal-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.ph-seasonal-modal-close {
    background: rgba(148,163,184,0.12); border: 1px solid rgba(148,163,184,0.25);
    border-radius: 50%; width: 36px; height: 36px;
    display: flex; align-items: center; justify-content: center;
    color: var(--insights-text-muted, #9aa6d1); cursor: pointer;
    font-size: 16px; line-height: 1; flex-shrink: 0;
    transition: all 0.2s cubic-bezier(0.4,0,0.2,1);
}
.ph-seasonal-modal-close:hover {
    background: rgba(239,68,68,0.2); border-color: #ef4444;
    color: #f87171; transform: rotate(90deg) scale(1.05);
}
/* Dropdowns */
.seasonal-dropdown-row { display: flex; align-items: flex-end; gap: 16px; margin-bottom: 20px; padding: 2px 0; }
.seasonal-dropdown-group { flex: 1; display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.seasonal-dropdown-label {
    display: inline-flex; align-items: center; gap: 6px; font-family: 'Onest',sans-serif;
    font-size: 11.5px; font-weight: 700; color: var(--insights-text-muted, #9aa6d1);
    text-transform: uppercase; letter-spacing: 0.6px; user-select: none;
}
.seasonal-dropdown-label svg { color: #51D55A; flex-shrink: 0; }
.seasonal-select-wrapper { position: relative; display: flex; align-items: center; }
.seasonal-dropdown-control {
    width: 100%; height: 40px; appearance: none; -webkit-appearance: none;
    background: var(--insights-input, rgba(19,39,91,0.8));
    border: 1px solid var(--insights-input-border, rgba(42,68,132,0.5));
    border-radius: 10px; color: var(--insights-text, #E3E3E3);
    padding: 0 36px 0 14px; font-family: 'Onest',sans-serif;
    font-size: 13px; font-weight: 500; cursor: pointer; outline: none;
    transition: border-color 0.18s ease, box-shadow 0.18s ease;
}
.seasonal-dropdown-control:focus { border-color: #51D55A; box-shadow: 0 0 0 3px rgba(81,213,90,0.18); }
.seasonal-dropdown-control option { background: #13275B; }
.seasonal-dropdown-arrow {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    color: var(--insights-text-muted, #9aa6d1); pointer-events: none; transition: color 0.15s ease;
}
.seasonal-select-wrapper:focus-within .seasonal-dropdown-arrow { color: #51D55A; }
[data-theme="light"] .seasonal-dropdown-control { background: #f1f5f9; border-color: #BFCFE0; color: #1E293B; }
[data-theme="light"] .seasonal-dropdown-control option { background: #ffffff; }
[data-theme="light"] .seasonal-select-wrapper:focus-within .seasonal-dropdown-arrow { color: #16a34a; }
@media(max-width:640px) { .seasonal-dropdown-row { flex-direction: column; gap: 12px; } }
/* Banner */
.ph-intel-banner {
    background: #11224d;
    border: 1px solid var(--insights-border, rgba(42,68,132,0.6));
    border-radius: 14px; padding: 22px 24px; margin-bottom: 22px;
}
[data-theme="light"] .ph-intel-banner { background: #ffffff; border-color: #cbd5e1; }
.ph-header-meta { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: wrap; }
.ph-season-pill {
    display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 999px;
    font-size: 11px; font-weight: 700; letter-spacing: 0.3px;
    background: rgba(81,213,90,0.12); color: #51D55A; border: 1px solid rgba(81,213,90,0.35);
}
[data-theme="light"] .ph-season-pill { background: rgba(81,213,90,0.15); color: #15803d; border-color: rgba(22,163,74,0.4); }
.ph-weather-subtitle { font-size: 12px; color: var(--insights-text-muted, #9aa6d1); font-weight: 500; }
.ph-main-heading { font-size: 20px; font-weight: 700; color: #ffffff; margin: 0 0 6px 0; letter-spacing: -0.2px; }
[data-theme="light"] .ph-main-heading { color: #0f172a; }
.ph-main-subtext { font-size: 13px; color: var(--insights-text-muted, #9aa6d1); margin: 0; line-height: 1.45; }
.ph-section-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.8px; color: var(--insights-text-muted, #9aa6d1); margin: 22px 0 10px 0;
}
.consumption-triggers-grid { display: grid; grid-template-columns: repeat(2,1fr); gap: 10px; }
@media(max-width:640px) { .consumption-triggers-grid { grid-template-columns: 1fr; } }
.trigger-card {
    background: rgba(19,39,91,0.8);
    border: 1px solid rgba(42,68,132,0.35);
    border-radius: 10px; padding: 12px 16px; font-size: 13px; line-height: 1.45;
    color: var(--insights-text, #E3E3E3);
}
[data-theme="light"] .trigger-card { background: #f8fafc; border-color: #e2e8f0; }
.demand-lift-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 12px; }
@media(max-width:768px) { .demand-lift-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px) { .demand-lift-grid { grid-template-columns: 1fr; } }
.metric-lift-card {
    background: rgba(19,39,91,0.8);
    border: 1px solid rgba(42,68,132,0.35);
    border-radius: 10px; padding: 12px 14px; display: flex; flex-direction: column; gap: 4px;
}
[data-theme="light"] .metric-lift-card { background: #f8fafc; border-color: #e2e8f0; }
.metric-lift-label { font-size: 12px; font-weight: 500; color: var(--insights-text-muted, #9aa6d1); }
.metric-lift-value { display: flex; align-items: baseline; gap: 6px; }
.lift-multiplier { font-size: 18px; font-weight: 800; color: #51D55A; letter-spacing: -0.3px; }
[data-theme="light"] .lift-multiplier { color: #16a34a; }
.lift-percentage { font-size: 12px; font-weight: 600; color: var(--insights-text-muted, #9aa6d1); }

/* Products Table */
.ph-products-container {
    background: #11224d;
    border: 1px solid var(--insights-border, rgba(42,68,132,0.6));
    border-radius: 14px; padding: 20px;
}
[data-theme="light"] .ph-products-container { background: #ffffff; border-color: #cbd5e1; }
.ph-table-header-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 8px; }
.ph-line-table { width: 100%; border-collapse: collapse; }
.ph-line-table th {
    text-align: left; color: var(--insights-text-muted, #9aa6d1); font-size: 11px;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px;
    padding: 10px 12px; border-bottom: 1px solid var(--insights-border, rgba(42,68,132,0.6));
}
.ph-line-table td {
    padding: 12px 12px;
    border-bottom: 1px solid var(--insights-border-soft, rgba(42,68,132,0.32));
    vertical-align: top; background: transparent;
}
.ph-line-table tr:hover td { background: var(--insights-row-hover, rgba(81,213,90,0.06)); }
/* Product image & thumbnail layout */
.seasonal-product-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.seasonal-product-thumb {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--insights-border, rgba(42,68,132,0.6));
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    padding: 3px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), border-color 0.2s ease, box-shadow 0.2s ease;
}
[data-theme="light"] .seasonal-product-thumb {
    background: #f8fafc;
    border-color: #cbd5e1;
    box-shadow: 0 2px 6px rgba(0,0,0,0.06);
}
.seasonal-product-thumb:hover {
    transform: scale(1.1);
    border-color: #51D55A;
    box-shadow: 0 4px 14px rgba(81, 213, 90, 0.3);
}
.seasonal-product-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    display: block;
}
.seasonal-thumb-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--insights-text-muted, #9aa6d1);
}
.seasonal-product-info {
    flex: 1;
    min-width: 0;
}
.table-product-name { font-size: 14px; font-weight: 600; color: var(--insights-heading, #FFFFFF); margin-bottom: 3px; }
.table-product-meta { font-size: 12px; color: var(--insights-text-muted, #9aa6d1); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.meta-sep { color: var(--insights-border, rgba(42,68,132,0.6)); }
.surge-tag { color: #51D55A; font-weight: 700; }
[data-theme="light"] .surge-tag { color: #16a34a; }
.table-status-pill {
    display: inline-flex; align-items: center; padding: 2px 7px; border-radius: 4px;
    font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 5px;
}
.table-status-pill.status-critical  { background: rgba(239,68,68,0.15);   color: #f87171; border: 1px solid rgba(239,68,68,0.3); }
.table-status-pill.status-warning   { background: rgba(245,158,11,0.15);  color: #fbbf24; border: 1px solid rgba(245,158,11,0.3); }
.table-status-pill.status-opportunity { background: rgba(59,130,246,0.15); color: #60a5fa; border: 1px solid rgba(59,130,246,0.3); }
.table-intel-sentence { font-size: 12.5px; line-height: 1.45; color: var(--insights-text-muted, #9aa6d1); }
.table-intel-sentence strong { font-weight: 700; color: var(--insights-heading, #FFFFFF); }
.table-action-callout {
    display: inline-flex; align-items: flex-start; gap: 6px; padding: 6px 10px;
    border-radius: 6px; font-size: 11.5px; font-weight: 500; line-height: 1.35; margin-top: 6px;
}
.table-action-callout strong { font-weight: 700; color: inherit; }
.table-action-callout.callout-urgent   { background: rgba(239,68,68,0.12);  border: 1px solid rgba(239,68,68,0.3);  color: #fca5a5; }
.table-action-callout.callout-warning  { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.3); color: #fde68a; }
.table-action-callout.callout-opportunity { background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.3); color: #93c5fd; }
[data-theme="light"] .table-action-callout.callout-urgent   { background: #fef2f2; border-color: #fecaca; color: #dc2626; }
[data-theme="light"] .table-action-callout.callout-warning  { background: #fffbeb; border-color: #fde68a; color: #d97706; }
[data-theme="light"] .table-action-callout.callout-opportunity { background: #eff6ff; border-color: #bfdbfe; color: #2563eb; }
.table-stock-wrap { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; min-width: 115px; }
.table-stock-num { font-size: 13px; font-weight: 700; color: var(--insights-heading, #FFFFFF); }
.table-stock-label { font-size: 11px; font-weight: 400; color: var(--insights-text-muted, #9aa6d1); }
.btn-table-check,
.btn-table-check.check-stock-btn {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    padding: 6px 13px !important;
    min-width: 110px !important;
    width: auto !important;
    max-width: none !important;
    background: rgba(81, 213, 90, 0.12) !important;
    border: 1.5px solid rgba(81, 213, 90, 0.45) !important;
    border-radius: 8px !important;
    color: #51D55A !important;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    box-sizing: border-box !important;
    line-height: 1.3 !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15) !important;
}
.btn-table-check:hover,
.btn-table-check.check-stock-btn:hover {
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
    color: #ffffff !important;
    border-color: #51D55A !important;
    box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4) !important;
    transform: translateY(-1px) !important;
}
[data-theme="light"] .btn-table-check,
[data-theme="light"] .btn-table-check.check-stock-btn {
    background: #F0FDF4 !important;
    color: #15803D !important;
    border: 1.5px solid #86EFAC !important;
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.12) !important;
}
[data-theme="light"] .btn-table-check:hover,
[data-theme="light"] .btn-table-check.check-stock-btn:hover {
    background: #16A34A !important;
    color: #FFFFFF !important;
    border-color: #16A34A !important;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25) !important;
    transform: translateY(-1px) !important;
}
.ph-pagination-container {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 16px; padding-top: 16px;
    border-top: 1px solid var(--insights-border-soft, rgba(42,68,132,0.32));
    flex-wrap: wrap; gap: 12px;
}

/* ══════════════════════════════════════════════════════════════
   Gemini AI Consumer Pulse & Daily Wants — Design System Consistency
   ══════════════════════════════════════════════════════════════ */

/* Tab navigation bar */
.ph-modal-tab-bar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    border-bottom: 1px solid var(--insights-border, rgba(42, 68, 132, 0.6));
    background: #0f1d44;
    flex-shrink: 0;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
[data-theme="light"] .ph-modal-tab-bar {
    background: #F8FAFC !important;
    border-bottom-color: #CBD5E1 !important;
}

.ph-modal-tab {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    font-family: 'Onest', sans-serif !important;
    font-size: 13px;
    font-weight: 600;
    color: var(--insights-text-muted, #9aa6d1);
    background: transparent;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    user-select: none;
}
.ph-modal-tab:hover {
    color: var(--insights-heading, #FFFFFF);
    background: rgba(255, 255, 255, 0.05);
}
.ph-modal-tab.active {
    color: #51D55A;
    background: rgba(81, 213, 90, 0.12);
    border-color: rgba(81, 213, 90, 0.4);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.15);
}
[data-theme="light"] .ph-modal-tab {
    color: #64748B !important;
}
[data-theme="light"] .ph-modal-tab:hover {
    color: #0F172A !important;
    background: rgba(0, 0, 0, 0.04) !important;
}
[data-theme="light"] .ph-modal-tab.active {
    color: #15803D !important;
    background: #FFFFFF !important;
    border-color: #86EFAC !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06) !important;
}

/* AI Badge */
.ph-ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-family: 'Onest', sans-serif !important;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 999px;
    letter-spacing: 0.4px;
    text-transform: uppercase;
    background: linear-gradient(135deg, #51D55A 0%, #10B981 100%);
    color: #ffffff;
    box-shadow: 0 2px 6px rgba(81, 213, 90, 0.3);
}

.ph-gemini-panel {
    padding: 2px 0 24px 0;
}

/* Preset Chips */
.ph-preset-chips-wrap {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding: 2px 2px 10px 2px;
    margin-bottom: 16px;
    -webkit-overflow-scrolling: touch;
}
.ph-preset-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 13px;
    border-radius: 8px;
    font-family: 'Onest', sans-serif !important;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    background: rgba(19, 39, 91, 0.7);
    border: 1px solid rgba(42, 68, 132, 0.45);
    color: var(--insights-text-muted, #9aa6d1);
    cursor: pointer;
    transition: all 0.18s cubic-bezier(0.4, 0, 0.2, 1);
    flex-shrink: 0;
}
.ph-preset-btn:hover {
    background: rgba(81, 213, 90, 0.12);
    border-color: rgba(81, 213, 90, 0.4);
    color: #FFFFFF;
    transform: translateY(-1px);
}
.ph-preset-btn.active {
    background: rgba(81, 213, 90, 0.18);
    border-color: #51D55A;
    color: #51D55A;
    box-shadow: 0 0 0 1px #51D55A inset;
    font-weight: 700;
}
[data-theme="light"] .ph-preset-btn {
    background: #FFFFFF !important;
    border-color: #CBD5E1 !important;
    color: #475569 !important;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03) !important;
}
[data-theme="light"] .ph-preset-btn:hover {
    background: #F0FDF4 !important;
    border-color: #86EFAC !important;
    color: #15803D !important;
}
[data-theme="light"] .ph-preset-btn.active {
    background: #DCFCE7 !important;
    border-color: #16A34A !important;
    color: #15803D !important;
    box-shadow: 0 0 0 1px #16A34A inset !important;
}

/* Query bar & controls */
.ph-query-bar {
    display: grid;
    grid-template-columns: 155px 175px 1fr auto;
    gap: 10px;
    margin-bottom: 20px;
    align-items: center;
}
@media (max-width: 900px) {
    .ph-query-bar {
        grid-template-columns: 1fr 1fr;
    }
    .ph-query-bar > div:nth-child(3),
    .ph-query-bar > button {
        grid-column: span 2;
    }
}
@media (max-width: 600px) {
    .ph-query-bar {
        grid-template-columns: 1fr;
    }
    .ph-query-bar > div:nth-child(3),
    .ph-query-bar > button {
        grid-column: span 1;
    }
}

.ph-gemini-input {
    background: var(--insights-input, rgba(19, 39, 91, 0.8));
    color: var(--insights-text, #E3E3E3);
    border: 1px solid var(--insights-input-border, rgba(42, 68, 132, 0.5));
    border-radius: 10px;
    padding: 0 14px;
    height: 40px;
    font-size: 13px;
    font-family: 'Onest', sans-serif !important;
    font-weight: 500;
    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
    width: 100%;
    box-sizing: border-box;
}
.ph-gemini-input:focus {
    border-color: #51D55A;
    box-shadow: 0 0 0 3px rgba(81, 213, 90, 0.16);
}
.ph-gemini-input[type="date"]::-webkit-calendar-picker-indicator {
    filter: invert(0.8);
    cursor: pointer;
}
[data-theme="light"] .ph-gemini-input[type="date"]::-webkit-calendar-picker-indicator {
    filter: none;
}
[data-theme="light"] .ph-gemini-input {
    background: #F1F5F9 !important;
    border-color: #BFCFE0 !important;
    color: #1E293B !important;
}
[data-theme="light"] .ph-gemini-input:focus {
    border-color: #16A34A !important;
    box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.16) !important;
}

/* Primary generate button */
.ph-btn-generate {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    height: 40px;
    padding: 0 18px;
    border-radius: 10px;
    font-family: 'Onest', sans-serif !important;
    font-size: 13px;
    font-weight: 600;
    color: #FFFFFF;
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%);
    border: none;
    box-shadow: 0 4px 12px rgba(81, 213, 90, 0.3), inset 0 1px 0 rgba(255, 255, 255, 0.2);
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    white-space: nowrap;
    user-select: none;
}
.ph-btn-generate:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(81, 213, 90, 0.45), inset 0 1px 0 rgba(255, 255, 255, 0.25);
}
.ph-btn-generate:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(81, 213, 90, 0.3);
}
[data-theme="light"] .ph-btn-generate {
    background: linear-gradient(135deg, #16A34A 0%, #15803D 100%) !important;
    border: 1px solid #15803D !important;
    box-shadow: 0 2px 8px -2px rgba(22, 163, 74, 0.35) !important;
}
[data-theme="light"] .ph-btn-generate:hover {
    background: linear-gradient(135deg, #15803D 0%, #0F6228 100%) !important;
    box-shadow: 0 4px 14px -4px rgba(22, 163, 74, 0.5) !important;
}

/* Briefing Card */
.ph-briefing-card {
    background: #11224d;
    border: 1px solid var(--insights-border, rgba(42, 68, 132, 0.6));
    border-radius: 14px;
    padding: 20px 22px;
    margin-bottom: 22px;
    position: relative;
    overflow: hidden;
    box-shadow: var(--insights-shadow, 0 10px 30px rgba(0, 0, 0, 0.22));
}
[data-theme="light"] .ph-briefing-card {
    background: #FFFFFF !important;
    border-color: #CBD5E1 !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06), 0 4px 16px rgba(15, 23, 42, 0.08) !important;
}

.ph-briefing-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 12px;
}
.ph-briefing-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.2px;
    background: rgba(148, 163, 184, 0.12);
    color: #E2E8F0;
    border: 1px solid rgba(148, 163, 184, 0.28);
}
[data-theme="light"] .ph-briefing-pill {
    background: #F1F5F9 !important;
    color: #1E293B !important;
    border-color: #CBD5E1 !important;
}
.ph-briefing-subline {
    font-family: 'Onest', sans-serif !important;
    font-size: 12px;
    font-weight: 500;
    color: var(--insights-text-muted, #9aa6d1);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
[data-theme="light"] .ph-briefing-subline {
    color: #64748B !important;
}
.ph-spending-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px;
    font-weight: 600;
    padding: 3px 9px;
    border-radius: 6px;
    background: rgba(19, 39, 91, 0.8);
    border: 1px solid rgba(42, 68, 132, 0.4);
    color: var(--insights-text, #E3E3E3);
}
[data-theme="light"] .ph-spending-badge {
    background: #F1F5F9 !important;
    border-color: #CBD5E1 !important;
    color: #1E293B !important;
}

/* Briefing Card Footer */
.ph-briefing-footer {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 14px;
    padding-top: 10px;
    border-top: 1px solid var(--insights-border-soft, rgba(42, 68, 132, 0.32));
    font-family: 'Onest', sans-serif !important;
    font-size: 11px;
    color: var(--insights-text-muted, #9aa6d1);
    opacity: 0.85;
}
[data-theme="light"] .ph-briefing-footer {
    border-top-color: #E2E8F0 !important;
    color: #64748B !important;
}

.ph-trending-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--insights-border-soft, rgba(42, 68, 132, 0.32));
}
[data-theme="light"] .ph-trending-chips {
    border-top-color: #E2E8F0 !important;
}
.ph-trending-label {
    font-size: 11px;
    font-weight: 600;
    color: var(--insights-text-muted, #9aa6d1);
    letter-spacing: 0.1px;
}
[data-theme="light"] .ph-trending-label {
    color: #64748B !important;
}
.ph-trend-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 6px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px;
    background: rgba(19, 39, 91, 0.6);
    border: 1px solid rgba(42, 68, 132, 0.35);
    color: var(--insights-text-muted, #9aa6d1);
}
[data-theme="light"] .ph-trend-chip {
    background: #F8FAFC !important;
    border-color: #E2E8F0 !important;
    color: #475569 !important;
}
.ph-trend-chip strong {
    color: var(--insights-heading, #FFFFFF);
    font-weight: 700;
}
[data-theme="light"] .ph-trend-chip strong {
    color: #0F172A !important;
}

/* Consumer Wants Grid & Equal-Height Cards */
.ph-wants-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(330px, 1fr));
    gap: 16px;
    align-items: stretch;
}
@media (max-width: 640px) {
    .ph-wants-grid {
        grid-template-columns: 1fr;
    }
}
.ph-want-card {
    background: #11224d;
    border: 1px solid var(--insights-border, rgba(42, 68, 132, 0.6));
    border-radius: 14px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    height: 100%;
    box-sizing: border-box;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
}
.ph-want-card:hover {
    border-color: #51D55A;
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
}
[data-theme="light"] .ph-want-card {
    background: #FFFFFF !important;
    border-color: #CBD5E1 !important;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05) !important;
}
[data-theme="light"] .ph-want-card:hover {
    border-color: #16A34A !important;
    box-shadow: 0 6px 18px rgba(22, 163, 74, 0.12) !important;
}

.ph-want-card-body {
    display: flex;
    flex-direction: column;
    flex: 1 1 auto;
}

/* Card Header: Title on Left, Large Surge Number in Top-Right */
.ph-want-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 8px;
}
.ph-want-title {
    flex: 1 1 auto;
    font-family: 'Onest', sans-serif !important;
    font-size: 15.5px;
    font-weight: 700;
    color: var(--insights-heading, #FFFFFF);
    line-height: 1.35;
    margin: 0;
    word-break: break-word;
}
[data-theme="light"] .ph-want-title {
    color: #0F172A !important;
}

.ph-want-surge-stat {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    text-align: right;
    padding: 3px 8px;
    background: rgba(81, 213, 90, 0.1);
    border: 1px solid rgba(81, 213, 90, 0.3);
    border-radius: 8px;
}
[data-theme="light"] .ph-want-surge-stat {
    background: #F0FDF4 !important;
    border-color: #BBF7D0 !important;
}
.ph-surge-number {
    font-family: 'Onest', sans-serif !important;
    font-size: 20px;
    font-weight: 800;
    color: #51D55A;
    line-height: 1.05;
    letter-spacing: -0.5px;
}
[data-theme="light"] .ph-surge-number {
    color: #16A34A !important;
}
.ph-surge-caption {
    font-family: 'Onest', sans-serif !important;
    font-size: 10px;
    font-weight: 700;
    color: #51D55A;
    opacity: 0.9;
    text-transform: none;
    line-height: 1.15;
    margin-top: 2px;
    max-width: 140px;
    word-break: break-word;
}
[data-theme="light"] .ph-surge-caption {
    color: #15803D !important;
}

/* Category and Audience chips: Neutral Gray (Green kept strictly for Surge & Primary Buttons) */
.ph-want-meta-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    margin-bottom: 12px;
}
.ph-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 8px;
    border-radius: 6px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    line-height: 1.25;
}
.ph-meta-chip.category-chip,
.ph-meta-chip.buyer-chip {
    background: rgba(148, 163, 184, 0.12);
    color: #94a3b8;
    border: 1px solid rgba(148, 163, 184, 0.25);
}
[data-theme="light"] .ph-meta-chip.category-chip,
[data-theme="light"] .ph-meta-chip.buyer-chip {
    background: #F1F5F9 !important;
    color: #64748B !important;
    border-color: #CBD5E1 !important;
}

/* "Why Filipinos want this today" box */
.ph-want-reason {
    background: rgba(19, 39, 91, 0.5);
    border-left: 3px solid rgba(148, 163, 184, 0.4);
    padding: 10px 12px;
    border-radius: 0 8px 8px 0;
    margin-bottom: 4px;
}
[data-theme="light"] .ph-want-reason {
    background: #F8FAFC !important;
    border-left-color: #CBD5E1 !important;
}
.ph-reason-tag {
    display: block;
    font-family: 'Onest', sans-serif !important;
    font-size: 11px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: 0.2px;
    color: var(--insights-text-muted, #9aa6d1);
    margin-bottom: 4px;
}
[data-theme="light"] .ph-reason-tag {
    color: #64748B !important;
}
.ph-reason-text {
    font-family: 'Onest', sans-serif !important;
    font-size: 12.5px;
    line-height: 1.5;
    color: var(--insights-text, #E3E3E3);
    margin: 0;
}
[data-theme="light"] .ph-reason-text {
    color: #334155 !important;
}

/* Card Bottom Group (Pushed to bottom via margin-top: auto) */
.ph-want-card-bottom {
    margin-top: auto;
    padding-top: 10px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

/* Merchandising tip: fixed-width icon column */
.ph-want-tip {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px;
    line-height: 1.45;
    color: var(--insights-text-muted, #9aa6d1);
}
[data-theme="light"] .ph-want-tip {
    color: #475569 !important;
}
.ph-tip-icon {
    flex-shrink: 0;
    width: 16px;
    font-size: 13px;
    line-height: 1.25;
}
.ph-tip-text {
    flex: 1;
    min-width: 0;
}

/* Color-coded stock status note */
.ph-stock-note {
    display: flex;
    align-items: flex-start;
    gap: 7px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px;
    line-height: 1.4;
    padding: 7px 10px;
    border-radius: 6px;
    box-sizing: border-box;
}
.ph-stock-dot {
    width: 6.5px;
    height: 6.5px;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 5px;
}
.ph-stock-note-text {
    flex: 1;
    min-width: 0;
}

/* In Stock (Green) */
.ph-stock-note.stock-in {
    background: rgba(81, 213, 90, 0.09);
    border: 1px solid rgba(81, 213, 90, 0.28);
    color: #51D55A;
}
.ph-stock-dot.green {
    background: #51D55A;
    box-shadow: 0 0 5px rgba(81, 213, 90, 0.5);
}
[data-theme="light"] .ph-stock-note.stock-in {
    background: #F0FDF4 !important;
    border-color: #86EFAC !important;
    color: #15803D !important;
}
[data-theme="light"] .ph-stock-dot.green {
    background: #15803D !important;
    box-shadow: 0 0 4px rgba(21, 128, 61, 0.4) !important;
}

/* Low Stock (Amber) */
.ph-stock-note.stock-low {
    background: rgba(245, 158, 11, 0.1);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #fbbf24;
}
.ph-stock-dot.amber {
    background: #fbbf24;
    box-shadow: 0 0 5px rgba(245, 158, 11, 0.5);
}
[data-theme="light"] .ph-stock-note.stock-low {
    background: #FFFBEB !important;
    border-color: #FDE68A !important;
    color: #B45309 !important;
}
[data-theme="light"] .ph-stock-dot.amber {
    background: #B45309 !important;
}

/* Out of Stock (Red) */
.ph-stock-note.stock-out {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.35);
    color: #f87171;
}
.ph-stock-dot.red {
    background: #f87171;
    box-shadow: 0 0 5px rgba(239, 68, 68, 0.5);
}
[data-theme="light"] .ph-stock-note.stock-out {
    background: #FEF2F2 !important;
    border-color: #FECACA !important;
    color: #DC2626 !important;
}
[data-theme="light"] .ph-stock-dot.red {
    background: #DC2626 !important;
}

/* Market Opportunity (Blue) */
.ph-stock-note.stock-opp {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    color: #60a5fa;
}
.ph-stock-dot.blue {
    background: #60a5fa;
    box-shadow: 0 0 5px rgba(59, 130, 246, 0.5);
}
[data-theme="light"] .ph-stock-note.stock-opp {
    background: #EFF6FF !important;
    border-color: #BFDBFE !important;
    color: #2563EB !important;
}
[data-theme="light"] .ph-stock-dot.blue {
    background: #2563EB !important;
}

.ph-want-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    padding-top: 14px;
    border-top: 1px solid var(--insights-border-soft, rgba(42, 68, 132, 0.32));
    margin-top: auto;
    width: 100%;
    box-sizing: border-box;
}
[data-theme="light"] .ph-want-footer {
    border-top-color: #E2E8F0 !important;
}

.ph-status-tag {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px;
    font-weight: 600;
    text-transform: none;
    letter-spacing: 0.1px;
    height: 32px;
    padding: 0 10px;
    border-radius: 8px;
    white-space: nowrap;
    box-sizing: border-box;
    flex-shrink: 0;
}
.ph-status-tag.in-stock {
    background: rgba(81, 213, 90, 0.12);
    color: #51D55A;
    border: 1px solid rgba(81, 213, 90, 0.35);
}
[data-theme="light"] .ph-status-tag.in-stock {
    background: #DCFCE7 !important;
    color: #15803D !important;
    border-color: #86EFAC !important;
}
.ph-status-tag.low {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
}
[data-theme="light"] .ph-status-tag.low {
    background: #FEF3C7 !important;
    color: #B45309 !important;
    border-color: #FCD34D !important;
}
.ph-status-tag.critical {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.35);
}
[data-theme="light"] .ph-status-tag.critical {
    background: #FEE2E2 !important;
    color: #DC2626 !important;
    border-color: #FCA5A5 !important;
}
.ph-status-tag.opportunity {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.35);
}
[data-theme="light"] .ph-status-tag.opportunity {
    background: #EFF6FF !important;
    color: #2563EB !important;
    border-color: #BFDBFE !important;
}

/* Action buttons in cards */
.btn-table-check,
.btn-table-check.check-stock-btn,
.btn-table-onboard {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    padding: 0 12px !important;
    height: 32px !important;
    min-width: 95px !important;
    width: auto !important;
    background: rgba(81, 213, 90, 0.12) !important;
    border: 1.5px solid rgba(81, 213, 90, 0.45) !important;
    border-radius: 8px !important;
    color: #51D55A !important;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    box-sizing: border-box !important;
    flex-shrink: 0 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15) !important;
}
.btn-table-check:hover,
.btn-table-check.check-stock-btn:hover {
    background: linear-gradient(135deg, #51D55A 0%, #43B74B 100%) !important;
    color: #FFFFFF !important;
    border-color: #51D55A !important;
    box-shadow: 0 4px 14px rgba(81, 213, 90, 0.4) !important;
    transform: translateY(-1px) !important;
}
[data-theme="light"] .btn-table-check,
[data-theme="light"] .btn-table-check.check-stock-btn {
    background: #F0FDF4 !important;
    color: #15803D !important;
    border: 1.5px solid #86EFAC !important;
    box-shadow: 0 1px 3px rgba(16, 185, 129, 0.12) !important;
}
[data-theme="light"] .btn-table-check:hover,
[data-theme="light"] .btn-table-check.check-stock-btn:hover {
    background: #16A34A !important;
    color: #FFFFFF !important;
    border-color: #16A34A !important;
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.25) !important;
    transform: translateY(-1px) !important;
}

.btn-table-onboard {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 5px !important;
    padding: 0 13px !important;
    height: 32px !important;
    min-width: 105px !important;
    width: auto !important;
    background: rgba(59, 130, 246, 0.12) !important;
    border: 1.5px solid rgba(59, 130, 246, 0.45) !important;
    border-radius: 8px !important;
    color: #60a5fa !important;
    font-family: 'Onest', sans-serif !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    text-decoration: none !important;
    white-space: nowrap !important;
    flex-shrink: 0 !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15) !important;
}
.btn-table-onboard:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%) !important;
    color: #FFFFFF !important;
    border-color: #2563EB !important;
    box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35) !important;
    transform: translateY(-1px) !important;
}
[data-theme="light"] .btn-table-onboard {
    background: #EFF6FF !important;
    color: #2563EB !important;
    border: 1.5px solid #BFDBFE !important;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.12) !important;
}
[data-theme="light"] .btn-table-onboard:hover {
    background: #2563EB !important;
    color: #FFFFFF !important;
    border-color: #2563EB !important;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25) !important;
    transform: translateY(-1px) !important;
}

.ph-loading-shimmer {
    padding: 40px 20px;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
}
@keyframes phSpinPulse {
    0% { transform: rotate(0deg) scale(1); }
    50% { transform: rotate(180deg) scale(1.15); }
    100% { transform: rotate(360deg) scale(1); }
}
.ph-ai-spinner {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    border: 3px solid rgba(81, 213, 90, 0.2);
    border-top-color: #51D55A;
    animation: phSpinPulse 1.2s infinite ease-in-out;
}

/* ══════════════════════════════════════════════════════════════
   PH Seasonal Modal — Mobile Bottom Sheet & Responsive UI (< 768px)
   ══════════════════════════════════════════════════════════════ */
@media (max-width: 767px) {
    /* Modal overlay becomes full-screen backdrop with bottom-sheet alignment */
    .ph-seasonal-modal-overlay {
        padding: 0 !important;
        align-items: flex-end !important;
        justify-content: center !important;
    }

    /* Modal dialog slides up from bottom as a sleek, native bottom sheet */
    .ph-seasonal-modal-dialog {
        width: 100% !important;
        max-width: 100% !important;
        height: 94vh !important;
        max-height: 94vh !important;
        border-radius: 20px 20px 0 0 !important;
        border-left: none !important;
        border-right: none !important;
        border-bottom: none !important;
        margin: 0 !important;
        min-height: 0 !important;
        display: flex !important;
        flex-direction: column !important;
        box-shadow: 0 -12px 48px rgba(0, 0, 0, 0.55) !important;
        animation: phModalSlideUpMobile 0.28s cubic-bezier(0.16, 1, 0.3, 1) both !important;
    }

    @keyframes phModalSlideUpMobile {
        from { transform: translateY(100%); opacity: 0.5; }
        to   { transform: translateY(0);    opacity: 1; }
    }

    /* Mobile pull handle indicator */
    .ph-modal-drag-handle {
        display: block !important;
        width: 38px !important;
        height: 4px !important;
        border-radius: 999px !important;
        background: rgba(148, 163, 184, 0.4) !important;
        margin: 8px auto 2px auto !important;
        flex-shrink: 0 !important;
    }

    /* Compact header on mobile */
    .ph-seasonal-modal-header {
        padding: 10px 14px 12px 14px !important;
        gap: 10px !important;
        flex-shrink: 0 !important;
    }
    .ph-seasonal-modal-header > div:first-child {
        gap: 10px !important;
        min-width: 0 !important;
        flex: 1 1 auto !important;
    }
    .ph-seasonal-modal-header > div:first-child > div:first-child {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px !important;
        min-height: 32px !important;
        border-radius: 8px !important;
        flex-shrink: 0 !important;
    }
    .ph-seasonal-modal-header > div:first-child > div:first-child svg {
        width: 17px !important;
        height: 17px !important;
    }
    #phSeasonalModalTitle {
        font-size: 14px !important;
        font-weight: 700 !important;
        line-height: 1.25 !important;
    }
    .ph-seasonal-modal-header > div:first-child > div:last-child > div {
        font-size: 11px !important;
        line-height: 1.3 !important;
        margin-top: 2px !important;
    }
    .ph-seasonal-modal-close {
        width: 32px !important;
        height: 32px !important;
        min-width: 32px !important;
        min-height: 32px !important;
        font-size: 18px !important;
        flex-shrink: 0 !important;
    }

    /* Scrollable body with smooth momentum scroll and comfortable padding */
    .ph-seasonal-modal-body {
        padding: 14px 14px 28px 14px !important;
        min-height: 0 !important;
        flex: 1 1 auto !important;
        overflow-y: auto !important;
        overflow-x: hidden !important;
        -webkit-overflow-scrolling: touch !important;
    }

    /* Dropdown controls stacked cleanly */
    .seasonal-dropdown-row {
        flex-direction: column !important;
        gap: 10px !important;
        margin-bottom: 14px !important;
    }
    .seasonal-dropdown-group {
        width: 100% !important;
        gap: 4px !important;
    }
    .seasonal-dropdown-label {
        font-size: 10.5px !important;
        letter-spacing: 0.4px !important;
    }
    .seasonal-dropdown-control {
        height: 38px !important;
        font-size: 12.5px !important;
        padding: 0 32px 0 12px !important;
        border-radius: 9px !important;
    }
    .seasonal-dropdown-arrow {
        right: 12px !important;
    }

    /* Intel Banner compact layout */
    .ph-intel-banner {
        padding: 14px 14px !important;
        border-radius: 12px !important;
        margin-bottom: 14px !important;
    }
    .ph-header-meta {
        gap: 8px !important;
        margin-bottom: 6px !important;
    }
    .ph-season-pill {
        font-size: 10px !important;
        padding: 2px 8px !important;
    }
    .ph-weather-subtitle {
        font-size: 11px !important;
    }
    .ph-main-heading {
        font-size: 15px !important;
        line-height: 1.25 !important;
        margin-bottom: 4px !important;
    }
    .ph-main-subtext {
        font-size: 11.5px !important;
        line-height: 1.35 !important;
    }
    .ph-section-label {
        font-size: 10px !important;
        margin: 14px 0 8px 0 !important;
    }
    .consumption-triggers-grid {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
    }
    .trigger-card {
        padding: 9px 12px !important;
        font-size: 12px !important;
        border-radius: 8px !important;
        line-height: 1.35 !important;
    }
    .demand-lift-grid {
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
    }
    .metric-lift-card {
        padding: 9px 10px !important;
        border-radius: 8px !important;
    }
    .metric-lift-label {
        font-size: 11px !important;
    }
    .lift-multiplier {
        font-size: 15px !important;
    }
    .lift-percentage {
        font-size: 10.5px !important;
    }

    /* Products table container */
    .ph-products-container {
        padding: 12px !important;
        border-radius: 12px !important;
        margin-top: 14px !important;
    }
    .ph-table-header-row {
        margin-bottom: 10px !important;
    }
    .ph-table-header-row div:first-child > div:first-child {
        font-size: 14px !important;
    }
    .ph-table-header-row div:first-child > div:last-child {
        font-size: 11px !important;
    }
    .ph-line-table {
        min-width: 580px !important;
    }
    .ph-line-table th {
        padding: 8px 10px !important;
        font-size: 10px !important;
    }
    .ph-line-table th:last-child,
    .ph-line-table td:last-child {
        min-width: 135px !important;
        width: 135px !important;
        text-align: right !important;
    }
    .ph-line-table td {
        padding: 10px 10px !important;
    }
    .seasonal-product-thumb {
        width: 36px !important;
        height: 36px !important;
        border-radius: 8px !important;
    }
    .table-product-name {
        font-size: 12.5px !important;
    }
    .table-intel-sentence {
        font-size: 11.5px !important;
        line-height: 1.35 !important;
    }

    /* Floating action button (#phSeasonalFab) */
    #phSeasonalFab {
        bottom: 16px !important;
        right: 14px !important;
        padding: 5px 8px 5px 12px !important;
        gap: 8px !important;
    }
    .ph-fab-title {
        font-size: 11.5px !important;
    }
    .ph-fab-badge {
        font-size: 9.5px !important;
    }
    .ph-fab-icon-disc {
        width: 28px !important;
        height: 28px !important;
    }
    .ph-fab-icon-disc svg {
        width: 15px !important;
        height: 15px !important;
    }

    /* Gemini Pulse mobile UI consistency */
    .ph-modal-tab-bar {
        padding: 8px 14px !important;
        gap: 6px !important;
    }
    .ph-modal-tab {
        padding: 6px 12px !important;
        font-size: 12px !important;
        border-radius: 6px !important;
    }
    .ph-preset-chips-wrap {
        padding-bottom: 6px !important;
        margin-bottom: 12px !important;
    }
    .ph-preset-btn {
        padding: 5px 10px !important;
        font-size: 11px !important;
        border-radius: 6px !important;
    }
    .ph-query-bar {
        grid-template-columns: 1fr !important;
        gap: 8px !important;
        margin-bottom: 14px !important;
    }
    .ph-gemini-input {
        height: 38px !important;
        font-size: 12px !important;
        padding: 0 12px !important;
        border-radius: 8px !important;
    }
    .ph-btn-generate {
        height: 38px !important;
        font-size: 12.5px !important;
        width: 100% !important;
        border-radius: 8px !important;
    }
    .ph-briefing-card {
        padding: 14px 14px !important;
        border-radius: 12px !important;
        margin-bottom: 14px !important;
    }
    .ph-wants-grid {
        grid-template-columns: 1fr !important;
        gap: 10px !important;
    }
    .ph-want-card {
        padding: 14px 14px !important;
        border-radius: 10px !important;
    }
    .btn-table-check,
    .btn-table-check.check-stock-btn,
    .btn-table-onboard {
        height: 30px !important;
        padding: 0 10px !important;
        font-size: 11px !important;
        min-width: 95px !important;
    }
}
</style>

<!-- ═══════════════════════════════════════════════════════════
     PH Seasonal FAB — Premium Glassmorphic Floating Button
     ═══════════════════════════════════════════════════════════ -->
<div id="phSeasonalFab"
     role="button"
     tabindex="0"
     aria-label="Open Philippine Market and Seasonal Demand Recommendations"
     onclick="openPhilippineSeasonalModal()"
     onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();openPhilippineSeasonalModal();}"
     title="Philippine Market &amp; Seasonal Demand Recommendations">

    <!-- Left: text labels -->
    <div class="ph-fab-text-col">
        <span class="ph-fab-title">PH Seasonal</span>
        <span class="ph-fab-badge"><?php echo htmlspecialchars($_psi_activeProfile['month_name'] ?? date('F')); ?></span>
    </div>

    <!-- Divider -->
    <div class="ph-fab-divider" aria-hidden="true" style="width:1px;height:24px;background:rgba(255,255,255,0.18);flex-shrink:0;"></div>

    <!-- Right: icon disc -->
    <div class="ph-fab-icon-disc" aria-hidden="true">
        <!-- Crisp 8-ray Philippine Sun icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;display:block;">
            <!-- Center sun core with subtle glow fill -->
            <circle cx="12" cy="12" r="4.2" fill="rgba(255,255,255,0.22)" stroke="#FFFFFF" stroke-width="2"/>
            <!-- 8 balanced rays -->
            <line x1="12" y1="2"    x2="12" y2="4.8"  stroke="#FFFFFF" stroke-width="2.2"/>
            <line x1="12" y1="19.2" x2="12" y2="22"   stroke="#FFFFFF" stroke-width="2.2"/>
            <line x1="2"    y1="12" x2="4.8"  y2="12"  stroke="#FFFFFF" stroke-width="2.2"/>
            <line x1="19.2" y1="12" x2="22"   y2="12"  stroke="#FFFFFF" stroke-width="2.2"/>
            <line x1="4.9"  y1="4.9"  x2="7"    y2="7"    stroke="#FFFFFF" stroke-width="2"/>
            <line x1="17"   y1="17"   x2="19.1" y2="19.1" stroke="#FFFFFF" stroke-width="2"/>
            <line x1="4.9"  y1="19.1" x2="7"    y2="17"   stroke="#FFFFFF" stroke-width="2"/>
            <line x1="17"   y1="7"    x2="19.1" y2="4.9"  stroke="#FFFFFF" stroke-width="2"/>
        </svg>
    </div>
</div>

<!-- Modal -->
<div id="philippineSeasonalModal" class="ph-seasonal-modal-overlay"
     onclick="handleSeasonalModalBackdrop(event)"
     role="dialog" aria-modal="true" aria-labelledby="phSeasonalModalTitle">
    <div class="ph-seasonal-modal-dialog">
        <div class="ph-modal-drag-handle"></div>
        <div class="ph-seasonal-modal-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;border-radius:10px;background:rgba(81,213,90,0.12);border:1.5px solid rgba(81,213,90,0.45);display:flex;align-items:center;justify-content:center;color:#51D55A;flex-shrink:0;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="2" y1="12" x2="22" y2="12"/>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>
                    </svg>
                </div>
                <div>
                    <h3 id="phSeasonalModalTitle" style="margin:0;font-size:18px;font-weight:700;color:var(--insights-heading,#FFFFFF);">
                        Philippine Market and Seasonal Demand Recommendations
                    </h3>
                    <div style="font-size:12px;color:var(--insights-text-muted,#9aa6d1);margin-top:2px;">
                        Multi-horizon retail forecasts, climate drivers and inventory stock alignment
                    </div>
                </div>
            </div>
            <button type="button" class="ph-seasonal-modal-close"
                    onclick="closePhilippineSeasonalModal()" title="Close (Esc)">&times;</button>
        </div>
        <!-- Modal Tabs: Seasonal Calendar vs Gemini AI Daily Consumer Wants -->
        <div class="ph-modal-tab-bar" role="tablist">
            <button type="button" class="ph-modal-tab active" id="tabSeasonalCalendarBtn" role="tab" aria-selected="true" onclick="switchPhModalView('calendar')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Macro Seasonal Forecast</span>
            </button>
            <button type="button" class="ph-modal-tab" id="tabGeminiPulseBtn" role="tab" aria-selected="false" onclick="switchPhModalView('gemini')">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z"/>
                </svg>
                <span>Gemini AI Daily Consumer Wants</span>
                <span class="ph-ai-badge">LIVE AI</span>
            </button>
        </div>
        <div class="ph-seasonal-modal-body">
            <!-- Macro Seasonal Calendar View -->
            <div id="phCalendarView">
                <!-- Dropdowns -->
            <div class="seasonal-dropdown-row">
                <div class="seasonal-dropdown-group">
                    <label for="seasonalHorizonSelect" class="seasonal-dropdown-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                        <span>Forecast Horizon</span>
                    </label>
                    <div class="seasonal-select-wrapper">
                        <select id="seasonalHorizonSelect" class="seasonal-dropdown-control"
                                onchange="handleSeasonalHorizonDropdownChange(this.value)">
                            <option value="monthly"     <?php echo $_psi_currentHorizon === 'monthly'     ? 'selected' : ''; ?>>Monthly (30-Day View)</option>
                            <option value="quarterly"   <?php echo $_psi_currentHorizon === 'quarterly'   ? 'selected' : ''; ?>>Quarterly (3-Month Cycle)</option>
                            <option value="semi-annual" <?php echo $_psi_currentHorizon === 'semi-annual' ? 'selected' : ''; ?>>Semi-Annual (6-Month Cycle)</option>
                        </select>
                        <svg class="seasonal-dropdown-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                </div>
                <div class="seasonal-dropdown-group">
                    <label for="seasonalPeriodSelect" class="seasonal-dropdown-label">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                        </svg>
                        <span id="seasonalPeriodFieldLabel">Target Month</span>
                    </label>
                    <div class="seasonal-select-wrapper">
                        <select id="seasonalPeriodSelect" class="seasonal-dropdown-control"
                                onchange="handleSeasonalPeriodDropdownChange(this.value)">
                            <?php
                            $currMonthNum = (int)date('n');
                            for ($m = 1; $m <= 12; $m++):
                                $prof   = $philippineProfiles[$m] ?? [];
                                $name   = $prof['month_name'] ?? date('F', mktime(0, 0, 0, $m, 10));
                                $badge  = !empty($prof['season_badge']) ? " ({$prof['season_badge']})" : '';
                                $isCurr = ($currMonthNum === $m) ? ' - Current Month' : '';
                                $isSel  = ($m === $_psi_selectedMonth) ? 'selected' : '';
                            ?>
                            <option value="<?php echo $m; ?>" <?php echo $isSel; ?>><?php echo htmlspecialchars($name . $badge . $isCurr); ?></option>
                            <?php endfor; ?>
                        </select>
                        <svg class="seasonal-dropdown-arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </div>
                </div>
            </div>
            <!-- Intelligence Banner -->
            <div id="modalSeasonalBanner" class="ph-intel-banner">
                <div class="ph-header-meta">
                    <span class="ph-season-pill"><?php echo htmlspecialchars($_psi_activeProfile['season_badge'] ?? 'Seasonal Cycle'); ?></span>
                    <span class="ph-weather-subtitle"><?php echo htmlspecialchars($_psi_activeProfile['weather_ph'] ?? ''); ?></span>
                </div>
                <h3 class="ph-main-heading">
                    <?php echo htmlspecialchars($_psi_activeProfile['month_name'] ?? ''); ?>:
                    <?php echo htmlspecialchars($_psi_activeProfile['tagline'] ?? ''); ?>
                </h3>
                <p class="ph-main-subtext">Philippine macroeconomic drivers, cultural events, and inventory stock alignment.</p>
                <div class="ph-section-label">CONSUMPTION TRIGGERS</div>
                <div class="consumption-triggers-grid">
                    <?php foreach (($_psi_activeProfile['cultural_drivers'] ?? []) as $driver): ?>
                    <div class="trigger-card"><?php echo htmlspecialchars($driver); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <!-- Products Table -->
            <div class="ph-products-container" style="margin-top:22px;">
                <div class="ph-table-header-row">
                    <div>
                        <div style="color:var(--insights-heading,#FFFFFF);font-weight:700;font-size:16px;display:flex;align-items:center;gap:8px;">
                            <span id="modalTableTitle">Seasonal Demand &amp; Stock Alignment: <?php echo htmlspecialchars($_psi_activeProfile['month_name'] ?? ''); ?></span>
                        </div>
                        <div style="color:var(--insights-text-muted,#9aa6d1);font-size:12px;margin-top:3px;">Cross-referenced with real-time stock inventory and seasonal multipliers.</div>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="ph-line-table">
                        <thead>
                            <tr>
                                <th style="width:34%;min-width:160px;">In-Demand Product / Category</th>
                                <th style="width:42%;min-width:220px;">Market Intelligence &amp; Recommendation</th>
                                <th style="width:24%;min-width:135px;text-align:right;">Stock &amp; Action</th>
                            </tr>
                        </thead>
                        <tbody id="modalSeasonalTableBody">
                            <?php
                            $rawMonthData  = $allSeasonalMonthsData[$_psi_selectedMonth] ?? [];
                            $currMonthData = isset($rawMonthData['items'])
                                ? $rawMonthData['items']
                                : (is_array($rawMonthData) ? $rawMonthData : []);
                            if (empty($currMonthData)):
                            ?>
                            <tr><td colspan="3" style="text-align:center;padding:28px;color:var(--insights-text-muted,#9aa6d1);">No products found matching the seasonal profile for this period.</td></tr>
                            <?php
                            else:
                                $modalInitialIdx = 0;
                                foreach ($currMonthData as $item):
                                    $rowDisplayAttr = ($modalInitialIdx >= 5) ? 'style="display:none;"' : '';
                                    $modalInitialIdx++;
                                    $typeStr      = $item['type'] ?? $item['status'] ?? '';
                                    $statusType   = strtolower($item['status_type'] ?? '');
                                    $actionNeeded = strtolower($item['action_needed'] ?? '');
                                    $statusPillHtml = '';
                                    if ($statusType === 'critical' || $statusType === 'high_risk' || stripos($typeStr, 'Critical') !== false || stripos($typeStr, 'Out of Stock') !== false) {
                                        $statusPillHtml = '<span class="table-status-pill status-critical">' . htmlspecialchars($typeStr ?: 'Critical Understock') . '</span>';
                                    } elseif ($statusType === 'moderate' || $actionNeeded === 'restock' || stripos($typeStr, 'Risk') !== false || stripos($typeStr, 'Low') !== false) {
                                        $statusPillHtml = '<span class="table-status-pill status-warning">' . htmlspecialchars($typeStr ?: 'Reorder Needed') . '</span>';
                                    } elseif ($statusType === 'opportunity' || stripos($typeStr, 'Opportunity') !== false) {
                                        $statusPillHtml = '<span class="table-status-pill status-opportunity">' . htmlspecialchars($typeStr ?: 'Market Opportunity') . '</span>';
                                    }
                                    $desc = trim($item['descriptive'] ?? $item['reason'] ?? '');
                                    $pred = trim($item['predictive'] ?? '');
                                    if ($desc && $pred) {
                                        $intelSentence = (substr($desc, -1) === '.' ? $desc : $desc . '.') . ' ' . $pred;
                                    } elseif ($pred) { $intelSentence = $pred; }
                                    elseif ($desc) { $intelSentence = $desc; }
                                    else { $units = number_format((int)($item['projected_demand'] ?? 0)); $intelSentence = "Anticipated seasonal demand surge during " . ($_psi_activeProfile['month_name'] ?? '') . " with projected volume of ~{$units} units."; }
                                    $actionText = trim($item['prescriptive'] ?? $item['action_text'] ?? '');
                                    $actionCalloutHtml = '';
                                    $svgW = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>';
                                    $svgI = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>';
                                    $svgT = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline><polyline points="17 6 23 6 23 12"></polyline></svg>';
                                    if (!empty($actionText)) {
                                        if ($actionNeeded === 'urgent' || $statusType === 'critical' || $statusType === 'high_risk')
                                            $actionCalloutHtml = '<div class="table-action-callout callout-urgent">' . $svgW . '<span>' . formatSafeSeasonalHtml($actionText) . '</span></div>';
                                        elseif ($actionNeeded === 'restock' || $actionNeeded === 'monitor' || $statusType === 'moderate')
                                            $actionCalloutHtml = '<div class="table-action-callout callout-warning">' . $svgI . '<span>' . formatSafeSeasonalHtml($actionText) . '</span></div>';
                                        elseif ($statusType === 'opportunity' || $actionNeeded === 'opportunity')
                                            $actionCalloutHtml = '<div class="table-action-callout callout-opportunity">' . $svgT . '<span>' . formatSafeSeasonalHtml($actionText) . '</span></div>';
                                    }
                                    $hasStock     = isset($item['current_stock']) && ($item['matched_in_stock'] ?? true);
                                    $stockDisplay = $hasStock
                                        ? number_format((int)$item['current_stock']) . ' <span class="table-stock-label">units</span>'
                                        : '<span class="table-stock-label">Not in catalog</span>';

                                    $imgUrl = $item['image_url'] ?? null;
                                    if (!$imgUrl && !empty($item['product_id'])) {
                                        if (file_exists(__DIR__ . '/../../uploads/products/' . (int)$item['product_id'] . '.png')) {
                                            $imgUrl = 'uploads/products/' . (int)$item['product_id'] . '.png';
                                        } elseif (file_exists(__DIR__ . '/../../uploads/products/' . (int)$item['product_id'] . '.jpg')) {
                                            $imgUrl = 'uploads/products/' . (int)$item['product_id'] . '.jpg';
                                        }
                                    }
                                    if (!$imgUrl && stripos($item['product_name'] ?? '', 'Graham') !== false && file_exists(__DIR__ . '/../../uploads/products/graham_gelatin.png')) {
                                        $imgUrl = 'uploads/products/graham_gelatin.png';
                                    }
                            ?>
                            <tr <?php echo $rowDisplayAttr; ?>>
                                <td style="width:34%;">
                                    <div class="seasonal-product-cell">
                                        <div class="seasonal-product-thumb" title="<?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?>">
                                            <?php if (!empty($imgUrl)): ?>
                                                <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?>" class="seasonal-product-img" loading="lazy" onerror="this.style.display='none';if(this.nextElementSibling)this.nextElementSibling.style.display='flex';">
                                                <div class="seasonal-thumb-fallback" style="display:none;">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                                </div>
                                            <?php else: ?>
                                                <div class="seasonal-thumb-fallback">
                                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="seasonal-product-info">
                                            <div class="table-product-name"><?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?></div>
                                            <div class="table-product-meta">
                                                <span><?php echo htmlspecialchars($item['category_name'] ?? ''); ?></span>
                                                <?php if (!empty($item['demand_lift'])): ?>
                                                <span class="meta-sep">&middot;</span>
                                                <span class="surge-tag"><?php echo htmlspecialchars($item['demand_lift']); ?> Surge</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($statusPillHtml): ?><div style="margin-top:4px;"><?php echo $statusPillHtml; ?></div><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td style="width:42%;min-width:220px;">
                                    <div class="table-intel-sentence"><?php echo formatSafeSeasonalHtml($intelSentence); ?></div>
                                    <?php echo $actionCalloutHtml; ?>
                                </td>
                                <td style="width:24%;min-width:135px;text-align:right;">
                                    <div class="table-stock-wrap">
                                        <div class="table-stock-num"><?php echo $stockDisplay; ?></div>
                                        <button type="button" class="btn-table-check check-stock-btn action-btn"
                                            data-product="<?php echo htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-category="<?php echo htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php if (!empty($item['product_id'])): ?>data-id="<?php echo (int)$item['product_id']; ?>"<?php endif; ?>
                                            onclick="handleCheckStockClick(event, this)">
                                            Check stock &rarr;
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <div id="modalSeasonalPagination" class="ph-pagination-container"></div>
            </div><!-- /.ph-products-container -->
            </div><!-- /#phCalendarView -->

            <!-- Gemini AI Daily Consumer Wants Panel -->
            <div id="phGeminiPulseView" style="display:none;" class="ph-gemini-panel">
                <!-- Preset Quick Buttons -->
                <div class="ph-preset-chips-wrap" role="toolbar" aria-label="Quick Philippine consumer scenarios">
                    <button type="button" class="ph-preset-btn active" data-preset="payday" onclick="selectPulsePreset('payday', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>
                        <span>Payday Quincena (15th/30th)</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="tgif" onclick="selectPulsePreset('tgif', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M18 8h1a4 4 0 0 1 0 8h-1"></path><path d="M2 8h16v9a4 4 0 0 1-4 4H6a4 4 0 0 1-4-4V8z"></path><line x1="6" y1="1" x2="6" y2="4"></line><line x1="10" y1="1" x2="10" y2="4"></line><line x1="14" y1="1" x2="14" y2="4"></line></svg>
                        <span>TGIF Friday Inuman</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="weekend_groceries" onclick="selectPulsePreset('weekend_groceries', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                        <span>Saturday Grocery Run</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="sunday_salusalo" onclick="selectPulsePreset('sunday_salusalo', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                        <span>Sunday Family Salu-Salo</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="rainy_comfort" onclick="selectPulsePreset('rainy_comfort', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><line x1="16" y1="13" x2="16" y2="21"></line><line x1="8" y1="13" x2="8" y2="21"></line><line x1="12" y1="15" x2="12" y2="23"></line><path d="M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25"></path></svg>
                        <span>Tag-Ulan Rainy Day Comfort</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="summer_heat" onclick="selectPulsePreset('summer_heat', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                        <span>Tag-Init Summer Heatwave</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="undas" onclick="selectPulsePreset('undas', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3z"></path></svg>
                        <span>Undas / All Saints' Day</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="simbang_gabi" onclick="selectPulsePreset('simbang_gabi', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path></svg>
                        <span>Paskong Pinoy &amp; Simbang Gabi</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="holy_week" onclick="selectPulsePreset('holy_week', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                        <span>Semana Santa Holy Week</span>
                    </button>
                    <button type="button" class="ph-preset-btn" data-preset="back_to_school" onclick="selectPulsePreset('back_to_school', this)">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
                        <span>Balik-Eskwela Season</span>
                    </button>
                </div>

                <!-- Custom Query / Filters Bar -->
                <div class="ph-query-bar">
                    <div>
                        <input type="date" id="phPulseDateInput" class="ph-gemini-input"
                               value="<?php echo date('Y-m-d'); ?>"
                               onchange="handleDateOrRegionChange()"
                               title="Target Calendar Date" aria-label="Target Calendar Date" />
                    </div>
                    <div>
                        <select id="phPulseRegionSelect" class="ph-gemini-input"
                                onchange="handleDateOrRegionChange()"
                                title="Philippine Region" aria-label="Philippine Region">
                            <option value="Philippines (Nationwide)">Nationwide (All PH)</option>
                            <option value="NCR / Metro Manila">NCR / Metro Manila</option>
                            <option value="Luzon Provinces">Luzon Provinces</option>
                            <option value="Visayas (Cebu, Iloilo)">Visayas (Cebu / Iloilo)</option>
                            <option value="Mindanao (Davao, CDO)">Mindanao (Davao / CDO)</option>
                        </select>
                    </div>
                    <div>
                        <input type="text" id="phPulseCustomPrompt" class="ph-gemini-input"
                               placeholder="e.g. Typhoon monsoon storm cravings, or Friday payday dinner..."
                               onkeydown="if(event.key==='Enter'){event.preventDefault();generateGeminiPulse();}" />
                    </div>
                    <div>
                        <button type="button" id="phPulseGenerateBtn" class="ph-btn-generate" onclick="generateGeminiPulse()">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z"/>
                            </svg>
                            <span>Analyze Wants</span>
                        </button>
                    </div>
                </div>

                <!-- Live Loading State -->
                <div id="geminiPulseLoading" style="display:none;" class="ph-loading-shimmer">
                    <div class="ph-ai-spinner"></div>
                    <div style="font-size:14px;font-weight:700;color:var(--insights-heading,#FFFFFF);">
                        Consulting Google Gemini AI Consumer Pulse...
                    </div>
                    <div style="font-size:12px;color:var(--insights-text-muted,#9aa6d1);">
                        Analyzing daily purchasing intent, Pinoy cravings, payday cycles, and warehouse catalog alignment.
                    </div>
                </div>

                <!-- Results Output Container -->
                <div id="geminiPulseResults">
                    <!-- Populated dynamically via JS -->
                </div>
            </div><!-- /#phGeminiPulseView -->
        </div><!-- /.ph-seasonal-modal-body -->
    </div><!-- /.ph-seasonal-modal-dialog -->
</div><!-- /.ph-seasonal-modal-overlay -->

<script>
// --- Philippine Seasonal Tab Switching ---
window.switchPhModalView = function(view) {
    const calendarView = document.getElementById('phCalendarView');
    const geminiView = document.getElementById('phGeminiPulseView');
    const tabCalBtn = document.getElementById('tabSeasonalCalendarBtn');
    const tabGeminiBtn = document.getElementById('tabGeminiPulseBtn');
    const titleEl = document.getElementById('phSeasonalModalTitle');

    if (view === 'gemini') {
        if (calendarView) calendarView.style.display = 'none';
        if (geminiView) geminiView.style.display = 'block';
        if (tabCalBtn) {
            tabCalBtn.classList.remove('active');
            tabCalBtn.setAttribute('aria-selected', 'false');
        }
        if (tabGeminiBtn) {
            tabGeminiBtn.classList.add('active');
            tabGeminiBtn.setAttribute('aria-selected', 'true');
        }
        if (titleEl) {
            titleEl.textContent = 'Philippine Market & Daily Consumer Wants (Gemini AI)';
        }
        // Auto-generate on first open if empty
        const resultsEl = document.getElementById('geminiPulseResults');
        if (resultsEl && (!resultsEl.children || resultsEl.children.length === 0)) {
            generateGeminiPulse();
        }
    } else {
        if (calendarView) calendarView.style.display = 'block';
        if (geminiView) geminiView.style.display = 'none';
        if (tabCalBtn) {
            tabCalBtn.classList.add('active');
            tabCalBtn.setAttribute('aria-selected', 'true');
        }
        if (tabGeminiBtn) {
            tabGeminiBtn.classList.remove('active');
            tabGeminiBtn.setAttribute('aria-selected', 'false');
        }
        if (titleEl) {
            titleEl.textContent = 'Philippine Market and Seasonal Demand Recommendations';
        }
    }
};

let currentPulsePreset = 'auto';

window.handleDateOrRegionChange = function() {
    currentPulsePreset = 'auto';
    generateGeminiPulse();
};

window.selectPulsePreset = function(presetKey, btnEl) {
    currentPulsePreset = presetKey;
    document.querySelectorAll('.ph-preset-btn').forEach(function(b) {
        b.classList.remove('active');
    });
    if (btnEl) btnEl.classList.add('active');
    const promptInput = document.getElementById('phPulseCustomPrompt');
    if (promptInput) promptInput.value = '';
    generateGeminiPulse();
};

function updateActivePresetButton(activeDayType) {
    if (!activeDayType) return;
    const presetBtns = document.querySelectorAll('.ph-preset-btn');
    let matchedBtn = null;
    presetBtns.forEach(function(btn) {
        btn.classList.remove('active');
        const p = btn.getAttribute('data-preset');
        if (p === activeDayType || 
            (activeDayType === 'friday_night' && p === 'tgif') ||
            (activeDayType === 'saturday_haul' && p === 'weekend_groceries') ||
            (activeDayType === 'sunday_family' && p === 'sunday_salusalo') ||
            (activeDayType === 'rainy_typhoon' && p === 'rainy_comfort') ||
            (activeDayType === 'summer_heat' && p === 'summer_heat') ||
            (activeDayType === 'holiday_undas' && p === 'undas') ||
            (activeDayType === 'regular_weekday' && p === 'back_to_school')) {
            matchedBtn = btn;
        }
    });
    if (matchedBtn) {
        matchedBtn.classList.add('active');
    }
}

function escapePulseHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

window.generateGeminiPulse = function() {
    const loadingEl = document.getElementById('geminiPulseLoading');
    const resultsEl = document.getElementById('geminiPulseResults');
    const genBtn = document.getElementById('phPulseGenerateBtn');
    const dateInput = document.getElementById('phPulseDateInput');
    const regionSelect = document.getElementById('phPulseRegionSelect');
    const promptInput = document.getElementById('phPulseCustomPrompt');

    const dateVal = dateInput ? dateInput.value : '';
    const regionVal = regionSelect ? regionSelect.value : 'Philippines (Nationwide)';
    const customPrompt = promptInput ? promptInput.value.trim() : '';

    if (loadingEl) loadingEl.style.display = 'flex';
    if (resultsEl) resultsEl.style.display = 'none';
    if (genBtn) {
        genBtn.disabled = true;
        genBtn.style.opacity = '0.6';
    }

    const payload = {
        date: dateVal,
        event: currentPulsePreset,
        region: regionVal,
        prompt: customPrompt
    };

    fetch('api/gemini_ph_market_pulse.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(payload)
    })
    .then(function(res) {
        return res.json();
    })
    .then(function(data) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (resultsEl) resultsEl.style.display = 'block';
        if (genBtn) {
            genBtn.disabled = false;
            genBtn.style.opacity = '1';
        }

        if (!data || !data.success) {
            resultsEl.innerHTML = '<div style="text-align:center;padding:30px;color:#f87171;">Failed to generate insights: ' + (data && data.message ? escapePulseHtml(data.message) : 'Unknown error') + '</div>';
            return;
        }

        renderGeminiPulseResults(data);
    })
    .catch(function(err) {
        if (loadingEl) loadingEl.style.display = 'none';
        if (resultsEl) resultsEl.style.display = 'block';
        if (genBtn) {
            genBtn.disabled = false;
            genBtn.style.opacity = '1';
        }
        resultsEl.innerHTML = '<div style="text-align:center;padding:30px;color:#f87171;">Network or server error while contacting Gemini AI service. Please try again.</div>';
    });
};

function toFormalCase(str) {
    if (!str) return '';
    return String(str).replace(/\b\w+/g, function(txt) {
        return txt.charAt(0).toUpperCase() + txt.slice(1);
    });
}

function renderGeminiPulseResults(data) {
    const resultsEl = document.getElementById('geminiPulseResults');
    if (!resultsEl) return;

    // Dynamically update active preset button from backend classification
    if (data && data.day_type) {
        updateActivePresetButton(data.day_type);
    }

    const briefing = data.briefing || {};
    const wants = data.top_consumer_wants || data.recommended_wants || [];
    const isLive = Boolean(data.is_ai_live);
    const model = data.model || 'Gemini';
    const eventName = toFormalCase(data.day_type_label || data.target_event || data.primary_driver || 'Daily Market Demand');
    const region = data.region || 'Philippines';
    const dateStr = data.target_date || data.query_date || '';
    const headline = data.primary_driver || (briefing && briefing.headline) || eventName;
    const sentimentSummary = data.consumer_sentiment_summary || (briefing && briefing.cultural_driver) || '';
    const spendingMood = data.spending_power_level || (briefing && briefing.spending_mood) || '';

    // Standardized line icons (Feather / Lucide set)
    const flameSvg = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 3z"></path></svg>`;
    const creditCardSvg = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>`;
    const lightbulbSvg = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M9 18h6"></path><path d="M10 22h4"></path><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .5 2.5 1.5 3.5.76.76 1.23 1.52 1.41 2.5"></path></svg>`;
    const sparkSvg = `<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.75;flex-shrink:0;"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8l-6.2 4.5 2.4-7.4L2 9.4h7.6z"/></svg>`;

    // Format date and location into one muted text line with full month word: "October 10, 2026 · Nationwide"
    let formattedDate = dateStr;
    if (dateStr && /^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        const parts = dateStr.split('-');
        const dObj = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        if (!isNaN(dObj.getTime())) {
            formattedDate = dObj.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
        }
    } else if (dateStr) {
        formattedDate = dateStr.replace(/\bJan\b/i, 'January')
                               .replace(/\bFeb\b/i, 'February')
                               .replace(/\bMar\b/i, 'March')
                               .replace(/\bApr\b/i, 'April')
                               .replace(/\bMay\b/i, 'May')
                               .replace(/\bJun\b/i, 'June')
                               .replace(/\bJul\b/i, 'July')
                               .replace(/\bAug\b/i, 'August')
                               .replace(/\bSep\b/i, 'September')
                               .replace(/\bOct\b/i, 'October')
                               .replace(/\bNov\b/i, 'November')
                               .replace(/\bDec\b/i, 'December');
    }
    let regionClean = (region || 'Nationwide')
        .replace(/^PHILIPPINES\s*\(NATIONWIDE\)$/i, 'Nationwide')
        .replace(/^PHILIPPINES$/i, 'Nationwide');
    if (regionClean === regionClean.toUpperCase()) {
        regionClean = regionClean.toLowerCase().replace(/\b\w/g, function(c) { return c.toUpperCase(); });
    }
    const dateLocationLine = `${escapePulseHtml(formattedDate)}${regionClean ? ' &middot; ' + escapePulseHtml(regionClean) : ''}`;

    // Updated timestamp for footer (e.g., "8:51 pm")
    const now = new Date();
    const updatedTimeFormatted = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }).toLowerCase();

    // Trending Categories chips with SVG line icon
    let cravingsHtml = '';
    if (data.trending_categories && Array.isArray(data.trending_categories)) {
        data.trending_categories.forEach(function(item) {
            const catName = typeof item === 'string' ? item : (item.name || '');
            const lift = (typeof item === 'object' && item.lift_pct) ? ' (' + item.lift_pct + ')' : '';
            if (catName) {
                cravingsHtml += '<span class="ph-trend-chip">' + flameSvg + '<strong>' + escapePulseHtml(catName) + '</strong>' + escapePulseHtml(lift) + '</span>';
            }
        });
    } else if (briefing.trending_cravings && Array.isArray(briefing.trending_cravings)) {
        briefing.trending_cravings.forEach(function(item) {
            cravingsHtml += '<span class="ph-trend-chip">' + flameSvg + '<strong>' + escapePulseHtml(item) + '</strong></span>';
        });
    }

    // Briefing card HTML (no model badge in hero card, merged date & location, subtle footer)
    const briefingCardHtml = `
        <div class="ph-briefing-card">
            <div class="ph-briefing-meta">
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                    <span class="ph-briefing-pill">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                        </svg>
                        <span>${escapePulseHtml(eventName)}</span>
                    </span>
                    <span class="ph-briefing-subline">${dateLocationLine}</span>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    ${spendingMood ? '<span class="ph-spending-badge">' + creditCardSvg + ' <span>' + escapePulseHtml(toFormalCase(spendingMood)) + '</span></span>' : ''}
                </div>
            </div>
            <h4 style="font-size:17px;font-weight:700;color:var(--insights-heading,#FFFFFF);margin:0 0 8px 0;line-height:1.35;">
                ${escapePulseHtml(headline)}
            </h4>
            <p style="font-size:13px;line-height:1.55;color:var(--insights-text-muted,#9aa6d1);margin:0;">
                ${escapePulseHtml(sentimentSummary)}
            </p>
            ${cravingsHtml ? '<div class="ph-trending-chips"><span class="ph-trending-label">Surging Product Categories:</span> ' + cravingsHtml + '</div>' : ''}
            <div class="ph-briefing-footer">
                ${sparkSvg}
                <span>Powered by Gemini &middot; updated ${updatedTimeFormatted}</span>
            </div>
        </div>
    `;

    // Recommended products grid
    let cardsHtml = '';
    wants.forEach(function(want) {
        const inCatalog = Boolean(want.matched_in_stock || want.product_id);
        const stockUnits = parseInt(want.current_stock || 0, 10);
        const reorderPoint = parseInt(want.reorder_point || 0, 10);
        const pName = want.product_name;
        const pCat = want.category || 'Retail Goods';
        const pId = want.product_id || '';
        const rawSurge = want.demand_surge || want.surge_factor || '+50% Surge';
        const stockStatus = want.stock_status || (inCatalog ? (stockUnits <= 0 ? 'out_of_stock' : (reorderPoint > 0 && stockUnits <= reorderPoint ? 'low_stock' : 'in_stock')) : 'opportunity');

        // Parse surge for big number display in top-right with formal title case
        let surgeDisplay = String(rawSurge).trim();
        let surgeNum = surgeDisplay;
        let surgeCap = 'Demand Surge';
        const surgeMatch = surgeDisplay.match(/^([+]?\d+%?)(.*)$/i);
        if (surgeMatch) {
            surgeNum = surgeMatch[1];
            if (!surgeNum.includes('%')) surgeNum += '%';
            if (!surgeNum.startsWith('+')) surgeNum = '+' + surgeNum;
            const rest = surgeMatch[2].trim();
            if (rest) {
                surgeCap = toFormalCase(rest);
            } else {
                surgeCap = 'Demand Surge';
            }
        }

        // Neutral gray chips
        const categoryChip = `
            <span class="ph-meta-chip category-chip">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="16.5" y1="9.4" x2="7.5" y2="4.21"></line>
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                <span>${escapePulseHtml(pCat)}</span>
            </span>`;

        const buyerChip = want.target_buyer ? `
            <span class="ph-meta-chip buyer-chip">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>${escapePulseHtml(want.target_buyer)}</span>
            </span>` : '';

        // Merchandising Tip (SVG line icon)
        const tipText = want.retail_merchandising_tip || want.merchandising_tip || '';
        const tipHtml = tipText ? `
            <div class="ph-want-tip">
                <span class="ph-tip-icon" aria-hidden="true">${lightbulbSvg}</span>
                <div class="ph-tip-text">${escapePulseHtml(tipText)}</div>
            </div>` : '';

        // Fully Dynamic Stock status with DB reorder point threshold (formal title case)
        let stockStatusKey = 'stock-opp';
        let dotKey = 'blue';
        let statusBadgeHtml = '';

        if (stockStatus === 'out_of_stock' || (inCatalog && stockUnits <= 0)) {
            stockStatusKey = 'stock-out';
            dotKey = 'red';
            statusBadgeHtml = `<span class="ph-status-tag critical"><span class="ph-stock-dot red"></span> Out of Stock (0 units)</span>`;
        } else if (stockStatus === 'low_stock' || (inCatalog && reorderPoint > 0 && stockUnits <= reorderPoint)) {
            stockStatusKey = 'stock-low';
            dotKey = 'amber';
            statusBadgeHtml = `<span class="ph-status-tag low"><span class="ph-stock-dot amber"></span> Low Stock (${stockUnits} units &middot; Min: ${reorderPoint})</span>`;
        } else if (stockStatus === 'in_stock' || inCatalog) {
            stockStatusKey = 'stock-in';
            dotKey = 'green';
            statusBadgeHtml = `<span class="ph-status-tag in-stock"><span class="ph-stock-dot green"></span> In Stock (${stockUnits} units)</span>`;
        } else {
            stockStatusKey = 'stock-opp';
            dotKey = 'blue';
            statusBadgeHtml = `<span class="ph-status-tag opportunity"><span class="ph-stock-dot blue"></span> Market Opportunity</span>`;
        }

        const stockNoteHtml = want.stock_advice ? `
            <div class="ph-stock-note ${stockStatusKey}">
                <span class="ph-stock-dot ${dotKey}"></span>
                <div class="ph-stock-note-text">${escapePulseHtml(want.stock_advice)}</div>
            </div>` : '';

        // Action button (formal Title Case)
        let actionBtnHtml = '';
        if (inCatalog) {
            actionBtnHtml = `
                <button type="button" class="btn-table-check check-stock-btn"
                        data-product="${escapePulseHtml(pName)}"
                        data-category="${escapePulseHtml(pCat)}"
                        data-id="${escapePulseHtml(pId)}"
                        onclick="handleCheckStockClick(event, this)">
                    Check Stock &rarr;
                </button>
            `;
        } else {
            actionBtnHtml = `
                <a href="products.php?add=1&new_item=${encodeURIComponent(want.product_name)}" class="btn-table-onboard" title="Add product to warehouse inventory">
                    + Onboard Item
                </a>
            `;
        }

        cardsHtml += `
            <div class="ph-want-card">
                <div class="ph-want-card-body">
                    <div class="ph-want-header-row">
                        <div class="ph-want-title">${escapePulseHtml(want.product_name)}</div>
                        <div class="ph-want-surge-stat">
                            <span class="ph-surge-number">${escapePulseHtml(surgeNum)}</span>
                            <span class="ph-surge-caption">${escapePulseHtml(surgeCap)}</span>
                        </div>
                    </div>
                    <div class="ph-want-meta-row">
                        ${categoryChip}
                        ${buyerChip}
                    </div>
                    <div class="ph-want-reason">
                        <span class="ph-reason-tag">Why Filipinos Want This Today</span>
                        <p class="ph-reason-text">${escapePulseHtml(want.why_people_want_this || '')}</p>
                    </div>
                </div>

                <div class="ph-want-card-bottom">
                    ${tipHtml}
                    ${stockNoteHtml}
                    <div class="ph-want-footer">
                        ${statusBadgeHtml}
                        ${actionBtnHtml}
                    </div>
                </div>
            </div>
        `;
    });

    resultsEl.innerHTML = briefingCardHtml + `
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
            <div style="font-size:14px;font-weight:700;color:var(--insights-heading,#FFFFFF);display:flex;align-items:center;gap:8px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="color:#51D55A;flex-shrink:0;">
                    <circle cx="12" cy="12" r="10"></circle>
                    <circle cx="12" cy="12" r="6"></circle>
                    <circle cx="12" cy="12" r="2"></circle>
                </svg>
                <span>What Filipinos Are Buying &amp; Craving Right Now</span>
                <span style="font-size:11px;font-weight:600;color:var(--insights-text-muted,#9aa6d1);">(${wants.length} Consumer Wants Analyzed)</span>
            </div>
            <div style="font-size:11.5px;color:var(--insights-text-muted,#9aa6d1);">
                Cross-referenced with warehouse inventory
            </div>
        </div>
        <div class="ph-wants-grid">
            ${cardsHtml}
        </div>
    `;
}

if (typeof window.checkStock !== 'function') {
    window.checkStock = function(productName, categoryName, productId) {
        var params = new URLSearchParams();
        if (productId) params.set('product_id', productId);
        if (categoryName) params.set('category', categoryName);
        if (productName) params.set('q', productName);
        params.set('ref', 'insights');
        window.location.href = 'transactions.php?' + params.toString();
    };
}

if (typeof window.handleCheckStockClick !== 'function') {
    window.handleCheckStockClick = function(e, btn) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        var button = btn || (e ? e.target.closest('.check-stock-btn, .btn-table-check') : null);
        if (!button) return;
        var pName = button.getAttribute('data-product') || button.dataset.product || '';
        var cName = button.getAttribute('data-category') || button.dataset.category || '';
        var pId   = button.getAttribute('data-id') || button.dataset.id || '';
        window.checkStock(pName, cName, pId);
    };
}
</script>
