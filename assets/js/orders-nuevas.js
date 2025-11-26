(function () {
  const app = document.getElementById("orders-nuevas-app");
  if (!app) {
    return;
  }

  const config = safeJson(app.dataset.config);
  if (!config || !config.endpoints || !config.endpoints.list) {
    console.error("Orders nuevas: configuracion invalida");
    return;
  }

  const tableBody = app.querySelector("tbody");
  const searchInput = app.querySelector("[data-orders-search]");
  const daysSelect = app.querySelector("[data-orders-days]");
  const perPageSelect = app.querySelector("[data-orders-per-page]");
  const refreshBtn = app.querySelector("[data-orders-refresh]");
  const prevBtn = app.querySelector("[data-orders-prev]");
  const nextBtn = app.querySelector("[data-orders-next]");
  const summaryEl = app.querySelector("[data-orders-summary]");

  const state = {
    page: config.filters?.page || 1,
    perPage: config.filters?.perPage || 25,
    days: config.filters?.days || 30,
    search: config.filters?.search || "",
    totalPages: 1,
    total: 0,
    loading: false,
    lastOrderId: null, // 👈 aquí
    lastOrderDate: null, // 👈 NUEVO
    lastViewsTs: null, // 👈 nuevo
  };

  if (searchInput) {
    searchInput.value = state.search;
  }
  if (daysSelect) {
    daysSelect.value = state.days;
  }
  if (perPageSelect) {
    perPageSelect.value = state.perPage;
  }

  function fetchOrders() {
    if (state.loading) {
      return;
    }
    state.loading = true;
    setTableMessage("Cargando ordenes...");
    const params = new URLSearchParams({
      page: String(state.page),
      per_page: String(state.perPage),
      search: state.search || "",
      days: String(state.days),
    });

    fetch(`${config.endpoints.list}?${params.toString()}`, {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then(async (response) => {
        if (!response.ok) {
          const text = await response.text();
          let message = "No fue posible cargar la informacion.";
          try {
            const payload = JSON.parse(text);
            message = payload.error || message;
          } catch (err) {
            console.error("Respuesta inesperada", text);
          }
          throw new Error(message);
        }
        return response.json();
      })
      .then((payload) => {
        if (payload.error) {
          throw new Error(payload.error);
        }
        state.total = payload.meta?.total || 0;
        state.totalPages = payload.meta?.total_pages || 1;

        const rows = payload.data || [];
        renderTable(rows);

        // Solo usamos esta lista como referencia de "lo más nuevo"
        // cuando NO hay búsqueda y estamos en la página 1.
        if (!state.search && state.page === 1 && rows.length) {
          state.lastOrderId = rows[0].ID_COMPRA;
          state.lastOrderDate = rows[0].FECHA; // viene del backend
          // console.log("Cursor actualizado:", state.lastOrderId, state.lastOrderDate);
        }
        // 👇 guarda último timestamp de vistas que manda el backend
        if (payload.meta && payload.meta.last_views_ts) {
          state.lastViewsTs = payload.meta.last_views_ts;
        }
        renderSummary();
      })

      .catch((error) => {
        console.error("Error cargando ordenes", error);
        setTableMessage(
          error.message || "No fue posible cargar la informacion."
        );
      })
      .finally(() => {
        state.loading = false;
        updatePager();
      });
  }

  function renderTable(rows) {
    if (!rows.length) {
      setTableMessage("Sin ordenes para los filtros seleccionados.");
      return;
    }

    const fragment = document.createDocumentFragment();
    rows.forEach((row) => {
      const tr = document.createElement("tr");
      tr.innerHTML = `
                <td>
                    <div class="fw-bold text-dark">${escapeHtml(
                      row.ALIAS || ""
                    )}-${row.FOLIO}</div>
                    <div class="text-muted small">ID ${row.ID_COMPRA}</div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${escapeHtml(
                      row.RAZON_SOCIAL
                    )}</div>
                    <div class="text-muted small">Proveedor ${escapeHtml(
                      row.NUMERO_PROVEEDOR || ""
                    )}</div>
                </td>
                <td>
                    <div class="fw-semibold text-dark">${escapeHtml(
                      row.NOMBRE_CORTO || "Sin tienda"
                    )}</div>
                    <div class="text-muted small">${
                      row.ID_TIENDA_CONSIGNADA
                        ? "Consignada #" + row.ID_TIENDA_CONSIGNADA
                        : "Central"
                    }</div>
                </td>
                <td>
                    <div class="fw-semibold">${formatDate(row.FECHA)}</div>
                    <div class="text-muted small">${escapeHtml(
                      row.TEMPORADA || ""
                    )}</div>
                </td>
                <td class="text-end fw-bold">${formatMoney(row.TOTAL)}</td>
                <td class="text-center">${row.DIAS_CREDITO || 0} dias</td>
                <td>
                    <div class="text-muted" style="max-width: 260px">${escapeHtml(
                      row.LUGAR_ENTREGA || ""
                    )}</div>
                </td>
                <td>${renderSeen(row.seen)}</td>
                <td class="text-end">
                    <div class="btn-group orders-table-actions">
                        <a class="btn btn-sm btn-primary" href="${buildDetailUrl(
                          row.ID_COMPRA
                        )}" target="_blank" rel="noopener" data-action="detail" data-order="${
        row.ID_COMPRA
      }" data-provider="${row.ID_PROVEEDOR}">
                            <i class="fa-solid fa-arrow-up-right-from-square me-1"></i>Detalle
                        </a>
                        <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown"></button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <button class="dropdown-item" data-action="download" data-format="pdf" data-order="${
                              row.ID_COMPRA
                            }" data-provider="${
        row.ID_PROVEEDOR
      }"><i class="fa-solid fa-file-pdf me-2"></i>Descargar PDF</button>
                            <button class="dropdown-item" data-action="download" data-format="xml" data-order="${
                              row.ID_COMPRA
                            }" data-provider="${
        row.ID_PROVEEDOR
      }"><i class="fa-solid fa-code me-2"></i>Descargar XML</button>
                            <button class="dropdown-item" data-action="download" data-format="csv" data-order="${
                              row.ID_COMPRA
                            }" data-provider="${
        row.ID_PROVEEDOR
      }"><i class="fa-solid fa-file-csv me-2"></i>Descargar CSV</button>
                            <button class="dropdown-item" data-action="download" data-format="xlsx" data-order="${
                              row.ID_COMPRA
                            }" data-provider="${
        row.ID_PROVEEDOR
      }"><i class="fa-solid fa-file-excel me-2"></i>Descargar XLSX</button>
                        </div>
                    </div>
                </td>
            `;
      fragment.appendChild(tr);
    });

    tableBody.innerHTML = "";
    tableBody.appendChild(fragment);
  }

  function renderSeen(seen) {
    if (!seen) {
      return `<span class="orders-status-pill not-seen">Sin revisar</span>`;
    }
    if (seen.seen_by_me) {
      const date = seen.seen_by_me_at ? formatDate(seen.seen_by_me_at) : "";
      return `<span class="orders-status-pill seen-self"><i class="fa-solid fa-user-check"></i>Tu (${date})</span>`;
    }
    if (seen.seen_by_others) {
      const user = escapeHtml(seen.last_view_user || "Otro usuario");
      const date = seen.last_view_at ? formatDate(seen.last_view_at) : "";
      return `<span class="orders-status-pill seen-other"><i class="fa-solid fa-user-group"></i>${user} ${date}</span>`;
    }
    return `<span class="orders-status-pill not-seen">Sin revisar</span>`;
  }

  function renderSummary() {
    if (!summaryEl) {
      return;
    }
    const start = state.total ? (state.page - 1) * state.perPage + 1 : 0;
    const end = state.total
      ? Math.min(state.page * state.perPage, state.total)
      : 0;
    summaryEl.textContent = `Mostrando ${start}-${end} de ${state.total} ordenes`;
  }

  function updatePager() {
    if (prevBtn) {
      prevBtn.disabled = state.page <= 1 || state.loading;
    }
    if (nextBtn) {
      nextBtn.disabled = state.page >= state.totalPages || state.loading;
    }
  }

  function setTableMessage(message) {
    if (!tableBody) {
      return;
    }
    tableBody.innerHTML = `<tr><td colspan="8" class="text-center text-muted py-10">${message}</td></tr>`;
  }

  function formatMoney(value) {
    const number = Number(value || 0);
    return (
      "$ " +
      number.toLocaleString("es-MX", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }

  function formatDate(value) {
    if (!value) {
      return "";
    }
    const date = new Date(value.replace(" ", "T"));
    if (Number.isNaN(date.getTime())) {
      return value;
    }
    return date.toLocaleDateString("es-MX", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    });
  }

  function escapeHtml(value) {
    return (value || "")
      .toString()
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function safeJson(raw) {
    try {
      return raw ? JSON.parse(raw) : null;
    } catch (error) {
      return null;
    }
  }

  function debounce(fn, wait) {
    let timeout;
    return function () {
      const args = arguments;
      clearTimeout(timeout);
      timeout = setTimeout(() => fn.apply(null, args), wait);
    };
  }

  function handleTableClick(event) {
    const action = event.target.closest("[data-action]");
    if (!action) {
      return;
    }
    const orderId = Number(action.getAttribute("data-order"));
    if (!orderId) {
      return;
    }
    if (action.dataset.action === "download") {
      const format = action.getAttribute("data-format");
      const providerId = Number(action.getAttribute("data-provider")) || null;
      if (format) {
        downloadOrder(orderId, providerId, format);
      }
      return;
    }
    if (action.dataset.action === "detail") {
      event.preventDefault();
      const providerId = Number(action.getAttribute("data-provider")) || null;
      const href = action.getAttribute("href");
      markOrder(orderId, providerId, { refresh: true, silent: true })
        .catch(() => null)
        .finally(() => {
          if (href) {
            window.open(href, "_blank");
          }
        });
      return;
    }
  }

  function downloadOrder(orderId, providerId, format) {
    if (!config.endpoints.export) {
      return;
    }
    const url = new URL(config.endpoints.export, window.location.origin);
    url.searchParams.set("id", orderId);
    url.searchParams.set("format", format);

    markOrder(orderId, providerId, { refresh: true, silent: true })
      .catch(() => null)
      .finally(() => {
        window.open(url.toString(), "_blank");
      });
  }

  function markOrder(orderId, providerId, options = {}) {
    if (!config.endpoints.markSeen) {
      return Promise.resolve();
    }
    const { refresh = true, silent = false } = options;
    return fetch(config.endpoints.markSeen, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      body: JSON.stringify({
        order_id: orderId,
        provider_id: providerId,
      }),
    })
      .then(async (response) => {
        if (!response.ok) {
          const payload = await response.json().catch(() => ({}));
          throw new Error(payload.error || "No se pudo registrar la vista.");
        }
        return response.json();
      })
      .then(() => {
        if (refresh) {
          fetchOrders();
        }
      })
      .catch((error) => {
        console.error("No se pudo registrar la vista", error);
        if (!silent) {
          alert(error.message || "No se pudo registrar la vista de la orden.");
        }
        throw error;
      });
  }

  if (searchInput) {
    searchInput.addEventListener(
      "input",
      debounce(function (event) {
        state.search = event.target.value.trim();
        state.page = 1;
        fetchOrders();
      }, 400)
    );
  }

  if (daysSelect) {
    daysSelect.addEventListener("change", function (event) {
      state.days = Number(event.target.value) || 30;
      state.page = 1;
      fetchOrders();
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener("change", function (event) {
      state.perPage = Number(event.target.value) || 25;
      state.page = 1;
      fetchOrders();
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener("click", function () {
      fetchOrders();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener("click", function () {
      if (state.page > 1) {
        state.page -= 1;
        fetchOrders();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", function () {
      if (state.page < state.totalPages) {
        state.page += 1;
        fetchOrders();
      }
    });
  }

  if (tableBody) {
    tableBody.addEventListener("click", handleTableClick);
  }

  function buildDetailUrl(orderId) {
    if (!config.endpoints.detail) {
      return "#";
    }
    const url = new URL(config.endpoints.detail, window.location.origin);
    url.searchParams.set("id", orderId);
    return url.toString();
  }
  function checkForNewOrders() {
    if (!config.endpoints.checkNew) {
      return;
    }

    // Si estamos cargando, no pegamos otra vez
    if (state.loading) {
      return;
    }

    // 🚫 No hacer auto-refresh cuando hay búsqueda activa
    if (state.search && state.search.length > 0) {
      return;
    }

    // 🚫 Solo tiene sentido checar nuevas órdenes en la página 1
    if (state.page !== 1) {
      return;
    }

    // Si aún no tenemos cursor, esperamos al primer fetch
    if (!state.lastOrderId || !state.lastOrderDate) {
      return;
    }

    const params = new URLSearchParams({
      since_id: String(state.lastOrderId),
      since_date: state.lastOrderDate, // 👈 mandar también la fecha
      days: String(state.days),
    });

    fetch(`${config.endpoints.checkNew}?${params.toString()}`, {
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then((response) => response.json())
      .then((payload) => {
        // console.log("checkNew payload", payload);
        if (payload && payload.has_new) {
          fetchOrders();
        }
      })
      .catch((error) => {
        console.error("Error verificando nuevas ordenes", error);
      });
  }

  const AUTO_REFRESH_MS = 45000;

  // Si tenemos endpoint de verificación, usamos polling ligero
  if (config.endpoints.checkNew) {
    setInterval(() => {
      checkForNewOrders();
    }, AUTO_REFRESH_MS);
  } else {
    // fallback: comportamiento anterior (refrescar todo)
    setInterval(() => {
      if (!state.loading) {
        fetchOrders();
      }
    }, AUTO_REFRESH_MS);
  }

  fetchOrders();
})();
