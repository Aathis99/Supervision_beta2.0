<?php
// forms/save_quickwin_data.php
session_start();

// 1. เชื่อมต่อฐานข้อมูล
require_once '../config/db_connect.php';

// 2. ตรวจสอบว่าข้อมูลถูกส่งมาแบบ POST หรือไม่
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 3. รับข้อมูลจากฟอร์มและป้องกัน XSS
    // สังเกต: ชื่อตัวแปรจากฟอร์มคือ supervisor_pid, teacher_pid, option_id, observation_notes
    $p_id = $_POST['supervisor_pid'] ?? '';
    $t_id = $_POST['teacher_pid'] ?? '';
    $options = $_POST['option_id'] ?? '';
    $option_other = $_POST['observation_notes'] ?? '';

    // ตรวจสอบว่ามีค่าที่จำเป็นครบถ้วนหรือไม่
    // ⭐️ ปรับปรุงการตรวจสอบ: ใช้ isset และเช็คค่าว่าง ('') เพื่อให้รองรับการกรอก "0" หรือค่าอื่นๆ ที่ empty() มองว่าเป็นค่าว่าง
    if (!isset($_POST['supervisor_pid']) || $_POST['supervisor_pid'] === '' ||
        !isset($_POST['teacher_pid']) || $_POST['teacher_pid'] === '' ||
        !isset($_POST['option_id']) || $_POST['option_id'] === '' ||
        !isset($_POST['observation_notes']) || $_POST['observation_notes'] === '') {
        die("เกิดข้อผิดพลาด: ข้อมูลที่จำเป็นไม่ครบถ้วน (p_id, t_id, option, notes)");
    }

    try {
        // ⭐️ ตั้งค่าโซนเวลาและสร้างวันที่ปัจจุบัน
        date_default_timezone_set('Asia/Bangkok');
        $supervision_date = date('Y-m-d H:i:s');

        // 4. เตรียมคำสั่ง SQL เพื่อบันทึกข้อมูล (ใช้ Prepared Statement)
        // ⭐️ เพิ่มคอลัมน์ supervision_date เข้าไปในคำสั่ง SQL
        // ไม่ต้องใส่ id เพราะเป็น auto-increment
        $sql = "INSERT INTO quick_win (p_id, t_id, options, option_other, supervision_date) VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);

        // ตรวจสอบว่า prepare statement สำเร็จหรือไม่
        if ($stmt === false) {
            throw new Exception("Prepare statement failed: " . $conn->error);
        }

        // 5. Bind Parameters
        // ⭐️ เพิ่มประเภทข้อมูล 's' สำหรับ supervision_date
        // ssis: string, string, integer, string
        $stmt->bind_param("ssiss", $p_id, $t_id, $options, $option_other, $supervision_date);

        // 6. Execute คำสั่ง
        if ($stmt->execute()) {
            // ล้างข้อมูล session ที่ไม่ต้องการแล้ว
            unset($_SESSION['inspection_data']);

            // บันทึกสำเร็จ: ส่งผู้ใช้ไปยังหน้า history
            header('Location: ../history.php'); // ⭐️ เปลี่ยนเส้นทางไปที่นี่
            exit(); // จบการทำงานของสคริปต์ทันทีหลังจากการ redirect
        } else {
            // บันทึกไม่สำเร็จ
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // ปิด statement
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