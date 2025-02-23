<?php
require_once 'config.php';
session_start();

header('Content-Type: application/json');

// 获取访问者信息
$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group_id'])) {
    try {
        $pdo->beginTransaction();
        
        // 先检查用户是否已经领取过
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM claims WHERE ip = ? AND user_agent = ?");
        $stmt->execute([$ip, $ua]);
        if ($stmt->fetchColumn() > 0) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '您已经领取过图片了']);
            exit;
        }
        
        // 检查组别是否存在且未被领取
        $stmt = $pdo->prepare("SELECT * FROM groups WHERE id = ? AND is_claimed = 0");
        $stmt->execute([$_POST['group_id']]);
        $group = $stmt->fetch();
        
        if ($group) {
            // 标记为已领取
            $stmt = $pdo->prepare("UPDATE groups SET is_claimed = 1 WHERE id = ?");
            $stmt->execute([$_POST['group_id']]);
            
            // 记录领取信息
            $stmt = $pdo->prepare("INSERT INTO claims (group_id, ip, user_agent, image_path) VALUES (?, ?, ?, ?)");
            $stmt->execute([$_POST['group_id'], $ip, $ua, $group['image_path']]);
            
            // 检查是否启用了自动隐藏
            $stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_hide_claimed'");
            $autoHide = $stmt->fetchColumn() === '1';
            
            // 如果启用了自动隐藏，则更新组别的可见性
            if ($autoHide) {
                $stmt = $pdo->prepare("UPDATE groups SET is_active = 0 WHERE id = ?");
                $stmt->execute([$_POST['group_id']]);
            }
            
            $pdo->commit();
            echo json_encode(['success' => true]);
        } else {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => '该组别不存在或已被领取']);
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("领取失败: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => '领取失败']);
    }
} else {
    echo json_encode(['success' => false, 'message' => '无效的请求']);
} 