<?php
// 1. เชื่อมต่อฐานข้อมูล
require_once 'db_connect.php';

// --- 2. กำหนดค่าพื้นฐาน ---
$uploadDir = 'uploads/'; // โฟลเดอร์สำหรับเก็บรูปภาพ
$maxUploads = 2;         // จำนวนรูปสูงสุดที่อนุญาตให้อัพโหลด

// สร้างโฟลเดอร์ uploads หากยังไม่มี
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$message = ''; // สำหรับเก็บข้อความแจ้งเตือน

// --- 3. ส่วนจัดการการลบรูปภาพ ---
if (isset($_GET['delete'])) {
    $imageId = filter_var($_GET['delete'], FILTER_VALIDATE_INT);

    if ($imageId) {
        try {
            $conn->begin_transaction();
            // 3.1 ค้นหาชื่อไฟล์จากฐานข้อมูลก่อนลบ
            $stmt = $conn->prepare("SELECT file_name FROM images WHERE id = ?");
            $stmt->bind_param("i", $imageId);
            $stmt->execute();
            $image = $stmt->get_result()->fetch_assoc();

            if ($image) {
                // 3.2 ลบไฟล์รูปภาพออกจากเซิร์ฟเวอร์
                $filePath = $uploadDir . $image['file_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // 3.3 ลบข้อมูลออกจากฐานข้อมูล
                $deleteStmt = $conn->prepare("DELETE FROM images WHERE id = ?");
                $deleteStmt->bind_param("i", $imageId);
                $deleteStmt->execute();

                $conn->commit();
                $message = '<p style="color: green;">ลบรูปภาพสำเร็จแล้ว</p>';
            }
        } catch (Exception $e) {
            $conn->rollback();
            $message = '<p style="color: red;">เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage() . '</p>';
        }
    }
    // Redirect เพื่อเคลียร์ query string และป้องกันการลบซ้ำเมื่อรีเฟรช
    header("Location: imageupload.php");
    exit();
}


// --- 4. ส่วนจัดการการอัพโหลดรูปภาพ ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image_upload'])) {
    try {
        // 4.1 นับจำนวนรูปภาพที่มีอยู่แล้วในฐานข้อมูล
        $result = $conn->query("SELECT COUNT(*) as count FROM images");
        $currentImageCount = $result->fetch_assoc()['count'];
        
        $files = $_FILES['image_upload'];
        $filesToUploadCount = count(array_filter($files['name']));

        if ($currentImageCount + $filesToUploadCount > $maxUploads) {
            $message = '<p style="color: red;">อัพโหลดเกินจำนวนที่กำหนด! คุณสามารถอัพโหลดได้สูงสุด ' . $maxUploads . ' รูปเท่านั้น (ปัจจุบันมี ' . $currentImageCount . ' รูป)</p>';
        } else {
            // 4.2 วนลูปจัดการแต่ละไฟล์ที่อัพโหลดมา
            foreach ($files['name'] as $key => $name) {
                if ($files['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$key];
                    
                    // 4.3 ตรวจสอบว่าเป็นไฟล์รูปภาพจริงหรือไม่
                    $fileInfo = getimagesize($tmpName);
                    $allowedTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
                    if ($fileInfo && in_array($fileInfo[2], $allowedTypes)) {

                        // 4.4 สร้างชื่อไฟล์ใหม่เพื่อป้องกันการซ้ำกัน
                        $extension = pathinfo($name, PATHINFO_EXTENSION);
                        $newFileName = uniqid('img_', true) . '.' . strtolower($extension);
                        $destination = $uploadDir . $newFileName;

                        // 4.5 ย้ายไฟล์ไปยังโฟลเดอร์ uploads
                        if (move_uploaded_file($tmpName, $destination)) {
                            // 4.6 บันทึกชื่อไฟล์ลงฐานข้อมูล
                            $insertStmt = $conn->prepare("INSERT INTO images (file_name) VALUES (?)");
                            $insertStmt->bind_param("s", $newFileName);
                            $insertStmt->execute();
                            $message .= '<p style="color: green;">อัพโหลดไฟล์ ' . htmlspecialchars($name) . ' สำเร็จ</p>';
                        } else {
                            $message .= '<p style="color: red;">ไม่สามารถย้ายไฟล์ ' . htmlspecialchars($name) . ' ได้</p>';
                        }
                    } else {
                        $message .= '<p style="color: red;">ไฟล์ ' . htmlspecialchars($name) . ' ไม่ใช่ไฟล์รูปภาพที่รองรับ (JPG, PNG, GIF)</p>';
                    }
                }
            }
        }
    } catch (Exception $e) {
        $message = '<p style="color: red;">เกิดข้อผิดพลาดกับฐานข้อมูล: ' . $e->getMessage() . '</p>';
    }
}

// --- 5. ส่วนดึงข้อมูลรูปภาพมาแสดงผล ---
try {
    $result = $conn->query("SELECT id, file_name FROM images ORDER BY uploaded_on DESC");
    $uploadedImages = $result->fetch_all(MYSQLI_ASSOC);
    $canUpload = count($uploadedImages) < $maxUploads;
} catch (Exception $e) {
    $message = '<p style="color: red;">ไม่สามารถดึงข้อมูลรูปภาพได้: ' . $e->getMessage() . '</p>';
    $uploadedImages = [];
    $canUpload = true; // หรือ false ขึ้นอยู่กับว่าต้องการให้ฟอร์มใช้งานได้หรือไม่เมื่อเกิด error
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ทดลองระบบอัพโหลดรูปภาพ</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; margin: 20px; }
        .container { max-width: 800px; margin: auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px; }
        .image-gallery { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
        .image-item { border: 1px solid #ddd; padding: 10px; border-radius: 5px; text-align: center; }
        .image-item img { max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px; }
        .delete-btn { color: #fff; background-color: #dc3545; border: none; padding: 8px 12px; border-radius: 4px; text-decoration: none; cursor: pointer; }
        .delete-btn:hover { background-color: #c82333; }
        .upload-form { margin-top: 20px; padding: 15px; background-color: #f8f9fa; border-radius: 5px; }
        .disabled-form { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body>

<div class="container">
    <h1>ระบบอัพโหลดและจัดการรูปภาพ</h1>
    <p>คุณสามารถอัพโหลดรูปภาพได้สูงสุด <?= $maxUploads ?> รูป</p>

    <!-- แสดงข้อความแจ้งเตือน -->
    <?php if (!empty($message)) echo "<div>$message</div>"; ?>

    <hr>

    <h2>รูปภาพที่อัพโหลดแล้ว (<?= count($uploadedImages) ?>/<?= $maxUploads ?>)</h2>
    <div class="image-gallery">
        <?php if (empty($uploadedImages)): ?>
            <p>ยังไม่มีรูปภาพที่อัพโหลด</p>
        <?php else: ?>
            <?php foreach ($uploadedImages as $img): ?>
                <div class="image-item">
                    <img src="<?= htmlspecialchars($uploadDir . $img['file_name']) ?>" alt="Uploaded Image">
                    <a href="?delete=<?= $img['id'] ?>" class="delete-btn" onclick="return confirm('คุณแน่ใจหรือไม่ว่าต้องการลบรูปภาพนี้?');">
                        ลบรูปภาพ
                    </a>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <hr>

    <!-- ส่วนของฟอร์มอัพโหลด -->
    <div class="upload-form <?= !$canUpload ? 'disabled-form' : '' ?>">
        <h2>อัพโหลดรูปภาพใหม่</h2>
        <?php if ($canUpload): ?>
            <form action="imageupload.php" method="post" enctype="multipart/form-data">
                <p>เลือกไฟล์รูปภาพ (JPG, PNG, GIF):</p>
                <!-- ใช้ multiple เพื่อให้เลือกได้หลายไฟล์พร้อมกัน -->
                <input type="file" name="image_upload[]" accept="image/jpeg,image/png,image/gif" multiple>
                <br><br>
                <button type="submit">อัพโหลด</button>
            </form>
        <?php else: ?>
            <p style="color: #777;">คุณอัพโหลดรูปภาพครบ 2 รูปแล้ว หากต้องการอัพโหลดใหม่ กรุณาลบรูปเก่าออกก่อน</p>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
