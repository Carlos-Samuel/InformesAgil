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
        /* Tabla enorme, sin DataTable ni paginación */
        table.tabla-existencias {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.88rem;
            font-family: 'Segoe UI', Roboto, sans-serif;
        }
        table.tabla-existencias thead th {
            position: sticky;
            top: 0;
            background: #198754;
            color: #fff;
            padding: 8px 12px;
            text-align: left;
            border-bottom: 2px solid #0f5132;
            z-index: 2;
        }
        table.tabla-existencias tbody td {
            padding: 6px 12px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        table.tabla-existencias tbody tr:nth-child(even) {
            background: #f8f9fa;
        }
        table.tabla-existencias tbody tr:hover {
            background: #e9f5ee;
        }
        .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
            font-family: 'Consolas', 'Courier New', monospace;
        }
        .procod-col {
            font-weight: 600;
            color: #0f5132;
            font-family: 'Consolas', 'Courier New', monospace;
        }
        .existencia-negativa {
            color: #dc3545;
            font-weight: 700;
        }
        .existencia-cero {
            color: #6c757d;
        }
        #panelResultados { display: none; }
        #tablaWrapper {
            max-height: calc(100vh - 260px);
            overflow: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
        }
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>
        <div id="overlay" class="overlay"></div>
        <div class="layout">
            <div class="banner-titulo">
                Existencias de Todos los Productos
            </div>
            <main class="content">

                <!-- Controles -->
                <div class="card p-3 shadow mt-1 mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-auto">
                            <button id="btnCalcular" class="btn btn-success">
                                <i class="fa fa-calculator me-1"></i>
                                Calcular existencias
                            </button>
                            <button id="btnExportarCsv"
                                    class="btn btn-outline-success ms-1"
                                    disabled>
                                <i class="fa fa-file-csv me-1"></i>
                                Exportar CSV
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Alerta durante el escaneo -->
                <div id="alertBuscando"
                     class="alert alert-warning py-2 mb-3"
                     style="display:none;">
                    <i class="fa fa-clock me-1"></i>
                    Escaneando <code>KARMOVI.DBF</code> completo. Por favor espere…
                </div>

                <!-- Resultados -->
                <div id="panelResultados">

                    <div class="row mb-3 g-3">
                        <div class="col-md-3">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-boxes-stacking me-1"></i>
                                        Productos
                                    </div>
                                    <div id="statProductos"
                                         style="font-size:1.6rem;font-weight:700;color:#198754;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-list-ol me-1"></i>
                                        Registros KARMOVI
                                    </div>
                                    <div id="statRegistros"
                                         style="font-size:1.6rem;font-weight:700;color:#0d6efd;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-arrow-up me-1"></i>
                                        Con existencia &gt; 0
                                    </div>
                                    <div id="statConExistencia"
                                         style="font-size:1.6rem;font-weight:700;color:#198754;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-arrow-down me-1"></i>
                                        Con existencia &lt; 0
                                    </div>
                                    <div id="statNegativos"
                                         style="font-size:1.6rem;font-weight:700;color:#dc3545;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla enorme, sin paginar, sin DataTable -->
                    <div id="tablaWrapper">
                        <table class="tabla-existencias" id="tablaExistencias">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Código de Producto</th>
                                    <th class="num">Existencia</th>
                                    <th class="num">Movimientos</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyExistencias">
                                <!-- filas inyectadas por JS -->
                            </tbody>
                        </table>
                    </div>

                </div><!-- /panelResultados -->

            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>

    <script>
    $(document).ready(function () {

        var datosActuales = []; // arreglo global con {procod, existencia, movimientos}

        function fmt(n) {
            n = parseFloat(n) || 0;
            return n.toLocaleString('es-CO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 4
            });
        }

        function fmtEntero(n) {
            return (parseInt(n, 10) || 0).toLocaleString('es-CO');
        }

        function pintarTabla(data) {
            var $tbody = $('#tbodyExistencias');
            $tbody.empty();

            // Construir el HTML en memoria (mucho más rápido que append por fila)
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var row = data[i];
                var exis = parseFloat(row.existencia) || 0;
                var clase = '';
                if (exis < 0)        clase = 'existencia-negativa';
                else if (exis === 0) clase = 'existencia-cero';

                html += '<tr>'
                      +   '<td class="num">' + (i + 1) + '</td>'
                      +   '<td class="procod-col">' + $('<div/>').text(row.procod).html() + '</td>'
                      +   '<td class="num ' + clase + '">' + fmt(exis) + '</td>'
                      +   '<td class="num">' + fmtEntero(row.movimientos) + '</td>'
                      + '</tr>';
            }
            $tbody.html(html);
        }

        function actualizarStats(data, debug) {
            var conExis = 0, negativos = 0;
            for (var i = 0; i < data.length; i++) {
                var e = parseFloat(data[i].existencia) || 0;
                if (e > 0) conExis++;
                else if (e < 0) negativos++;
            }
            $('#statProductos').text(fmtEntero(data.length));
            $('#statRegistros').text(fmtEntero(debug.registros_karmovi || 0));
            $('#statConExistencia').text(fmtEntero(conExis));
            $('#statNegativos').text(fmtEntero(negativos));
        }

        function exportarCsv() {
            if (!datosActuales.length) return;

            var lines = ['PROCOD,EXISTENCIA,MOVIMIENTOS'];
            for (var i = 0; i < datosActuales.length; i++) {
                var r = datosActuales[i];
                // Escapar comillas simples en el código de producto por si acaso
                var procod = String(r.procod).replace(/"/g, '""');
                lines.push('"' + procod + '",'
                         + (parseFloat(r.existencia) || 0) + ','
                         + (parseInt(r.movimientos, 10) || 0));
            }
            var blob = new Blob(["\uFEFF" + lines.join('\r\n')],
                                { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'existencias_todas_' +
                (new Date()).toISOString().slice(0,19).replace(/[:T-]/g,'') +
                '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function cargar() {
            $('#panelResultados').hide();
            $('#alertBuscando').show();
            $('#btnCalcular').prop('disabled', true);
            $('#btnExportarCsv').prop('disabled', true);

            $.ajax({
                url  : 'controladores/datatable/existenciasTodas.php',
                type : 'POST',
                dataType: 'json',
                timeout: 600000
            }).done(function (json) {
                $('#alertBuscando').hide();
                $('#btnCalcular').prop('disabled', false);

                if (json.error) {
                    Swal.fire('Error', json.error, 'error');
                    return;
                }

                datosActuales = json.data || [];
                actualizarStats(datosActuales, json.debug || {});
                pintarTabla(datosActuales);

                $('#panelResultados').show();
                $('#btnExportarCsv').prop('disabled', datosActuales.length === 0);

            }).fail(function (xhr, status) {
                $('#alertBuscando').hide();
                $('#btnCalcular').prop('disabled', false);
                Swal.fire('Error',
                    'No se pudo calcular las existencias. (' + status + ')',
                    'error');
            });
        }

        $('#btnCalcular').on('click', cargar);
        $('#btnExportarCsv').on('click', exportarCsv);

        // Cargar automáticamente al abrir la vista
        cargar();
    });
    </script>
</body>
</html>
