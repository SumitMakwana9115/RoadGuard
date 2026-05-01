<?php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$pdo = getDBConnection();

$title = $_POST['title'] ?? '';
$category_id = $_POST['category_id'] ?? 0;
$user_id = $_SESSION['user_id'];

try {
    // Check if user submitted a similar complaint in the last 30 days
    $stmt = $pdo->prepare("
        SELECT id, complaint_uid, status_id 
        FROM complaints 
        WHERE complainant_id = ? 
        AND category_id = ? 
        AND title LIKE ?
        AND complaint_date > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    
    $searchTerm = '%' . substr($title, 0, 10) . '%'; // Match first 10 chars of title
    $stmt->execute([$user_id, $category_id, $searchTerm]);
    $duplicate = $stmt->fetch();
    
    if ($duplicate) {
        echo json_encode(['is_duplicate' => true, 'duplicate_id' => $duplicate['complaint_uid']]);
    } else {
        echo json_encode(['is_duplicate' => false]);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
