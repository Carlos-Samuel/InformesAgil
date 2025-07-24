<?php
require_once '../Connection.php';
header('Content-Type: application/json');

// Validar fechas obligatorias
$fechaInicio = $_POST['fechaInicio'] ?? null;
$fechaFin = $_POST['fechaFin'] ?? null;

if (!$fechaInicio || !$fechaFin) {
    echo json_encode([
        'data' => [],
        'error' => 'Fechas obligatorias'
    ]);
    exit;
}

$vendedor = $_POST['vendedor'] ?? null;
$grupo = $_POST['grupo'] ?? null;
$subgrupo = $_POST['subgrupo'] ?? null;

$con = Connection::getInstance()->getConnection();

// Construcción del WHERE dinámico
$where = "WHERE ve.vtafec BETWEEN ? AND ?";
$params = [$fechaInicio, $fechaFin];
$types = "ss";

if ($vendedor) {
    $where .= " AND ven.venid = ?";
    $params[] = $vendedor;
    $types .= "s";
}
if ($grupo) {
    $where .= " AND prod.grpid = ?";
    $params[] = $grupo;
    $types .= "s";
}
if ($subgrupo) {
    $where .= " AND prod.subid = ?";
    $params[] = $subgrupo;
    $types .= "s";
}

// Consulta principal
$query = $con->prepare(
    "SELECT 
        ve.vtafec AS fecha_factura,
        pre.prfcod AS prefijo,
        pre.prfnom AS prefijo_nombre,
        ve.vtanum AS numero_factura,
        ven.vencod AS codigo_vendedor,
        ven.vennom AS nombre_vendedor,
        ter.ternit AS tercero_nit,
        ter.ternom AS tercero_nombre,
        prod.procod AS codigo_producto,
        prod.pronom AS nombre_producto,
        vedet.vtacant AS cantidad,
        vedet.vtavlruni AS valor_unitario,
        vedet.vtacant * vedet.vtavlruni AS subtotal,
        gru.grpnom AS grupo,
        subgru.subnom AS subgrupo,
        mar.marcod AS codigo_marca,
        mar.marnom AS nombre_marca,
        cc.ctonom AS centro_costos
    FROM ventas AS ve 
    LEFT JOIN prefijo AS pre ON ve.prfid = pre.prfid
    LEFT JOIN ventasdet AS vedet ON ve.vtaid = vedet.vtaid
    LEFT JOIN productos AS prod ON vedet.proid = prod.proid
    LEFT JOIN vendedor AS ven ON ve.venid = ven.venid
    LEFT JOIN terceros AS ter ON ve.terid = ter.terid
    LEFT JOIN ingrupos AS gru ON prod.grpid = gru.grpid
    LEFT JOIN insubgrupo AS subgru ON prod.subid = subgru.subid
    LEFT JOIN inmarca AS mar ON prod.marid = mar.marid
    LEFT JOIN centrocosto AS cc ON ve.ctoid = cc.ctoid
    $where
    ORDER BY ve.vtafec, ve.vtahor
");

$query->bind_param($types, ...$params);
$query->execute();
$result = $query->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['data' => $data]);
