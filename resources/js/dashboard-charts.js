import ApexCharts from 'apexcharts';

const ROOT_SELECTOR = '[data-dashboard-page]';
const CHART_SELECTOR = '[data-chart-key]';
const FORM_SELECTOR = '[data-dashboard-filters-form]';

const chartCache = new Map();
let resizeObserver = null;

const formatNumber = (value) => {
  const num = Number(value);
  return isNaN(num) ? '0' : new Intl.NumberFormat('es-EC').format(num);
};

const getTheme = () => {
  const root = document.documentElement;
  const styles = getComputedStyle(root);
  const dark = root.classList.contains('panel-theme-dark');

  const read = (name, fallback) => styles.getPropertyValue(name).trim() || fallback;

  return {
    dark,
    foreColor: read('--dashboard-chart-ink', dark ? '#e5e7eb' : '#1f2937'),
    mutedColor: read('--dashboard-chart-muted', dark ? '#9ca3af' : '#6b7280'),
    gridColor: read('--dashboard-chart-grid', dark ? '#334155' : '#e5e7eb'),
    surface: read('--dashboard-chart-surface', dark ? '#111827' : '#ffffff'),
    tooltipTheme: dark ? 'dark' : 'light',
    primary: read('--dashboard-chart-primary', '#334155'),
    secondary: read('--dashboard-chart-secondary', '#3b82f6'),
    accent: read('--dashboard-chart-accent', '#f59e0b'),
    danger: read('--dashboard-chart-danger', '#ef4444'),
    purple: read('--dashboard-chart-purple', '#8b5cf6'),
    teal: read('--dashboard-chart-teal', read('--dashboard-chart-primary', '#334155')),
  };
};

const destroyChart = (container) => {
  const instance = chartCache.get(container);
  if (instance) {
    instance.destroy();
    chartCache.delete(container);
  }
};

const hasData = (chart) => {
  const series = Array.isArray(chart.series) ? chart.series : [];

  if (series.length === 0) {
    return false;
  }

  if (chart.type === 'donut') {
    return series.some((value) => Number(value) > 0);
  }

  return series.some((item) => {
    if (Array.isArray(item?.data)) {
      return item.data.some((point) => Number(point) > 0);
    }

    return Number(item) > 0;
  });
};

const setEmptyState = (container, show) => {
  const empty = container.closest('[data-chart-shell]')?.querySelector('[data-chart-empty]');
  if (!empty) {
    return;
  }

  empty.classList.toggle('hidden', !show);
  container.classList.toggle('hidden', show);
};

const buildBaseOptions = (chart, theme) => ({
  chart: {
    type: chart.type || 'line',
    height: chart.height || 340,
    background: 'transparent',
    fontFamily: 'inherit',
    foreColor: theme.foreColor,
    toolbar: {
      show: false,
    },
  },
  theme: {
    mode: theme.dark ? 'dark' : 'light',
  },
  colors: chart.colors?.length ? chart.colors : [theme.primary, theme.secondary, theme.accent, theme.danger, theme.purple],
  grid: {
    borderColor: theme.gridColor,
    strokeDashArray: 4,
  },
  dataLabels: {
    enabled: Boolean(chart.dataLabels),
  },
  legend: {
    show: true,
    position: 'top',
    horizontalAlign: 'left',
    floating: false,
    markers: {
      width: 10,
      height: 10,
      radius: 999,
    },
    labels: {
      colors: theme.foreColor,
    },
  },
  noData: {
    text: 'No hay datos para el período seleccionado',
    style: {
      color: theme.mutedColor,
      fontFamily: 'inherit',
    },
  },
  tooltip: {
    theme: theme.tooltipTheme,
    style: {
      fontFamily: 'inherit',
    },
  },
  stroke: {
    curve: 'smooth',
    width: 3,
  },
});

const buildSharedAxis = (theme, categories = []) => ({
  categories,
  labels: {
    style: {
      colors: theme.foreColor,
    },
  },
  axisBorder: {
    color: theme.gridColor,
  },
  axisTicks: {
    color: theme.gridColor,
  },
});

