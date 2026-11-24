
// VENDEDOR
$('#vendedor').select2({
    placeholder: 'Seleccione uno o mas vendedores',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/vendedores.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || '' 
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: item.text
                }))
            };
        },
        cache: true
    }
});


// EMPRESA
$('#empresa').select2({
    placeholder: 'Seleccione una o mas empresas',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/empresas.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || ''
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: `${item.codigo} - ${item.text}`
                }))
            };
        },
        cache: true
    }
});


// MARCA
$('#marca').select2({
    placeholder: 'Seleccione una o mas marcas',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/marcas.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || '',
                empresa_id: $('#empresa').val() || null
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: `${item.codigo} - ${item.text}`
                }))
            };
        },
        cache: true
    }
});

// PREFIJOS
$('#prefijo').select2({
    placeholder: 'Seleccione uno o mas prefijos',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/prefijos.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || '',
                empresa_id: $('#empresa').val() || null
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: `${item.codigo} - ${item.text}`
                }))
            };
        },
        cache: true
    }
});

// GRUPO
$('#grupo').select2({
    placeholder: 'Seleccione uno o mas grupos',
    width: '100%',
    multiple: true,
    ajax: {
        url: 'controladores/select2/grupos.php',
        type: 'POST',
        dataType: 'json',
        delay: 250,
        data: function (params) {
            return {
                search: params.term || '',
                empresa_id: $('#empresa').val() || null
            };
        },
        processResults: function (data) {
            return {
                results: data.map(item => ({
                    id: item.id,
                    text: `${item.codigo} - ${item.text}`
                }))
            };
        },
        cache: true
    }
});

// SUBGRUPO (dependiente del grupo)
$('#subgrupo').select2({
    placeholder: 'Seleccione uno o mas subgrupos',
    width: '100%',
    multiple: true,
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
                results: data.map(item => ({
                    id: item.id,
                    text: `${item.codigo} - ${item.text}`
                }))
            };
        },
        cache: true
    }
});

$('#empresa').on('change', function () {
    $('#grupo').val(null).trigger('change');
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
        scrollX: true,
        scrollY: '60vh',
        scrollCollapse: true,
        scrollX: true,
        fixedHeader: true,
        dom: '<"row align-items-center mb-2"' +
                '<"col-sm-4"l>' +       // Mostrar X registros
                '<"col-sm-4 text-center"B>' + // Botones de exportar
                '<"col-sm-4 text-end"f>' +    // Filtro de búsqueda
            '>' +
            '<"row"<"col-sm-12"tr>>' +       // Tabla
            '<"row mt-2"' +
                '<"col-sm-6"i>' +       // Información ("Mostrando de X a Y")
                '<"col-sm-6 text-end"p>' +  // Paginación
            '>',
        ajax: {
            url: 'controladores/datatable/informeVentasProductoConsolidado.php',
            type: 'POST',
            data: datosFiltro
        },
        columns: [
            { data: 'codigo_producto' },
            { data: 'registro_ica' },
            { data: 'nombre_producto' },
            { data: 'cantidad_vendida' },
            { data: 'cantidad_devuelta' },
            { data: 'cantidad' },
            {
                data: 'valor_unitario',
                render: function (data, type, row) {
                    if (type === "display" || type === "filter") {
                        return formatoDinero(data);
                    }
                    return data;
                }
            },

            {
                data: 'subtotal',
                render: function (data, type, row) {
                    if (type === "display" || type === "filter") {
                        return formatoDinero(data);
                    }
                    return data;
                }
            },
            { data: 'existencias' },
            { data: 'grupo' },
            { data: 'subgrupo' },
            { data: 'marca' }
        ],
        language: {
            url: 'js/datatable/es-ES.json'
        },
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

                    const getMultipleTexts = (selector) => {
                        return $(selector).select2('data').map(item => item.text.trim()).join(', ') || 'Todos';
                    };

                    const info = [
                        `Fecha Inicial: ${$('#fechaInicio').val() || ''}`,
                        `Fecha Final: ${$('#fechaFin').val() || ''}`,
                        `Vendedor(es): ${getMultipleTexts('#vendedor')}`,
                        `Empresa(s): ${getMultipleTexts('#empresa')}`,
                        `Marca(s): ${getMultipleTexts('#marca')}`,
                        `Prefijos(s): ${getMultipleTexts('#prefijo')}`,
                        `Grupo(s): ${getMultipleTexts('#grupo')}`,
                        `Subgrupo(s): ${getMultipleTexts('#subgrupo')}`
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
                    for (let i = info.length - 1; i >= 0; i--) {
                        const row = doc.createElement('row');
                        row.setAttribute('r', i + 1);

                        const cell = doc.createElement('c');
                        cell.setAttribute('t', 'inlineStr');
                        cell.setAttribute('r', 'A' + (i + 1));

                        const is = doc.createElement('is');
                        const t = doc.createElement('t');
                        t.textContent = info[i] || '';

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
        ],
        drawCallback: function () {
            const api = this.api();
            const data = api.rows({ page: 'all' }).data();

            let totalVendida = 0;
            let totalDevuelta = 0;
            let totalCantidad = 0;
            let totalSubtotal = 0;
            let totalExistencias = 0;

            for (let i = 0; i < data.length; i++) {
                const vend = parseFloat(data[i].cantidad_vendida);
                const dev  = parseFloat(data[i].cantidad_devuelta);
                if (!isNaN(vend)) totalVendida += vend;
                if (!isNaN(dev))  totalDevuelta += dev;

                const cant = parseFloat(data[i].cantidad);
                const sub  = parseFloat(data[i].subtotal);
                const exi  = parseFloat(data[i].existencias);

                if (!isNaN(cant)) totalCantidad += cant;
                if (!isNaN(sub))  totalSubtotal += sub;
                if (!isNaN(exi))  totalExistencias += exi;
            }

            $('#totalesVisual').show();
            $('#totalVendida').text(totalVendida.toLocaleString('es-CO'));
            $('#totalDevuelta').text(totalDevuelta.toLocaleString('es-CO'));
            $('#totalCantidad').text(totalCantidad.toLocaleString('es-CO'));
            $('#totalSubtotal').text(totalSubtotal.toLocaleString('es-CO', { style: 'currency', currency: 'COP' }));
            $('#totalExistencias').text(totalExistencias.toLocaleString('es-CO'));
        }



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
        subgrupo: $('#subgrupo').val(),
        empresa: $('#empresa').val(),
        marca : $('#marca').val(),
        prefijo: $('#prefijo').val()
    });

});


function formatoDinero(valor) {
    let num = parseFloat(valor);
    if (isNaN(num)) num = 0;

    return num.toLocaleString('es-CO', {
        style: 'currency',
        currency: 'COP',
        minimumFractionDigits: 0
    });
}



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