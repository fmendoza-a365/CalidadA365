import * as echarts from 'echarts/core';
import { BarChart, LineChart } from 'echarts/charts';
import { GraphicComponent, GridComponent, LegendComponent, TooltipComponent } from 'echarts/components';
import { LabelLayout } from 'echarts/features';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([
    BarChart,
    LineChart,
    GraphicComponent,
    GridComponent,
    LabelLayout,
    LegendComponent,
    TooltipComponent,
    CanvasRenderer,
]);

const instances = new Map();
const registry = new Map();
let resizeObserver = null;

const colorTokens = {
    indigo: '#818cf8',
    sky: '#38bdf8',
    teal: '#2dd4bf',
    rose: '#fb7185',
    amber: '#fbbf24',
    violet: '#a78bfa',
    emerald: '#34d399',
    pink: '#f472b6',
    cyan: '#22d3ee',
    orange: '#fb923c',
    slate: '#64748b',
};

function isDarkMode() {
    return document.documentElement.classList.contains('dark');
}

function themeTokens() {
    const dark = isDarkMode();

    return {
        text: dark ? '#e5e7eb' : '#111827',
        muted: dark ? '#94a3b8' : '#64748b',
        grid: dark ? '#2a2a2a' : '#e5e7eb',
        border: dark ? '#2a2a2a' : '#d1d5db',
        tooltipBg: dark ? '#1c1c1c' : '#ffffff',
        axisLine: dark ? '#404040' : '#cbd5e1',
        empty: dark ? '#64748b' : '#94a3b8',
    };
}

function rows(data) {
    return Array.isArray(data) ? data : [];
}

function labelOf(item) {
    return item?.label ?? item?.name ?? 'Sin dato';
}

function numberOf(item, key) {
    const value = Number(item?.[key] ?? 0);

    return Number.isFinite(value) ? value : 0;
}

function formatValue(value, suffix = '') {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return suffix ? `0${suffix}` : '0';
    }

    const formatted = Number.isInteger(number) ? number.toString() : number.toFixed(1);

    return suffix ? `${formatted}${suffix}` : formatted;
}

function dataLabel(options = {}) {
    const tokens = themeTokens();
    const suffix = options.suffix ?? '';

    return {
        show: true,
        position: options.position ?? 'top',
        distance: options.distance ?? 6,
        color: options.color ?? tokens.text,
        fontFamily: 'Inter, sans-serif',
        fontSize: options.fontSize ?? 10,
        fontWeight: 700,
        formatter(params) {
            const value = Array.isArray(params.value) ? params.value.at(-1) : params.value;

            if (options.hideZero && Number(value) === 0) {
                return '';
            }

            return formatValue(value, suffix);
        },
    };
}

function axisLabelConfig(dataLength) {
    const tokens = themeTokens();

    return {
        color: tokens.muted,
        fontFamily: 'Inter, sans-serif',
        fontSize: 10,
        hideOverlap: true,
        interval: dataLength > 10 ? 'auto' : 0,
        rotate: dataLength > 8 ? 25 : 0,
        width: 92,
        overflow: 'truncate',
    };
}

function baseOption() {
    const tokens = themeTokens();

    return {
        backgroundColor: 'transparent',
        animationDuration: 650,
        animationEasing: 'cubicOut',
        textStyle: {
            color: tokens.text,
            fontFamily: 'Inter, sans-serif',
        },
        grid: {
            left: 8,
            right: 20,
            top: 42,
            bottom: 16,
            containLabel: true,
        },
        legend: {
            show: false,
            top: 0,
            right: 0,
            itemWidth: 10,
            itemHeight: 6,
            textStyle: {
                color: tokens.muted,
                fontFamily: 'Inter, sans-serif',
                fontSize: 10,
            },
        },
        tooltip: {
            trigger: 'item',
            confine: true,
            backgroundColor: tokens.tooltipBg,
            borderColor: tokens.border,
            borderWidth: 1,
            textStyle: {
                color: tokens.text,
                fontFamily: 'Inter, sans-serif',
                fontSize: 11,
            },
            axisPointer: {
                type: 'none',
            },
        },
    };
}

function valueAxis(max = null, showLabels = false) {
    const tokens = themeTokens();

    return {
        type: 'value',
        max,
        splitLine: {
            show: false,
        },
        axisLine: {
            show: false,
        },
        axisTick: {
            show: false,
        },
        axisLabel: {
            show: showLabels,
            color: tokens.muted,
            fontFamily: 'Inter, sans-serif',
            fontSize: 10,
        },
    };
}