const buildDonutOptions = (chart, theme, base) => ({
  ...base,
  chart: {
    ...base.chart,
    type: 'donut',
  },
  labels: chart.labels || [],
  series: Array.isArray(chart.series) ? chart.series : [],
  plotOptions: {
    pie: {
      donut: {
        size: '68%',
        labels: {
          show: true,
          name: {
            show: true,
            color: theme.foreColor,
          },
          value: {
            show: true,
            color: theme.foreColor,
            formatter: (value) => formatNumber(value),
          },
          total: {
            show: true,
            showAlways: true,
            label: chart.centerLabel || 'Total',
            color: theme.foreColor,
            formatter: (w) => formatNumber(
              w.globals.seriesTotals.reduce((sum, value) => sum + Number(value || 0), 0)
            ),
          },
        },
      },
    },
  },
  dataLabels: {
    enabled: true,
    formatter: (value) => `${Math.round(value)}%`,
    style: {
      colors: [theme.foreColor],
    },
  },
  legend: {
    ...base.legend,
    position: 'bottom',
  },
  responsive: [
    {
      breakpoint: 768,
      options: {
        legend: {
          position: 'bottom',
        },
      },
    },
  ],
});

const buildBarOptions = (chart, theme, base) => ({
  ...base,
  chart: {
    ...base.chart,
    type: 'bar',
    stacked: Boolean(chart.stacked),
  },
  series: Array.isArray(chart.series) ? chart.series : [],
  plotOptions: {
    bar: {
      horizontal: Boolean(chart.horizontal),
      barHeight: chart.horizontal ? '60%' : '48%',
      columnWidth: chart.horizontal ? '60%' : '46%',
      borderRadius: 6,
      distributed: Boolean(chart.distributed),
    },
  },
  xaxis: {
    ...buildSharedAxis(theme, chart.labels || []),
    labels: {
      ...buildSharedAxis(theme, chart.labels || []).labels,
      trim: true,
      rotate: -35,
      hideOverlappingLabels: true,
    },
  },
  yaxis: {
    labels: {
      formatter: chart.horizontal
        ? (value) => (typeof value === 'number' ? formatNumber(value) : String(value ?? ''))
        : (value) => formatNumber(value),
      style: {
        colors: theme.foreColor,
      },
    },
  },
  tooltip: {
    ...base.tooltip,
    y: {
      formatter: (value) => formatNumber(value),
    },
  },
  dataLabels: {
    enabled: Boolean(chart.dataLabels),
  },
  legend: {
    ...base.legend,
    position: chart.stacked ? 'bottom' : 'top',
  },
  responsive: [
    {
      breakpoint: 768,
      options: {
        plotOptions: {
          bar: {
            borderRadius: 4,
            columnWidth: chart.horizontal ? '70%' : '52%',
            barHeight: chart.horizontal ? '72%' : '52%',
          },
        },
        legend: {
          position: 'bottom',
        },
      },
    },
  ],
});

const buildLineOptions = (chart, theme, base) => {
  const isArea = chart.type === 'area';
  return {
    ...base,
    chart: {
      ...base.chart,
      type: isArea ? 'area' : 'line',
      stacked: Boolean(chart.stacked),
    },
    series: Array.isArray(chart.series) ? chart.series : [],
    xaxis: {
      ...buildSharedAxis(theme, chart.labels || []),
      tickAmount: chart.labels && chart.labels.length > 8 ? 8 : undefined,
      labels: {
        ...buildSharedAxis(theme, chart.labels || []).labels,
        trim: true,
        rotate: 0,
        hideOverlappingLabels: true,
        style: {
          colors: theme.foreColor,
          fontSize: '11px',
        },
      },
      crosshairs: {
        show: true,
        width: 1,
        position: 'back',
        stroke: {
          color: theme.gridColor,
          width: 1,
          dashArray: 3,
        },
      },
    },
    yaxis: {
      labels: {
        formatter: (value) => formatNumber(value),
        style: {
          colors: theme.foreColor,
          fontSize: '11px',
        },
      },
    },
    tooltip: {
      ...base.tooltip,
      shared: true,
      intersect: false,
      theme: theme.tooltipTheme,
      x: {
        show: true,
      },
      y: {
        formatter: (value) => formatNumber(value),
      },
      style: {
        fontSize: '12px',
        fontFamily: 'inherit',
      },
    },
    fill: isArea
      ? {
          type: 'gradient',
          gradient: {
            shadeIntensity: 0.5,
            opacityFrom: 0.35,
            opacityTo: 0.02,
            stops: [0, 90, 100],
          },
        }
      : undefined,
    markers: {
      size: isArea ? 4 : 5,
      strokeWidth: 2,
      strokeColors: theme.surface,
      hover: {
        size: 6,
      },
    },
    stroke: {
      curve: 'smooth',
      width: isArea ? 2.5 : 3,
    },
    legend: {
      ...base.legend,
      position: 'top',
    },
    responsive: [
      {
        breakpoint: 768,
        options: {
          legend: {
            position: 'bottom',
          },
        },
      },
    ],
  };
};

