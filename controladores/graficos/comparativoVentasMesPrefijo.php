<?php
require_once '../Connection.php';
require_once '../Devoluciones.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT 
            MONTH(v.fecha) AS mes,
            p.prfnom AS prefijo,
            SUM(v.valor) AS total
        FROM (
            SELECT vtafec AS fecha, prfid, vtasubtot AS valor FROM ventas
            UNION ALL
            -- Devoluciones: en el prefijo de la factura original
            SELECT d.devfec, d.prfid, -d.devsubt FROM tbl_devolucion AS d WHERE " . DEV_CONDICION . "
        ) AS v
        JOIN prefijo p ON v.prfid = p.prfid
        WHERE YEAR(v.fecha) = YEAR(CURDATE())
        GROUP BY mes, prefijo
        ORDER BY mes, prefijo
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $mes = (int) $row['mes'];
        $prefijo = $row['prefijo'];
        $total = (float) $row['total'];

        if (!isset($data[$prefijo])) {
            $data[$prefijo] = []; // no se inicializa con todos los meses
        }

        $data[$prefijo][$mes] = $total;
    }


    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
