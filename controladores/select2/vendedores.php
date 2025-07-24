<?php

require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');
        
try {
    $con = Connection::getInstance()->getConnection();
    $result = $con->query(
        "SELECT 
            venid AS id, 
            vennom AS nombre
        FROM vendedor
        ORDER BY nombre
        ");

    $vendedores = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $vendedores[] = $row;
        }
    }
    echo json_encode($vendedores);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}