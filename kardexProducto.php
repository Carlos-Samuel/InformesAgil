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
                                Ingrese el código exacto del producto para ver las existencias calculadas.
                                El cálculo escanea el archivo completo — puede tardar unos segundos.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Panel de resultados (oculto hasta buscar) -->
                <div id="panelResultados">

                    <div id="alertBuscando" class="alert alert-warning py-2 mb-3" style="display:none;">
                        <i class="fa fa-clock me-1"></i>
                        Escaneando el archivo completo. Por favor espere…
                    </div>

                    <!-- Tarjeta de existencia total + total movimientos -->
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
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-body d-flex flex-column justify-content-center">
                                    <div class="text-muted mb-2" style="font-size:0.85rem;">
                                        <i class="fa fa-file-csv me-1"></i> Exportar movimientos
                                    </div>
                                    <button id="btnDescargarCsv" class="btn btn-outline-success" disabled>
                                        <i class="fa fa-download me-1"></i>
                                        Descargar CSV con todos los registros
                                    </button>
                                    <div class="text-muted mt-2" style="font-size:0.78rem;">
                                        Se incluirán los <strong id="totalMovimientosBtn">0</strong> movimientos
                                        del producto seleccionado.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Regla aplicada (informativo) -->
                    <div class="card shadow mb-3">
                        <div class="card-header py-2" style="font-size:0.9rem;">
                            <i class="fa fa-balance-scale me-1"></i>
                            Regla aplicada
                        </div>
                        <div class="card-body p-2">
                            <div style="font-size:0.88rem;">
                                <strong>Ignora TMINOAFECT · desc. RESTAN</strong>
                                <div class="text-muted mt-1" style="font-size:0.82rem;">
                                    Todos los movimientos se suman o restan según <code>TMITIPO</code>
                                    (se ignora el campo <code>TMINOAFECT</code>). Los <code>KARTIPO</code>
                                    no registrados en <code>TIPMOVINV</code> se tratan como <strong>resta</strong>.
                                </div>
                            </div>
                            <div class="text-muted mt-2" style="font-size:0.78rem;">
                                KARTIPOS no presentes en TIPMOVINV: <strong id="dbgDesconocidos">—</strong>
                            </div>
                        </div>
                    </div>

                </div><!-- /panelResultados -->

            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>

    <script>
    $(document).ready(function () {

        var procodActual = '';

        function fmt(n) {
            n = parseFloat(n) || 0;
            return n.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }

        function resetUI() {
            $('#existenciaValor').text('…');
            $('#totalMovimientos').text('…');
            $('#totalMovimientosBtn').text('0');
            $('#dbgDesconocidos').text('—');
            $('#btnDescargarCsv').prop('disabled', true);
        }

        function cargarKardex(procod) {
            procodActual = procod;
            $('#panelResultados').show();
            resetUI();
            $('#alertBuscando').show();

            $.ajax({
                url  : 'controladores/datatable/kardexProducto.php',
                type : 'POST',
                data : { procod: procod },
                dataType: 'json'
            }).done(function (json) {
                $('#alertBuscando').hide();

                if (json.error) {
                    Swal.fire('Error', json.error, 'error');
                    return;
                }

                var existencia = parseFloat(json.existencia) || 0;
                var $val = $('#existenciaValor');
                $val.text(fmt(existencia));
                $val.toggleClass('existencia-negativa', existencia < 0);

                var totalMov = json.recordsFiltered || 0;
                $('#totalMovimientos').text(totalMov.toLocaleString('es-CO'));
                $('#totalMovimientosBtn').text(totalMov.toLocaleString('es-CO'));
                $('#existenciaProcod').text(procod.toUpperCase());

                var dbg = json.debug || {};
                var desc = dbg.kartipos_desconocidos || {};
                var keys = Object.keys(desc);
                $('#dbgDesconocidos').text(
                    keys.length === 0
                        ? 'ninguno'
                        : keys.map(function (k) { return k + '(' + desc[k] + ')'; }).join(', ')
                );

                // Habilitar botón CSV sólo si hay registros
                $('#btnDescargarCsv').prop('disabled', totalMov === 0);

            }).fail(function () {
                $('#alertBuscando').hide();
                Swal.fire('Error', 'No se pudo consultar KARMOVI.DBF.', 'error');
            });
        }

        // Buscar
        $('#btnBuscar').on('click', function () {
            var procod = $('#inputProcod').val().trim();
            if (procod === '') {
                Swal.fire('Atención', 'Por favor ingrese un código de producto.', 'warning');
                return;
            }
            cargarKardex(procod);
        });

        // Enter dispara la búsqueda
        $('#inputProcod').on('keydown', function (e) {
            if (e.key === 'Enter') $('#btnBuscar').click();
        });

        // Limpiar
        $('#btnLimpiar').on('click', function () {
            $('#inputProcod').val('').focus();
            $('#panelResultados').hide();
            procodActual = '';
        });

        // Descargar CSV con todos los movimientos del producto consultado
        $('#btnDescargarCsv').on('click', function () {
            if (!procodActual) return;
            var url = 'controladores/datatable/kardexProducto.php'
                    + '?format=csv'
                    + '&procod=' + encodeURIComponent(procodActual);
            window.location.href = url;
        });
    });
    </script>
</body>
</html>
