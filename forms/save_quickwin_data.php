<?php
// forms/save_quickwin_data.php
session_start();

// 1. เชื่อมต่อฐานข้อมูล
require_once '../config/db_connect.php';

// 2. ตรวจสอบว่าข้อมูลถูกส่งมาแบบ POST หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. รับข้อมูลจากฟอร์ม
    $p_id         = trim($_POST['supervisor_pid']     ?? '');
    $t_id         = trim($_POST['teacher_pid']        ?? '');
    $options      = $_POST['option_id']               ?? '';
    $option_other = trim($_POST['observation_notes']  ?? ''); // ✅ กรอกหรือไม่ก็ได้

    // 4. ตรวจสอบว่ามีค่าที่จำเป็นครบถ้วนหรือไม่ (ไม่เช็ค observation_notes แล้ว)
    if ($p_id === '' || $t_id === '' || $options === '') {
        die("เกิดข้อผิดพลาด: ข้อมูลที่จำเป็นไม่ครบถ้วน (p_id, t_id, option)");
    }

    // แปลง option เป็น int (เผื่อใน DB เป็น INT)
    $options_int = (int)$options;

    try {
        // ตั้งค่าโซนเวลาและสร้างวันที่ปัจจุบัน
        date_default_timezone_set('Asia/Bangkok');
        $supervision_date = date('Y-m-d H:i:s');

        // 5. เตรียมคำสั่ง SQL เพื่อบันทึกข้อมูล
        $sql = "INSERT INTO quick_win (p_id, t_id, options, option_other, supervision_date)
                VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        // ssiss : p_id(string), t_id(string), options(int), option_other(string), supervision_date(string)
        $stmt->bind_param("ssiss", $p_id, $t_id, $options_int, $option_other, $supervision_date);

        if ($stmt->execute()) {
            // ล้างข้อมูล session ที่ไม่ต้องการแล้ว
            unset($_SESSION['inspection_data']);

            // บันทึกสำเร็จ: ส่งผู้ใช้ไปยังหน้า history
            header('Location: ../history.php');
            exit();
        } else {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $stmt->close();

    } catch (Exception $e) {
        // หากเกิดข้อผิดพลาดในการบันทึก
        $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage();
    }

    // ปิดการเชื่อมต่อฐานข้อมูล
    $conn->close();

} else {
    // ถ้าไม่ได้เข้ามาหน้านี้ผ่าน POST ให้ redirect กลับไปหน้าหลัก
    header('Location: ../index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>สถานะการบันทึกข้อมูล</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6 text-center">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <h4 class="alert-heading"><i class="fas fa-check-circle"></i> สำเร็จ!</h4>
                    <p><?php echo $success_message; ?></p>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <h4 class="alert-heading"><i class="fas fa-times-circle"></i> เกิดข้อผิดพลาด!</h4>
                    <p><?php echo $error_message ?? 'ไม่สามารถบันทึกข้อมูลได้'; ?></p>
                </div>
            <?php endif; ?>
            <a href="../index.php" class="btn btn-primary mt-3"><i class="fas fa-home"></i> กลับไปหน้าหลัก</a>
        </div>
    </div>
</div>
</body>
</html>
