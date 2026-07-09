(function () {
    'use strict';

    var STORAGE_KEY = 'iw-theme';
    var DEFAULT_THEME = 'dark';

    function getTheme() {
        return document.documentElement.getAttribute('data-theme') || DEFAULT_THEME;
    }

    function getPreferredTheme() {
        var stored = localStorage.getItem(STORAGE_KEY);
        return stored === 'light' || stored === 'dark' ? stored : DEFAULT_THEME;
    }

    function chartColors() {
        var isLight = getTheme() === 'light';
        return {
            text: isLight ? '#334155' : '#E3E3E3',
            muted: isLight ? '#64748b' : '#9aa6d1',
            grid: isLight ? 'rgba(148, 163, 184, 0.35)' : 'rgba(42, 68, 132, 0.3)',
            border: isLight ? 'rgba(148, 163, 184, 0.3)' : 'rgba(42, 68, 132, 0.3)'
        };
    }

    function applyChartDefaults() {
        if (typeof Chart === 'undefined') {
            return;
        }

        var colors = chartColors();
        Chart.defaults.color = colors.muted;
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

    function applyTheme(theme, persist) {
        if (theme !== 'light' && theme !== 'dark') {
            theme = DEFAULT_THEME;
        }

        document.documentElement.setAttribute('data-theme', theme);

        if (persist !== false) {
            localStorage.setItem(STORAGE_KEY, theme);
        }

        refreshAllCharts();
        document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme } }));
    }

    function toggleTheme() {
        applyTheme(getTheme() === 'dark' ? 'light' : 'dark');
    }

    window.IWTheme = {
        getTheme: getTheme,
        getPreferredTheme: getPreferredTheme,
        applyTheme: applyTheme,
        toggleTheme: toggleTheme,
        chartColors: chartColors,
        refreshAllCharts: refreshAllCharts
    };

    document.addEventListener('DOMContentLoaded', function () {
        applyTheme(getPreferredTheme(), false);
    });
})();
