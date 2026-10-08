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
/* Ambient glow pulse on the outer ring */
@keyframes phFabGlowPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(81, 213, 90, 0); }
    50%       { box-shadow: 0 0 0 6px rgba(81, 213, 90, 0.18); }
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
    bottom: 28px !important;
    right: 28px !important;
    z-index: 999990 !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 6px 8px 6px 14px !important;
    border-radius: 999px !important;
    cursor: pointer !important;
    outline: none !important;
    user-select: none !important;
    -webkit-tap-highlight-color: transparent !important;
    /* Glassmorphic dark pill */
    background: linear-gradient(135deg, rgba(10, 22, 55, 0.96) 0%, rgba(14, 30, 72, 0.94) 100%) !important;
    backdrop-filter: blur(18px) saturate(1.6) !important;
    -webkit-backdrop-filter: blur(18px) saturate(1.6) !important;
    /* Gradient border via box-shadow layering */
    border: 1.5px solid rgba(81, 213, 90, 0.45) !important;
    box-shadow:
        0 10px 40px rgba(0, 0, 0, 0.5),
        0 4px 16px rgba(16, 185, 129, 0.22),
        inset 0 1px 0 rgba(255, 255, 255, 0.07) !important;
    /* Smooth spring lift */
    transition:
        transform 0.26s cubic-bezier(0.34, 1.56, 0.64, 1),
        box-shadow 0.26s ease,
        border-color 0.22s ease,
        background 0.22s ease !important;
    /* Slide in on load */
    animation: phFabSlideIn 0.4s cubic-bezier(0.22, 1, 0.36, 1) both !important;
}
#phSeasonalFab:hover {
    transform: translateY(-4px) scale(1.04) !important;
    border-color: rgba(81, 213, 90, 0.8) !important;
    background: linear-gradient(135deg, rgba(12, 26, 65, 0.98) 0%, rgba(20, 46, 100, 0.97) 100%) !important;
    box-shadow:
        0 16px 48px rgba(0, 0, 0, 0.6),
        0 6px 22px rgba(16, 185, 129, 0.4),
        0 0 0 4px rgba(81, 213, 90, 0.12),
        inset 0 1px 0 rgba(255, 255, 255, 0.1) !important;
}
#phSeasonalFab:active {
    transform: translateY(-1px) scale(0.975) !important;
    box-shadow:
        0 6px 20px rgba(0, 0, 0, 0.4),
        0 2px 10px rgba(16, 185, 129, 0.25) !important;
}
#phSeasonalFab:focus-visible {
    outline: 2px solid #51D55A !important;
    outline-offset: 3px !important;
}

/* ── Left text area ── */
.ph-fab-text-col {
    display: flex;
    flex-direction: column;
    gap: 1px;
}
.ph-fab-title {
    font-family: 'Onest', sans-serif;
    font-size: 12.5px;
    font-weight: 700;
    color: #e8f4ff;
    white-space: nowrap;
    letter-spacing: 0.15px;
    line-height: 1.2;
}
.ph-fab-subtitle {
    font-family: 'Onest', sans-serif;
    font-size: 10px;
    font-weight: 500;
    color: rgba(154, 166, 209, 0.75);
    white-space: nowrap;
    letter-spacing: 0.2px;
    line-height: 1.2;
}

/* ── Month pill badge ── */
.ph-fab-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: rgba(81, 213, 90, 0.14);
    color: #5be469;
    font-family: 'Onest', sans-serif;
    font-size: 10px;
    font-weight: 800;
    padding: 2px 7px;
    border-radius: 20px;
    border: 1px solid rgba(81, 213, 90, 0.32);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    align-self: flex-start;
}
.ph-fab-badge::before {
    content: '';
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #51D55A;
    display: inline-block;
    animation: phFabGlowPulse 2s ease-in-out infinite;
}

