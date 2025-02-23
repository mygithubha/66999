<?php
require_once 'config.php';
session_start();

// 验证管理员登录
if (!isset($_SESSION['admin'])) {
    exit('未授权访问');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id']) && isset($_POST['is_active'])) {
    $group_id = $_POST['group_id'];
    $is_active = $_POST['is_active'];
    
    try {
        $stmt = $pdo->prepare("UPDATE groups SET is_active = ? WHERE id = ?");
        $stmt->execute([$is_active, $group_id]);
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} 