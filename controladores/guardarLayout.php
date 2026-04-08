<?php
require_once 'ConnectionParametrizacion.php';
require_once realpath(__DIR__ . '/../vendor/autoload.php');
header('Content-Type: application/json');

session_start();
$usuario_id = 1;

try {
    $con = ConnectionParametrizacion::getInstance()->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    $layout = $input['layout'] ?? [];

    $stmt = $con->prepare("
        INSERT INTO dashboard_layout (usuario_id, grafico_id, pos_x, pos_y, ancho, alto, visible)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            pos_x = VALUES(pos_x),
            pos_y = VALUES(pos_y),
            ancho = VALUES(ancho),
            alto = VALUES(alto),
            visible = VALUES(visible)
    ");

    foreach ($layout as $item) {
        $grafico_id = $item['grafico_id'];
        $pos_x = $item['pos_x'];
        $pos_y = $item['pos_y'];
        $ancho = $item['ancho'];
        $alto = $item['alto'];
        $visible = isset($item['visible']) ? (int)$item['visible'] : 1;

        $stmt->bind_param(
            'isiiiii',
            $usuario_id,
            $grafico_id,
            $pos_x,
            $pos_y,
            $ancho,
            $alto,
            $visible
        );

        $stmt->execute();
    }


    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
