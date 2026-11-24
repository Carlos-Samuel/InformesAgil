<?php

require_once '../Connection.php';
require_once '../Devoluciones.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $con = Connection::getInstance()->getConnection();

    // Obtener los últimos 3 años
    $stmt = $con->prepare("
        SELECT
            YEAR(fecha) AS anio,
            MONTH(fecha) AS mes,
            SUM(valor) AS total
        FROM (
            SELECT vtafec AS fecha, vtasubtot AS valor FROM ventas
            UNION ALL
            SELECT d.devfec, -d.devsubt FROM tbl_devolucion AS d WHERE " . DEV_CONDICION . "
        ) AS t
        WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
        GROUP BY anio, mes
        ORDER BY anio, mes
    ");
    $stmt->execute();

    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $anio = $row['anio'];
        $mes = (int) $row['mes'];
        $total = (float) $row['total'];
        
        if (!isset($data[$anio])) {
            $data[$anio] = array_fill(1, 12, 0); // Inicializa todos los meses en 0
        }

        $data[$anio][$mes] = $total;
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
