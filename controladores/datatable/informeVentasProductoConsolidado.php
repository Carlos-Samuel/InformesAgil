<?php
require_once '../Connection.php';
header('Content-Type: application/json');

$fechaInicio = $_POST['fechaInicio'] ?? null;
$fechaFin    = $_POST['fechaFin'] ?? null;

if (!$fechaInicio || !$fechaFin) {
    echo json_encode([
        'data' => [],
        'error' => 'Fechas obligatorias'
    ]);
    exit;
}

$vendedor = $_POST['vendedor'] ?? [];
$empresa  = $_POST['empresa'] ?? [];
$grupo    = $_POST['grupo'] ?? [];
$subgrupo = $_POST['subgrupo'] ?? [];
$marca    = $_POST['marca'] ?? [];
$prefijo  = $_POST['prefijo'] ?? [];

$con = Connection::getInstance()->getConnection();

function agregarFiltroMultiple(&$where, &$params, &$types, $nombreColumna, $valores) {
    if (is_array($valores) && count($valores) > 0) {
        $placeholders = implode(',', array_fill(0, count($valores), '?'));
        $where .= " AND $nombreColumna IN ($placeholders)";
        foreach ($valores as $valor) {
            $params[] = $valor;
            $types .= 's';
        }
    }
}

/* ============================
   WHERE PRINCIPAL (VENTAS)
   ============================ */
$whereVentas = "WHERE ve.vtafec BETWEEN ? AND ?";
$paramsVentas = [$fechaInicio, $fechaFin];
$typesVentas = "ss";

agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'ven.venid', $vendedor);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'ven.empid', $empresa);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'prod.grpid', $grupo);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'prod.subid', $subgrupo);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'mar.marid', $marca);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'pre.prfid', $prefijo);

/* ============================
   WHERE SUBCONSULTA (KARMOVI)
   ============================ */
$whereKarmovi = "WHERE km.karfec <= ?";
$paramsKarmovi = [$fechaFin];
$typesKarmovi = "s";

// Aquí aplicas empresa sobre karmovi
agregarFiltroMultiple($whereKarmovi, $paramsKarmovi, $typesKarmovi, 'km.empid', $empresa);

$sql = "
    SELECT
        prod.procod AS codigo_producto,
        prod.prorefer AS registro_ica,
        prod.pronom AS nombre_producto,

        SUM(vedet.vtacant) AS cantidad,

        CASE
            WHEN SUM(vedet.vtacant) = 0 THEN 0
            ELSE ROUND(SUM(vedet.vtacant * vedet.vtavlruni) / SUM(vedet.vtacant), 2)
        END AS valor_unitario,

        SUM(vedet.vtacant * vedet.vtavlruni) AS subtotal,

        COALESCE(inv.existencias, 0) AS existencias,

        gru.grpnom AS grupo,
        subgru.subnom AS subgrupo,
        mar.marnom AS marca

    FROM ventas AS ve
    LEFT JOIN ventasdet AS vedet ON ve.vtaid = vedet.vtaid
    LEFT JOIN productos AS prod ON vedet.proid = prod.proid
    LEFT JOIN vendedor AS ven ON ve.venid = ven.venid
    LEFT JOIN inmarca AS mar ON prod.marid = mar.marid
    LEFT JOIN ingrupos AS gru ON prod.grpid = gru.grpid
    LEFT JOIN insubgrupo AS subgru ON prod.subid = subgru.subid
    LEFT JOIN prefijo AS pre ON ve.prfid = pre.prfid

    LEFT JOIN (
        SELECT
            km.proid,
            SUM(
                CASE
                    WHEN tmi.tmiid IS NULL THEN -km.karcan
                    WHEN tmi.TmiTipo = '+' THEN km.karcan
                    WHEN tmi.TmiTipo = '-' THEN -km.karcan
                    ELSE 0
                END
            ) AS existencias
        FROM karmovi km
        INNER JOIN tipmovinv tmi ON km.tmiid = tmi.tmiid
        $whereKarmovi
        GROUP BY km.proid
    ) AS inv ON prod.proid = inv.proid

    $whereVentas
    AND prod.procod IS NOT NULL

    GROUP BY
        prod.proid,
        prod.procod,
        prod.prorefer,
        prod.pronom,
        inv.existencias,
        gru.grpnom,
        subgru.subnom,
        mar.marnom

    ORDER BY prod.pronom
";

/*
    El orden de parámetros debe coincidir con el orden de los ? en el SQL:
    1. Primero los de la subconsulta (karmovi)
    2. Luego los del WHERE principal (ventas)
*/
$paramsFinal = array_merge($paramsKarmovi, $paramsVentas);
$typesFinal  = $typesKarmovi . $typesVentas;

$query = $con->prepare($sql);

if (!$query) {
    echo json_encode([
        'data' => [],
        'error' => 'Error al preparar la consulta: ' . $con->error
    ]);
    exit;
}

$query->bind_param($typesFinal, ...$paramsFinal);
$query->execute();
$result = $query->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['data' => $data]);