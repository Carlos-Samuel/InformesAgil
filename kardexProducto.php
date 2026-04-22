<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        .banner-titulo {
            background: #198754;
            color: #fff;
            padding: 10px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        #tablaKardexProducto td, #tablaKardexProducto th {
            white-space: nowrap;
            font-size: 0.82rem;
        }
        .card-existencia {
            border-left: 5px solid #198754;
        }
        .existencia-valor {
            font-size: 2rem;
            font-weight: 700;
            color: #198754;
        }
        .existencia-negativa {
            color: #dc3545 !important;
        }
        #panelResultados {
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
                Kardex por Producto — Existencia y Movimientos
            </div>
            <main class="content">

                <!-- Buscador de producto -->
                <div class="card p-3 shadow mt-1 mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <label for="inputProcod" class="form-label fw-semibold mb-1">
                                <i class="fa fa-barcode me-1"></i> Código de Producto (PROCOD)
                            </label>
                            <input type="text"
                                   id="inputProcod"
                                   class="form-control"
                                   placeholder="Ej: 001234"
                                   autocomplete="off"
                                   autofocus>
                        </div>
                        <div class="col-auto">
                            <button id="btnBuscar" class="btn btn-success">
                                <i class="fa fa-search me-1"></i> Buscar
                            </button>
                            <button id="btnLimpiar" class="btn btn-outline-secondary ms-1">
                                <i class="fa fa-times me-1"></i> Limpiar
                            </button>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">
                                Ingrese el código exacto del producto para ver todos sus movimientos en el kardex.
                                La búsqueda escanea el archivo completo — puede tardar unos segundos.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Panel de resultados (oculto hasta buscar) -->
                <div id="panelResultados">

                    <!-- Tarjeta de existencia total -->
                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <div class="card shadow card-existencia h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1" style="font-size:0.85rem;">
                                        <i class="fa fa-cubes me-1"></i> Existencia Total
                                    </div>
                                    <div id="existenciaValor" class="existencia-valor">—</div>
                                    <div class="text-muted mt-1" style="font-size:0.78rem;">
                                        Suma acumulada de <code>KARCANT</code> para el producto
                                        <strong id="existenciaProcod"></strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1" style="font-size:0.85rem;">
                                        <i class="fa fa-list-ol me-1"></i> Total de Movimientos
                                    </div>
                                    <div id="totalMovimientos" class="existencia-valor" style="color:#0d6efd;">—</div>
                                    <div class="text-muted mt-1" style="font-size:0.78rem;">
                                        Registros encontrados en KARMOVI.DBF
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de movimientos -->
                    <div class="card p-3 shadow">
                        <div id="alertBuscando" class="alert alert-warning py-2 mb-2" style="display:none;">
                            <i class="fa fa-clock me-1"></i>
                            Escaneando el archivo completo. Por favor espere…
                        </div>
                        <div class="table-responsive">
                            <table id="tablaKardexProducto"
                                   class="table table-bordered table-striped table-hover"
                                   style="width:100%">
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
                </div><!-- /panelResultados -->

            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>

    <script>
    $(document).ready(function () {

        var tabla = null;
        var procodActual = '';

        function inicializarTabla(procod) {
            // Destruir instancia anterior si existe
            if (tabla !== null) {
                tabla.destroy();
                $('#tablaKardexProducto').empty().append('<thead class="table-dark"><tr><th>ID</th><th>Producto</th><th>Tipo Mov.</th><th>Documento</th><th>Prefijo</th><th>Sede</th><th>Fecha</th><th>Hora</th><th>Bodega</th><th>Cantidad</th><th>Valor</th><th>Costo</th><th>Consecutivo</th><th>Estado</th><th>Empresa</th><th>Unidad</th><th>Tipo Aux.</th></tr></thead><tbody></tbody>');
                tabla = null;
            }

            tabla = $('#tablaKardexProducto').DataTable({
                processing : true,
                serverSide : true,
                searching  : false,
                ordering   : false,
                pageLength : 50,
                lengthMenu : [25, 50, 100, 250],
                language   : {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
                },
                ajax: {
                    url  : 'controladores/datatable/kardexProducto.php',
                    type : 'POST',
                    data : function (d) {
                        d.procod = procod;
                        return d;
                    },
                    dataSrc: function (json) {
                        // Actualizar existencia y totales
                        var existencia = parseFloat(json.existencia) || 0;
                        var $val = $('#existenciaValor');
                        $val.text(existencia.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 }));
                        $val.toggleClass('existencia-negativa', existencia < 0);

                        $('#totalMovimientos').text((json.recordsFiltered || 0).toLocaleString('es-CO'));
                        $('#existenciaProcod').text(procod.toUpperCase());

                        if (json.error) {
                            Swal.fire('Error', json.error, 'error');
                        }
                        return json.data || [];
                    },
                    error: function () {
                        Swal.fire('Error', 'No se pudo cargar KARMOVI.DBF.', 'error');
                    }
                },
                columns: [
                    { data: 0 },
                    { data: 1 },
                    { data: 2 },
                    { data: 3 },
                    { data: 4 },
                    { data: 5 },
                    { data: 6 },
                    { data: 7 },
                    { data: 8 },
                    { data: 9,  className: 'text-end' },
                    { data: 10, className: 'text-end' },
                    { data: 11, className: 'text-end' },
                    { data: 12 },
                    { data: 13 },
                    { data: 14 },
                    { data: 15 },
                    { data: 16 },
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
                preDrawCallback: function () {
                    $('#alertBuscando').show();
                },
                drawCallback: function () {
                    $('#alertBuscando').hide();
                }
            });
        }

        // Botón buscar
        $('#btnBuscar').on('click', function () {
            var procod = $('#inputProcod').val().trim();
            if (procod === '') {
                Swal.fire('Atención', 'Por favor ingrese un código de producto.', 'warning');
                return;
            }
            procodActual = procod;
            $('#panelResultados').show();
            $('#existenciaValor').text('…');
            $('#totalMovimientos').text('…');
            inicializarTabla(procod);
        });

        // Buscar al presionar Enter
        $('#inputProcod').on('keydown', function (e) {
            if (e.key === 'Enter') $('#btnBuscar').click();
        });

        // Limpiar
        $('#btnLimpiar').on('click', function () {
            $('#inputProcod').val('').focus();
            $('#panelResultados').hide();
            if (tabla !== null) {
                tabla.destroy();
                tabla = null;
            }
        });
    });
    </script>
</body>
</html>