/* ── Right icon disc ── */
.ph-fab-icon-disc {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    /* Rich gradient background */
    background: linear-gradient(145deg, #0e9f6e 0%, #047857 50%, #065f46 100%);
    border: 2.5px solid rgba(255, 255, 255, 0.15);
    box-shadow:
        0 0 0 3px rgba(81, 213, 90, 0.22),
        0 4px 14px rgba(5, 150, 105, 0.45),
        inset 0 1px 0 rgba(255, 255, 255, 0.2);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
    overflow: hidden;
}
/* Sheen sweep on hover */
.ph-fab-icon-disc::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50%;
    background: linear-gradient(130deg, rgba(255,255,255,0.22) 0%, transparent 55%);
    opacity: 0;
    transition: opacity 0.3s ease;
}
#phSeasonalFab:hover .ph-fab-icon-disc {
    transform: rotate(22deg) scale(1.1);
    box-shadow:
        0 0 0 4px rgba(81, 213, 90, 0.35),
        0 6px 20px rgba(5, 150, 105, 0.65),
        inset 0 1px 0 rgba(255, 255, 255, 0.25);
}
#phSeasonalFab:hover .ph-fab-icon-disc::after {
    opacity: 1;
}
.ph-fab-icon-disc svg {
    width: 22px;
    height: 22px;
    color: #ffffff;
    flex-shrink: 0;
    filter: drop-shadow(0 1px 3px rgba(0,0,0,0.3));
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
    display: flex; flex-direction: column;
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.65); overflow: hidden;
    transform: translateZ(0); /* Hardware accelerated */
    isolation: isolate;
    animation: phModalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) both;
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
}
[data-theme="light"] .ph-seasonal-modal-header {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.ph-seasonal-modal-body {
    padding: 24px;
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    overscroll-behavior: contain;
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
.table-stock-wrap { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.table-stock-num { font-size: 13px; font-weight: 700; color: var(--insights-heading, #FFFFFF); }
.table-stock-label { font-size: 11px; font-weight: 400; color: var(--insights-text-muted, #9aa6d1); }
.btn-table-check {
    background: transparent;
    border: 1px solid var(--insights-border, rgba(42,68,132,0.6));
    color: var(--insights-text-muted, #9aa6d1); font-family: 'Onest',sans-serif;
    font-size: 11px; font-weight: 600; padding: 4px 10px; border-radius: 6px;
    cursor: pointer; transition: all 0.2s ease; white-space: nowrap;
}
.btn-table-check:hover { color: #51D55A; border-color: #51D55A; background: rgba(81,213,90,0.08); }
.ph-pagination-container {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 16px; padding-top: 16px;
    border-top: 1px solid var(--insights-border-soft, rgba(42,68,132,0.32));
    flex-wrap: wrap; gap: 12px;
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
        <span class="ph-fab-badge"><?php echo htmlspecialchars($_psi_activeProfile['short_name'] ?? date('M')); ?></span>
    </div>

    <!-- Divider -->
    <div class="ph-fab-divider" aria-hidden="true" style="width:1px;height:28px;background:rgba(255,255,255,0.1);flex-shrink:0;"></div>

    <!-- Right: icon disc -->
    <div class="ph-fab-icon-disc" aria-hidden="true">
        <!-- Philippine Sun + analytics spark merged icon -->
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
             stroke-linecap="round" stroke-linejoin="round">
            <!-- 8-ray Philippine sun -->
            <circle cx="12" cy="12" r="3.8" fill="rgba(255,255,255,0.25)" stroke="white" stroke-width="1.6"/>
            <!-- Cardinal rays -->
            <line x1="12" y1="2.2"  x2="12" y2="5"    stroke="white" stroke-width="1.8"/>
            <line x1="12" y1="19"   x2="12" y2="21.8" stroke="white" stroke-width="1.8"/>
            <line x1="2.2" y1="12" x2="5"   y2="12"   stroke="white" stroke-width="1.8"/>
            <line x1="19"  y1="12" x2="21.8" y2="12"  stroke="white" stroke-width="1.8"/>
            <!-- Diagonal rays -->
            <line x1="5.1"  y1="5.1"  x2="7"    y2="7"    stroke="white" stroke-width="1.5"/>
            <line x1="17"   y1="17"   x2="18.9" y2="18.9" stroke="white" stroke-width="1.5"/>
            <line x1="5.1"  y1="18.9" x2="7"    y2="17"   stroke="white" stroke-width="1.5"/>
            <line x1="17"   y1="7"    x2="18.9" y2="5.1"  stroke="white" stroke-width="1.5"/>
            <!-- Analytics spark (trend line) -->
            <polyline points="8.2,14.5 10.2,11.8 12,13.2 14.5,9.8"
                      stroke="rgba(255,255,255,0.9)" stroke-width="1.7" fill="none"/>
        </svg>
    </div>
</div>

<!-- Modal -->
<div id="philippineSeasonalModal" class="ph-seasonal-modal-overlay"
     onclick="handleSeasonalModalBackdrop(event)"
     role="dialog" aria-modal="true" aria-labelledby="phSeasonalModalTitle">
    <div class="ph-seasonal-modal-dialog" onclick="event.stopPropagation()">
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
        <div class="ph-seasonal-modal-body">
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
                <div class="ph-section-label">PROJECTED DEMAND LIFT VS. BASELINE</div>
                <div class="demand-lift-grid">
                    <?php foreach (($_psi_activeProfile['in_demand_categories'] ?? []) as $catName => $mult):
                        $pctLift  = round(($mult - 1.0) * 100);
                        $liftSign = $pctLift >= 0 ? "+{$pctLift}%" : "{$pctLift}%";
                    ?>
                    <div class="metric-lift-card">
                        <div class="metric-lift-label"><?php echo htmlspecialchars($catName); ?></div>
                        <div class="metric-lift-value">
                            <span class="lift-multiplier"><?php echo $mult; ?>x</span>
                            <span class="lift-percentage"><?php echo $liftSign; ?></span>
                        </div>
                    </div>
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
                                <th style="width:34%;">In-Demand Product / Category</th>
                                <th style="width:48%;">Market Intelligence &amp; Recommendation</th>
                                <th style="width:18%;text-align:right;">Stock &amp; Action</th>
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
                                <td style="width:48%;">
                                    <div class="table-intel-sentence"><?php echo formatSafeSeasonalHtml($intelSentence); ?></div>
                                    <?php echo $actionCalloutHtml; ?>
                                </td>
                                <td style="width:18%;text-align:right;">
                                    <div class="table-stock-wrap">
                                        <div class="table-stock-num"><?php echo $stockDisplay; ?></div>
                                        <button type="button" class="btn-table-check check-stock-btn"
                                            data-product="<?php echo htmlspecialchars($item['product_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-category="<?php echo htmlspecialchars($item['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            <?php if (!empty($item['product_id'])): ?>data-id="<?php echo (int)$item['product_id']; ?>"<?php endif; ?>>
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
            </div>
        </div><!-- /.ph-seasonal-modal-body -->
    </div><!-- /.ph-seasonal-modal-dialog -->
</div><!-- /.ph-seasonal-modal-overlay -->
