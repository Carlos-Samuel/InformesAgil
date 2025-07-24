<?php

require_once '../Connection.php';
require_once realpath(__DIR__ . '/../../vendor/autoload.php');
header('Content-Type: application/json');

try {
    if (!isset($_POST['grupo_id'])) {
        throw new Exception("Falta el parámetro 'grupo_id'");
    }

    $grupoId = intval($_POST['grupo_id']);

    $con = Connection::getInstance()->getConnection();

    $stmt = $con->prepare("SELECT subid AS id, subnom AS nombre FROM insubgrupo WHERE grpid = ? ORDER BY nombre");
    $stmt->bind_param("i", $grupoId);
    $stmt->execute();

    $result = $stmt->get_result();

    $subgrupos = [];
    while ($row = $result->fetch_assoc()) {
        $subgrupos[] = $row;
    }

    echo json_encode($subgrupos);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