function categoryAxis(data, inverse = false, options = {}) {
    const tokens = themeTokens();
    const list = rows(data);

    return {
        type: 'category',
        inverse,
        data: list.map(labelOf),
        axisTick: {
            show: false,
        },
        axisLine: {
            show: false,
        },
        axisLabel: {
            ...axisLabelConfig(list.length),
            show: options.showLabels ?? true,
            rotate: options.rotate ?? (inverse ? 0 : axisLabelConfig(list.length).rotate),
            width: options.width ?? (inverse ? 160 : 92),
        },
    };
}

function emptyOption(message = 'Sin datos para mostrar') {
    const tokens = themeTokens();

    return {
        ...baseOption(),
        legend: {
            show: false,
        },
        xAxis: {
            show: false,
        },
        yAxis: {
            show: false,
        },
        series: [],
        graphic: {
            type: 'text',
            left: 'center',
            top: 'middle',
            style: {
                text: message,
                fill: tokens.empty,
                font: '600 12px Inter, sans-serif',
            },
        },
    };
}

function observeNode(node) {
    if (!window.ResizeObserver) {
        return;
    }

    if (!resizeObserver) {
        resizeObserver = new ResizeObserver((entries) => {
            entries.forEach((entry) => {
                const chart = instances.get(entry.target);

                if (chart) {
                    chart.resize();
                }
            });
        });
    }

    resizeObserver.observe(node);
}

function buildOption(optionFactory) {
    return typeof optionFactory === 'function' ? optionFactory() : optionFactory;
}

function render(selector, optionFactory) {
    const node = document.querySelector(selector);

    if (!node) {
        return null;
    }

    registry.set(selector, optionFactory);

    let chart = instances.get(node);

    if (!chart) {
        chart = echarts.init(node, null, { renderer: 'canvas' });
        instances.set(node, chart);
        observeNode(node);
    }

    chart.setOption(buildOption(optionFactory), true);
    requestAnimationFrame(() => chart.resize());

    return chart;
}

function resizeAll() {
    instances.forEach((chart) => chart.resize());
}

function refreshAll() {
    instances.forEach((chart) => chart.dispose());
    instances.clear();

    registry.forEach((optionFactory, selector) => {
        render(selector, optionFactory);
    });
}

function combo(selector, data, options = {}) {
    const list = rows(data);
    const barColor = options.barColor ?? colorTokens.indigo;
    const barName = options.barName ?? 'Nota %';

    return render(selector, () => {
        if (!list.length) {
            return emptyOption();
        }

        return {
            ...baseOption(),
            color: [barColor],
            tooltip: {
                ...baseOption().tooltip,
                formatter(params) {
                    const item = list[params.dataIndex] ?? {};

                    return [
                        `<strong>${labelOf(item)}</strong>`,
                        `${barName}: <strong>${formatValue(numberOf(item, 'avg_score'), '%')}</strong>`,
                        `Cantidad: <strong>${formatValue(numberOf(item, 'count'))}</strong>`,
                    ].join('<br>');
                },
            },
            xAxis: categoryAxis(list),
            yAxis: valueAxis(100),
            series: [
                {
                    name: barName,
                    type: 'bar',
                    data: list.map((item) => numberOf(item, 'avg_score')),
                    barWidth: '46%',
                    itemStyle: {
                        borderRadius: [4, 4, 0, 0],
                    },
                    label: dataLabel({ suffix: '%' }),
                    labelLayout: {
                        hideOverlap: true,
                    },
                },
            ],
        };
    });
}

function bar(selector, data, options = {}) {
    const list = rows(data);
    const metric = options.metric ?? 'count';
    const valueName = options.valueName ?? 'Total';
    const color = options.color ?? colorTokens.indigo;
    const max = options.max ?? null;

    return render(selector, () => {
        if (!list.length) {
            return emptyOption();
        }

        return {
            ...baseOption(),
            grid: {
                left: 8,
                right: 20,
                top: 36,
                bottom: 16,
                containLabel: true,
            },
            color: [color],
            xAxis: categoryAxis(list),
            yAxis: valueAxis(max),
            series: [
                {
                    name: valueName,
                    type: 'bar',
                    data: list.map((item) => numberOf(item, metric)),
                    barWidth: '52%',
                    itemStyle: {
                        borderRadius: [4, 4, 0, 0],
                    },
                    label: dataLabel({ suffix: options.suffix ?? '' }),
                    labelLayout: {
                        hideOverlap: true,
                    },
                },
            ],
        };
    });
}

