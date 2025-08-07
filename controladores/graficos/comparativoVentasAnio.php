<?php

require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT 
            YEAR(vtafec) AS anio,
            SUM(vtasubtot) AS total
        FROM ventas
        WHERE vtafec >= DATE_SUB(CURDATE(), INTERVAL 3 YEAR)
        GROUP BY anio
        ORDER BY anio
    ");
    $stmt->execute();

    $result = $stmt->get_result();

    $data = [];
    while ($row = $result->fetch_assoc()) {
        $anio = $row['anio'];
        $total = (float) $row['total'];
        $data[$anio] = $total;
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
