<!doctype html>
<html lang="es" data-bs-theme="auto">
    <head>
        <?php
            include('partes/head.php')
        ?>
        <style>
            #graficoComparativoVentasMesAnioContainer {
                position: relative;
                width: 100%;
                height: 400px;
            }

            #graficoComparativoVentasAnioContainer {
                position: relative;
                width: 100%;
                height: 400px;
            }

        </style>
    </head>
    <body>
        <div class="layout has-sidebar fixed-sidebar fixed-header">
            <?php
                include('partes/sidebar.php');
            ?>  
            <div id="overlay" class="overlay"></div>
            <div class="layout">
                <main class="content">
                    <h1>Interfaz empresarial modular</h1>
                    <div id="panel-graficos-ocultos" class="mb-3">
                        <h5>Gráficos Ocultos</h5>
                        <div id="graficos-ocultos" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="grid-stack">
                        <div class="grid-stack-item" gs-w="6" gs-h="4" gs-x="0" gs-y="0" id="item-graficoComparativoVentasMesAnio">
                            <div class="grid-stack-item-content">
                                <button class="btn btn-sm btn-danger btn-ocultar-grafico" data-id="graficoComparativoVentasMesAnio" style="position: absolute; top: 5px; right: 5px; z-index: 10;">×</button>
                                <div style="position: relative; width: 100%; height: 100%;">
                                    <canvas id="graficoComparativoVentasMesAnio"></canvas>
                                </div>
                            </div>
                        </div>

                        <div class="grid-stack-item" gs-w="6" gs-h="4" gs-x="6" gs-y="0" id="item-graficoComparativoVentasAnio">
                            <div class="grid-stack-item-content">
                                <button class="btn btn-sm btn-danger btn-ocultar-grafico" data-id="graficoComparativoVentasAnio" style="position: absolute; top: 5px; right: 5px; z-index: 10;">×</button>
                                <div style="position: relative; width: 100%; height: 100%;">
                                    <canvas id="graficoComparativoVentasAnio"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button id="btnReiniciarLayout" class="btn btn-warning mb-3">Reestablecer Layout</button>
                </main>
            </div>
        </div>
        <?php
            include('partes/foot.php')
        ?>  
        <!-- CSS Gridstack -->
        <link href="https://cdn.jsdelivr.net/npm/gridstack@9.2.2/dist/gridstack.min.css" rel="stylesheet" />

        <!-- JS Gridstack y lodash -->
        <script src="https://cdn.jsdelivr.net/npm/lodash@4/lodash.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/gridstack@9.2.2/dist/gridstack-all.min.js"></script>

        <!-- Incluye Chart.js -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>

            let grid;

            const colorPalettes = {
                1: 0,
                2: 0
            };

            const definedColors = [
                '#3366CC', // Azul fuerte
                '#DC3912', // Rojo
                '#FF9900', // Naranja
                '#109618', // Verde
                '#990099', // Púrpura
                '#3B3EAC', // Azul oscuro
                '#0099C6', // Cian
                '#DD4477', // Rosado
                '#66AA00', // Verde claro
                '#B82E2E'  // Rojo oscuro
            ];

            function getColor(group) {
                if (!(group in colorPalettes)) {
                    colorPalettes[group] = 0;
                }

                const index = colorPalettes[group];

                if (index < definedColors.length) {
                    return definedColors[colorPalettes[group]++];
                } else {
                    return 'hsl(' + Math.floor(Math.random() * 360) + ', 70%, 50%)';
                }
            }

            function formatearNumeroEje(value) {
                if (value >= 1_000_000_000) {
                    return '$' + (value / 1_000_000_000).toFixed(1) + ' mil M'; // mil millones
                } else if (value >= 1_000_000) {
                    return '$' + (value / 1_000_000).toFixed(1) + ' M'; // millones
                } else if (value >= 1_000) {
                    return '$' + (value / 1_000).toFixed(1) + ' mil'; // miles
                }
                return '$' + value;
            }

            function calcularTamanioFuente(canvas) {
                const alto = canvas.clientHeight;
                return Math.max(10, Math.round(alto / 20));
            }

            $(document).ready(function () {
                $('#btnReiniciarLayout').click(function () {
                    if (confirm('¿Estás seguro de que deseas reestablecer el layout? Esta acción eliminará tu configuración personalizada.')) {
                        fetch('controladores/reiniciarLayout.php', {
                            method: 'POST'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload(); // Recarga para aplicar layout por defecto
                            } else {
                                alert('No se pudo reestablecer el layout: ' + data.error);
                            }
                        })
                        .catch(err => {
                            console.error('Error al reestablecer:', err);
                            alert('Ocurrió un error inesperado.');
                        });
                    }
                });

            });
        </script>

        <script>
            $(document).ready(function () {
                $.ajax({
                    url: 'controladores/graficos/comparativoVentasMesAnio.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function (response) {
                        if (!response.success) {
                            console.error("Error en datos:", response.error);
                            return;
                        }

                        const data = response.data;
                        const meses = ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"];
                        const datasets = [];

                        Object.keys(data).sort().forEach(anio => {
                            datasets.push({
                                label: anio,
                                data: Object.values(data[anio]),
                                fill: false,
                                borderColor: getColor(1),
                                tension: 0.3
                            });
                        });


                        const canvasVentasMesAnio = document.getElementById("graficoComparativoVentasMesAnio");
                        const tamanioFuente = calcularTamanioFuente(canvasVentasMesAnio);
                        const chartVentasMesAnio = new Chart(canvasVentasMesAnio, {
                            type: 'line',
                            data: {
                                labels: meses,
                                datasets: datasets
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Ventas por Mes - Últimos 3 Años',
                                        font: {
                                            size: tamanioFuente + 4
                                        }
                                    },
                                    legend: {
                                        labels: {
                                            font: {
                                                size: tamanioFuente
                                            }
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                const value = context.raw;
                                                return new Intl.NumberFormat('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    maximumFractionDigits: 0
                                                }).format(value);
                                            }
                                        },
                                        bodyFont: {
                                            size: tamanioFuente
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        title: {
                                            display: true,
                                            text: 'Total Ventas',
                                            font: {
                                                size: tamanioFuente
                                            }
                                        },
                                        ticks: {
                                            font: {
                                                size: tamanioFuente
                                            },
                                            callback: formatearNumeroEje
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Mes',
                                            font: {
                                                size: tamanioFuente
                                            }
                                        },
                                        ticks: {
                                            font: {
                                                size: tamanioFuente
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        canvasVentasMesAnio.chart = chartVentasMesAnio;

                    },
                    error: function (xhr, status, error) {
                        console.error("Error en AJAX:", error);
                    }
                });

            });
        </script>
        <script>
            $(document).ready(function () {
                $.ajax({
                    url: 'controladores/graficos/comparativoVentasAnio.php',
                    type: 'POST',
                    dataType: 'json',
                    success: function (response) {
                        if (!response.success) {
                            console.error("Error en datos:", response.error);
                            return;
                        }

                        const data = response.data;
                        const labels = Object.keys(data);
                        const values = Object.values(data);

                        const backgroundColors = labels.map(() => getColor(2));

                        const canvasVentasAnio = document.getElementById("graficoComparativoVentasAnio");
                        const tamanioFuente = calcularTamanioFuente(canvasVentasAnio);

                        const chartVentasAnio = new Chart(canvasVentasAnio, {
                            type: 'bar',
                            data: {
                                labels: labels,
                                datasets: [{
                                    label: 'Total Ventas por Año',
                                    data: values,
                                    backgroundColor: backgroundColors
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    title: {
                                        display: true,
                                        text: 'Comparativo de Ventas por Año',
                                        font: {
                                            size: tamanioFuente + 4
                                        }
                                    },
                                    legend: {
                                        labels: {
                                            font: {
                                                size: tamanioFuente
                                            }
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function (context) {
                                                const value = context.raw;
                                                return new Intl.NumberFormat('es-CO', {
                                                    style: 'currency',
                                                    currency: 'COP',
                                                    maximumFractionDigits: 0
                                                }).format(value);
                                            }
                                        },
                                        bodyFont: {
                                            size: tamanioFuente
                                        }
                                    }
                                },
                                scales: {
                                    y: {
                                        title: {
                                            display: true,
                                            text: 'Total Ventas',
                                            font: {
                                                size: tamanioFuente
                                            }
                                        },
                                        ticks: {
                                            font: {
                                                size: tamanioFuente
                                            },
                                            callback: formatearNumeroEje
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Año',
                                            font: {
                                                size: tamanioFuente
                                            }
                                        },
                                        ticks: {
                                            font: {
                                                size: tamanioFuente
                                            }
                                        }
                                    }
                                }
                            }
                        });
                        canvasVentasAnio.chart = chartVentasAnio;
                    },
                    error: function (xhr, status, error) {
                        console.error("Error en AJAX:", error);
                    }
                });
            });
        </script>
        <script>
            function aplicarFuenteDinamica(chart, canvas, base = 0) {
                const size = calcularTamanioFuente(canvas);

                chart.options.plugins.title.font.size = size + 4 + base;
                chart.options.plugins.legend.labels.font.size = size + base;
                chart.options.plugins.tooltip.bodyFont.size = size + base;
                chart.options.scales.x.title.font.size = size + base;
                chart.options.scales.x.ticks.font.size = size + base;
                chart.options.scales.y.title.font.size = size + base;
                chart.options.scales.y.ticks.font.size = size + base;

                chart.update();
            }

            const graficosOcultos = new Set();

            document.addEventListener('DOMContentLoaded', function () {

                function iniciarDashboard(layoutGuardado) {
                    document.querySelectorAll('.grid-stack-item').forEach(item => {
                        const canvas = item.querySelector('canvas');
                        if (canvas) {
                            const config = layoutGuardado[canvas.id];
                            if (config) {
                                item.setAttribute('gs-x', config.pos_x);
                                item.setAttribute('gs-y', config.pos_y);
                                item.setAttribute('gs-w', config.ancho);
                                item.setAttribute('gs-h', config.alto);

                                if (config.visible == 0) {
                                    graficosOcultos.add(canvas.id);
                                }

                            }
                        }
                    });

                    grid = GridStack.init({
                        cellHeight: 100,
                        float: true,
                        resizable: { handles: 'all' }
                    });

                    window.gridInstance = grid;

                    grid.on('resizestop', function (event, element) {
                        const canvas = element.querySelector('canvas');
                        if (canvas && canvas.chart) {
                            aplicarFuenteDinamica(canvas.chart, canvas);
                            canvas.chart.resize();
                        }
                    });

                    grid.on('change', function (event, items) {
                        const cambios = items.map(item => ({
                            grafico_id: item.el.querySelector('canvas')?.id || '',
                            pos_x: item.x,
                            pos_y: item.y,
                            ancho: item.w,
                            alto: item.h
                        }));

                        if (cambios.length > 0) {
                            fetch('controladores/guardarLayout.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ layout: cambios })
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (!data.success) console.error("Error guardando layout:", data.error);
                            })
                            .catch(err => console.error("Error en solicitud:", err));
                        }
                    });

                    graficosOcultos.forEach(id => {
                        const item = document.getElementById('item-' + id);
                        if (item) {
                            item.style.display = 'none';
                        }
                    });
                    actualizarPanelOcultos();

                }

                
                fetch('controladores/obtenerLayout.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            iniciarDashboard(data.layout);
                        } else {
                            console.error('No se pudo obtener el layout:', data.error);
                            iniciarDashboard({}); // continuar sin layout
                        }
                    })
                    .catch(err => {
                        console.error('Error obteniendo layout:', err);
                        iniciarDashboard({}); // continuar sin layout
                    });


                document.addEventListener('click', function (e) {
                    if (e.target.classList.contains('btn-ocultar-grafico')) {
                        const graficoId = e.target.dataset.id;
                        const item = document.getElementById('item-' + graficoId);
                        if (item) {
                            item.style.display = 'none';
                            graficosOcultos.add(graficoId);
                            actualizarPanelOcultos();
                            guardarLayoutActual();
                        }
                    }

                    if (e.target.classList.contains('btn-restaurar-grafico')) {
                        const graficoId = e.target.dataset.id;
                        const item = document.getElementById('item-' + graficoId);
                        if (item) {
                            item.style.display = '';
                            graficosOcultos.delete(graficoId);
                            actualizarPanelOcultos();
                            guardarLayoutActual();
                        }
                    }
                });

                function actualizarPanelOcultos() {
                    const contenedor = document.getElementById('graficos-ocultos');
                    contenedor.innerHTML = '';
                    graficosOcultos.forEach(id => {
                        const btn = document.createElement('button');
                        btn.className = 'btn btn-outline-primary btn-restaurar-grafico';
                        btn.dataset.id = id;
                        btn.textContent = `Mostrar ${id}`;
                        contenedor.appendChild(btn);
                    });
                }

                function guardarLayoutActual() {
                    grid = window.gridInstance;

                    graficosOcultos.forEach(id => {
                        const item = document.getElementById('item-' + id);
                        if (item) {
                            item.style.display = 'none';
                        }
                        actualizarPanelOcultos(); // también actualiza los botones
                    });

                    const items = grid.engine.nodes;
                    const cambios = items.map(item => {
                        const idCanvas = item.el.querySelector('canvas')?.id || '';
                        return {
                            grafico_id: idCanvas,
                            pos_x: item.x,
                            pos_y: item.y,
                            ancho: item.w,
                            alto: item.h,
                            visible: !graficosOcultos.has(idCanvas)
                        };
                    });

                    fetch('controladores/guardarLayout.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ layout: cambios })
                    }).then(response => response.json())
                    .then(data => {
                        if (!data.success) console.error("Error guardando layout:", data.error);
                    }).catch(err => console.error("Error en solicitud:", err));
                }


               
               
            });

        </script>
    </body>
</html>