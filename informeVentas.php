<!doctype html>
<html lang="es" data-bs-theme="auto">
<head>
    <?php include('partes/head.php'); ?>
    <style>
        .banner-titulo {
            background-color: #007bff;
            color: white;
            padding: 0.5rem;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>  
        <div id="overlay" class="overlay">
        </div>
        <div class="layout">
            <div class="banner-titulo">
                Informe por Rango de Fechas
            </div>
            <main class="content">
                <div class="card p-4 shadow mt-1">
                    <form id="formFechas">
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label for="fechaInicio" class="form-label">Fecha inicio</label>
                                <input type="date" class="form-control" id="fechaInicio" name="fechaInicio" required>
                            </div>
                            <div class="col-md-3">
                                <label for="fechaFin" class="form-label">Fecha fin</label>
                                <input type="date" class="form-control" id="fechaFin" name="fechaFin" required>
                            </div>
                            <div class="col-md-2">
                                <label for="vendedor" class="form-label">Vendedor</label>
                                <select class="form-select" id="vendedor" name="vendedor"></select>
                            </div>
                            <div class="col-md-2">
                                <label for="grupo" class="form-label">Grupo</label>
                                <select class="form-select" id="grupo" name="grupo"></select>
                            </div>
                            <div class="col-md-2">
                                <label for="subgrupo" class="form-label">Subgrupo</label>
                                <select class="form-select" id="subgrupo" name="subgrupo"></select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Procesar</button>
                    </form>
                </div>
                <div id="contenedorTabla" class="mt-1" style="display: none;">
                    <table id="tablaInforme" class="table table-bordered table-striped" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Fecha Factura</th>
                                <th>Prefijo</th>
                                <th>Producto</th>
                                <th>#Fac</th>
                                <th>#Ven</th>
                                <th>Vendedor</th>
                                <th>Ter Nit</th>
                                <th>Tercero</th>
                                <th>Cod Prod</th>
                                <th>Producto</th>
                                <th>Can</th>
                                <th>Vlr Uni</th>
                                <th>Subtotal</th>
                                <th>Grupo</th>
                                <th>SubGrupo</th>
                                <th>Codigo Marca</th>
                                <th>Marca</th>
                                <th>Centro de costos</th>
                            </tr>
                        </thead>
                    </table>
                </div>
                <div id="cargando" class="mt-3 text-info" style="display: none;">Procesando, por favor espere...</div>
                <div id="resultado" class="mt-3"></div>
            </main>
        </div>
    </div>

    <?php include('partes/foot.php'); ?>  

    <script>
        // VENDEDOR
        $('#vendedor').select2({
            placeholder: 'Seleccione un vendedor',
            width: '100%',
            ajax: {
                url: 'controladores/select2/vendedores.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                    return {
                        results: data.map(item => ({ id: item.id, text: item.nombre }))
                    };
                },
                cache: true
            }
        });

        // GRUPO
        $('#grupo').select2({
            placeholder: 'Seleccione un grupo',
            width: '100%',
            ajax: {
                url: 'controladores/select2/grupos.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                processResults: function (data) {
                    return {
                        results: data.map(item => ({ id: item.id, text: item.nombre + " - " + item.empresa }))
                    };
                },
                cache: true
            }
        });

        // SUBGRUPO (dependiente del grupo)
        $('#subgrupo').select2({
            placeholder: 'Seleccione un subgrupo',
            width: '100%',
            ajax: {
                url: 'controladores/select2/subgrupos.php',
                type: 'POST',
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {
                        search: params.term || '',
                        grupo_id: $('#grupo').val() || null
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.map(item => ({ id: item.id, text: item.nombre}))
                    };
                },
                cache: true
            }
        });

        $('#grupo').on('change', function () {
            $('#subgrupo').val(null).trigger('change');
        });

        let tablaInicializada = false;

        function cargarTabla(datosFiltro) {
            if (tablaInicializada) {
                $('#tablaInforme').DataTable().clear().destroy();
            }

            $('#contenedorTabla').show();

            $('#tablaInforme').DataTable({
                ajax: {
                    url: 'controladores/datatable/informeVentas.php',
                    type: 'POST',
                    data: datosFiltro
                },
                columns: [
                    { data: 'fecha_factura' },
                    { data: 'prefijo' },
                    { data: 'prefijo_nombre' },
                    { data: 'numero_factura' },
                    { data: 'codigo_vendedor' },
                    { data: 'nombre_vendedor' },
                    { data: 'tercero_nit' },
                    { data: 'tercero_nombre' },
                    { data: 'codigo_producto' },
                    { data: 'nombre_producto' },
                    { data: 'cantidad' },
                    { data: 'valor_unitario' },
                    { data: 'subtotal' },
                    { data: 'grupo' },
                    { data: 'subgrupo' },
                    { data: 'codigo_marca' },
                    { data: 'nombre_marca' },
                    { data: 'centro_costos' }
                ],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
                },
                dom: '<"row mt-2 mb-2 d-flex justify-content-between align-items-center"' +
                        '<"col-sm-6 text-start"f>' +
                        '<"col-sm-6 text-end"B>' +  
                    '>' +
                    '<"row"<"col-12"tr>>' +
                    '<"row mt-2"' +
                        '<"col-sm-5"i>' +
                        '<"col-sm-7"p>' +
                    '>'
                ,
                buttons: [
                    {
                        extend: 'excelHtml5',
                        text: '<i class="fa fa-file-excel"></i> Exportar a Excel',
                        title: null,
                        className: 'btn btn-success',
                        exportOptions: {
                        columns: ':visible'
                        },
                        customize: function (xlsx) {
                            const sheet = xlsx.xl.worksheets['sheet1.xml'];
                            const sheetData = sheet.getElementsByTagName('sheetData')[0];
                            const doc = sheetData.ownerDocument;
                            const info = [
                                `Fecha Inicial: ${$('#fechaInicio').val() || ''}`,
                                `Fecha Final: ${$('#fechaFin').val() || ''}`,
                                `Vendedor: ${$('#vendedor option:selected').text() || 'Todos'}`,
                                `Grupo: ${$('#grupo option:selected').text() || 'Todos'}`,
                                `Subgrupo: ${$('#subgrupo option:selected').text() || 'Todos'}`
                            ];

                            // Desplaza filas existentes
                            const existingRows = sheetData.getElementsByTagName('row');
                            for (let i = 0; i < existingRows.length; i++) {
                                const row = existingRows[i];
                                const oldR = parseInt(row.getAttribute('r'), 10);
                                row.setAttribute('r', oldR + info.length);

                                const cells = row.getElementsByTagName('c');
                                for (let j = 0; j < cells.length; j++) {
                                    const cell = cells[j];
                                    const ref = cell.getAttribute('r');
                                    if (ref) {
                                        const col = ref.replace(/[0-9]/g, '');
                                        cell.setAttribute('r', col + (oldR + info.length));
                                    }
                                }
                            }

                            // Inserta nuevas filas
                            for (let i = 0; i < info.length; i++) {
                                const row = doc.createElement('row');
                                row.setAttribute('r', i + 1);

                                const cell = doc.createElement('c');
                                cell.setAttribute('t', 'inlineStr');
                                cell.setAttribute('r', 'A' + (i + 1));

                                const is = doc.createElement('is');
                                const t = doc.createElement('t');
                                t.textContent = info[i];

                                is.appendChild(t);
                                cell.appendChild(is);
                                row.appendChild(cell);

                                sheetData.insertBefore(row, sheetData.firstChild);
                            }

                            // Ajusta la dimensión de la hoja
                            const dimension = sheet.getElementsByTagName('dimension')[0];
                            if (dimension) {
                                dimension.setAttribute('ref', `A1:Z${existingRows.length + info.length}`);
                            }
                        }


                    }
                ]


            });

            tablaInicializada = true;
        }



        $('#formFechas').on('submit', function(e) {
            e.preventDefault();
            cargarTabla({
                fechaInicio: $('#fechaInicio').val(),
                fechaFin: $('#fechaFin').val(),
                vendedor: $('#vendedor').val(),
                grupo: $('#grupo').val(),
                subgrupo: $('#subgrupo').val()
            });

        });

        $(document).ready(function () {
            const fechaActual = new Date();
            const primerDiaMes = new Date(fechaActual.getFullYear(), fechaActual.getMonth(), 1);

            function formatear(fecha) {
                const anio = fecha.getFullYear();
                const mes = (fecha.getMonth() + 1).toString().padStart(2, '0');
                const dia = fecha.getDate().toString().padStart(2, '0');
                return `${anio}-${mes}-${dia}`;
            }

            $('#fechaInicio').val(formatear(primerDiaMes));
            $('#fechaFin').val(formatear(fechaActual));
        });
    </script>
</body>
</html>
