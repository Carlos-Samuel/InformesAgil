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
        /* Tabla "CSV en pantalla": sin DataTable, sin paginación */
        table.tabla-csv {
            width: auto;               /* se ajusta al contenido */
            min-width: 100%;
            border-collapse: collapse;
            font-size: 0.82rem;
            font-family: 'Consolas', 'Courier New', monospace;
            white-space: nowrap;
        }
        table.tabla-csv thead th {
            position: sticky;
            top: 0;
            background: #198754;
            color: #fff;
            padding: 6px 10px;
            text-align: left;
            border-bottom: 2px solid #0f5132;
            border-right: 1px solid #0f5132;
            z-index: 2;
        }
        table.tabla-csv tbody td {
            padding: 3px 10px;
            border-bottom: 1px solid #dee2e6;
            border-right: 1px solid #f1f3f5;
            vertical-align: middle;
        }
        table.tabla-csv tbody tr:nth-child(even) { background: #f8f9fa; }
        table.tabla-csv tbody tr:hover            { background: #e9f5ee; }

        .row-num {
            color: #6c757d;
            font-size: 0.75rem;
        }

        #panelResultados { display: none; }
        #tablaWrapper {
            max-height: calc(100vh - 220px);
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
                Kardex Completo — Volcado de KARMOVI.DBF
            </div>
            <main class="content">

                <!-- Controles -->
                <div class="card p-3 shadow mt-1 mb-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-auto">
                            <button id="btnCargar" class="btn btn-success">
                                <i class="fa fa-rotate me-1"></i>
                                Cargar archivo
                            </button>
                            <button id="btnExportarCsv"
                                    class="btn btn-outline-success ms-1"
                                    disabled>
                                <i class="fa fa-file-csv me-1"></i>
                                Exportar CSV
                            </button>
                        </div>
                        <div class="col">
                            <small class="text-muted">
                                Muestra <code>KARMOVI.DBF</code> tal cual, en el
                                orden físico del archivo, con todas sus columnas.
                                Sin cálculos, sin filtros, sin paginación.
                                Puede tardar varios segundos si el archivo es grande.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Alerta durante la carga -->
                <div id="alertCargando"
                     class="alert alert-warning py-2 mb-3"
                     style="display:none;">
                    <i class="fa fa-clock me-1"></i>
                    Leyendo <code>KARMOVI.DBF</code> completo. Por favor espere…
                </div>

                <!-- Resultados -->
                <div id="panelResultados">

                    <div class="row mb-3 g-3">
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-list-ol me-1"></i>
                                        Registros
                                    </div>
                                    <div id="statRegistros"
                                         style="font-size:1.6rem;font-weight:700;color:#0d6efd;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-table-columns me-1"></i>
                                        Columnas
                                    </div>
                                    <div id="statColumnas"
                                         style="font-size:1.6rem;font-weight:700;color:#198754;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow h-100">
                                <div class="card-body">
                                    <div class="text-muted mb-1"
                                         style="font-size:0.85rem;">
                                        <i class="fa fa-stopwatch me-1"></i>
                                        Tiempo de lectura
                                    </div>
                                    <div id="statTiempo"
                                         style="font-size:1.6rem;font-weight:700;color:#6c757d;">
                                        —
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla "CSV en pantalla" -->
                    <div id="tablaWrapper">
                        <table class="tabla-csv" id="tablaCsv">
                            <thead>
                                <tr id="theadTr"><!-- columnas inyectadas por JS --></tr>
                            </thead>
                            <tbody id="tbodyCsv">
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

        var columnasActuales = [];
        var datosActuales    = []; // arreglo de arreglos (filas)

        function esc(s) {
            return $('<div/>').text(s == null ? '' : String(s)).html();
        }

        function pintarTabla(columns, data) {
            // Encabezados
            var headHtml = '<th class="row-num" style="width:60px;">#</th>';
            for (var c = 0; c < columns.length; c++) {
                headHtml += '<th>' + esc(columns[c]) + '</th>';
            }
            $('#theadTr').html(headHtml);

            // Cuerpo — construir HTML en memoria para máxima velocidad
            var html = '';
            for (var i = 0; i < data.length; i++) {
                var fila = data[i];
                html += '<tr>';
                html += '<td class="row-num">' + (i + 1) + '</td>';
                for (var j = 0; j < fila.length; j++) {
                    html += '<td>' + esc(fila[j]) + '</td>';
                }
                html += '</tr>';
            }
            $('#tbodyCsv').html(html);
        }

        function fmtEntero(n) {
            return (parseInt(n, 10) || 0).toLocaleString('es-CO');
        }

        function exportarCsv() {
            if (!datosActuales.length) return;

            var lines = [];
            // Encabezado
            lines.push(columnasActuales.map(csvCell).join(','));
            // Filas
            for (var i = 0; i < datosActuales.length; i++) {
                lines.push(datosActuales[i].map(csvCell).join(','));
            }

            var blob = new Blob(["\uFEFF" + lines.join('\r\n')],
                                { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'karmovi_completo_' +
                (new Date()).toISOString().slice(0,19).replace(/[:T-]/g,'') +
                '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function csvCell(v) {
            if (v === null || v === undefined) return '';
            var s = String(v);
            if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 ||
                s.indexOf('\n') !== -1 || s.indexOf('\r') !== -1) {
                return '"' + s.replace(/"/g, '""') + '"';
            }
            return s;
        }

        function cargar() {
            $('#panelResultados').hide();
            $('#alertCargando').show();
            $('#btnCargar').prop('disabled', true);
            $('#btnExportarCsv').prop('disabled', true);

            $.ajax({
                url  : 'controladores/datatable/kardexCompleto.php',
                type : 'POST',
                dataType: 'json',
                timeout: 600000
            }).done(function (json) {
                $('#alertCargando').hide();
                $('#btnCargar').prop('disabled', false);

                if (json.error) {
                    Swal.fire('Error', json.error, 'error');
                    return;
                }

                columnasActuales = json.columns || [];
                datosActuales    = json.data    || [];

                $('#statRegistros').text(fmtEntero(datosActuales.length));
                $('#statColumnas').text(fmtEntero(columnasActuales.length));
                $('#statTiempo').text(((json.debug && json.debug.tiempo_ms) || 0) + ' ms');

                pintarTabla(columnasActuales, datosActuales);

                $('#panelResultados').show();
                $('#btnExportarCsv').prop('disabled', datosActuales.length === 0);

            }).fail(function (xhr, status) {
                $('#alertCargando').hide();
                $('#btnCargar').prop('disabled', false);
                Swal.fire('Error',
                    'No se pudo leer KARMOVI.DBF. (' + status + ')',
                    'error');
            });
        }

        $('#btnCargar').on('click', cargar);
        $('#btnExportarCsv').on('click', exportarCsv);

        // Cargar automáticamente al abrir la vista
        cargar();
    });
    </script>
</body>
</html>
