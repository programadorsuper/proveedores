
(function () {
    const filterBtn      = document.getElementById('btnFilterOrders');
    const storeSelect    = document.getElementById('filterStoreId');
    const seasonSelect   = document.getElementById('filterSeasonType');
    const tbody          = document.getElementById('ordersAjaxTbody');
    const alertBox       = document.getElementById('ordersAjaxAlert');

    const orderFilesModalEl = document.getElementById('orderFilesModal');
    const orderFilesForm    = document.getElementById('orderFilesForm');
    const orderFilesIdInput = document.getElementById('orderFilesIdCompra');
    const orderSummaryContainer = document.getElementById('orderSummaryContainer');
    const orderFilesUploadAlert = document.getElementById('orderFilesUploadAlert');

    function showAlert(box, type, message) {
        if (!box) return;
        box.className = 'alert alert-' + type;
        box.textContent = message;
        box.classList.remove('d-none');
    }

    function clearAlert(box) {
        if (!box) return;
        box.classList.add('d-none');
        box.textContent = '';
    }

    // --- 1) Filtrar órdenes por AJAX ---
    if (filterBtn) {
        filterBtn.addEventListener('click', function () {
            clearAlert(alertBox);
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-8">Cargando órdenes…</td></tr>';

            const storeId    = storeSelect.value;
            const seasonType = seasonSelect.value;

            if (!storeId || !seasonType) {
                tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-8">Selecciona tienda y tipo de temporada.</td></tr>';
                showAlert(alertBox, 'warning', 'Debes seleccionar tienda y tipo de temporada.');
                return;
            }

            const url = (basePath ? basePath : '') +
                '/citas/ordenes-disponibles?store_id=' + encodeURIComponent(storeId) +
                '&season_type=' + encodeURIComponent(seasonType);

            fetch(url, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-8">Sin resultados.</td></tr>';
                        if (data.message) {
                            showAlert(alertBox, 'danger', data.message);
                        }
                        return;
                    }

                    const orders = data.orders || [];
                    if (!orders.length) {
                        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-8">No hay órdenes nuevas para estos filtros.</td></tr>';
                        return;
                    }

                    const rows = orders.map(o => {
                        const id     = parseInt(o.ID_COMPRA || o.id_compra || 0, 10);
                        const folio  = o.FOLIO ?? '';
                        const nombre = o.NOMBRE_CORTO ?? '';
                        const entrega= o.LUGAR_ENTREGA ?? '';
                        const alias  = o.ALIAS ?? '';
                        const temp   = o.TEMPORADA ?? '';
                        const razon  = o.RAZON_SOCIAL ?? '';
                        const numProv= o.NUMERO_PROVEEDOR ?? '';
                        const fecha  = (o.FECHA || '').toString().substring(0, 19);
                        const total  = parseFloat(o.TOTAL || 0).toFixed(2);

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
        <div class="fw-bold text-dark">#${folio}</div>
        <div class="text-muted small">ID ${id}</div>
    </td>
    <td>
        <div class="fw-semibold">${escapeHtml(nombre)}</div>
        <div class="text-muted small">${escapeHtml(entrega)}</div>
    </td>
    <td>[${escapeHtml(alias)}] ${escapeHtml(temp)}</td>
    <td>
        <div class="fw-semibold">${escapeHtml(razon)}</div>
        <div class="text-muted small">Proveedor ${escapeHtml(numProv)}</div>
    </td>
    <td>${escapeHtml(fecha)}</td>
    <td class="text-end fw-bold">$ ${total}</td>
    <td class="text-end">
        <button type="button"
                class="btn btn-sm btn-light-primary js-order-summary"
                data-order-id="${id}">
            <i class="fa-solid fa-file-circle-plus me-1"></i>Ver / XML &amp; PDF
        </button>
    </td>
</tr>`;
                    });

                    tbody.innerHTML = rows.join('');
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-8">Error al cargar órdenes.</td></tr>';
                    showAlert(alertBox, 'danger', 'Ocurrió un error al consultar las órdenes.');
                });
        });
    }

    // Escape simple para evitar XSS
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // --- 2) Abrir modal de resumen / archivos al pulsar "Ver / XML & PDF" ---
    if (tbody) {
        tbody.addEventListener('click', function (e) {
            const btn = e.target.closest('.js-order-summary');
            if (!btn) return;

            const orderId = btn.getAttribute('data-order-id');
            if (!orderId) return;

            // Limpia estado previo
            orderFilesIdInput.value = orderId;
            orderSummaryContainer.innerHTML = '<p class="text-muted mb-4">Cargando información de la orden…</p>';
            clearAlert(orderFilesUploadAlert);
            document.getElementById('orderFilesInput').value = '';

            const url = (basePath ? basePath : '') +
                '/citas/orden-resumen?id_compra=' + encodeURIComponent(orderId);

            fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest'}})
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        orderSummaryContainer.innerHTML =
                            '<p class="text-danger">No se pudo cargar el resumen: ' + escapeHtml(data.message || '') + '</p>';
                        return;
                    }

                    const h = data.header || {};
                    const detalle = data.detalle || [];

                    const total = parseFloat(h.TOTAL || 0).toFixed(2);
                    const folio = h.FOLIO ?? '';
                    const tienda= h.NOMBRE_CORTO ?? '';
                    const lugar = h.LUGAR_ENTREGA ?? '';
                    const alias = h.ALIAS ?? '';
                    const temp  = h.TEMPORADA ?? '';
                    const fecha = (h.FECHA || '').toString().substring(0,19);
                    const prov  = h.RAZON_SOCIAL ?? '';
                    const numProv = h.NUMERO_PROVEEDOR ?? '';

                    const rowsDetalle = detalle.map(d => {
                        const desc = d.DESCRIPCION ?? '';
                        const sku  = d.SKU ?? '';
                        const cant = d.CANTIDAD_SOLICITADA ?? d.CANTIDAD ?? 0;
                        const cost = d.COSTO ?? 0;
                        const unidad = d.UNIDAD_CORTA ?? '';
                        return `
<tr>
    <td>${escapeHtml(sku)}</td>
    <td>${escapeHtml(desc)}</td>
    <td class="text-end">${cant}</td>
    <td class="text-end">${parseFloat(cost).toFixed(2)}</td>
    <td>${escapeHtml(unidad)}</td>
</tr>`;
                    }).join('');

                    orderSummaryContainer.innerHTML = `
<div class="mb-4">
    <div class="fw-bold fs-5 mb-1">Orden #${folio} (ID ${h.ID_COMPRA})</div>
    <div class="text-muted fs-8">
        <strong>Proveedor:</strong> ${escapeHtml(prov)} (${escapeHtml(numProv)})<br>
        <strong>Punto entrega:</strong> ${escapeHtml(tienda)}<br>
        <strong>Dirección:</strong> ${escapeHtml(lugar)}<br>
        <strong>Alias / temporada:</strong> [${escapeHtml(alias)}] ${escapeHtml(temp)}<br>
        <strong>Fecha:</strong> ${escapeHtml(fecha)}<br>
        <strong>Total:</strong> $ ${total}
    </div>
</div>
<div class="border rounded p-3" style="max-height:280px; overflow:auto;">
    <table class="table table-sm table-row-dashed align-middle mb-0">
        <thead class="text-muted fw-semibold">
            <tr>
                <th>SKU</th>
                <th>Descripción</th>
                <th class="text-end">Cant.</th>
                <th class="text-end">Costo</th>
                <th>Unidad</th>
            </tr>
        </thead>
        <tbody>
            ${rowsDetalle || '<tr><td colspan="5" class="text-center text-muted">Sin detalle disponible.</td></tr>'}
        </tbody>
    </table>
</div>`;
                })
                .catch(err => {
                    console.error(err);
                    orderSummaryContainer.innerHTML =
                        '<p class="text-danger">Error al cargar información de la orden.</p>';
                });

            const modal = new bootstrap.Modal(orderFilesModalEl);
            modal.show();
        });
    }

    // --- 3) Subir archivos XML/PDF para la orden ---
    if (orderFilesForm) {
        orderFilesForm.addEventListener('submit', function (e) {
            e.preventDefault();
            clearAlert(orderFilesUploadAlert);

            const formData = new FormData(orderFilesForm);
            const url = orderFilesForm.getAttribute('action');

            fetch(url, {
                method: 'POST',
                body: formData,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) {
                        showAlert(orderFilesUploadAlert, 'danger',
                            (data.message || 'No se pudieron guardar todos los archivos.'));
                    } else {
                        showAlert(orderFilesUploadAlert, 'success', 'Archivos guardados correctamente.');
                    }

                    // Marcar/asegurar que la orden quede seleccionada en el form principal
                    const id = orderFilesIdInput.value;
                    if (id) {
                        const chk = document.querySelector('.js-order-check[value="' + id + '"]');
                        if (chk) chk.checked = true;
                    }
                })
                .catch(err => {
                    console.error(err);
                    showAlert(orderFilesUploadAlert, 'danger',
                        'Ocurrió un error al subir los archivos.');
                });
        });
    }
})();