<?php
require_once 'config.php';
session_start();

// 简单的登录验证
if (!isset($_SESSION['admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && 
        isset($_POST['username']) && 
        isset($_POST['password'])) {
        if ($_POST['username'] === 'admin' && $_POST['password'] === 'admin123') {
            $_SESSION['admin'] = true;
        } else {
            $error = '用户名或密码错误';
        }
    }
    
    if (!isset($_SESSION['admin'])) {
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>管理员登录</title>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f0f8ff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .login-container {
                    background: white;
                    padding: 20px;
                    border-radius: 8px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                }
                input {
                    display: block;
                    margin: 10px 0;
                    padding: 8px;
                    width: 200px;
                }
                button {
                    background: #1e90ff;
                    color: white;
                    border: none;
                    padding: 10px;
                    width: 100%;
                    border-radius: 4px;
                    cursor: pointer;
                }
            </style>
        </head>
        <body>
            <div class="login-container">
                <h2>管理员登录</h2>
                <?php if (isset($error)) echo "<p style='color: red'>$error</p>"; ?>
                <form method="post">
                    <input type="text" name="username" placeholder="用户名" required>
                    <input type="password" name="password" placeholder="密码" required>
                    <button type="submit">登录</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// 处理图片上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_group':
            // 检查是否有文件上传和组别名称
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0 && !empty($_POST['group_name'])) {
                $group_name = $_POST['group_name'];
                $target_dir = "uploads/";
                if (!file_exists($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }
                
                // 生成唯一的文件名
                $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $stmt = $pdo->prepare("INSERT INTO groups (group_name, image_path) VALUES (?, ?)");
                    $stmt->execute([$group_name, $target_file]);
                    
                    // 添加成功后重定向
                    header('Location: ' . $_SERVER['PHP_SELF']);
                    exit;
                }
            }
            break;
                
        case 'delete_group':
            $group_id = $_POST['group_id'];
            try {
                $pdo->beginTransaction();
                
                // 先获取图片路径
                $stmt = $pdo->prepare("SELECT image_path FROM groups WHERE id = ?");
                $stmt->execute([$group_id]);
                $group = $stmt->fetch();
                
                // 删除数据库记录
                $stmt = $pdo->prepare("DELETE FROM claims WHERE group_id = ?");
                $stmt->execute([$group_id]);
                
                $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
                $stmt->execute([$group_id]);
                
                // 删除图片文件
                if ($group && !empty($group['image_path']) && file_exists($group['image_path'])) {
                    unlink($group['image_path']);
                }
                
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                error_log("删除失败: " . $e->getMessage());
                echo "<script>alert('删除失败：" . $e->getMessage() . "');</script>";
            }
            break;
                
        case 'update_group':
            $group_id = $_POST['group_id'];
            $group_name = $_POST['group_name'];
            if (isset($_FILES['image']) && $_FILES['image']['size'] > 0) {
                // 先获取旧图片路径
                $stmt = $pdo->prepare("SELECT image_path FROM groups WHERE id = ?");
                $stmt->execute([$group_id]);
                $old_image = $stmt->fetchColumn();
                
                // 删除旧图片
                if ($old_image && file_exists($old_image)) {
                    unlink($old_image);
                }
                
                $target_dir = "uploads/";
                // 生成唯一的文件名
                $file_extension = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
                $new_filename = uniqid() . '.' . $file_extension;
                $target_file = $target_dir . $new_filename;
                
                if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
                    $stmt = $pdo->prepare("UPDATE groups SET group_name = ?, image_path = ? WHERE id = ?");
                    $stmt->execute([$group_name, $target_file, $group_id]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE groups SET group_name = ? WHERE id = ?");
                $stmt->execute([$group_name, $group_id]);
            }
            break;
                
        case 'batch_delete':
            if (isset($_POST['group_ids'])) {
                $groupIds = json_decode($_POST['group_ids']);
                if (!empty($groupIds)) {
                    try {
                        $pdo->beginTransaction();
                        
                        // 禁用外键检查
                        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                        
                        // 获取要删除的图片路径
                        $stmt = $pdo->prepare("SELECT image_path FROM groups WHERE id IN (" . str_repeat('?,', count($groupIds) - 1) . "?)");
                        $stmt->execute($groupIds);
                        $groups = $stmt->fetchAll();
                        
                        // 删除关联的claims记录
                        $stmt = $pdo->prepare("DELETE FROM claims WHERE group_id IN (" . str_repeat('?,', count($groupIds) - 1) . "?)");
                        $stmt->execute($groupIds);
                        
                        // 删除组别
                        $stmt = $pdo->prepare("DELETE FROM groups WHERE id IN (" . str_repeat('?,', count($groupIds) - 1) . "?)");
                        $stmt->execute($groupIds);
                        
                        // 删除图片文件
                        foreach ($groups as $group) {
                            if (file_exists($group['image_path'])) {
                                unlink($group['image_path']);
                            }
                        }
                        
                        // 重新启用外键检查
                        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                        
                        $pdo->commit();
                        
                        // 重定向以防止重复提交
                        header('Location: ' . $_SERVER['PHP_SELF']);
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                        error_log("批量删除失败: " . $e->getMessage());
                        echo "<script>alert('批量删除失败：" . $e->getMessage() . "');</script>";
                    }
                }
            }
            break;
    }
}

// 在获取组别之前，添加获取设置的代码
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_hide_claimed'");
$autoHideSetting = $stmt->fetchColumn();
$autoHide = $autoHideSetting === '1';

// 获取所有组别
$stmt = $pdo->query("SELECT * FROM groups ORDER BY id DESC");
$groups = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>后台管理</title>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f0f8ff;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .group-list {
            margin-top: 20px;
        }
        .group-item {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
        }
        .group-item img {
            max-width: 100px;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
        }
        .status-claimed {
            background-color: #ff4444;
        }
        .status-unclaimed {
            background-color: #4CAF50;
        }
        form {
            margin-bottom: 20px;
        }
        input, button {
            margin: 5px;
            padding: 8px;
        }
        button {
            background: #1e90ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button.delete {
            background: #ff4444;
        }
        .logout {
            float: right;
        }
        .checkbox-container {
            margin: 10px 0;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .batch-actions {
            margin: 10px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
        }
        .select-all, .select-none, .select-inverse {
            background: #1e90ff;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .delete-selected {
            background: #ff4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .hide-claimed {
            background: #ffa500;  /* 橙色按钮 */
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .group-item.hidden {
            opacity: 0.5;  /* 半透明效果 */
            background: #f0f0f0;
        }
        .auto-hide-switch {
            display: inline-block;
            margin-left: 15px;
            padding: 5px 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .auto-hide-switch input[type="checkbox"] {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>后台管理
            <a href="logout.php" class="logout">
                <button>退出登录</button>
            </a>
        </h1>

        <!-- 添加新组别 -->
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_group">
            <input type="text" name="group_name" placeholder="组别名称" required>
            <input type="file" name="image" accept="image/*" required>
            <button type="submit">添加组别</button>
        </form>

        <!-- 批量操作按钮 -->
        <div class="batch-actions">
            <button type="button" class="select-all" onclick="selectAll()">全选</button>
            <button type="button" class="select-none" onclick="selectNone()">取消全选</button>
            <button type="button" class="select-inverse" onclick="selectInverse()">反选</button>
            <button type="button" class="delete-selected" onclick="deleteSelected()">删除选中</button>
            <button type="button" class="hide-claimed" onclick="toggleClaimed()">隐藏已领取</button>
            <div class="auto-hide-switch">
                <input type="checkbox" id="autoHide" <?php echo $autoHide ? 'checked' : ''; ?> onchange="toggleAutoHide(this)">
                <label for="autoHide">自动隐藏已领取组别</label>
            </div>
        </div>

        <!-- 组别列表 -->
        <div class="group-list">
            <?php foreach($groups as $group): ?>
                <div class="group-item">
                    <div class="checkbox-container">
                        <input type="checkbox" class="group-checkbox" value="<?php echo $group['id']; ?>">
                    </div>
                    <img src="<?php echo htmlspecialchars($group['image_path']); ?>" alt="组别图片" style="max-width: 100px;">
                    <form method="post" enctype="multipart/form-data" style="display: inline-block;">
                        <input type="hidden" name="action" value="update_group">
                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                        <input type="text" name="group_name" value="<?php echo $group['group_name']; ?>" required>
                        <input type="file" name="image" accept="image/*">
                        <button type="submit">更新</button>
                    </form>
                    <form method="post" style="display: inline-block;">
                        <input type="hidden" name="action" value="delete_group">
                        <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                        <button type="submit" class="delete" onclick="return confirm('确定要删除吗？')">删除</button>
                    </form>
                    <p>状态: <span class="status <?php echo $group['is_claimed'] ? 'status-claimed' : 'status-unclaimed'; ?>">
                        <?php echo $group['is_claimed'] ? '已被领取' : '可以领取'; ?>
                    </span></p>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 在 body 结束标签前添加 JavaScript -->
        <script>
            function selectAll() {
                document.querySelectorAll('.group-checkbox').forEach(checkbox => checkbox.checked = true);
            }

            function selectNone() {
                document.querySelectorAll('.group-checkbox').forEach(checkbox => checkbox.checked = false);
            }

            function selectInverse() {
                document.querySelectorAll('.group-checkbox').forEach(checkbox => checkbox.checked = !checkbox.checked);
            }

            function deleteSelected() {
                const selectedIds = Array.from(document.querySelectorAll('.group-checkbox:checked'))
                    .map(checkbox => checkbox.value);

                if (selectedIds.length === 0) {
                    alert('请选择要删除的组别');
                    return;
                }

                if (!confirm(`确定要删除选中的 ${selectedIds.length} 个组别吗？`)) {
                    return;
                }

                // 创建一个表单来提交删除请求
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';

                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'batch_delete';

                const idsInput = document.createElement('input');
                idsInput.type = 'hidden';
                idsInput.name = 'group_ids';
                idsInput.value = JSON.stringify(selectedIds);

                form.appendChild(actionInput);
                form.appendChild(idsInput);
                document.body.appendChild(form);
                form.submit();
            }

            function toggleClaimed() {
                const claimedGroups = document.querySelectorAll('.group-item');
                const button = document.querySelector('.hide-claimed');
                let isHiding = button.textContent === '隐藏已领取';
                
                claimedGroups.forEach(group => {
                    const statusSpan = group.querySelector('.status-claimed');
                    if (statusSpan) {
                        if (isHiding) {
                            // 隐藏已领取的组别
                            group.classList.add('hidden');
                            // 更新数据库状态
                            updateGroupVisibility(group.querySelector('.group-checkbox').value, 0);
                        } else {
                            // 显示已领取的组别
                            group.classList.remove('hidden');
                            // 更新数据库状态
                            updateGroupVisibility(group.querySelector('.group-checkbox').value, 1);
                        }
                    }
                });
                
                // 更新按钮文本
                button.textContent = isHiding ? '显示已领取' : '隐藏已领取';
            }

            function updateGroupVisibility(groupId, isActive) {
                fetch('update_visibility.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `group_id=${groupId}&is_active=${isActive}`
                })
                .catch(error => console.error('Error:', error));
            }

            function toggleAutoHide(checkbox) {
                fetch('update_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `setting_key=auto_hide_claimed&setting_value=${checkbox.checked ? '1' : '0'}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 如果开启了自动隐藏，立即隐藏所有已领取的组别
                        if (checkbox.checked) {
                            const claimedGroups = document.querySelectorAll('.group-item');
                            claimedGroups.forEach(group => {
                                const statusSpan = group.querySelector('.status-claimed');
                                if (statusSpan) {
                                    group.classList.add('hidden');
                                    updateGroupVisibility(group.querySelector('.group-checkbox').value, 0);
                                }
                            });
                        }
                    } else {
                        alert('设置更新失败: ' + (data.error || '未知错误'));
                        checkbox.checked = !checkbox.checked; // 恢复原状态
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('设置更新失败: ' + error.message);
                    checkbox.checked = !checkbox.checked; // 恢复原状态
                });
            }
        </script>
    </div>
</body>
</html> 