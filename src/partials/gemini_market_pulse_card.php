<?php
/**
 * src/partials/gemini_market_pulse_card.php
 *
 * Real-Time Philippine Market Pulse & Consumer Demand Intelligence Card
 * Powered by Google Gemini AI (gemini-3.5-flash-lite) with Live Warehouse Inventory Sync.
 */
?>
<style>
/* ══════════════════════════════════════════════════════════════
   Gemini AI Philippine Market Pulse Dashboard Card
   ══════════════════════════════════════════════════════════════ */
.gemini-pulse-card {
    background: linear-gradient(135deg, rgba(16, 34, 82, 0.95) 0%, rgba(10, 22, 56, 0.98) 100%);
    border: 1.5px solid rgba(81, 213, 90, 0.42);
    border-radius: 18px;
    padding: 24px 26px;
    margin-bottom: 24px;
    box-shadow: 0 14px 40px rgba(0, 0, 0, 0.35), inset 0 1px 0 rgba(255, 255, 255, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.25s ease;
}
[data-theme="light"] .gemini-pulse-card {
    background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
    border: 1.5px solid rgba(81, 213, 90, 0.55);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.06);
}
.gemini-pulse-card .gemini-ambient-glow {
    position: absolute;
    top: -60px;
    right: -40px;
    width: 280px;
    height: 280px;
    background: radial-gradient(circle, rgba(81, 213, 90, 0.12) 0%, rgba(0, 0, 0, 0) 70%);
    pointer-events: none;
}
.gemini-pulse-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 14px;
    margin-bottom: 18px;
    position: relative;
    z-index: 2;
}
.gemini-pulse-title-area {
    display: flex;
    align-items: center;
    gap: 12px;
}
.gemini-ai-icon-badge {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: linear-gradient(135deg, rgba(81, 213, 90, 0.22) 0%, rgba(59, 130, 246, 0.2) 100%);
    border: 1.5px solid rgba(81, 213, 90, 0.45);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #51D55A;
    box-shadow: 0 0 16px rgba(81, 213, 90, 0.3);
    flex-shrink: 0;
}
.gemini-pulse-title {
    font-size: 17.5px;
    font-weight: 700;
    font-family: 'Onest', sans-serif;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 8px;
    letter-spacing: -0.01em;
}
[data-theme="light"] .gemini-pulse-title {
    color: #0f172a;
}
.gemini-pulse-sub {
    font-size: 12.5px;
    color: var(--insights-text-muted);
    margin-top: 3px;
    font-family: 'Onest', sans-serif;
}
.gemini-header-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
.gemini-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 11.5px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    background: rgba(81, 213, 90, 0.12);
    border: 1px solid rgba(81, 213, 90, 0.38);
    color: #51D55A;
    transition: all 0.25s ease;
}
.gemini-status-badge.is-loading {
    background: rgba(245, 158, 11, 0.12);
    border-color: rgba(245, 158, 11, 0.38);
    color: #f59e0b;
}
.gemini-pulse-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #51D55A;
    box-shadow: 0 0 8px #51D55A;
    animation: geminiDotPulse 1.6s infinite ease-in-out;
}
.gemini-status-badge.is-loading .gemini-pulse-dot {
    background: #f59e0b;
    box-shadow: 0 0 8px #f59e0b;
}
@keyframes geminiDotPulse {
    0%, 100% { transform: scale(0.9); opacity: 0.7; }
    50% { transform: scale(1.3); opacity: 1; box-shadow: 0 0 12px currentColor; }
}
.gemini-btn-sync {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    background: rgba(81, 213, 90, 0.14);
    border: 1px solid rgba(81, 213, 90, 0.35);
    color: #51D55A;
    cursor: pointer;
    transition: all 0.2s ease;
}
.gemini-btn-sync:hover {
    background: rgba(81, 213, 90, 0.25);
    border-color: #51D55A;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(81, 213, 90, 0.2);
}
.gemini-btn-sync.is-spinning svg {
    animation: geminiSpin 0.75s linear infinite;
}
@keyframes geminiSpin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
.gemini-btn-modal-open {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 13px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    background: rgba(29, 56, 123, 0.5);
    border: 1px solid rgba(42, 68, 132, 0.6);
    color: #E3E3E3;
    cursor: pointer;
    transition: all 0.2s ease;
}
[data-theme="light"] .gemini-btn-modal-open {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #334155;
}
.gemini-btn-modal-open:hover {
    background: rgba(42, 68, 132, 0.7);
    color: #ffffff;
    border-color: rgba(81, 213, 90, 0.45);
}

