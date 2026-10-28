(function () {
    'use strict';

    var STORAGE_KEY  = 'iw-theme';
    var OVERRIDE_KEY = 'iw-theme-override';
    var MANUAL_KEY   = 'iw-theme-manual';
    var DEFAULT_THEME = 'dark';

    function setCookie(name, val, days) {
        try {
            var d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + encodeURIComponent(val) + '; path=/; expires=' + d.toUTCString() + '; SameSite=Lax';
        } catch (e) {}
    }

    function deleteCookie(name) {
        try {
            document.cookie = name + '=; path=/; expires=Thu, 01 Jan 1970 00:00:00 GMT; SameSite=Lax';
        } catch (e) {}
    }

    /**
     * Compute current Philippine Time (Asia/Manila, UTC+8) and corresponding theme.
     * Daytime (6:00 AM to 5:59 PM PHT) -> 'light'
     * Nighttime (6:00 PM to 5:59 AM PHT) -> 'dark'
     */
    function getPhilippineTime() {
        try {
            var now = new Date();
            var dFmt = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila',
                hour: 'numeric',
                minute: 'numeric',
                second: 'numeric',
                hour12: false
            });
            var parts = dFmt.formatToParts(now);
            var h = 0, m = 0, s = 0;
            parts.forEach(function (p) {
                if (p.type === 'hour') h = parseInt(p.value, 10);
                if (p.type === 'minute') m = parseInt(p.value, 10);
                if (p.type === 'second') s = parseInt(p.value, 10);
            });
            if (h === 24) h = 0;
            var isDay = (h >= 6 && h < 18);
            var h12 = h % 12 || 12;
            var ampm = (h >= 12) ? 'PM' : 'AM';
            var timeFormatted = h12 + ':' + (m < 10 ? '0' + m : m) + ' ' + ampm;
            return {
                hour: h,
                minute: m,
                second: s,
                isDay: isDay,
                theme: isDay ? 'light' : 'dark',
                timeString: timeFormatted
            };
        } catch (e) {
            var d = new Date();
            var phH = (d.getUTCHours() + 8) % 24;
            var isDayF = (phH >= 6 && phH < 18);
            var h12F = phH % 12 || 12;
            var ampmF = (phH >= 12) ? 'PM' : 'AM';
            var mF = d.getUTCMinutes();
            return {
                hour: phH,
                minute: mF,
                second: d.getUTCSeconds(),
                isDay: isDayF,
                theme: isDayF ? 'light' : 'dark',
                timeString: h12F + ':' + (mF < 10 ? '0' + mF : mF) + ' ' + ampmF
            };
        }
    }

    function getTheme() {
        return document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
    }

    function isManualOverrideActive() {
        var manual = sessionStorage.getItem(MANUAL_KEY) || localStorage.getItem(OVERRIDE_KEY);
        return (manual === 'light' || manual === 'dark');
    }

    function getPreferredTheme() {
        // Respect explicit manual toggle if performed in this session
        var manual = sessionStorage.getItem(MANUAL_KEY) || localStorage.getItem(OVERRIDE_KEY);
        if (manual === 'light' || manual === 'dark') {
            return manual;
        }
        var saved = localStorage.getItem(STORAGE_KEY);
        if (saved === 'light' || saved === 'dark') {
            return saved;
        }
        var currentAttr = document.documentElement.getAttribute('data-theme');
        if (currentAttr === 'light' || currentAttr === 'dark') {
            return currentAttr;
        }
        // Base theme on Philippine Time (6:00 AM - 5:59 PM = Light, 6:00 PM - 5:59 AM = Dark)
        return getPhilippineTime().theme;
    }

    function chartColors() {
        var isLight = getTheme() === 'light';
        return {
            text: isLight ? '#0F172A' : '#E3E3E3',
            muted: isLight ? '#475569' : '#9aa6d1',
            grid: isLight ? 'rgba(148, 163, 184, 0.25)' : 'rgba(42, 68, 132, 0.3)',
            border: isLight ? 'rgba(148, 163, 184, 0.3)' : 'rgba(42, 68, 132, 0.3)',
            pointLabel: isLight ? '#0F172A' : '#E3E3E3'
        };
    }

    function applyChartDefaults() {
        if (typeof Chart === 'undefined') {
            return;
        }

        var colors = chartColors();
        Chart.defaults.color = colors.text;
        Chart.defaults.borderColor = colors.grid;
        Chart.defaults.font.family = "'Onest', sans-serif";
    }

    function updateChartInstance(chart) {
        if (!chart || !chart.options) {
            return;
        }

        var colors = chartColors();
        var scales = chart.options.scales || {};

        Object.keys(scales).forEach(function (key) {
            var scale = scales[key];
            if (!scale) {
                return;
            }

            if (scale.ticks) {
                scale.ticks.color = colors.text;
            }
            if (scale.grid) {
                scale.grid.color = colors.grid;
            }
            if (scale.border) {
                scale.border.color = colors.border;
            }
            if (scale.title) {
                scale.title.color = colors.muted;
            }
            if (scale.pointLabels) {
                scale.pointLabels.color = colors.pointLabel;
            }
            if (scale.angleLines) {
                scale.angleLines.color = colors.grid;
            }
        });

        if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
            chart.options.plugins.legend.labels.color = colors.text;
        }

        chart.update('none');
    }

    function refreshAllCharts() {
        applyChartDefaults();

        if (typeof Chart === 'undefined') {
            return;
        }

        document.querySelectorAll('canvas').forEach(function (canvas) {
            var chart = Chart.getChart(canvas);
            if (chart) {
                updateChartInstance(chart);
            }
        });
    }

    function updateToggleButtonState(theme, isManual) {
        var btn = document.getElementById('themeToggleBtn');
        if (!btn) return;
        var ph = getPhilippineTime();
        var themeLabel = (theme === 'dark') ? 'Dark Mode' : 'Light Mode';
        if (isManual) {
            btn.setAttribute('title', 'Theme: ' + themeLabel + ' (Manual override) • Click to toggle, Double-click to sync with Philippine Time (' + ph.timeString + ')');
            btn.setAttribute('aria-label', 'Theme: ' + themeLabel + ' (Manual override)');
        } else {
            var periodDesc = ph.isDay ? 'Daytime (Light)' : 'Nighttime (Dark)';
            btn.setAttribute('title', 'Theme: ' + themeLabel + ' (Philippine Time: ' + ph.timeString + ' PHT ' + periodDesc + ') • Click to toggle');
            btn.setAttribute('aria-label', 'Theme: ' + themeLabel + ' based on Philippine Time');
        }
    }

    function applyTheme(theme, persist, isManual) {
        if (theme !== 'light' && theme !== 'dark') {
            theme = getPhilippineTime().theme;
        }

        if (document.documentElement.getAttribute('data-theme') !== theme) {
            document.documentElement.setAttribute('data-theme', theme);
        }

        if (persist !== false) {
            try {
                localStorage.setItem(STORAGE_KEY, theme);
                setCookie(STORAGE_KEY, theme, 365);
            } catch (e) {}
        }

        if (typeof isManual === 'undefined') {
            isManual = isManualOverrideActive();
        }

        updateToggleButtonState(theme, isManual);
        refreshAllCharts();
        document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme, isManual: isManual } }));
    }

    function toggleTheme() {
        var current = getTheme();
        var next = (current === 'dark') ? 'light' : 'dark';
        try {
            sessionStorage.setItem(MANUAL_KEY, next);
            localStorage.setItem(OVERRIDE_KEY, next);
            localStorage.setItem(STORAGE_KEY, next);
            setCookie(STORAGE_KEY, next, 365);
        } catch (e) {}
        applyTheme(next, true, true);
    }

    function resetToPhilippineTime() {
        try {
            sessionStorage.removeItem(MANUAL_KEY);
            localStorage.removeItem(OVERRIDE_KEY);
            localStorage.removeItem(STORAGE_KEY);
            deleteCookie(STORAGE_KEY);
        } catch (e) {}
        var ph = getPhilippineTime();
        setCookie(STORAGE_KEY, ph.theme, 365);
        applyTheme(ph.theme, false, false);
    }

    // Auto-check Philippine Time periodically so day/night transitions switch automatically
    var phTimer = null;
    function startPhilippineTimeWatcher() {
        if (phTimer) clearInterval(phTimer);
        phTimer = setInterval(function () {
            if (!isManualOverrideActive()) {
                var ph = getPhilippineTime();
                if (getTheme() !== ph.theme) {
                    applyTheme(ph.theme, false, false);
                } else {
                    updateToggleButtonState(ph.theme, false);
                }
            } else {
                updateToggleButtonState(getTheme(), true);
            }
        }, 30000);
    }

    function releaseThemePreload() {
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                document.documentElement.classList.remove('theme-loading');
            });
        });
    }

    window.IWTheme = {
        getTheme: getTheme,
        getPreferredTheme: getPreferredTheme,
        getPhilippineTime: getPhilippineTime,
        applyTheme: applyTheme,
        toggleTheme: toggleTheme,
        resetToPhilippineTime: resetToPhilippineTime,
        chartColors: chartColors,
        refreshAllCharts: refreshAllCharts
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            var initialTheme = getPreferredTheme();
            var isManual = isManualOverrideActive();

            if (document.documentElement.getAttribute('data-theme') !== initialTheme) {
                document.documentElement.setAttribute('data-theme', initialTheme);
            }
            setCookie(STORAGE_KEY, initialTheme, 365);

            updateToggleButtonState(initialTheme, isManual);
            refreshAllCharts();
            startPhilippineTimeWatcher();
            releaseThemePreload();

            var btn = document.getElementById('themeToggleBtn');
            if (btn) {
                btn.addEventListener('dblclick', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    resetToPhilippineTime();
                });
            }
        });
    } else {
        var initialTheme = getPreferredTheme();
        var isManual = isManualOverrideActive();
        if (document.documentElement.getAttribute('data-theme') !== initialTheme) {
            document.documentElement.setAttribute('data-theme', initialTheme);
        }
        setCookie(STORAGE_KEY, initialTheme, 365);
        updateToggleButtonState(initialTheme, isManual);
        refreshAllCharts();
        startPhilippineTimeWatcher();
        releaseThemePreload();
    }
})();
