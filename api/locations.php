<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_GET['action'] ?? '';
$pdo = getDBConnection();

try {
    if ($action === 'get_areas') {
        $ward_id = $_GET['ward_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, name FROM area_master WHERE ward_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$ward_id]);
        $areas = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $areas]);
    } 
    elseif ($action === 'get_spots') {
        $area_id = $_GET['area_id'] ?? 0;
        $stmt = $pdo->prepare("SELECT id, name FROM spot_master WHERE area_id = ? AND status = 'active' ORDER BY name");
        $stmt->execute([$area_id]);
        $spots = $stmt->fetchAll();
        
        echo json_encode(['status' => 'success', 'data' => $spots]);
    } 
    else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