/* Preset buttons row */
.gemini-preset-row {
    display: flex;
    align-items: center;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 12px;
    margin-bottom: 16px;
    border-bottom: 1px solid rgba(42, 68, 132, 0.4);
    scrollbar-width: none;
    position: relative;
    z-index: 2;
}
.gemini-preset-row::-webkit-scrollbar { display: none; }
.gemini-preset-chip {
    padding: 6px 13px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    font-family: 'Onest', sans-serif;
    color: var(--insights-text-muted);
    background: rgba(29, 56, 123, 0.45);
    border: 1px solid rgba(42, 68, 132, 0.5);
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
[data-theme="light"] .gemini-preset-chip {
    background: #f1f5f9;
    border-color: #cbd5e1;
    color: #475569;
}
.gemini-preset-chip:hover {
    color: #ffffff;
    border-color: rgba(81, 213, 90, 0.6);
    background: rgba(81, 213, 90, 0.12);
}
[data-theme="light"] .gemini-preset-chip:hover {
    color: #0f172a;
    background: #e2e8f0;
}
.gemini-preset-chip.active {
    background: linear-gradient(135deg, rgba(81, 213, 90, 0.22) 0%, rgba(16, 185, 129, 0.16) 100%);
    border-color: #51D55A;
    color: #51D55A;
    font-weight: 700;
    box-shadow: 0 0 12px rgba(81, 213, 90, 0.2);
}

/* Sentiment Bar */
.gemini-sentiment-box {
    background: rgba(14, 28, 68, 0.65);
    border: 1px solid rgba(42, 68, 132, 0.5);
    border-radius: 14px;
    padding: 16px 20px;
    margin-bottom: 20px;
    position: relative;
    z-index: 2;
    transition: all 0.25s ease;
}
[data-theme="light"] .gemini-sentiment-box {
    background: #f8fafc;
    border-color: #e2e8f0;
}
.gemini-sentiment-headline {
    font-size: 15px;
    font-weight: 700;
    font-family: 'Onest', sans-serif;
    color: #ffffff;
    margin-bottom: 5px;
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}
[data-theme="light"] .gemini-sentiment-headline {
    color: #0f172a;
}
.gemini-spending-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(59, 130, 246, 0.16);
    border: 1px solid rgba(59, 130, 246, 0.4);
    color: #60a5fa;
}
.gemini-sentiment-text {
    font-size: 13px;
    line-height: 1.55;
    color: #b2bede;
    margin-bottom: 12px;
    font-family: 'Onest', sans-serif;
}
[data-theme="light"] .gemini-sentiment-text {
    color: #475569;
}
.gemini-trending-chips {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.gemini-trending-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 8px;
    font-size: 11.5px;
    font-weight: 600;
    background: rgba(245, 158, 11, 0.12);
    border: 1px solid rgba(245, 158, 11, 0.35);
    color: #f59e0b;
}

/* Wants Grid */
.gemini-wants-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    position: relative;
    z-index: 2;
}
@media (max-width: 1024px) {
    .gemini-wants-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .gemini-wants-grid { grid-template-columns: 1fr; }
}
.gemini-want-card {
    background: rgba(14, 28, 68, 0.7);
    border: 1px solid rgba(42, 68, 132, 0.55);
    border-radius: 14px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.22s ease;
}
[data-theme="light"] .gemini-want-card {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 4px 14px rgba(0,0,0,0.04);
}
.gemini-want-card:hover {
    transform: translateY(-3px);
    border-color: rgba(81, 213, 90, 0.6);
    box-shadow: 0 8px 24px rgba(0,0,0,0.25);
}
[data-theme="light"] .gemini-want-card:hover {
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
}
.gemini-want-top {
    margin-bottom: 10px;
}
.gemini-want-title {
    font-size: 14px;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.35;
    margin-bottom: 4px;
}
[data-theme="light"] .gemini-want-title {
    color: #0f172a;
}
.gemini-want-category {
    font-size: 11.5px;
    color: var(--insights-text-muted);
}
.gemini-surge-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(81, 213, 90, 0.15);
    color: #51D55A;
    border: 1px solid rgba(81, 213, 90, 0.35);
}
.gemini-stock-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 9px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    margin-top: 8px;
}
.gemini-stock-pill.out-of-stock {
    background: rgba(239, 68, 68, 0.15);
    color: #f87171;
    border: 1px solid rgba(239, 68, 68, 0.35);
}
.gemini-stock-pill.low-stock {
    background: rgba(245, 158, 11, 0.15);
    color: #fbbf24;
    border: 1px solid rgba(245, 158, 11, 0.35);
}
.gemini-stock-pill.in-stock {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.35);
}
.gemini-stock-pill.opportunity {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.35);
}
.gemini-want-body {
    font-size: 12px;
    color: #cbd5e1;
    line-height: 1.45;
    margin: 8px 0 12px;
    font-family: 'Onest', sans-serif;
}
[data-theme="light"] .gemini-want-body {
    color: #475569;
}
.gemini-want-tip {
    font-size: 11.5px;
    color: #9aa6d1;
    background: rgba(29, 56, 123, 0.3);
    border-left: 3px solid #51D55A;
    padding: 6px 9px;
    border-radius: 0 6px 6px 0;
    margin-bottom: 12px;
    line-height: 1.4;
    font-family: 'Onest', sans-serif;
}
[data-theme="light"] .gemini-want-tip {
    background: #f1f5f9;
    color: #334155;
}
.gemini-want-action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid rgba(42, 68, 132, 0.35);
    padding-top: 10px;
    margin-top: auto;
}
[data-theme="light"] .gemini-want-action-bar {
    border-top-color: #e2e8f0;
}

