(() => {
    const config = window.DASHBOARD_DATA || null;
    if (!config) {
        return;
    }

    const selectors = {
        form: document.getElementById('dashboard-filters'),
        applyBtn: document.getElementById('apply-filters'),
        clearBtn: document.getElementById('clear-filters'),
        providerSelect: document.getElementById('filter-provider'),
        periodSelect: document.getElementById('filter-period'),
        channelSelect: document.getElementById('filter-channel'),
        categorySelect: document.getElementById('filter-category'),
        loader: document.querySelector('[data-dashboard="loader"]'),
        sellinValue: document.querySelector('[data-dashboard="sellin-value"]'),
        sellinGrowth: document.querySelector('[data-dashboard="sellin-growth"]'),
        sellinGrowthValue: document.querySelector('[data-dashboard="sellin-growth-value"]'),
        selloutValue: document.querySelector('[data-dashboard="sellout-value"]'),
        selloutGrowth: document.querySelector('[data-dashboard="sellout-growth"]'),
        selloutGrowthValue: document.querySelector('[data-dashboard="sellout-growth-value"]'),
        ordersPending: document.querySelector('[data-dashboard="orders-pending"]'),
        alerts: document.querySelector('[data-dashboard="alerts"]'),
        ordersFulfilled: document.querySelector('[data-dashboard="orders-fulfilled"]'),
        returnsValue: document.querySelector('[data-dashboard="returns"]'),
        alertsOperational: document.querySelector('[data-dashboard="alerts-operational"]'),
        ordersFulfilledProgress: document.querySelector('[data-dashboard="orders-fulfilled-progress"]'),
        returnsProgress: document.querySelector('[data-dashboard="returns-progress"]'),
        alertsProgress: document.querySelector('[data-dashboard="alerts-progress"]'),
        productsBody: document.querySelector('[data-dashboard="products-body"]'),
    };

    const currencyFormatter = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        maximumFractionDigits: 0,
    });

    const numberFormatter = new Intl.NumberFormat('es-MX', {
        maximumFractionDigits: 0,
    });

    const state = {
        endpoint: config.endpoint,
        currentFilters: { ...config.filters },
        permissions: config.permissions || {},
        charts: {
            trend: null,
            orders: null,
        },
    };

    function setLoading(isLoading) {
        if (selectors.loader) {
            selectors.loader.classList.toggle('d-none', !isLoading);
        }
        if (selectors.applyBtn) {
            selectors.applyBtn.disabled = isLoading;
        }
        if (selectors.form) {
            selectors.form.classList.toggle('opacity-75', isLoading);
        }
    }

    function formatCurrency(value) {
        return currencyFormatter.format(value || 0);
    }

    function formatNumber(value) {
        return numberFormatter.format(value || 0);
    }

    function updateBadge(element, value) {
        if (!element) {
            return;
        }

        const growth = Number(value || 0);
        element.classList.remove('badge-light-success', 'badge-light-danger');
        element.classList.add(growth >= 0 ? 'badge-light-success' : 'badge-light-danger');

        const icon = element.querySelector('i');
        if (icon) {
            icon.className = `fa-solid ${growth >= 0 ? 'fa-arrow-up' : 'fa-arrow-down'} me-1`;
        }

        const span = element.querySelector('span[data-dashboard$="-growth-value"]');
        if (span) {
            span.textContent = Math.abs(growth).toFixed(1);
        }
    }

    function updateSummary(summary) {
        if (!summary) {
            return;
        }

        if (selectors.sellinValue) {
            selectors.sellinValue.textContent = formatCurrency(summary.sellin);
        }
        updateBadge(selectors.sellinGrowth, summary.sellin_growth);

        if (selectors.selloutValue) {
            selectors.selloutValue.textContent = formatCurrency(summary.sellout);
        }
        updateBadge(selectors.selloutGrowth, summary.sellout_growth);

        if (selectors.ordersPending) {
            selectors.ordersPending.textContent = formatNumber(summary.orders_pending);
        }

        if (selectors.alerts) {
            selectors.alerts.textContent = formatNumber(summary.alerts);
        }

        if (selectors.ordersFulfilled) {
            selectors.ordersFulfilled.textContent = formatNumber(summary.orders_fulfilled);
        }

        if (selectors.returnsValue) {
            selectors.returnsValue.textContent = formatNumber(summary.returns);
        }

        if (selectors.alertsOperational) {
            selectors.alertsOperational.textContent = formatNumber(summary.alerts);
        }

        const totalOrders = Number(summary.orders_fulfilled || 0) + Number(summary.orders_pending || 0);
        const fulfilledPercent = totalOrders > 0 ? Math.min(100, Math.round((summary.orders_fulfilled / totalOrders) * 100)) : 0;
        const returnsPercent = totalOrders > 0 ? Math.min(100, Math.round((summary.returns / totalOrders) * 100)) : 0;
        const alertsPercent = totalOrders > 0 ? Math.min(100, Math.round((summary.alerts / totalOrders) * 100)) : 0;

        if (selectors.ordersFulfilledProgress) {
            selectors.ordersFulfilledProgress.style.width = `${fulfilledPercent}%`;
        }
        if (selectors.returnsProgress) {
            selectors.returnsProgress.style.width = `${returnsPercent}%`;
        }
        if (selectors.alertsProgress) {
            selectors.alertsProgress.style.width = `${alertsPercent}%`;
        }
    }

    function initCharts(initialTrend, initialOrders) {
        const trendElement = document.querySelector('#chart_sales_trend');
        if (trendElement) {
            const options = {
                series: initialTrend.series || [],
                chart: { type: 'area', height: 320, toolbar: { show: false } },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: { categories: initialTrend.categories || [] },
                yaxis: {
                    labels: {
                        formatter: value => formatCurrency(value).replace('$', ''),
                    },
                },
                legend: { position: 'top' },
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [15, 120, 100] },
                },
                tooltip: {
                    y: { formatter: value => formatCurrency(value) },
                },
            };
            state.charts.trend = new ApexCharts(trendElement, options);
            state.charts.trend.render();
        }

        const ordersElement = document.querySelector('#chart_orders_status');
        if (ordersElement) {
            const options = {
                series: (initialOrders || []).map(item => Number(item.value || 0)),
                chart: { type: 'donut', height: 320 },
                labels: (initialOrders || []).map(item => item.status || ''),
                dataLabels: { enabled: false },
                legend: { position: 'bottom' },
                plotOptions: {
                    pie: {
                        donut: {
                            size: '60%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    fontSize: '13px',
                                    formatter() {
                                        const total = (initialOrders || []).reduce((acc, item) => acc + Number(item.value || 0), 0);
                                        return numberFormatter.format(total);
                                    },
                                },
                            },
                        },
                    },
                },
            };
            state.charts.orders = new ApexCharts(ordersElement, options);
            state.charts.orders.render();
        }
    }

    function updateCharts(trend, orders) {
        if (state.charts.trend) {
            state.charts.trend.updateOptions({ xaxis: { categories: trend.categories || [] } });
            state.charts.trend.updateSeries(trend.series || []);
        }
        if (state.charts.orders) {
            const series = (orders || []).map(item => Number(item.value || 0));
            const labels = (orders || []).map(item => item.status || '');
            const total = series.reduce((acc, value) => acc + value, 0);

            state.charts.orders.updateOptions({
                labels,
                plotOptions: {
                    pie: {
                        donut: {
                            labels: {
                                total: {
                                    formatter() {
                                        return numberFormatter.format(total);
                                    },
                                },
                            },
                        },
                    },
                },
            });
            state.charts.orders.updateSeries(series);
        }
    }

    function updateTopProducts(products) {
        if (!selectors.productsBody) {
            return;
        }

        if (!Array.isArray(products) || products.length === 0) {
            selectors.productsBody.innerHTML = '<tr><td class="ps-9 text-muted" colspan="4">Sin información disponible.</td></tr>';
            return;
        }

        const rows = products.map(product => {
            const growth = Number(product.growth || 0);
            const badgeClass = growth >= 0 ? 'badge-light-success' : 'badge-light-danger';
            const growthLabel = `${growth.toFixed(1)}%`;
            return `
                <tr>
                    <td class="ps-9 fw-bold text-dark">${(product.sku || '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
                    <td class="text-muted">${(product.description || '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;')}</td>
                    <td class="text-end fw-bold text-dark">${formatCurrency(product.sellout)}</td>
                    <td class="text-end pe-9">
                        <span class="badge ${badgeClass}">${growthLabel}</span>
                    </td>
                </tr>
            `;
        }).join('');

        selectors.productsBody.innerHTML = rows;
    }

    function gatherFilters() {
        return {
            provider_id: selectors.providerSelect ? Number(selectors.providerSelect.value) : state.currentFilters.provider_id,
            period: selectors.periodSelect ? selectors.periodSelect.value : state.currentFilters.period,
            channel: selectors.channelSelect ? selectors.channelSelect.value : state.currentFilters.channel,
            category: selectors.categorySelect ? selectors.categorySelect.value : state.currentFilters.category,
        };
    }

    async function requestData(filters) {
        setLoading(true);
        try {
            const response = await fetch(state.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(filters),
            });

            if (!response.ok) {
                throw new Error(`Error ${response.status}`);
            }

            const payload = await response.json();
            if (!payload.ok) {
                throw new Error(payload.message || 'No fue posible procesar la solicitud.');
            }

            state.currentFilters = { ...payload.filters };

            updateSummary(payload.data.summary || {});
            updateCharts(payload.data.trend || { categories: [], series: [] }, payload.data.orders || []);
            updateTopProducts(payload.data.topProducts || []);
        } catch (error) {
            console.error('Dashboard request error:', error);
        } finally {
            setLoading(false);
        }
    }

    function attachEvents() {
        if (selectors.form) {
            selectors.form.addEventListener('submit', event => {
                event.preventDefault();
                const filters = gatherFilters();
                requestData(filters);
            });
        }

        [selectors.providerSelect, selectors.periodSelect, selectors.channelSelect, selectors.categorySelect]
            .filter(Boolean)
            .forEach(select => {
                select.addEventListener('change', () => {
                    if (selectors.form) {
                        selectors.form.dispatchEvent(new Event('submit', { bubbles: false }));
                    }
                });
            });

        if (selectors.clearBtn) {
            selectors.clearBtn.addEventListener('click', () => {
                if (selectors.providerSelect && selectors.providerSelect.options.length > 0) {
                    selectors.providerSelect.selectedIndex = 0;
                }
                if (selectors.periodSelect) {
                    selectors.periodSelect.value = 'mtm';
                }
                if (selectors.channelSelect) {
                    selectors.channelSelect.value = 'all';
                }
                if (selectors.categorySelect) {
                    selectors.categorySelect.value = 'all';
                }
                if (selectors.form) {
                    selectors.form.dispatchEvent(new Event('submit', { bubbles: false }));
                }
            });
        }
    }

    function initialise() {
        updateSummary(config.summary || {});
        updateTopProducts(config.topProducts || []);
        initCharts(config.trend || { categories: [], series: [] }, config.orders || []);
        attachEvents();
    }

    initialise();
})();
