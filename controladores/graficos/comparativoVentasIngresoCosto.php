<?php

require_once '../Connection.php';
require_once '../Devoluciones.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("
        SELECT 
            MONTH(fecha) AS mes,
            SUM(ingreso) AS ingreso,
            SUM(costo) AS costo
        FROM (
            -- vtavlruni y vtacosto son valores UNITARIOS: se multiplican por la cantidad
            SELECT ventas.vtafec AS fecha,
                   ventasdet.vtacant * ventasdet.vtavlruni AS ingreso,
                   ventasdet.vtacant * ventasdet.vtacosto  AS costo
            FROM ventasdet
            JOIN ventas ON ventasdet.vtaid = ventas.vtaid
            UNION ALL
            SELECT d.devfec,
                   -" . DEV_VALOR_LINEA . ",
                   -dd.devcant * dd.devcosto
            FROM tbl_devolucion AS d
            JOIN tbl_devolucion_detalle AS dd ON dd.devid = d.devid
            WHERE " . DEV_CONDICION . "
        ) AS t
        WHERE YEAR(fecha) = YEAR(CURDATE())
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
