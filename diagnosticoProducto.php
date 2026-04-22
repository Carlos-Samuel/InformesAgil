<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        .banner-titulo {
            background: #6f42c1;
            color: #fff;
            padding: 10px 20px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        #panelResultados {
            display: none;
        }
        .mini-table td, .mini-table th {
            font-size: 0.82rem;
            white-space: nowrap;
        }
        .hex-cell {
            font-family: Consolas, "Courier New", monospace;
            font-size: 0.75rem;
            color: #6c757d;
            max-width: 260px;
            overflow-x: auto;
        }
        .badge-match {
            background: #198754;
        }
        .badge-nomatch {
            background: #dc3545;
        }
        .existencia-neg { color: #dc3545; font-weight: 600; }
        .existencia-pos { color: #198754; font-weight: 600; }
        .procod-cell {
            font-family: Consolas, "Courier New", monospace;
            font-size: 0.85rem;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            white-space: pre;
        }
        .card-resumen .card-body {
            padding: 0.8rem 1rem;
        }
        .card-resumen .valor {
            font-size: 1.4rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>
        <div id="overlay" class="overlay"></div>
        <div class="layout">
            <div class="banner-titulo">
                Diagnóstico de Producto — MySQL vs KARMOVI.DBF
            </div>
            <main class="content">

                <!-- Buscador -->
                <div class="card p-3 shadow mt-1 mb-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label for="inputQuery" class="form-label fw-semibold mb-1">
                                <i class="fa fa-search me-1"></i> Código o nombre de producto
                            </label>
                            <input type="text"
                                   id="inputQuery"
                                   class="form-control"
                                   placeholder='Ej: 18 * 18 * 18 * X 50'
                                   autocomplete="off"
                                   autofocus>
                        </div>
                        <div class="col-auto">
                            <button id="btnBuscar" class="btn btn-primary">
                                <i class="fa fa-search me-1"></i> Diagnosticar
                            </button>
                            <button id="btnLimpiar" class="btn btn-outline-secondary ms-1">
                                <i class="fa fa-times me-1"></i> Limpiar
                            </button>
                        </div>
                        <div class="col-12">
                            <small class="text-muted">
                                Busca en <code>productos</code> (campos <code>procod</code> y <code>pronom</code>)
                                y en <code>KARMOVI.DBF</code> todos los <code>PROCOD</code> que contengan el texto.
                                Muestra diferencias de casing/espacios para detectar por qué un producto puede
                                aparecer distinto en el informe vs. el kardex.
                            </small>
                        </div>
                    </div>
                </div>

                <div id="alertBuscando" class="alert alert-warning py-2" style="display:none;">
                    <i class="fa fa-clock me-1"></i>
                    Escaneando KARMOVI (~638 000 registros). Puede tardar 10–20 segundos…
                </div>

                <div id="panelResultados">

                    <!-- Resumen -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <div class="card shadow card-resumen h-100" style="border-left: 5px solid #0d6efd;">
                                <div class="card-body">
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        Filas en <code>productos</code>
                                    </div>
                                    <div id="resMysql" class="valor text-primary">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow card-resumen h-100" style="border-left: 5px solid #6f42c1;">
                                <div class="card-body">
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        PROCODs distintos en KARMOVI
                                    </div>
                                    <div id="resDbf" class="valor" style="color:#6f42c1;">—</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card shadow card-resumen h-100" style="border-left: 5px solid #198754;">
                                <div class="card-body">
                                    <div class="text-muted" style="font-size:0.8rem;">
                                        Suma total de existencia en KARMOVI
                                    </div>
                                    <div id="resTotal" class="valor" style="color:#198754;">—</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla 1: productos en MySQL -->
                    <div class="card shadow mb-3">
                        <div class="card-header py-2">
                            <i class="fa fa-database me-1"></i>
                            Productos en MySQL que coinciden con la búsqueda
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="tablaMysql" class="table table-bordered table-striped mb-0 mini-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>PROID</th>
                                            <th>PROCOD</th>
                                            <th>Bytes</th>
                                            <th>Chars</th>
                                            <th>HEX(procod)</th>
                                            <th>PRONOM</th>
                                            <th class="text-end">Existencia en DBF</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla 2: PROCODs en KARMOVI -->
                    <div class="card shadow mb-3">
                        <div class="card-header py-2">
                            <i class="fa fa-file-code me-1"></i>
                            PROCODs distintos encontrados en KARMOVI.DBF
                            <small class="text-muted ms-2">(ordenados por existencia absoluta)</small>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="tablaDbf" class="table table-bordered table-striped mb-0 mini-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th>PROCOD (tal cual en DBF)</th>
                                            <th>Bytes</th>
                                            <th>Chars</th>
                                            <th>HEX(procod)</th>
                                            <th class="text-end"># Movs</th>
                                            <th class="text-end">Existencia</th>
                                            <th>¿Está en productos?</th>
                                            <th>KARTIPOs usados</th>
                                            <th>Kardex</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
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

        function fmtNum(n) {
            n = parseFloat(n);
            if (isNaN(n)) return '—';
            return n.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }

        function esc(s) {
            return $('<div>').text(s == null ? '' : s).html();
        }

        function classExist(n) {
            n = parseFloat(n);
            if (isNaN(n) || n === 0) return '';
            return n < 0 ? 'existencia-neg' : 'existencia-pos';
        }

        function buscar() {
            var q = $('#inputQuery').val().trim();
            if (q === '') {
                Swal.fire('Atención', 'Ingrese un término de búsqueda.', 'warning');
                return;
            }

            $('#panelResultados').hide();
            $('#alertBuscando').show();
            $('#tablaMysql tbody, #tablaDbf tbody').empty();

            $.ajax({
                url     : 'controladores/datatable/diagnosticoProducto.php',
                type    : 'POST',
                data    : { q: q },
                dataType: 'json'
            }).done(function (json) {
                $('#alertBuscando').hide();

                if (json.error) {
                    Swal.fire('Error', json.error, 'error');
                    return;
                }

                // Resumen
                var res = json.resumen || {};
                $('#resMysql').text((res.productos_en_mysql || 0).toLocaleString('es-CO'));
                $('#resDbf'  ).text((res.procods_distintos_en_dbf || 0).toLocaleString('es-CO'));
                $('#resTotal').text(fmtNum(res.suma_total_dbf || 0))
                              .removeClass('existencia-pos existencia-neg')
                              .addClass(classExist(res.suma_total_dbf || 0));

                // Tabla MySQL
                var $tbM = $('#tablaMysql tbody');
                if ((json.mysql || []).length === 0) {
                    $tbM.append('<tr><td colspan="7" class="text-center text-muted p-3">Sin coincidencias en MySQL</td></tr>');
                } else {
                    json.mysql.forEach(function (r) {
                        var existDbf = (r.existencia_dbf == null) ? null : r.existencia_dbf;
                        $tbM.append(
                            '<tr>' +
                                '<td>' + esc(r.proid) + '</td>' +
                                '<td><span class="procod-cell">' + esc(r.procod) + '</span></td>' +
                                '<td class="text-end">' + r.procod_len + '</td>' +
                                '<td class="text-end">' + r.procod_chars + '</td>' +
                                '<td class="hex-cell">' + esc(r.procod_hex) + '</td>' +
                                '<td>' + esc(r.pronom) + '</td>' +
                                '<td class="text-end ' + classExist(existDbf) + '">' +
                                    (existDbf == null ? '<em class="text-muted">no hay movs</em>' : fmtNum(existDbf)) +
                                '</td>' +
                            '</tr>'
                        );
                    });
                }

                // Tabla DBF
                var $tbD = $('#tablaDbf tbody');
                if ((json.dbf || []).length === 0) {
                    $tbD.append('<tr><td colspan="9" class="text-center text-muted p-3">Sin coincidencias en KARMOVI</td></tr>');
                } else {
                    json.dbf.forEach(function (r) {
                        var tiposStr = Object.keys(r.tipos || {})
                            .map(function (k) { return k + '(' + r.tipos[k] + ')'; })
                            .join(', ');
                        var badge = r.en_mysql
                            ? '<span class="badge badge-match">Sí</span>'
                            : '<span class="badge badge-nomatch">No</span>';
                        var kardexBtn = '<a class="btn btn-sm btn-outline-success" target="_blank" ' +
                                        'href="kardexProducto.php?procod=' + encodeURIComponent(r.procod) + '" ' +
                                        'title="Abrir kardex para este PROCOD">' +
                                        '<i class="fa fa-external-link-alt"></i></a>';

                        $tbD.append(
                            '<tr>' +
                                '<td><span class="procod-cell">' + esc(r.procod) + '</span></td>' +
                                '<td class="text-end">' + r.procod_len + '</td>' +
                                '<td class="text-end">' + r.procod_chars + '</td>' +
                                '<td class="hex-cell">' + esc(r.procod_hex) + '</td>' +
                                '<td class="text-end">' + r.count.toLocaleString('es-CO') + '</td>' +
                                '<td class="text-end ' + classExist(r.existencia) + '">' + fmtNum(r.existencia) + '</td>' +
                                '<td>' + badge + '</td>' +
                                '<td style="white-space:normal; font-size:0.75rem;">' + esc(tiposStr) + '</td>' +
                                '<td>' + kardexBtn + '</td>' +
                            '</tr>'
                        );
                    });
                }

                $('#panelResultados').show();

            }).fail(function () {
                $('#alertBuscando').hide();
                Swal.fire('Error', 'No se pudo ejecutar el diagnóstico.', 'error');
            });
        }

        $('#btnBuscar').on('click', buscar);
        $('#inputQuery').on('keydown', function (e) {
            if (e.key === 'Enter') buscar();
        });

        $('#btnLimpiar').on('click', function () {
            $('#inputQuery').val('').focus();
            $('#panelResultados').hide();
        });
    });
    </script>
</body>
</html>
