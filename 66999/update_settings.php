<?php
require_once 'config.php';
session_start();

// 验证管理员登录
if (!isset($_SESSION['admin'])) {
    exit(json_encode(['success' => false, 'error' => '未授权访问']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setting_key']) && isset($_POST['setting_value'])) {
    try {
        // 先检查设置是否存在
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
        $stmt->execute([$_POST['setting_key']]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            // 如果存在就更新
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$_POST['setting_value'], $_POST['setting_key']]);
        } else {
            // 如果不存在就插入
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$_POST['setting_key'], $_POST['setting_value']]);
        }
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        error_log("设置更新失败: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => '无效的请求']);
} 