const makeChartOptions = (chart, theme) => {
  const base = buildBaseOptions(chart, theme);

  if (chart.type === 'donut') {
    return buildDonutOptions(chart, theme, base);
  }

  if (chart.type === 'bar') {
    return buildBarOptions(chart, theme, base);
  }

  return buildLineOptions(chart, theme, base);
};

const renderChart = async (container, chart) => {
  destroyChart(container);

  const renderChartData = {
    ...chart,
    type: container.dataset.chartType || chart.type || 'line',
  };

  if (!hasData(renderChartData)) {
    setEmptyState(container, true);
    return;
  }

  setEmptyState(container, false);

  const theme = getTheme();
  const options = makeChartOptions(renderChartData, theme);

  if (Array.isArray(renderChartData.links) && renderChartData.links.length > 0) {
    options.chart = {
      ...options.chart,
      events: {
        dataPointSelection: (event, chartContext, config) => {
          const url = renderChartData.links?.[config.dataPointIndex];
          if (url) {
            window.location.href = url;
          }
        },
      },
    };
  }

  const instance = new ApexCharts(container, options);
  chartCache.set(container, instance);
  await instance.render();
};

const buildUrl = (endpoint, params = {}) => {
  const url = new URL(endpoint, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== null && value !== undefined && String(value).trim() !== '') {
      url.searchParams.set(key, value);
    }
  });

  return url;
};

const readInitialState = (root) => {
  const script = root.querySelector('[data-dashboard-state]');
  if (!script) {
    return null;
  }

  try {
    return JSON.parse(script.textContent || '{}');
  } catch {
    return null;
  }
};

let resizeRaf = null;

const resizeCharts = () => {
  if (resizeRaf !== null) {
    cancelAnimationFrame(resizeRaf);
  }

  resizeRaf = requestAnimationFrame(() => {
    window.dispatchEvent(new Event('resize'));
    resizeRaf = null;
  });
};

const renderPayload = async (root, payload) => {
  if (!payload) {
    return;
  }

  const charts = payload.charts || {};
  await Promise.all(
    Array.from(root.querySelectorAll(CHART_SELECTOR)).map((container) => {
      const key = container.dataset.chartKey;
      return renderChart(container, charts[key] || {});
    })
  );

  resizeCharts();
};

const initDashboard = async (root) => {
  const endpoint = root.dataset.dashboardEndpoint;
  const form = root.querySelector(FORM_SELECTOR);
  let currentState = readInitialState(root);

  if (currentState) {
    await renderPayload(root, currentState);
  }

  if (!form || !endpoint) {
    return;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const formData = new FormData(form);
    const url = buildUrl(endpoint, {
      period: formData.get('period'),
    });

    try {
      const response = await fetch(url, {
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      currentState = await response.json();
      await renderPayload(root, currentState);

      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.set('period', formData.get('period') || 'month');
      currentUrl.searchParams.delete('from');
      currentUrl.searchParams.delete('to');

      window.history.replaceState({}, '', currentUrl);
    } catch (error) {
      console.error('No se pudo actualizar el dashboard.', error);
    }
  });

  const themeObserver = new MutationObserver(() => {
    if (currentState) {
      renderPayload(root, currentState).catch((error) => {
        console.error('No se pudo re-renderizar el dashboard.', error);
      });
    }
  });

  themeObserver.observe(document.documentElement, {
    attributes: true,
    attributeFilter: ['class', 'data-panel-theme'],
  });

  if (!resizeObserver && 'ResizeObserver' in window) {
    resizeObserver = new ResizeObserver(() => resizeCharts());
  }

  if (resizeObserver) {
    resizeObserver.observe(root);
  }

  window.addEventListener('sidebar:toggle', resizeCharts);
};

const boot = () => {
  document.querySelectorAll(ROOT_SELECTOR).forEach((root) => {
    initDashboard(root).catch((error) => {
      console.error('No se pudo inicializar el dashboard.', error);
    });
  });
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
