<!doctype html>
<html lang="es" data-bs-theme="auto">
    <head>
        <?php
            include('partes/head.php')
        ?>
        <link rel="stylesheet" href="styles/dashboard.css">
    </head>
    <body>
        <div class="layout has-sidebar fixed-sidebar fixed-header">
            <?php
                include('partes/sidebar.php');
            ?>  
            <div id="overlay" class="overlay"></div>
            <div class="layout">
                <main class="content">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h1 class="m-0">Interfaz empresarial modular</h1>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalGraficosOcultos">
                            Gráficos ocultos / Reestablecer Layout
                        </button>
                    </div>

                    <div class="grid-stack">
                        <div class="grid-stack-item" gs-w="6" gs-h="4" gs-x="0" gs-y="0" id="item-graficoComparativoVentasMesAnio">
                            <div class="grid-stack-item-content">
                                <button class="btn btn-sm btn-danger btn-ocultar-grafico" data-id="graficoComparativoVentasMesAnio" style="position: absolute; top: 5px; right: 5px; z-index: 10;">×</button>
                                <div id = "graficoComparativoVentasMesAnioContainer">
                                    <canvas id="graficoComparativoVentasMesAnio"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="grid-stack-item" gs-w="6" gs-h="4" gs-x="6" gs-y="0" id="item-graficoComparativoVentasAnio">
                            <div class="grid-stack-item-content">
                                <button class="btn btn-sm btn-danger btn-ocultar-grafico" data-id="graficoComparativoVentasAnio" style="position: absolute; top: 5px; right: 5px; z-index: 10;">×</button>
                                <div id = "graficoComparativoVentasAnioContainer">
                                    <canvas id="graficoComparativoVentasAnio"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="grid-stack-item" gs-w="12" gs-h="5" gs-x="0" gs-y="4" id="item-graficoComparativoVentasMesPrefijo">
                            <div class="grid-stack-item-content">
                                <button class="btn btn-sm btn-danger btn-ocultar-grafico" data-id="graficoComparativoVentasMesPrefijo" style="position: absolute; top: 5px; right: 5px; z-index: 10;">×</button>
                                <div id = "graficoComparativoVentasMesPrefijoContainer">
                                    <canvas id="graficoComparativoVentasMesPrefijo"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="grid-stack-item" gs-w="12" gs-h="5" gs-x="0" gs-y="9" id="item-graficoComparativoIngresoCostoUtilidad">
                            <div class="grid-stack-item-content">
                                <button class="btn btn-sm btn-danger btn-ocultar-grafico" data-id="graficoComparativoIngresoCostoUtilidad" style="position: absolute; top: 5px; right: 5px; z-index: 10;">×</button>
                                <div id="graficoComparativoIngresoCostoUtilidadContainer">
                                    <canvas id="graficoComparativoIngresoCostoUtilidad"></canvas>
                                </div>
                            </div>
                        </div>

                    </div>

                </main>
            </div>
        </div>
        <?php
            include('partes/foot.php')
        ?>  

        <div class="modal fade" id="modalGraficosOcultos" tabindex="-1" aria-labelledby="modalGraficosOcultosLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalGraficosOcultosLabel">Administrar Gráficos</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <h6>Gráficos Ocultos</h6>
                        <div id="graficos-ocultos" class="d-flex flex-wrap gap-2 mb-3"></div>
                        <hr>
                        <button id="btnReiniciarLayout" class="btn btn-warning">Reestablecer Layout</button>
                    </div>
                </div>
            </div>
        </div>


        <!-- CSS Gridstack -->
        <link href="https://cdn.jsdelivr.net/npm/gridstack@9.2.2/dist/gridstack.min.css" rel="stylesheet" />

        <!-- JS Gridstack y lodash -->
        <script src="https://cdn.jsdelivr.net/npm/lodash@4/lodash.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gridstack@9.2.2/dist/gridstack-all.min.js"></script>

        <!-- Incluye Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script src="scripts/dashboard.js"></script>

        <!-- Modales -->
         <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        
    </body>
</html>