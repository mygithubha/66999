<?php
require_once 'config.php';
session_start();

// 获取访问者信息
$ip = $_SERVER['REMOTE_ADDR'];
$ua = $_SERVER['HTTP_USER_AGENT'];

// 获取自动隐藏设置
$stmt = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'auto_hide_claimed'");
$autoHide = $stmt->fetchColumn() === '1';

// 根据设置修改查询条件
$sql = "SELECT * FROM groups WHERE 1=1";
if ($autoHide) {
    $sql .= " AND (is_claimed = 0 OR is_active = 1)";
} else {
    $sql .= " AND is_active = 1";
}
$stmt = $pdo->query($sql);
$groups = $stmt->fetchAll();

// 获取领取记录 - 修改SQL查询，只获取当前访问者的记录
$stmt = $pdo->prepare("SELECT c.*, g.group_name FROM claims c 
                       JOIN groups g ON c.group_id = g.id 
                       WHERE c.ip = ? AND c.user_agent = ?
                       ORDER BY c.claim_time DESC LIMIT 10");
$stmt->execute([$ip, $ua]);
$claims = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>图片领取系统</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f8ff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .container {
            max-width: 800px;
            margin: 1px 5px;
            background-color: white;
            padding: 0;
            border-radius: 8px;
            box-shadow: none;
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            min-height: auto;
            width: calc(100% - 10px);
        }
        .content-wrapper {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            padding: 0 15px 15px;
        }
        .title {
            color: #1e90ff;
            font-size: 1.2em;
            margin: 5px 0 20px 0;
            padding: 0;
            text-align: center;
        }
        select {
            padding: 10px;
            font-size: 16px;
            width: 200px;
            margin-right: 10px;
            border: 1px solid #1e90ff;
            border-radius: 4px;
            display: block;
            margin: 0 auto;
        }
        .claim-container {
            margin-top: 12px;
            text-align: center;
            margin-bottom: 15px;
        }
        button {
            padding: 10px 20px;
            background-color: #1e90ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .records {
            max-height: 150px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 10px;
            border-radius: 4px;
            background-color: #f8f9fa;
            margin-bottom: 0;
        }
        .records-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .save-tip {
            font-size: 12px;
            color: #666;
            margin: 0;
        }
        .record-item {
            display: inline-block;
            margin-right: 15px;
            text-align: center;
            margin-bottom: 5px;
        }
        .image-preview {
            width: 50px;
            height: 50px;
            object-fit: cover;
            cursor: pointer;
            border-radius: 4px;
        }
        .record-info {
            font-size: 12px;
            color: #666;
            margin: 2px 0;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 80%;
            margin-bottom: 15px;
        }
        .download-btn {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-top: 10px;
        }
        .download-btn:hover {
            background-color: #45a049;
        }
        /* 资源网格布局 */
        .resource-grid {
            width: calc(100% - 10px);
            margin: 1px 5px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            flex: 1;
        }
        .resource-grid .item-link {
            display: block;
            width: 100%;
            height: 100%;
        }
        .resource-grid .item-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 8px;
            animation: gentle-swing 3s ease-in-out infinite;
        }
        .resource-grid .item-image:hover {
            animation-play-state: paused;
        }
        @keyframes gentle-swing {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="title">图片领取系统</h1>
        
        <div class="content-wrapper">
            <div>
                <select id="groupSelect">
                    <option value="">请选择组别</option>
                    <?php foreach($groups as $group): ?>
                        <option value="<?php echo $group['id']; ?>" 
                                <?php echo $group['is_claimed'] ? 'disabled' : ''; ?>>
                            <?php echo $group['group_name']; ?>
                            <?php echo $group['is_claimed'] ? ' (已被领取)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="claim-container">
                <button id="claimBtn" onclick="claimImage()">领取图片</button>
            </div>

            <div class="records">
                <div class="records-header">
                    <h3 style="margin: 0;">我的领取记录</h3>
                    <p class="save-tip">长按图片可保存</p>
                </div>
                <div style="white-space: nowrap;">
                    <?php if (empty($claims)): ?>
                        <p style="text-align: center; color: #666;">暂无领取记录</p>
                    <?php else: ?>
                        <?php foreach($claims as $claim): ?>
                            <div class="record-item">
                                <img src="<?php echo htmlspecialchars($claim['image_path']); ?>" class="image-preview" onclick="showImage(this.src)">
                                <p class="record-info"><?php echo htmlspecialchars($claim['group_name']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

 
    <!-- 资源展示区域 -->
	
	<!--
	
    <div class="resource-grid">
        <div class="grid-item">
            <a class="item-link" href="https://www.baidu.com" target="_blank" rel="noopener">
                <img class="item-image" src="resources/banner1.jpg" alt="资源1">
            </a>
        </div>
        <div class="grid-item">
            <a class="item-link" href="https://www.baidu.com" target="_blank" rel="noopener">
                <img class="item-image" src="resources/banner2.jpg" alt="资源2">
            </a>
        </div>
        <div class="grid-item">
            <a class="item-link" href="https://www.baidu.com" target="_blank" rel="noopener">
                <img class="item-image" src="resources/banner3.jpg" alt="资源3">
            </a>
        </div>
        <div class="grid-item">
            <a class="item-link" href="https://www.baidu.com" target="_blank" rel="noopener">
                <img class="item-image" src="resources/banner4.jpg" alt="资源4">
            </a>
        </div>
        <div class="grid-item">
            <a class="item-link" href="https://www.baidu.com" target="_blank" rel="noopener">
                <img class="item-image" src="resources/banner5.jpg" alt="资源5">
            </a>
        </div>
        <div class="grid-item">
            <a class="item-link" href="https://www.baidu.com" target="_blank" rel="noopener">
                <img class="item-image" src="resources/banner6.jpg" alt="资源6">
            </a>
        </div>
    </div>
	
	  -->

    <div id="imageModal" class="modal" onclick="hideModal(event)">
        <img class="modal-content" id="modalImage">
        <button class="download-btn" onclick="downloadImage(event)">下载图片</button>
    </div>

    <script>
        function claimImage() {
            const groupId = document.getElementById('groupSelect').value;
            if (!groupId) {
                alert('请选择组别');
                return;
            }

            fetch('claim.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `group_id=${groupId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('领取成功！');
                    location.reload();
                } else {
                    alert(data.message || '领取失败');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('发生错误，请稍后重试');
            });
        }

        function showImage(src) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            modal.style.display = "flex";
            modalImg.src = src;
        }

        function hideModal(event) {
            if (event.target.id === 'imageModal') {
                document.getElementById('imageModal').style.display = "none";
            }
        }

        function downloadImage(event) {
            event.stopPropagation();
            const image = document.getElementById('modalImage');
            const link = document.createElement('a');
            link.href = image.src;
            link.download = 'image.jpg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html> 