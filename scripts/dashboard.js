let grid;

const colorPalettes = {
    1: 0,
    2: 0,
    3: 0,
    4: 0
};

const graficosOcultos = new Set();

const graficosConfig = [
    {
        idCanvas: 'graficoComparativoVentasMesAnio',
        tipo: 'line',
        url: 'comparativoVentasMesAnio.php',
        procesarDatos: function (response) {
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

            return {
                labels: meses,
                datasets: datasets,
                options: {
                    title: 'Ventas por Mes - Últimos 4 Años',
                    ejeX: 'Mes',
                    ejeY: 'Total Ventas'
                }
            };
        }
    },
    {
        idCanvas: 'graficoComparativoVentasAnio',
        tipo: 'bar',
        url: 'comparativoVentasAnio.php',
        procesarDatos: function (response) {
            const data = response.data;
            const labels = Object.keys(data);
            const values = Object.values(data);
            const backgroundColors = labels.map(() => getColor(2));

            return {
                labels: labels,
                datasets: [{
                    label: 'Total Ventas por Año',
                    data: values,
                    backgroundColor: backgroundColors
                }],
                options: {
                    title: 'Comparativo de Ventas por Año',
                    ejeX: 'Año',
                    ejeY: 'Total Ventas'
                }
            };
        }
    },
    {
        idCanvas: 'graficoComparativoVentasMesPrefijo',
        tipo: 'bar',
        url: 'comparativoVentasMesPrefijo.php',
        procesarDatos: function (response) {
            const rawData = response.data;
            const allMeses = new Set();

            // Recolectar todos los meses presentes
            Object.values(rawData).forEach(prefijoData => {
                Object.keys(prefijoData).forEach(mes => {
                    allMeses.add(parseInt(mes));
                });
            });

            const mesesOrdenados = Array.from(allMeses).sort((a, b) => a - b);
            const labels = mesesOrdenados.map(m => ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"][m - 1]);

            const datasets = [];

            Object.keys(rawData).forEach(prefijo => {
                const ventas = mesesOrdenados.map(mes => rawData[prefijo][mes] || 0);
                datasets.push({
                    label: prefijo,
                    data: ventas,
                    backgroundColor: getColor(3)
                });
            });

            return {
                labels: labels,
                datasets: datasets,
                options: {
                    title: 'Comparativo de Ventas Mensuales por Prefijo (Año Actual)',
                    ejeX: 'Mes',
                    ejeY: 'Total Ventas'
                }
            };
        }

    },
    {
        idCanvas: 'graficoComparativoIngresoCostoUtilidad',
        tipo: 'bar',
        url: 'comparativoVentasIngresoCosto.php',
        procesarDatos: function (response) {
            const data = response.data;
            const meses = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];

            const labels = Object.keys(data)
                .map(m => parseInt(m))
                .sort((a, b) => a - b)
                .map(m => meses[m - 1]);

            const ingreso = [];
            const costo = [];
            const utilidad = [];

            Object.keys(data).sort((a, b) => a - b).forEach(mes => {
                ingreso.push(data[mes].ingreso);
                costo.push(data[mes].costo);
                utilidad.push(data[mes].utilidad);
            });

            return {
                labels: labels,
                datasets: [
                    {
                        label: 'Ingreso',
                        data: ingreso,
                        backgroundColor: '#3498db',
                        type: 'bar'
                    },
                    {
                        label: 'Costo',
                        data: costo,
                        backgroundColor: '#2ecc71',
                        type: 'bar'
                    },
                    {
                        label: 'Utilidad',
                        data: utilidad,
                        borderColor: '#e67e22',
                        backgroundColor: 'transparent',
                        borderWidth: 3,
                        type: 'line',
                        tension: 0.3,
                    }
                ],
                options: {
                    title: 'Utilidad en Ventas Bruta: Ingreso vs Costo',
                    ejeX: 'Mes',
                    ejeY: 'Valores COP'
                }
            };
        }
    }


];

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
    return Math.max(10, Math.round(alto / 30));
}

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

function cargarGraficosDesdeConfig(config) {
    const canvas = document.getElementById(config.idCanvas);

    $.ajax({
        url: 'controladores/graficos/' + config.url,
        type: 'POST',
        dataType: 'json',
        success: function (response) {
            if (!response.success) {
                console.error(`Error en datos para ${config.idCanvas}:`, response.error);
                return;
            }

            const procesado = config.procesarDatos(response);

            const tamanioFuente = calcularTamanioFuente(canvas);

            const chart = new Chart(canvas, {
                type: config.tipo,
                data: {
                    labels: procesado.labels,
                    datasets: procesado.datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        title: {
                            display: true,
                            text: procesado.options.title,
                            font: { size: tamanioFuente + 4 }
                        },
                        legend: {
                            labels: {
                                font: { size: tamanioFuente }
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
                            bodyFont: { size: tamanioFuente }
                        }
                    },
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: procesado.options.ejeY,
                                font: { size: tamanioFuente }
                            },
                            ticks: {
                                font: { size: tamanioFuente },
                                callback: formatearNumeroEje
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: procesado.options.ejeX,
                                font: { size: tamanioFuente }
                            },
                            ticks: { font: { size: tamanioFuente } }
                        }
                    }
                }
            });

            canvas.chart = chart;
            aplicarFuenteDinamica(chart, canvas);
        },
        error: function (xhr, status, error) {
            console.error(`Error en AJAX para ${config.idCanvas}:`, error);
        }
    });
}

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
        actualizarPanelOcultos();
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

    $.ajax({
        url: 'controladores/guardarLayout.php',
        type: 'POST',
        contentType: 'application/json',
        dataType: 'json',
        data: JSON.stringify({ layout: cambios }),
        success: function (data) {
            if (!data.success) {
                console.error("Error guardando layout:", data.error);
            }
        },
        error: function (xhr, status, error) {
            console.error("Error en solicitud:", error);
        }
    });

}

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
            $.ajax({
                url: 'controladores/guardarLayout.php',
                type: 'POST',
                contentType: 'application/json',
                dataType: 'json',
                data: JSON.stringify({ layout: cambios }),
                success: function (data) {
                    if (!data.success) {
                        console.error("Error guardando layout:", data.error);
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error en solicitud:", error);
                }
            });
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

function reestablecerLayout() {
    if (confirm('¿Estás seguro de que deseas reestablecer el layout? Esta acción eliminará tu configuración personalizada.')) {
        $.ajax({
            url: 'controladores/reiniciarLayout.php',
            type: 'POST',
            dataType: 'json',
            success: function (data) {
                if (data.success) {
                    location.reload();
                } else {
                    alert('No se pudo reestablecer el layout: ' + data.error);
                }
            },
            error: function (xhr, status, error) {
                console.error('Error al reestablecer:', error);
                alert('Ocurrió un error inesperado.');
            }
        });
    }
}

function obtenerLayout() {
    $.ajax({
        url: 'controladores/obtenerLayout.php',
        type: 'GET',
        dataType: 'json',
        success: function (data) {
            if (data.success) {
                iniciarDashboard(data.layout);
            } else {
                console.error('No se pudo obtener el layout:', data.error);
                iniciarDashboard({}); // continuar sin layout
            }
        },
        error: function (xhr, status, error) {
            console.error('Error obteniendo layout:', error);
            iniciarDashboard({}); // continuar sin layout
        }
    });
}

$(document).ready(function () {
    $('#btnReiniciarLayout').click(reestablecerLayout);

    graficosConfig.forEach(cargarGraficosDesdeConfig);
    
    obtenerLayout();

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
    
});