/* Shimmer Loading Skeleton */
.gemini-skeleton-holder {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
}
@media (max-width: 1024px) {
    .gemini-skeleton-holder { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 640px) {
    .gemini-skeleton-holder { grid-template-columns: 1fr; }
}
.gemini-skeleton-box {
    height: 180px;
    border-radius: 14px;
    background: linear-gradient(90deg, rgba(29, 56, 123, 0.3) 0%, rgba(81, 213, 90, 0.1) 50%, rgba(29, 56, 123, 0.3) 100%);
    background-size: 200% 100%;
    animation: geminiShimmer 1.5s infinite;
    border: 1px solid rgba(42, 68, 132, 0.3);
}
[data-theme="light"] .gemini-skeleton-box {
    background: linear-gradient(90deg, #f1f5f9 0%, #e2e8f0 50%, #f1f5f9 100%);
    background-size: 200% 100%;
}
@keyframes geminiShimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
</style>

<div class="gemini-pulse-card" id="geminiMarketPulseDashboardCard">
    <div class="gemini-ambient-glow"></div>

    <div class="gemini-pulse-header">
        <div class="gemini-pulse-title-area">
            <div class="gemini-ai-icon-badge">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/>
                </svg>
            </div>
            <div>
                <div class="gemini-pulse-title">
                    <span>Gemini AI Philippine Market Pulse</span>
                </div>
                <div class="gemini-pulse-sub">
                    Real-time consumer purchasing intent & Pinoy cravings cross-referenced with live warehouse inventory.
                </div>
            </div>
        </div>

        <div class="gemini-header-actions">
            <div id="dashGeminiStatusPill" class="gemini-status-badge">
                <span class="gemini-pulse-dot"></span>
                <span id="dashGeminiStatusText">⚡ Gemini AI Sync: gemini-3.5-flash-lite</span>
            </div>

            <button type="button" id="btnRefreshDashPulse" class="gemini-btn-sync" onclick="loadDashboardGeminiPulse(currentDashPreset, true)" title="Re-query Google Gemini with live warehouse inventory">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/>
                    <path d="M21 3v5h-5"/>
                </svg>
                <span>Sync Live Pulse</span>
            </button>

            <button type="button" class="gemini-btn-modal-open" onclick="openPhilippineSeasonalModal('monthly', currentModalMonth)" title="Explore full 12-month Philippine seasonal calendar">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                <span>Seasonal Guide</span>
            </button>
        </div>
    </div>

    <!-- Quick Scenario Presets -->
    <div class="gemini-preset-row">
        <button type="button" class="gemini-preset-chip active" data-preset="auto" onclick="switchDashboardPulsePreset('auto', this)">
            <span>⚡ Today's Pulse</span>
        </button>
        <button type="button" class="gemini-preset-chip" data-preset="payday" onclick="switchDashboardPulsePreset('payday', this)">
            <span>💰 15th/30th Payday</span>
        </button>
        <button type="button" class="gemini-preset-chip" data-preset="tgif" onclick="switchDashboardPulsePreset('tgif', this)">
            <span>🍻 Friday TGIF</span>
        </button>
        <button type="button" class="gemini-preset-chip" data-preset="weekend_groceries" onclick="switchDashboardPulsePreset('weekend_groceries', this)">
            <span>🛒 Saturday Haul</span>
        </button>
        <button type="button" class="gemini-preset-chip" data-preset="sunday_salusalo" onclick="switchDashboardPulsePreset('sunday_salusalo', this)">
            <span>👨‍👩‍👧‍👦 Sunday Salu-Salo</span>
        </button>
        <button type="button" class="gemini-preset-chip" data-preset="rainy_comfort" onclick="switchDashboardPulsePreset('rainy_comfort', this)">
            <span>🌧️ Tag-ulan / Rainy</span>
        </button>
        <button type="button" class="gemini-preset-chip" data-preset="summer_heat" onclick="switchDashboardPulsePreset('summer_heat', this)">
            <span>☀️ Tag-init / Summer</span>
        </button>
    </div>

    <!-- Shimmer Skeleton -->
    <div id="dashPulseLoadingSkeleton" style="display: none;" class="gemini-skeleton-holder">
        <div class="gemini-skeleton-box"></div>
        <div class="gemini-skeleton-box"></div>
        <div class="gemini-skeleton-box"></div>
    </div>

    <!-- Results Container -->
    <div id="dashPulseContentArea">
        <div class="gemini-sentiment-box">
            <div class="gemini-sentiment-headline">
                <span id="dashPulseDriverHeadline">Analyzing daily purchasing intent...</span>
                <span id="dashPulseSpendingPill" class="gemini-spending-pill">Moderate</span>
            </div>
            <div id="dashPulseSentimentText" class="gemini-sentiment-text">
                Consulting Google Gemini AI for consumer buying psychology, payday liquidity, and active catalog matches.
            </div>
            <div class="gemini-trending-chips" id="dashPulseTrendingChips"></div>
        </div>

        <div class="gemini-wants-grid" id="dashPulseWantsGrid"></div>
    </div>
</div>

<script>
let currentDashPreset = 'auto';

function switchDashboardPulsePreset(presetKey, btnEl) {
    currentDashPreset = presetKey;
    document.querySelectorAll('.gemini-preset-chip').forEach(b => b.classList.remove('active'));
    if (btnEl) btnEl.classList.add('active');
    loadDashboardGeminiPulse(presetKey, true);
}

function escapePulseDashHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function loadDashboardGeminiPulse(preset, forceRefresh) {
    preset = preset || 'auto';
    const skeleton = document.getElementById('dashPulseLoadingSkeleton');
    const content = document.getElementById('dashPulseContentArea');
    const syncBtn = document.getElementById('btnRefreshDashPulse');
    const statusPill = document.getElementById('dashGeminiStatusPill');
    const statusText = document.getElementById('dashGeminiStatusText');

    const cacheKey = 'intelliware_gemini_pulse_' + preset;
    if (!forceRefresh) {
        try {
            const cached = sessionStorage.getItem(cacheKey);
            if (cached) {
                const parsed = JSON.parse(cached);
                if (parsed && parsed.data && (Date.now() - parsed.timestamp < 300000)) { // 5 min TTL
                    renderDashboardPulseData(parsed.data);
                    return;
                }
            }
        } catch (e) {}
    }

    // UI Loading state
    if (skeleton) skeleton.style.display = 'grid';
    if (content) content.style.opacity = '0.35';
    if (syncBtn) syncBtn.classList.add('is-spinning');
    if (statusPill) statusPill.classList.add('is-loading');
    if (statusText) statusText.textContent = 'Consulting Gemini AI & Live DB...';

    fetch('api/gemini_ph_market_pulse.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            event: preset,
            region: 'Metro Manila / Philippines'
        })
    })
    .then(res => {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    })
    .then(data => {
        if (!data || !data.success) {
            throw new Error(data.error || 'Failed to fetch Gemini pulse');
        }

        try {
            sessionStorage.setItem(cacheKey, JSON.stringify({
                timestamp: Date.now(),
                data: data
            }));
        } catch (e) {}

        renderDashboardPulseData(data);
    })
    .catch(err => {
        console.error('[GeminiPulse] Fetch error:', err);
        if (statusText) statusText.textContent = 'Sync fallback active (Live DB)';
    })
    .finally(() => {
        if (skeleton) skeleton.style.display = 'none';
        if (content) content.style.opacity = '1';
        if (syncBtn) syncBtn.classList.remove('is-spinning');
        if (statusPill) statusPill.classList.remove('is-loading');
    });
}

