<?php
$host = 'free.cz128.com';
$dbname = 'a202501171658291';
$username = 'a202501171658291';
$password = 'CBgdyjze09';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("数据库连接失败: " . $e->getMessage());
    die("连接失败");
}
?> 