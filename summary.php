<?php
// ไฟล์: summary.php
session_start();
require_once 'config/db_connect.php'; // ⭐️ เพิ่มการเชื่อมต่อ DB สำหรับโหมดแก้ไข

// ----------------------------------------------------------------
// A) ตรวจสอบการส่งข้อมูลจากหน้า index.php
// ----------------------------------------------------------------

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // เมื่อข้อมูลถูกส่งมาจาก index.php
    if (isset($_POST['evaluation_type'])) {
        // บันทึกข้อมูลทั้งหมด (ผู้นิเทศ, ผู้รับนิเทศ, ประเภทฟอร์ม) ลงใน Session
        $_SESSION['inspection_data'] = $_POST;

        $selected_form = $_POST['evaluation_type'] ?? null;

        // ⭐️ หากเลือก 'policy_form' ให้บันทึกข้อมูลลง DB แล้วไปหน้า history.php
        if ($selected_form === 'policy_form') {
            // ดึงข้อมูล p_id และ t_pid จาก POST
            $p_id = $_POST['s_p_id'] ?? null; // ⭐️ แก้ไข: เปลี่ยนจาก p_id เป็น s_p_id ให้ตรงกับฟอร์ม
            $t_pid = $_POST['t_pid'] ?? null; // t_pid ถูกต้องอยู่แล้ว

            if ($p_id && $t_pid) {
                // ⭐️ ตั้งค่าโซนเวลาเป็นของประเทศไทย
                date_default_timezone_set('Asia/Bangkok');
                $supervision_date = date('Y-m-d H:i:s');

                // เตรียม SQL Statement
                $stmt = $conn->prepare("INSERT INTO quick_win (p_id, t_id, supervision_date) VALUES (?, ?, ?)");
                // ⭐️ แก้ไข: เปลี่ยนประเภทข้อมูลจาก "sss" เป็น "iis" ให้ตรงกับชนิดข้อมูลในฐานข้อมูล (integer, integer, string)
                $stmt->bind_param("iis", $p_id, $t_pid, $supervision_date);

                if ($stmt->execute()) {
                    // บันทึกสำเร็จ: ตั้งค่า session สำหรับแจ้งเตือนและ redirect
                    $_SESSION['flash_message'] = "บันทึกข้อมูลเสร็จสิ้น";
                    header("Location: history.php");
                    exit();
                } else {
                    // บันทึกล้มเหลว
                    $error_message = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
                }
                $stmt->close();
            } else {
                // ข้อมูล p_id หรือ t_pid ไม่ครบ
                $error_message = "ข้อมูลผู้นิเทศหรือผู้รับนิเทศไม่สมบูรณ์ ไม่สามารถบันทึกได้";
            }
        } elseif ($selected_form !== 'kpi_form') {
            // กรณีไม่ได้เลือกฟอร์มใดๆ หรือค่าไม่ถูกต้อง
            $error_message = 'กรุณาเลือกแบบฟอร์มที่ต้องการดำเนินการ';
        }
        // หากเป็น 'kpi_form' โค้ดจะทำงานต่อไปเพื่อแสดงผล HTML ด้านล่าง
    }
}

// ----------------------------------------------------------------
// B) ตรวจสอบข้อมูลใน Session ก่อนแสดงผล
// ----------------------------------------------------------------
$inspection_data = $_SESSION['inspection_data'] ?? null;
$error_message = '';

// หากไม่มีข้อมูลใน Session (เช่น เข้าถึงหน้านี้โดยตรง) ให้แสดงข้อผิดพลาด
if (!$inspection_data) {
    $error_message = $error_message ?: 'ไม่พบข้อมูลบุคลากร กรุณาเริ่มต้นจากแบบฟอร์มหลัก';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบฟอร์มประเมิน KPI</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="main-card card my-5">
        <div class="form-header card-header text-center bg-success text-white">
            <i class="fas fa-check-circle"></i> <span class="fw-bold">แบบบันทึกข้อมูลการนิเทศ</span>
        </div>
        <div class="card-body">
            <?php if ($error_message !== ''): ?>
                <div class="alert alert-danger text-center">
                    <p><?php echo $error_message; ?></p>
                    <a href="supervision_start.php" class="btn btn-danger">ไปยังแบบฟอร์มเริ่มต้น</a>
                </div>
            <?php else: ?>
                <?php
                // รวมฟอร์ม KPI ทั้งหมดเข้ามาแสดงผลในหน้านี้
                include 'forms/kpi_form.php';
                ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>