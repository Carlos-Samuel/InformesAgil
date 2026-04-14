<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        .banner-titulo {
            background: #0d6efd;
            color: #fff;
            padding: 10px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        #tablaKarmovi td, #tablaKarmovi th {
            white-space: nowrap;
            font-size: 0.82rem;
        }
        .alert-search-warning {
            display: none;
        }
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>
        <div id="overlay" class="overlay"></div>
        <div class="layout">
            <div class="banner-titulo">
                Kardex de Movimientos de Inventario (KARMOVI)
            </div>
            <main class="content">
                <div class="card p-3 shadow mt-1">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            Los datos se cargan directamente desde <code>KARMOVI.DBF</code>.
                            La paginación es instantánea; la búsqueda requiere escanear el archivo completo.
                        </small>
                    </div>
                    <div id="alertSearchWarning" class="alert alert-warning py-2 alert-search-warning">
                        <i class="fa fa-clock me-1"></i>
                        Buscando en el archivo completo (~638 000 registros). Por favor espere…
                    </div>
                    <div class="table-responsive">
                        <table id="tablaKarmovi" class="table table-bordered table-striped table-hover" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Producto</th>
                                    <th>Tipo Mov.</th>
                                    <th>Documento</th>
                                    <th>Prefijo</th>
                                    <th>Sede</th>
                                    <th>Fecha</th>
                                    <th>Hora</th>
                                    <th>Bodega</th>
                                    <th>Cantidad</th>
                                    <th>Valor</th>
                                    <th>Costo</th>
                                    <th>Consecutivo</th>
                                    <th>Estado</th>
                                    <th>Empresa</th>
                                    <th>Unidad</th>
                                    <th>Tipo Aux.</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>

    <script>
    $(document).ready(function () {

        var searchTimer = null;

        var tabla = $('#tablaKarmovi').DataTable({
            processing   : true,
            serverSide   : true,
            searching    : true,
            ordering     : false,   // ordenamiento en servidor requeriría escaneo completo
            pageLength   : 50,
            lengthMenu   : [25, 50, 100, 250],
            language     : {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            },
            ajax: {
                url  : 'controladores/datatable/karmovi.php',
                type : 'POST',
                data : function (d) { return d; },
                error: function (xhr, error, thrown) {
                    Swal.fire('Error', 'No se pudo cargar KARMOVI.DBF. Verifique la ruta en el archivo .env', 'error');
                }
            },
            columns: [
                { data: 0 },  // KARID
                { data: 1 },  // PROCOD
                { data: 2 },  // KARTIPO
                { data: 3 },  // KARDOCU
                { data: 4 },  // PRFCOD
                { data: 5 },  // SEDE
                { data: 6 },  // KARFECHA
                { data: 7 },  // KARHORA
                { data: 8 },  // BODCOD
                { data: 9,  className: 'text-end' },  // KARCANT
                { data: 10, className: 'text-end' },  // KARVALOR
                { data: 11, className: 'text-end' },  // KARCOSTO
                { data: 12 }, // KARCONSE
                { data: 13 }, // KARESTADO
                { data: 14 }, // EMPCOD
                { data: 15 }, // PROUND
                { data: 16 }, // TMIAUX
            ],
            dom: 'Bfrtip',
            buttons: [
                {
                    extend   : 'excelHtml5',
                    text     : '<i class="fa fa-file-excel me-1"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend   : 'csvHtml5',
                    text     : '<i class="fa fa-file-csv me-1"></i> CSV',
                    className: 'btn btn-secondary btn-sm',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend   : 'print',
                    text     : '<i class="fa fa-print me-1"></i> Imprimir',
                    className: 'btn btn-info btn-sm',
                    exportOptions: { columns: ':visible' }
                }
            ],
            initComplete: function () {
                // Retrasar la búsqueda 1.5 s para evitar solicitudes por cada tecla
                var api = this.api();
                $('#tablaKarmovi_filter input').off('keyup.DT search.DT input.DT paste.DT cut.DT')
                    .on('keyup', function () {
                        var val = this.value;
                        clearTimeout(searchTimer);
                        if (val.length > 0) {
                            $('#alertSearchWarning').show();
                        }
                        searchTimer = setTimeout(function () {
                            api.search(val).draw();
                            if (val.length === 0) $('#alertSearchWarning').hide();
                        }, 1500);
                    });
            },
            drawCallback: function () {
                $('#alertSearchWarning').hide();
            }
        });
    });
    </script>
</body>
</html>
