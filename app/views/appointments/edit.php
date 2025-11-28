<?php
$appointment = $appointment ?? [];
$documents   = $documents ?? [];
?>
<div id="appointment-detail-app"
     data-config='<?= json_encode([
         "appointmentId" => (int)$appointment["id"],
         "storeId"       => (int)$appointment["delivery_point_code"],
         "endpoints"     => [
             "ordersSearch" => ($basePath ?: "") . "/citas/ordenes-disponibles",   // GET
             "orderSummary" => ($basePath ?: "") . "/citas/orden-resumen",        // GET
             "uploadFiles"  => ($basePath ?: "") . "/citas/orden-archivos",       // POST
             "deleteFile"   => ($basePath ?: "") . "/citas/orden-archivos-eliminar", // POST (futuro)
             "documents"    => ($basePath ?: "") . "/citas/" . (int)$appointment["id"] . "/documentos" // GET
         ]
     ], JSON_UNESCAPED_SLASHES) ?>'>

    <!-- HEADER -->
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-6">
        <div>
            <h1 class="fs-2hx fw-bold mb-1">
                Cita <?= htmlspecialchars($appointment['folio'] ?? ('CITA-' . $appointment['id']), ENT_QUOTES, 'UTF-8') ?>
            </h1>
            <div class="text-muted fs-7">
                Proveedor:
                <strong><?= htmlspecialchars($appointment['provider_name'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></strong>
                · Punto de entrega:
                <strong><?= htmlspecialchars($appointment['delivery_point_name'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></strong>
                (<?= htmlspecialchars($appointment['delivery_point_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>)
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= ($basePath !== '' ? $basePath : '') . '/citas' ?>" class="btn btn-light">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <div class="row g-5">
        <!-- Columna izquierda: datos generales -->
        <div class="col-xl-4">
            <div class="card border-0 shadow-sm mb-5">
                <div class="card-header">
                    <h3 class="card-title fw-bold mb-0">Datos generales</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="fw-semibold text-muted">Fecha</div>
                        <div><?= htmlspecialchars($appointment['appointment_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="fw-semibold text-muted">Horario</div>
                        <div>
                            <?= htmlspecialchars(($appointment['slot_start'] ?? '') . ' - ' . ($appointment['slot_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="fw-semibold text-muted">Estatus</div>
                        <span class="badge badge-light-primary">
                            <?= htmlspecialchars($appointment['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <div>
                        <div class="fw-semibold text-muted">Creada por</div>
                        <div><?= htmlspecialchars($appointment['created_by_username'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-muted small">
                            <?= htmlspecialchars($appointment['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna derecha: documentos de la cita -->
        <div class="col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title fw-bold mb-0">Documentos de la cita</h3>
                        <div class="text-muted fs-8">
                            Órdenes y backorders incluidos en esta cita.
                        </div>
                    </div>
                    <div>
                        <button type="button"
                                class="btn btn-sm btn-light-primary"
                                data-bs-toggle="modal"
                                data-bs-target="#ordersSearchModal">
                            <i class="fa-solid fa-file-circle-plus me-1"></i>Agregar orden
                        </button>
                    </div>
                </div>
                <div class="card-body" id="appointmentDocumentsContainer">
                    <table class="table table-row-dashed align-middle" id="appointmentDocumentsTable">
                        <thead class="text-muted fw-semibold">
                        <tr>
                            <th>Documento</th>
                            <th>Punto de entrega</th>
                            <th class="text-end">Solicitado</th>
                            <th class="text-end">Facturado</th>
                            <th class="text-center">Archivos</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($documents)): ?>
                            <?php foreach ($documents as $doc): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($doc['document_type'] ?? 'order', ENT_QUOTES, 'UTF-8') ?>
                                            #<?= htmlspecialchars($doc['document_reference'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="text-muted small">
                                            ID <?= (int)($doc['document_id'] ?? 0) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold">
                                            <?= htmlspecialchars($doc['delivery_point_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars($doc['delivery_point_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td class="text-end">
                                        $<?= number_format((float)($doc['requested_total'] ?? 0), 2) ?>
                                    </td>
                                    <td class="text-end">
                                        $<?= number_format((float)($doc['invoiced_total'] ?? 0), 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge badge-light">
                                            <?= (int)($doc['files_count'] ?? 0) ?> archivos
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <button type="button"
                                                class="btn btn-sm btn-light js-document-summary"
                                                data-document-id="<?= (int)$doc['id'] ?>"
                                                data-order-id="<?= (int)$doc['document_id'] ?>">
                                            Ver / XML &amp; PDF
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-10">
                                    Aún no has agregado órdenes a esta cita.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Buscar órdenes -->
<div class="modal fade" id="ordersSearchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Agregar órdenes a la cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-7 mb-4">
                    Esta cita está ligada al punto de entrega:
                    <strong><?= htmlspecialchars($appointment['delivery_point_name'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></strong>
                    (<?= htmlspecialchars($appointment['delivery_point_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>).<br>
                    Usa los filtros para buscar órdenes nuevas o backorders de este punto de entrega.
                </p>

                <input type="hidden"
                       id="filterStoreId"
                       value="<?= (int)($appointment['delivery_point_code'] ?? 0) ?>">

                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Serie</label>
                        <input type="text"
                               id="filterAlias"
                               class="form-control"
                               placeholder="Ej. E, V, G…">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Folio</label>
                        <input type="number"
                               id="filterFolio"
                               class="form-control"
                               placeholder="Folio exacto">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="button" id="btnFilterOrders" class="btn btn-primary w-100">
                            <i class="fa-solid fa-filter me-2"></i>Filtrar órdenes
                        </button>
                    </div>
                </div>

                <div id="ordersAjaxAlert" class="alert d-none mb-3"></div>

                <div class="border rounded p-3" style="max-height: 420px; overflow:auto;">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead class="text-muted fw-semibold">
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Folio</th>
                            <th>Punto de entrega</th>
                            <th>Alias</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody id="ordersAjaxTbody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-8">
                                Usa los filtros de arriba para cargar órdenes.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-muted fs-8">
                    <strong>Nota:</strong> se listan órdenes sin entrada (nuevas) o con entrada parcial
                    (backorders), autorizadas, de los últimos 2 meses, y que no estén reservadas en otra cita.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: Resumen de orden + XML/PDF -->
<div class="modal fade" id="orderFilesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form id="orderFilesForm"
                  method="post"
                  enctype="multipart/form-data"
                  action="<?= htmlspecialchars(($basePath !== '' ? $basePath : '') . '/citas/orden-archivos', ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Resumen de orden y archivos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body pt-2">
                    <input type="hidden" name="id_compra" id="orderFilesIdCompra">
                    <input type="hidden" name="appointment_id" value="<?= (int)$appointment['id'] ?>">

                    <div id="orderSummaryContainer">
                        <p class="text-muted mb-4">Cargando información de la orden…</p>
                    </div>

                    <div class="mt-3 small text-muted">
                        <span class="me-3">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#22c55e;margin-right:4px;"></span>
                            🟩 Producto completo (pedida = a entregar)
                        </span>
                        <span class="me-3">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#eab308;margin-right:4px;"></span>
                            🟨 Menor a la pedida
                        </span>
                        <span class="me-3">
                            <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#ef4444;margin-right:4px;"></span>
                            🟥 Mayor a la pedida
                        </span>
                        <span>
                            <span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#111827;margin-right:4px;"></span>
                            ⬛ En XML pero no solicitado en la orden
                        </span>
                    </div>

                    <!-- BLOQUE XML -->
                    <div class="mt-4 border rounded-3 p-3 bg-light">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                            <div>
                                <label class="form-label fw-semibold mb-1">Archivos XML</label>
                                <div class="text-muted fs-8">
                                    Sube uno o varios XML de facturas/entradas para esta orden / backorder.
                                </div>
                            </div>
                            <div class="ms-md-3">
                                <input type="file"
                                       name="xml_files_input[]"
                                       id="orderXmlInput"
                                       class="form-control"
                                       multiple
                                       accept=".xml,.XML">
                            </div>
                        </div>

                        <div id="xmlFilesList" class="mt-3 small text-muted">
                            <span class="fst-italic">Aún no has seleccionado XML.</span>
                        </div>

                        <div id="xmlUnrequestedContainer" class="mt-3"></div>
                    </div>

                    <!-- BLOQUE PDF -->
                    <div class="mt-3 border rounded-3 p-3 bg-light-subtle">
                        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                            <div>
                                <label class="form-label fw-semibold mb-1">Archivos PDF</label>
                                <div class="text-muted fs-8">
                                    Sube uno o varios PDFs (representación impresa de la factura, remisión, etc.).
                                </div>
                            </div>
                            <div class="ms-md-3">
                                <input type="file"
                                       name="pdf_files_input[]"
                                       id="orderPdfInput"
                                       class="form-control"
                                       multiple
                                       accept=".pdf,.PDF">
                            </div>
                        </div>

                        <div id="pdfFilesList" class="mt-3 small text-muted">
                            <span class="fst-italic">Aún no has seleccionado PDFs.</span>
                        </div>
                    </div>

                    <!-- Comentario -->
                    <div class="mt-4">
                        <label for="orderComment" class="form-label fw-semibold">Comentario</label>
                        <textarea id="orderComment"
                                  name="comment"
                                  class="form-control"
                                  rows="2"
                                  placeholder="Notas sobre esta entrada / factura (opcional)"></textarea>
                    </div>

                    <div id="orderFilesUploadAlert" class="alert d-none mt-4"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-upload me-2"></i>Guardar archivos y marcar orden para la cita
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
