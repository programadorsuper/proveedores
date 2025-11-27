// /assets/js/orders-backorders.js
(function () {
  function safeJson(raw, fallback = null) {
    if (!raw) return fallback;
    try {
      return JSON.parse(raw);
    } catch (e) {
      console.warn("safeJson: JSON inválido", raw);
      return fallback;
    }
  }

  function escapeHtml(str) {
    if (str == null) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, "&quot;");
  }

  function formatNumber(num) {
    if (typeof Intl !== "undefined" && Intl.NumberFormat) {
      return new Intl.NumberFormat("es-MX", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(num);
    }
    return Number(num).toFixed(2);
  }

  function debounce(fn, delay) {
    let timer = null;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }
  const app = document.getElementById("backorders-app");
  if (!app) {
    return;
  }

  const config = safeJson(app.dataset.config);
  if (!config || !config.endpoints || !config.endpoints.list) {
    console.error("Backorders: configuración inválida");
    return;
  }

  const tableBody = app.querySelector("[data-backorders-body]");
  const searchInput = app.querySelector("[data-backorders-search]");
  const perPageSelect = app.querySelector("[data-backorders-per-page]");
  const refreshBtn = app.querySelector("[data-backorders-refresh]");
  const prevBtn = app.querySelector("[data-backorders-prev]");
  const nextBtn = app.querySelector("[data-backorders-next]");
  const summaryEl = app.querySelector("[data-backorders-summary]");
  let page = 1;
  let total = 0;
  let perPage = parseInt(
    perPageSelect?.value || config.defaultPerPage || 25,
    10
  );
  let currentSearch = "";
  let loading = false;
  let lastController = null;
  let lastCount = 0;
  function setLoading(state) {
    loading = state;

    // Loader local (opcional)
    if (state) {
      app.classList.add("opacity-75");
    } else {
      app.classList.remove("opacity-75");
    }

    // Loader global
    if (window.AppLoading) {
      if (state) {
        window.AppLoading.show();
      } else {
        window.AppLoading.hide();
      }
    }
  }

  function buildQuery() {
    const params = new URLSearchParams();
    params.set("page", String(page));
    params.set("per_page", String(perPage));
    if (currentSearch) {
      params.set("search", currentSearch);
    }
    return "?" + params.toString();
  }

  function renderEmpty() {
    if (!tableBody) return;
    tableBody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center text-muted py-10">
          Sin backorders para los últimos 2 meses.
        </td>
      </tr>
    `;
  }

  function renderRows(items) {
    if (!tableBody) return;

    if (!items || !items.length) {
      renderEmpty();
      return;
    }

    const rowsHtml = items
      .map((row) => {
        const idCompra = Number(row.ID_COMPRA || row.id_compra || 0);
        const folio = row.FOLIO ?? row.folio ?? "";
        const razonSocial = row.RAZON_SOCIAL ?? row.razon_social ?? "";
        const numProv = row.NUMERO_PROVEEDOR ?? row.numero_proveedor ?? "";
        const tienda = row.NOMBRE_CORTO ?? row.nombre_corto ?? "N/D";
        const alias = (row.ALIAS ?? row.alias ?? "").trim();
        const temporada = (row.TEMPORADA ?? row.temporada ?? "").trim();
        const fechaRaw = String(row.FECHA ?? row.fecha ?? "");
        const fecha = fechaRaw.substring(0, 10);

        const totalSolicitado = Number(
          row.TOTAL_SOLICITADO ?? row.total_solicitado ?? 0
        );
        const pendiente = Number(row.PENDING_TOTAL ?? row.pending_total ?? 0);
        const percent = Number(
          row.PERCENT_RECEIVED ?? row.percent_received ?? 0
        );

        const seasonLabel =
          alias !== "" ? `[${alias}] ${temporada}` : temporada || "N/D";

        const percentText = percent.toFixed(2) + "%";
        const progressWidth = Math.min(100, Math.max(0, percent));
        const progressClass = percent >= 90 ? "bg-success" : "bg-warning";

        const detailUrl =
          basePath + "/ordenes/backorder/detalle?id=" + idCompra;

        return `
          <tr>
            <td>
              <div class="fw-bold text-dark">#${escapeHtml(folio)}</div>
              <div class="text-muted small">ID ${idCompra}</div>
            </td>
            <td>
              <div class="fw-semibold">${escapeHtml(razonSocial)}</div>
              <div class="text-muted small">Proveedor ${escapeHtml(
                numProv
              )}</div>
            </td>
            <td>${escapeHtml(tienda)}</td>
            <td>${escapeHtml(seasonLabel)}</td>
            <td>${escapeHtml(fecha)}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="progress w-150px">
                  <div class="progress-bar ${progressClass}" role="progressbar"
                       style="width: ${progressWidth}%;"></div>
                </div>
                <span class="fw-semibold">${percentText}</span>
              </div>
              <div class="text-muted small">
                Pendiente: ${formatNumber(pendiente)}
              </div>
            </td>
            <td class="text-end">
              <a class="btn btn-sm btn-light-primary"
                 href="${escapeAttr(detailUrl)}">
                <i class="fa-solid fa-eye me-1"></i>Ver detalle
              </a>
            </td>
          </tr>
        `;
      })
      .join("");

    tableBody.innerHTML = rowsHtml;
  }

  function updateSummary(itemsCount) {
    if (!summaryEl) return;

    if (!total) {
      summaryEl.textContent = "Sin backorders para los últimos 2 meses.";
      return;
    }

    const offset = (page - 1) * perPage;
    const start = offset + 1;
    const end = offset + itemsCount;

    summaryEl.textContent = `Mostrando ${start}–${end} de ${total} backorders`;
  }

  function updatePaginationButtons(count) {
    lastCount = count;
    console.log({ page, perPage, total, count });

    const hasPrev = page > 1;
    const hasNext = page * perPage < total;

    if (prevBtn) prevBtn.disabled = !hasPrev;
    if (nextBtn) nextBtn.disabled = !hasNext || !count;
  }

  async function loadBackorders(options) {
    const opts = options || {};
    if (opts.resetPage) {
      page = 1;
    }

    // Cancelamos petición anterior si sigue viva
    if (lastController) {
      lastController.abort();
    }
    const controller = new AbortController();
    lastController = controller;

    setLoading(true);

    try {
      const query = buildQuery();
      const response = await fetch(config.endpoints.list + query, {
        method: "GET",
        signal: controller.signal,
        headers: {
          Accept: "application/json",
        },
      });

      if (!response.ok) {
        throw new Error("Error al cargar backorders");
      }

      const data = await response.json();
      const items = data.items || [];
      total = Number(data.total || 0);
      page = Number(data.page || page);
      perPage = Number(data.per_page || perPage);

      renderRows(items);
      updateSummary(items.length);
      updatePaginationButtons(items.length);
    } catch (err) {
      if (err.name === "AbortError") {
        return;
      }
      console.error(err);
      renderEmpty();
      if (summaryEl) {
        summaryEl.textContent = "Ocurrió un error al cargar los backorders.";
      }
    } finally {
      setLoading(false);
    }
  }

  function debounce(fn, delay) {
    let timer = null;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function escapeHtml(str) {
    if (str == null) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function escapeAttr(str) {
    return escapeHtml(str).replace(/"/g, "&quot;");
  }

  function formatNumber(num) {
    if (typeof Intl !== "undefined" && Intl.NumberFormat) {
      return new Intl.NumberFormat("es-MX", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      }).format(num);
    }
    return Number(num).toFixed(2);
  }

  // Listeners
  if (searchInput) {
    const onSearch = debounce(() => {
      currentSearch = searchInput.value.trim();
      loadBackorders({ resetPage: true });
    }, 400);

    searchInput.addEventListener("input", onSearch);
    searchInput.addEventListener("keyup", (ev) => {
      if (ev.key === "Enter") {
        currentSearch = searchInput.value.trim();
        loadBackorders({ resetPage: true });
      }
    });
  }

  if (perPageSelect) {
    perPageSelect.addEventListener("change", () => {
      perPage = parseInt(perPageSelect.value, 10) || 25;
      loadBackorders({ resetPage: true });
    });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener("click", () => {
      loadBackorders({ resetPage: true });
    });
  }

  if (prevBtn) {
    console.log("Prev button found");
    prevBtn.addEventListener("click", () => {
      if (page > 1 && !loading) {
        page -= 1;
        loadBackorders();
      }
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", () => {
      if (!loading && page * perPage < total) {
        page += 1;
        loadBackorders();
      }
    });
  }

  // Primer carga
  loadBackorders({ resetPage: true });
})();