function renderDashboardPulseData(data) {
    const headlineEl = document.getElementById('dashPulseDriverHeadline');
    const spendingPill = document.getElementById('dashPulseSpendingPill');
    const sentimentText = document.getElementById('dashPulseSentimentText');
    const chipsContainer = document.getElementById('dashPulseTrendingChips');
    const grid = document.getElementById('dashPulseWantsGrid');
    const statusText = document.getElementById('dashGeminiStatusText');

    if (statusText) {
        if (data.is_ai_live) {
            statusText.textContent = '⚡ Gemini AI Active (' + (data.model || 'gemini-3.5-flash-lite') + ')';
        } else {
            statusText.textContent = '⚡ Market Pulse (Live DB Sync)';
        }
    }

    if (headlineEl) {
        headlineEl.textContent = data.primary_driver || data.day_type_label || 'Daily Consumer Wants';
    }

    if (spendingPill) {
        spendingPill.textContent = data.spending_power_level || 'Moderate Liquidity';
    }

    if (sentimentText) {
        sentimentText.textContent = data.consumer_sentiment_summary || 'Filipino households are balancing grocery essentials and high-crave comfort foods.';
    }

    // Render Trending Category Chips
    if (chipsContainer) {
        let chipsHtml = '';
        const cats = Array.isArray(data.trending_categories) ? data.trending_categories : [];
        cats.forEach(c => {
            const name = typeof c === 'string' ? c : (c.name || '');
            const lift = (c && c.lift_pct) ? c.lift_pct : '+55% Lift';
            if (name) {
                chipsHtml += `
                    <span class="gemini-trending-chip">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 23c6.075 0 11-4.925 11-11 0-4.63-2.87-8.58-6.94-10.19-.51-.2-.84.44-.45.82 2.37 2.31 2.39 6.07.03 8.41-1.89 1.87-4.96 1.87-6.85 0-1.12-1.11-1.63-2.61-1.53-4.09.02-.37-.42-.58-.67-.3C4.1 9.3 2.92 12.5 3.35 15.86 3.96 20.61 8.27 23 12 23z"/>
                        </svg>
                        <span>${escapePulseDashHtml(name)}</span>
                        <strong style="color:#51D55A;">${escapePulseDashHtml(lift)}</strong>
                    </span>
                `;
            }
        });
        chipsContainer.innerHTML = chipsHtml;
    }

    // Render Top Wants Cards
    if (grid) {
        let cardsHtml = '';
        const wants = Array.isArray(data.top_consumer_wants) ? data.top_consumer_wants : [];

        wants.forEach(w => {
            const pName = escapePulseDashHtml(w.product_name || 'Consumer Staple');
            const pCat  = escapePulseDashHtml(w.category || 'Retail Goods');
            const surge = escapePulseDashHtml(w.demand_surge || '+65% Surge');
            const buyer = escapePulseDashHtml(w.target_buyer || 'General Shoppers');
            const why   = escapePulseDashHtml(w.why_people_want_this || '');
            const tip   = escapePulseDashHtml(w.retail_merchandising_tip || 'Position prominently near aisle front.');
            const pId   = w.product_id ? Number(w.product_id) : '';

            let stockClass = 'opportunity';
            let stockLabel = 'Market Opportunity: Unstocked SKU';
            if (w.matched_in_stock) {
                const stock = Number(w.current_stock || 0);
                const reorder = Number(w.reorder_point || 10);
                if (stock <= 0) {
                    stockClass = 'out-of-stock';
                    stockLabel = `🔴 Out of Stock: 0 units left (Min: ${reorder}) • Critical Restock`;
                } else if (stock <= reorder) {
                    stockClass = 'low-stock';
                    stockLabel = `🟡 Low Stock: ${stock} units left (Min: ${reorder}) • Restock Immediately`;
                } else {
                    const buffer = stock - reorder;
                    stockClass = 'in-stock';
                    stockLabel = `🟢 In Stock: ${stock} units (+${buffer} buffer) • Prime Front Display`;
                }
            }

            cardsHtml += `
                <div class="gemini-want-card">
                    <div class="gemini-want-top">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:8px; margin-bottom:4px;">
                            <div class="gemini-want-title">${pName}</div>
                            <span class="gemini-surge-badge">${surge}</span>
                        </div>
                        <div class="gemini-want-category">${pCat} &middot; <span style="color:#9aa6d1;">${buyer}</span></div>
                        <div class="gemini-stock-pill ${stockClass}">${stockLabel}</div>
                    </div>

                    <div class="gemini-want-body">
                        ${why}
                    </div>

                    <div class="gemini-want-tip">
                        <strong>Display Tip:</strong> ${tip}
                    </div>

                    <div class="gemini-want-action-bar">
                        <span style="font-size:11px; color:var(--insights-text-muted);">${w.matched_in_stock ? 'Active Catalog' : 'Procurement Opportunity'}</span>
                        <button type="button" class="action-btn check-stock-btn"
                            data-product="${pName}"
                            data-category="${pCat}"
                            data-id="${pId}">
                            Check Stock
                        </button>
                    </div>
                </div>
            `;
        });

        grid.innerHTML = cardsHtml;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    loadDashboardGeminiPulse('auto', false);
});
</script>
