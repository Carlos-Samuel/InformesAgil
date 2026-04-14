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
        #tablaTipMovInv td, #tablaTipMovInv th {
            white-space: nowrap;
            font-size: 0.83rem;
        }
        /* Colores según tipo de movimiento */
        td.tipo-entrada  { color: #198754; font-weight: 600; }
        td.tipo-salida   { color: #dc3545; font-weight: 600; }
    </style>
</head>
<body>
    <div class="layout has-sidebar fixed-sidebar fixed-header">
        <?php include('partes/sidebar.php'); ?>
        <div id="overlay" class="overlay"></div>
        <div class="layout">
            <div class="banner-titulo">
                Tipos de Movimiento de Inventario (TIPMOVINV)
            </div>
            <main class="content">
                <div class="card p-3 shadow mt-1">
                    <div class="mb-2">
                        <small class="text-muted">
                            Datos leídos directamente desde <code>TIPMOVINV.DBF</code>.
                        </small>
                    </div>
                    <div class="table-responsive">
                        <table id="tablaTipMovInv" class="table table-bordered table-striped table-hover" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Interno</th>
                                    <th>Último #</th>
                                    <th>Consec.</th>
                                    <th>Plan Cta.</th>
                                    <th>No Contab.</th>
                                    <th>Costo</th>
                                    <th>No Afecta</th>
                                    <th>Producc.</th>
                                    <th>Merma</th>
                                    <th>Tercero</th>
                                    <th>Inactivo</th>
                                    <th>Gen. IVA</th>
                                    <th>Gen. IC</th>
                                    <th>Plan Cta. IVA</th>
                                    <th>Porcentaje</th>
                                    <th>Plan Cta. NIIF</th>
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

        var tabla = $('#tablaTipMovInv').DataTable({
            processing : true,
            serverSide : true,
            searching  : true,
            ordering   : false,
            pageLength : 50,
            lengthMenu : [25, 50, 100, 200],
            language   : {
                url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json'
            },
            ajax: {
                url  : 'controladores/datatable/tipmovinv.php',
                type : 'POST',
                error: function () {
                    Swal.fire('Error', 'No se pudo cargar TIPMOVINV.DBF. Verifique la ruta en el archivo .env', 'error');
                }
            },
            columns: [
                { data: 0  },  // TMICOD
                { data: 1  },  // TMINOM
                {                            // TMITIPO — colorear + / -
                    data    : 2,
                    render  : function (data) {
                        if (data === '+') return '<span class="text-success fw-bold">+ Entrada</span>';
                        if (data === '-') return '<span class="text-danger fw-bold">− Salida</span>';
                        return data;
                    }
                },
                { data: 3  },  // TMIINTERNO
                { data: 4  },  // TMIULTIMO
                { data: 5  },  // TMICONSE
                { data: 6  },  // PLNCOD
                { data: 7  },  // TMINOCONTA
                { data: 8  },  // TMICTO
                { data: 9  },  // TMINOAFECT
                { data: 10 },  // TMIPRODUC
                { data: 11 },  // TMIMERMA
                { data: 12 },  // TMITER
                { data: 13 },  // TMIINACT
                { data: 14 },  // TMIGENIVA
                { data: 15 },  // TMIGENIC
                { data: 16 },  // PLNCODIVA
                { data: 17 },  // TMIPORC
                { data: 18 },  // PLNCODNIIF
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
