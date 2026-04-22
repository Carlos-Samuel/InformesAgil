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
                            Se descarga el archivo completo en la primera carga — puede tardar.
                        </small>
                    </div>
                    <div id="alertCargando" class="alert alert-warning py-2">
                        <i class="fa fa-clock me-1"></i>
                        Cargando el archivo completo (~638 000 registros). Por favor espere…
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

        $('#tablaKarmovi').DataTable({
            processing : true,
            serverSide : false,           // carga completa — paginación en cliente
            searching  : true,
            ordering   : true,
            pageLength : 50,
            lengthMenu : [25, 50, 100, 250, 500],
            language   : {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            },
            deferRender: true,            // no render todas las filas al inicio
            ajax: {
                url     : 'controladores/datatable/karmovi.php',
                type    : 'POST',
                dataSrc : function (json) {
                    $('#alertCargando').hide();
                    if (json.error) {
                        Swal.fire('Error', json.error, 'error');
                        return [];
                    }
                    return json.data || [];
                },
                error: function () {
                    $('#alertCargando').hide();
                    Swal.fire('Error', 'No se pudo cargar KARMOVI.DBF. Verifique la ruta en el archivo .env', 'error');
                }
            },
            columns: [
                { data: 0 },  { data: 1 },  { data: 2 },  { data: 3 },
                { data: 4 },  { data: 5 },  { data: 6 },  { data: 7 },
                { data: 8 },
                { data: 9,  className: 'text-end' },
                { data: 10, className: 'text-end' },
                { data: 11, className: 'text-end' },
                { data: 12 }, { data: 13 }, { data: 14 }, { data: 15 }, { data: 16 },
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
            ]
        });
    });
    </script>
</body>
</html>
