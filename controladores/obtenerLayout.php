<?php
require_once 'ConnectionParametrizacion.php';
require_once realpath(__DIR__ . '/../vendor/autoload.php');
header('Content-Type: application/json');

try {
    $usuario_id = $_SESSION['usuario_id'] ?? 1;

    $con = ConnectionParametrizacion::getInstance()->getConnection();
    $stmt = $con->prepare("SELECT grafico_id, pos_x, pos_y, ancho, alto, visible FROM dashboard_layout WHERE usuario_id = ?");
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $layout = [];
    while ($row = $result->fetch_assoc()) {
        $layout[$row['grafico_id']] = $row;
    }

    echo json_encode(['success' => true, 'layout' => $layout]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
