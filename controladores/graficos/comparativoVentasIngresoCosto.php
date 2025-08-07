<?php

require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT 
            MONTH(vtafec) AS mes,
            SUM(vtavlruni) AS ingreso,
            SUM(vtacosto) AS costo
        FROM ventasdet
        LEFT JOIN ventas ON ventasdet.vtaid = ventas.vtaid
        WHERE YEAR(vtafec) = YEAR(CURDATE())
        GROUP BY mes
        ORDER BY mes
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $data = [];

    while ($row = $result->fetch_assoc()) {
        $mes = (int) $row['mes'];
        $ingreso = (float) $row['ingreso'];
        $costo = (float) $row['costo'];
        $utilidad = $ingreso - $costo;

        $data[$mes] = [
            'ingreso' => $ingreso,
            'costo' => $costo,
            'utilidad' => $utilidad
        ];
    }

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