function horizontalBar(selector, data, options = {}) {
    const list = rows(data);
    const metric = options.metric ?? 'count';
    const valueName = options.valueName ?? 'Total';
    const color = options.color ?? colorTokens.violet;
    const max = options.max ?? null;

    return render(selector, () => {
        if (!list.length) {
            return emptyOption();
        }

        return {
            ...baseOption(),
            grid: {
                left: 8,
                right: 56,
                top: 30,
                bottom: 8,
                containLabel: true,
            },
            color: [color],
            xAxis: valueAxis(max),
            yAxis: categoryAxis(list, true),
            series: [
                {
                    name: valueName,
                    type: 'bar',
                    data: list.map((item) => numberOf(item, metric)),
                    barWidth: '52%',
                    itemStyle: {
                        borderRadius: [0, 4, 4, 0],
                    },
                    label: dataLabel({
                        suffix: options.suffix ?? '',
                        position: 'right',
                        distance: 8,
                    }),
                    labelLayout: {
                        hideOverlap: true,
                    },
                },
            ],
        };
    });
}

function area(selector, data, options = {}) {
    const list = rows(data);
    const metric = options.metric ?? 'avg_score';
    const valueName = options.valueName ?? 'Nota %';
    const color = options.color ?? colorTokens.indigo;
    const max = options.max ?? 100;

    return render(selector, () => {
        if (!list.length) {
            return emptyOption();
        }

        return {
            ...baseOption(),
            grid: {
                left: 8,
                right: 20,
                top: 38,
                bottom: 16,
                containLabel: true,
            },
            color: [color],
            xAxis: categoryAxis(list),
            yAxis: valueAxis(max),
            series: [
                {
                    name: valueName,
                    type: 'line',
                    data: list.map((item) => numberOf(item, metric)),
                    smooth: true,
                    symbolSize: 5,
                    lineStyle: {
                        width: 2.5,
                    },
                    areaStyle: {
                        opacity: 0.16,
                    },
                    label: dataLabel({ suffix: options.suffix ?? '%', position: 'top' }),
                    labelLayout: {
                        hideOverlap: true,
                    },
                },
            ],
        };
    });
}

function stacked(selector, data, options = {}) {
    const list = rows(data);
    const doneColor = options.doneColor ?? colorTokens.teal;
    const pendingColor = options.pendingColor ?? colorTokens.amber;

    return render(selector, () => {
        if (!list.length) {
            return emptyOption();
        }

        return {
            ...baseOption(),
            grid: {
                left: 8,
                right: 20,
                top: 36,
                bottom: 16,
                containLabel: true,
            },
            color: [doneColor, pendingColor],
            xAxis: categoryAxis(list),
            yAxis: valueAxis(),
            series: [
                {
                    name: 'Realizado',
                    type: 'bar',
                    stack: 'feedback',
                    data: list.map((item) => numberOf(item, 'done')),
                    barWidth: '50%',
                    itemStyle: {
                        borderRadius: [3, 3, 0, 0],
                    },
                    label: dataLabel({ position: 'inside', color: '#ffffff', hideZero: true }),
                },
                {
                    name: 'Pendiente',
                    type: 'bar',
                    stack: 'feedback',
                    data: list.map((item) => numberOf(item, 'pending') || Math.max(0, numberOf(item, 'total') - numberOf(item, 'done'))),
                    barWidth: '50%',
                    itemStyle: {
                        borderRadius: [3, 3, 0, 0],
                    },
                    label: dataLabel({ position: 'inside', color: '#ffffff', hideZero: true }),
                },
            ],
        };
    });
}

let lastDarkMode = isDarkMode();

new MutationObserver(() => {
    const nextDarkMode = isDarkMode();

    if (nextDarkMode !== lastDarkMode) {
        lastDarkMode = nextDarkMode;
        refreshAll();
    }
}).observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class'],
});

window.addEventListener('resize', resizeAll);
document.addEventListener('click', () => {
    window.setTimeout(resizeAll, 80);
});

window.QA365Charts = {
    colors: () => ({ ...colorTokens }),
    combo,
    bar,
    horizontalBar,
    area,
    stacked,
    resizeAll,
    refreshAll,
};

window.dispatchEvent(new CustomEvent('qa365:charts-ready'));
