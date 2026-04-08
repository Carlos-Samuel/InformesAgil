<?php
require_once 'ConnectionParametrizacion.php';
require_once realpath(__DIR__ . '/../vendor/autoload.php');
header('Content-Type: application/json');

session_start();
$usuario_id = $_SESSION['usuario_id'] ?? 1; 

try {
    $con = ConnectionParametrizacion::getInstance()->getConnection();

    $stmt = $con->prepare("DELETE FROM dashboard_layout WHERE usuario_id = ?");
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
