(function () {
  // Loader global
  function loaderShow() {
    if (window.AppLoading && typeof window.AppLoading.show === "function") {
      window.AppLoading.show();
    }
  }

  function loaderHide() {
    if (window.AppLoading && typeof window.AppLoading.hide === "function") {
      window.AppLoading.hide();
    }
  }

  // Config desde el contenedor principal
  const appRoot = document.getElementById("appointment-detail-app");
  let config = {};
  let appointmentId = null;
  let endpoints = {};

  if (appRoot) {
    try {
      config = JSON.parse(appRoot.dataset.config || "{}");
    } catch (e) {
      console.error("Error parseando config de appointment-detail-app", e);
    }
    appointmentId = config.appointmentId || null;
    endpoints = config.endpoints || {};
  }

  // Elementos principales
  const filterBtn = document.getElementById("btnFilterOrders");
  const storeSelect = document.getElementById("filterStoreId");
  const tbody = document.getElementById("ordersAjaxTbody");
  const alertBox = document.getElementById("ordersAjaxAlert");

  const orderFilesModalEl = document.getElementById("orderFilesModal");
  const orderFilesForm = document.getElementById("orderFilesForm");
  const orderFilesIdInput = document.getElementById("orderFilesIdCompra");
  const orderSummaryContainer = document.getElementById(
    "orderSummaryContainer"
  );
  const orderFilesUploadAlert = document.getElementById(
    "orderFilesUploadAlert"
  );
  const orderCommentInput = document.getElementById("orderComment");

  // Inputs de archivos
  const xmlInput = document.getElementById("orderXmlInput");
  const pdfInput = document.getElementById("orderPdfInput");
  const xmlFilesList = document.getElementById("xmlFilesList");
  const pdfFilesList = document.getElementById("pdfFilesList");
  const xmlUnrequestedContainer = document.getElementById(
    "xmlUnrequestedContainer"
  );

  // Filtros
  const aliasInput = document.getElementById("filterAlias");
  const folioInput = document.getElementById("filterFolio");
  const serieInput = document.getElementById("filterSerie");

  // Estado interno
  let currentOrderId = null;
  let xmlSessions = []; // [{ id, file, conceptos: [] }]
  let pdfSessions = []; // [{ id, file }]
  let xmlSessionIdCounter = 1;
  let pdfSessionIdCounter = 1;

  // Helpers
  function showAlert(box, type, message) {
    if (!box) return;
    box.className = "alert alert-" + type;
    box.textContent = message;
    box.classList.remove("d-none");
  }

  function clearAlert(box) {
    if (!box) return;
    box.classList.add("d-none");
    box.textContent = "";
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return "";
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatBytes(bytes) {
    if (!bytes && bytes !== 0) return "";
    const kb = bytes / 1024;
    if (kb < 1024) return kb.toFixed(1) + " KB";
    return (kb / 1024).toFixed(1) + " MB";
  }

  function filesEqual(a, b) {
    return a.name === b.name && a.size === b.size && a.type === b.type;
  }

  // Refrescar tabla de documentos de la cita
  function refreshAppointmentDocuments() {
    if (!appointmentId || !endpoints.documents) return;

    const tableBody = document.querySelector(
      "#appointmentDocumentsTable tbody"
    );
    if (!tableBody) return;

    loaderShow();
    fetch(endpoints.documents, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((r) => r.json())
      .then((data) => {
        if (!data.success) return;
        const docs = data.documents || [];
        if (!docs.length) {
          tableBody.innerHTML =
            '<tr><td colspan="6" class="text-center text-muted py-10">Aún no has agregado órdenes a esta cita.</td></tr>';
          return;
        }

        const rows = docs
          .map((doc) => {
            const requested = parseFloat(doc.requested_total || 0).toFixed(2);
            const invoiced = parseFloat(doc.invoiced_total || 0).toFixed(2);
            const filesCount = parseInt(doc.files_count || 0, 10);

            return `
              <tr>
                  <td>
                      <div class="fw-semibold">
                          ${escapeHtml(
                            doc.document_type || "order"
                          )} #${escapeHtml(doc.document_reference || "")}
                      </div>
                      <div class="text-muted small">
                          ID ${parseInt(doc.document_id || 0, 10)}
                      </div>
                  </td>
                  <td>
                      <div class="fw-semibold">
                          ${escapeHtml(doc.delivery_point_name || "")}
                      </div>
                      <div class="text-muted small">
                          ${escapeHtml(doc.delivery_point_code || "")}
                      </div>
                  </td>
                  <td class="text-end">$${requested}</td>
                  <td class="text-end">$${invoiced}</td>
                  <td class="text-center">
                      <span class="badge badge-light">${filesCount} archivos</span>
                  </td>
                  <td class="text-end">
                      <button type="button"
                              class="btn btn-sm btn-light js-document-summary"
                              data-document-id="${parseInt(doc.id || 0, 10)}"
                              data-order-id="${parseInt(
                                doc.document_id || 0,
                                10
                              )}">
                          Ver / XML &amp; PDF
                      </button>
                  </td>
              </tr>`;
          })
          .join("");

        tableBody.innerHTML = rows;
      })
      .catch((err) => console.error(err))
      .finally(() => loaderHide());
  }

  // 1) Filtrar órdenes
  if (filterBtn && tbody) {
    filterBtn.addEventListener("click", function () {
      clearAlert(alertBox);
      tbody.innerHTML =
        '<tr><td colspan="8" class="text-center text-muted py-8">Cargando órdenes…</td></tr>';

      let storeId = "";
      if (storeSelect) {
        storeId = storeSelect.value;
        if (!storeId) {
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-8">Selecciona tienda.</td></tr>';
          showAlert(alertBox, "warning", "Debes seleccionar tienda");
          return;
        }
      }

      const params = new URLSearchParams();
      if (storeId) params.set("store_id", storeId);
      if (aliasInput && aliasInput.value.trim() !== "") {
        params.set("alias", aliasInput.value.trim());
      }
      if (folioInput && folioInput.value.trim() !== "") {
        params.set("folio", folioInput.value.trim());
      }
      if (serieInput && serieInput.value.trim() !== "") {
        params.set("serie", serieInput.value.trim());
      }

      const url =
        (typeof basePath !== "undefined" && basePath ? basePath : "") +
        "/citas/ordenes-disponibles?" +
        params.toString();

      loaderShow();
      fetch(url, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data.success) {
            tbody.innerHTML =
              '<tr><td colspan="8" class="text-center text-muted py-8">Sin resultados.</td></tr>';
            if (data.message) {
              showAlert(alertBox, "danger", data.message);
            }
            return;
          }

          const orders = data.orders || [];
          if (!orders.length) {
            tbody.innerHTML =
              '<tr><td colspan="8" class="text-center text-muted py-8">No hay órdenes nuevas para estos filtros.</td></tr>';
            return;
          }

          const rows = orders.map((o) => {
            const id = parseInt(o.ID_COMPRA || o.id_compra || 0, 10);
            const folio = o.FOLIO ?? "";
            const nombre = o.NOMBRE_CORTO ?? "";
            const entrega = o.LUGAR_ENTREGA ?? "";
            const alias = o.ALIAS ?? "";
            const temp = o.TEMPORADA ?? "";
            const razon = o.RAZON_SOCIAL ?? "";
            const numProv = o.NUMERO_PROVEEDOR ?? "";
            const fecha = (o.FECHA || "").toString().substring(0, 19);
            const total = parseFloat(o.TOTAL || 0).toFixed(2);

            const kind = (o.ORDER_KIND || "NUEVA").toUpperCase();
            const percentPending =
              typeof o.PERCENT_PENDING !== "undefined"
                ? parseFloat(o.PERCENT_PENDING)
                : null;

            let badgeHtml = "";
            if (kind === "BACKORDER") {
              const pp = !isNaN(percentPending)
                ? " – " + percentPending.toFixed(2) + "% pendiente"
                : "";
              badgeHtml = `
                <div class="badge bg-light-danger text-danger fw-semibold mt-1">
                    Backorder${pp}
                </div>`;
            } else {
              badgeHtml = `
                <div class="badge bg-light-success text-success fw-semibold mt-1">
                    Nueva
                </div>`;
            }

            return `
              <tr data-order-id="${id}">
                  <td>
                      <input type="checkbox"
                          class="form-check-input js-order-check"
                          name="order_ids[]"
                          value="${id}"
                          form="newAppointmentForm">
                  </td>
                  <td>
                      <div class="fw-bold text-dark">#${escapeHtml(folio)}</div>
                      <div class="text-muted small">ID ${id}</div>
                      ${badgeHtml}
                  </td>
                  <td>
                      <div class="fw-semibold">${escapeHtml(nombre)}</div>
                      <div class="text-muted small">${escapeHtml(entrega)}</div>
                  </td>
                  <td>[${escapeHtml(alias)}] ${escapeHtml(temp)}</td>
                  <td>
                      <div class="fw-semibold">${escapeHtml(razon)}</div>
                      <div class="text-muted small">Proveedor ${escapeHtml(
                        numProv
                      )}</div>
                  </td>
                  <td>${escapeHtml(fecha)}</td>
                  <td class="text-end fw-bold">$ ${total}</td>
                  <td class="text-end">
                      <button type="button"
                              class="btn btn-sm btn-light-primary js-order-summary"
                              data-order-id="${id}">
                          <i class="fa-solid fa-file-circle-plus me-1"></i>Ver detalles / XML &amp; PDF
                      </button>
                  </td>
              </tr>`;
          });

          tbody.innerHTML = rows.join("");
        })
        .catch((err) => {
          console.error(err);
          tbody.innerHTML =
            '<tr><td colspan="8" class="text-center text-muted py-8">Error al cargar órdenes.</td></tr>';
          showAlert(
            alertBox,
            "danger",
            "Ocurrió un error al consultar las órdenes."
          );
        })
        .finally(() => loaderHide());
    });
  }

  // Reset modal archivos
  function resetFilesAndColors() {
    if (xmlInput) xmlInput.value = "";
    if (pdfInput) pdfInput.value = "";
    if (orderCommentInput) orderCommentInput.value = "";

    xmlSessions = [];
    pdfSessions = [];

    if (xmlFilesList) {
      xmlFilesList.innerHTML =
        '<span class="fst-italic">Aún no has seleccionado XML.</span>';
    }
    if (pdfFilesList) {
      pdfFilesList.innerHTML =
        '<span class="fst-italic">Aún no has seleccionado PDFs.</span>';
    }
    if (xmlUnrequestedContainer) {
      xmlUnrequestedContainer.innerHTML = "";
    }

    const rows = orderSummaryContainer
      ? orderSummaryContainer.querySelectorAll("tbody tr")
      : [];
    rows.forEach((tr) => {
      tr.style.backgroundColor = "";
      tr.removeAttribute("data-status");
    });
  }

  // Abrir modal resumen / archivos
  if (tbody) {
    tbody.addEventListener("click", function (e) {
      const btn = e.target.closest(".js-order-summary");
      if (!btn) return;

      const orderId = btn.getAttribute("data-order-id");
      if (!orderId) return;

      currentOrderId = orderId;

      if (orderFilesIdInput) orderFilesIdInput.value = orderId;
      if (orderSummaryContainer) {
        orderSummaryContainer.innerHTML =
          '<p class="text-muted mb-4">Cargando información de la orden…</p>';
      }
      clearAlert(orderFilesUploadAlert);
      resetFilesAndColors();

      const url =
        (typeof basePath !== "undefined" && basePath ? basePath : "") +
        "/citas/orden-resumen?id_compra=" +
        encodeURIComponent(orderId);

      loaderShow();
      fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
        .then((r) => r.json())
        .then((data) => {
          if (!data.success) {
            if (orderSummaryContainer) {
              orderSummaryContainer.innerHTML =
                '<p class="text-danger">No se pudo cargar el resumen: ' +
                escapeHtml(data.message || "") +
                "</p>";
            }
            return;
          }

          const h = data.header || {};
          const detalle = data.detalle || [];

          const total = parseFloat(h.TOTAL || 0).toFixed(2);
          const folio = h.FOLIO ?? "";
          const tienda = h.NOMBRE_CORTO ?? "";
          const lugar = h.LUGAR_ENTREGA ?? "";
          const alias = h.ALIAS ?? "";
          const fecha = (h.FECHA || "").toString().substring(0, 19);
          const prov = h.RAZON_SOCIAL ?? "";
          const numProv = h.NUMERO_PROVEEDOR ?? "";

          const rowsDetalle = detalle
            .map((d) => {
              const desc = d.DESCRIPCION ?? "";
              const sku = d.SKU ?? "";
              const unidad = d.UNIDAD_CORTA ?? "";

              const qtyRequested = Number(
                d.QTY_REQUESTED ?? d.CANTIDAD_SOLICITADA ?? 0
              );
              const qtyReceived = Number(d.QTY_RECEIVED ?? 0);
              const qtyPending = Number(
                d.QTY_PENDING ?? Math.max(qtyRequested - qtyReceived, 0)
              );
              const qtyToDeliver = Number(
                d.QTY_TO_DELIVER ?? d.QTY_PENDING ?? d.CANTIDAD_SOLICITADA ?? 0
              );
              const cost = d.COSTO ?? 0;

              return `
                <tr 
                    data-id-articulo="${d.ID_ARTICULO ?? ""}"
                    data-sku="${escapeHtml(sku)}"
                    data-pedida="${qtyRequested}"
                    data-recibida="${qtyReceived}"
                    data-faltante="${qtyPending}"
                    data-unit="${escapeHtml(unidad)}"
                    data-cost="${parseFloat(cost)}"
                >
                    <td>${escapeHtml(sku)}</td>
                    <td>${escapeHtml(desc)}</td>
                    <td class="text-end">${qtyRequested}</td>
                    <td class="text-end">${qtyReceived}</td>
                    <td class="text-end">${qtyPending}</td>
                    <td class="text-end">
                        <input 
                            type="number"
                            step="0.0001"
                            min="0"
                            name="deliver[${d.ID_ARTICULO ?? ""}]"
                            class="form-control form-control-sm text-end js-qty-deliver"
                            value="${qtyToDeliver}"
                        >
                    </td>
                    <td class="text-end">${parseFloat(cost).toFixed(4)}</td>
                    <td>${escapeHtml(unidad)}</td>
                </tr>`;
            })
            .join("");

          if (orderSummaryContainer) {
            orderSummaryContainer.innerHTML = `
              <div class="mb-4">
                  <div class="fw-bold fs-5 mb-1">
                      Orden ${escapeHtml(alias)} - ${escapeHtml(folio)} (ID ${
              h.ID_COMPRA
            })
                  </div>
                  <div class="text-muted fs-8">
                      <strong>Proveedor:</strong> ${escapeHtml(
                        prov
                      )} (${escapeHtml(numProv)})<br>
                      <strong>Punto de entrega:</strong> ${escapeHtml(
                        tienda
                      )}<br>
                      <strong>Dirección:</strong> ${escapeHtml(lugar)}<br>
                      <strong>Fecha:</strong> ${escapeHtml(fecha)}<br>
                      <strong>Total orden:</strong> $ ${total}
                  </div>
              </div>
              <div class="border rounded p-3 bg-white" style="max-height:280px; overflow:auto;">
                  <table class="table table-sm table-row-dashed align-middle mb-0">
                      <thead class="text-muted fw-semibold">
                          <tr>
                              <th>SKU</th>
                              <th>Descripción</th>
                              <th class="text-end">Pedida</th>
                              <th class="text-end">Recibida</th>
                              <th class="text-end">Faltante</th>
                              <th class="text-end">A entregar</th>
                              <th class="text-end">Costo</th>
                              <th>Unidad</th>
                          </tr>
                      </thead>
                      <tbody>
                          ${
                            rowsDetalle ||
                            '<tr><td colspan="8" class="text-center text-muted">Sin detalle disponible.</td></tr>'
                          }
                      </tbody>
                  </table>
              </div>`;
          }
        })
        .catch((err) => {
          console.error(err);
          if (orderSummaryContainer) {
            orderSummaryContainer.innerHTML =
              '<p class="text-danger">Error al cargar información de la orden.</p>';
          }
        })
        .finally(() => loaderHide());

      const modal = new bootstrap.Modal(orderFilesModalEl);
      modal.show();
    });
  }

  // Colores según "A entregar" vs "Faltante"
  function updateRowStatusFromInput(tr) {
    if (!tr) return;
    const pending = Number(tr.dataset.faltante || 0);
    const input = tr.querySelector(".js-qty-deliver");
    if (!input) return;

    const toDeliver = Number(input.value || 0);

    if (!pending && !toDeliver) {
      tr.style.backgroundColor = "";
      tr.removeAttribute("data-status");
      return;
    }

    let bg = "";
    let status = "";

    if (toDeliver === pending) {
      bg = "#dcfce7";
      status = "full";
    } else if (toDeliver < pending) {
      bg = "#fef9c3";
      status = "partial";
    } else if (toDeliver > pending) {
      bg = "#fee2e2";
      status = "over";
    }

    tr.style.backgroundColor = bg;
    tr.setAttribute("data-status", status);
  }

  if (orderSummaryContainer) {
    orderSummaryContainer.addEventListener("input", function (e) {
      if (!e.target.classList.contains("js-qty-deliver")) return;
      const tr = e.target.closest("tr");
      updateRowStatusFromInput(tr);
    });
  }

  // 5) Parseo de XML y cruce con la tabla
  function renderFilesList(container, sessions, emptyText, type) {
    if (!container) return;
    if (!sessions || !sessions.length) {
      container.innerHTML = `<span class="fst-italic">${emptyText}</span>`;
      return;
    }

    const lis = sessions
      .map((s) => {
        const f = s.file;
        return `
          <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2">
            <span class="text-truncate" style="max-width: 260px;">
              <i class="fa-solid fa-file me-1"></i>${escapeHtml(f.name)}
            </span>
            <span class="d-flex align-items-center gap-2">
              <span class="text-muted small">${formatBytes(f.size)}</span>
              <button type="button"
                      class="btn btn-sm btn-icon btn-light-danger border-0 js-remove-file"
                      data-file-id="${s.id}"
                      data-file-type="${type}"
                      title="Eliminar archivo">
                <i class="fa-solid fa-xmark"></i>
              </button>
            </span>
          </li>`;
      })
      .join("");

    container.innerHTML = `
      <ul class="list-group list-group-flush border rounded-3">
        ${lis}
      </ul>`;
  }

  function extractConceptsFromXml(xmlText) {
    const conceptos = [];
    try {
      const parser = new DOMParser();
      const doc = parser.parseFromString(xmlText, "text/xml");

      let nodes = Array.from(
        doc.getElementsByTagNameNS("http://www.sat.gob.mx/cfd/4", "Concepto")
      );
      if (!nodes.length)
        nodes = Array.from(doc.getElementsByTagName("cfdi:Concepto"));
      if (!nodes.length)
        nodes = Array.from(doc.getElementsByTagName("Concepto"));

      nodes.forEach((n) => {
        const sku = n.getAttribute("NoIdentificacion") || "";
        const desc = n.getAttribute("Descripcion") || "";
        const unidad =
          n.getAttribute("Unidad") || n.getAttribute("ClaveUnidad") || "";
        const qty = parseFloat(n.getAttribute("Cantidad") || "0") || 0;

        if (!sku && !desc) return;

        conceptos.push({
          sku: sku.trim(),
          descripcion: desc.trim(),
          unidad: unidad.trim(),
          cantidad: qty,
        });
      });
    } catch (e) {
      console.error("Error parseando XML:", e);
    }
    return conceptos;
  }

  function recalculateXmlEffects() {
    if (!orderSummaryContainer) return;

    const rows = orderSummaryContainer.querySelectorAll("tbody tr");
    if (!rows.length) return;

    const aggregated = {};

    xmlSessions.forEach((session) => {
      const file = session.file;
      const conceptos = session.conceptos || [];
      conceptos.forEach((c) => {
        const key = c.sku || c.descripcion;
        if (!key) return;
        if (!aggregated[key]) {
          aggregated[key] = {
            sku: c.sku,
            descripcion: c.descripcion,
            total: 0,
            unidades: new Set(),
            files: new Set(),
          };
        }
        aggregated[key].total += c.cantidad;
        if (c.unidad) aggregated[key].unidades.add(c.unidad);
        aggregated[key].files.add(file.name);
      });
    });

    rows.forEach((tr) => {
      tr.style.backgroundColor = "";
      tr.removeAttribute("data-status");
    });

    rows.forEach((tr) => {
      const sku = (tr.dataset.sku || "").trim();
      if (!sku) return;

      const agg = aggregated[sku];
      if (!agg) return;

      const pending = Number(tr.dataset.faltante || 0);
      const xmlQty = agg.total;

      const input = tr.querySelector(".js-qty-deliver");
      if (input) input.value = xmlQty;

      let bg = "";
      let status = "";
      if (xmlQty === pending) {
        bg = "#dcfce7";
        status = "full";
      } else if (xmlQty < pending) {
        bg = "#fef9c3";
        status = "partial";
      } else if (xmlQty > pending) {
        bg = "#fee2e2";
        status = "over";
      }

      tr.style.backgroundColor = bg;
      tr.setAttribute("data-status", status);

      delete aggregated[sku];
    });

    if (!xmlUnrequestedContainer) return;

    const leftovers = Object.values(aggregated);
    if (!leftovers.length) {
      xmlUnrequestedContainer.innerHTML = "";
      return;
    }

    const rowsExtra = leftovers
      .map((item) => {
        const unidades = Array.from(item.unidades).join(", ");
        const filesNames = Array.from(item.files).join(", ");
        return `
          <tr style="background:#111827;color:#f9fafb;">
              <td>${escapeHtml(item.sku || "")}</td>
              <td>${escapeHtml(item.descripcion || "")}</td>
              <td class="text-end">${item.total}</td>
              <td>${escapeHtml(unidades || "")}</td>
              <td class="small">${escapeHtml(filesNames || "")}</td>
          </tr>`;
      })
      .join("");

    xmlUnrequestedContainer.innerHTML = `
      <div class="mt-3">
          <div class="fw-semibold mb-1">
              Artículos en XML que no están en la orden (⬛)
          </div>
          <div class="border rounded-3" style="max-height:180px; overflow:auto;">
              <table class="table table-sm mb-0">
                  <thead class="text-muted fw-semibold">
                      <tr>
                          <th>SKU</th>
                          <th>Descripción</th>
                          <th class="text-end">Cantidad XML</th>
                          <th>Unidad</th>
                          <th>Archivos</th>
                      </tr>
                  </thead>
                  <tbody>
                      ${rowsExtra}
                  </tbody>
              </table>
          </div>
      </div>`;
  }

  // Nuevos XML
  if (xmlInput) {
    xmlInput.addEventListener("change", function (e) {
      const files = Array.from(e.target.files || []);
      if (!files.length) return;

      xmlInput.value = "";

      files.forEach((file) => {
        const already = xmlSessions.some((s) => filesEqual(s.file, file));
        if (already) {
          console.warn("XML duplicado ignorado:", file.name);
          return;
        }

        const sessionId = xmlSessionIdCounter++;
        const session = { id: sessionId, file, conceptos: [] };
        xmlSessions.push(session);

        const reader = new FileReader();
        reader.onload = () => {
          session.conceptos = extractConceptsFromXml(reader.result || "");
          recalculateXmlEffects();
        };
        reader.onerror = () => {
          console.error("Error leyendo XML:", file.name);
        };
        reader.readAsText(file);
      });

      renderFilesList(
        xmlFilesList,
        xmlSessions,
        "Aún no has seleccionado XML.",
        "xml"
      );
    });
  }

  // Nuevos PDFs
  if (pdfInput) {
    pdfInput.addEventListener("change", function (e) {
      const files = Array.from(e.target.files || []);
      if (!files.length) return;

      pdfInput.value = "";

      files.forEach((file) => {
        const already = pdfSessions.some((s) => filesEqual(s.file, file));
        if (already) {
          console.warn("PDF duplicado ignorado:", file.name);
          return;
        }

        const sessionId = pdfSessionIdCounter++;
        pdfSessions.push({ id: sessionId, file });
      });

      renderFilesList(
        pdfFilesList,
        pdfSessions,
        "Aún no has seleccionado PDFs.",
        "pdf"
      );
    });
  }

  // Eliminar archivos de la lista
  document.addEventListener("click", function (e) {
    const btn = e.target.closest(".js-remove-file");
    if (!btn) return;

    const fileId = parseInt(btn.getAttribute("data-file-id") || "0", 10);
    const fileType = btn.getAttribute("data-file-type");
    if (!fileId || !fileType) return;

    if (fileType === "xml") {
      xmlSessions = xmlSessions.filter((s) => s.id !== fileId);
      renderFilesList(
        xmlFilesList,
        xmlSessions,
        "Aún no has seleccionado XML.",
        "xml"
      );
      recalculateXmlEffects();
    } else if (fileType === "pdf") {
      pdfSessions = pdfSessions.filter((s) => s.id !== fileId);
      renderFilesList(
        pdfFilesList,
        pdfSessions,
        "Aún no has seleccionado PDFs.",
        "pdf"
      );
    }
  });

  // 6) Envío del formulario (subir archivos + items + comentario)
  if (orderFilesForm) {
    orderFilesForm.addEventListener("submit", function (e) {
      e.preventDefault();
      clearAlert(orderFilesUploadAlert);

      const url = orderFilesForm.getAttribute("action");
      if (!url) {
        showAlert(
          orderFilesUploadAlert,
          "danger",
          "No se encontró la URL de envío del formulario."
        );
        return;
      }

      const items = [];
      if (orderSummaryContainer) {
        const rows = orderSummaryContainer.querySelectorAll("tbody tr");

        // Aseguramos status de todas las filas antes de leerlas
        rows.forEach((tr) => updateRowStatusFromInput(tr));

        rows.forEach((tr) => {
          const idArt = tr.dataset.idArticulo || "";
          if (!idArt) return;

          const sku = tr.dataset.sku || "";
          const qtyPedida = Number(tr.dataset.pedida || 0);
          const qtyRecibida = Number(tr.dataset.recibida || 0);
          const qtyFaltante = Number(tr.dataset.faltante || 0);
          const unit = tr.dataset.unit || "";
          const cost = Number(tr.dataset.cost || 0);
          const input = tr.querySelector(".js-qty-deliver");
          const qtyEntregar = input ? Number(input.value || 0) : 0;
          let status = tr.dataset.status || null;

          // Fallback en front si por alguna razón no hay status
          if (!status && qtyFaltante > 0) {
            if (qtyEntregar === qtyFaltante) status = "full";
            else if (qtyEntregar < qtyFaltante) status = "partial";
            else if (qtyEntregar > qtyFaltante) status = "over";
          }

          items.push({
            id_articulo: idArt,
            sku,
            qty_pedida: qtyPedida,
            qty_recibida: qtyRecibida,
            qty_faltante: qtyFaltante,
            qty_entregar: qtyEntregar,
            unit,
            cost,
            status,
          });
        });
      }

      if (xmlInput) xmlInput.value = "";
      if (pdfInput) pdfInput.value = "";

      const formData = new FormData(orderFilesForm);
      formData.append("delivery_items", JSON.stringify(items));

      xmlSessions.forEach((session) => {
        formData.append("xml_files[]", session.file);
      });

      pdfSessions.forEach((session) => {
        formData.append("pdf_files[]", session.file);
      });

      loaderShow();
      fetch(url, {
        method: "POST",
        body: formData,
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((r) => r.json())
        .then((data) => {
          if (!data.success) {
            showAlert(
              orderFilesUploadAlert,
              "danger",
              data.message || "No se pudieron guardar todos los archivos."
            );
          } else {
            showAlert(
              orderFilesUploadAlert,
              "success",
              "Archivos y cantidades guardados correctamente."
            );

            refreshAppointmentDocuments();

            const modalInstance =
              bootstrap.Modal.getInstance(orderFilesModalEl);
            setTimeout(function () {
              resetFilesAndColors();
              if (orderSummaryContainer) {
                orderSummaryContainer.innerHTML =
                  '<p class="text-muted mb-4">Selecciona otra orden para ver el resumen…</p>';
              }
              if (modalInstance) modalInstance.hide();
            }, 600);
          }

          const id = orderFilesIdInput ? orderFilesIdInput.value : "";
          if (id) {
            const chk = document.querySelector(
              '.js-order-check[value="' + id + '"]'
            );
            if (chk) chk.checked = true;
          }
        })
        .catch((err) => {
          console.error(err);
          showAlert(
            orderFilesUploadAlert,
            "danger",
            "Ocurrió un error al subir los archivos."
          );
        })
        .finally(() => loaderHide());
    });
  }
})();
