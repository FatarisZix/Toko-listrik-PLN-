<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : (isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0);

if (!$customer_id) {
    echo json_encode(['success' => false, 'error' => 'ID pelanggan tidak valid']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT period_month, meter_start, meter_end FROM usage_records WHERE customer_id = ? ORDER BY period_month DESC LIMIT 1");
    $stmt->execute([$customer_id]);
    $data = $stmt->fetch();
    if ($data) {
        $data['kwh_usage'] = $data['meter_end'] - $data['meter_start'];
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Data laporan meter tidak ditemukan']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
} 