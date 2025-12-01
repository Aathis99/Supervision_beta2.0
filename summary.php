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

        // ตรวจสอบการเลือกฟอร์มและ redirect ไปยังหน้าที่ถูกต้อง
        if ($selected_form === 'quickwin_form') {
            // ถ้าเลือก Quick Win Form ให้ไปที่หน้า quickwin_form.php
            header("Location: forms/quickwin_form.php");
            exit();
        } elseif ($selected_form === 'kpi_form') {
            // ถ้าเลือก KPI Form ให้แสดงผลในหน้านี้ต่อไป (ไม่ต้องทำอะไร)
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
                    <a href="index.php" class="btn btn-danger">ไปยังแบบฟอร์มเริ่มต้น</a>
                </div>
            <?php else: ?>
                <!-- ⭐️ ย้ายปุ่มย้อนกลับมาไว้ด้านบน ⭐️ -->
                <div class="d-flex justify-content-end mb-3">
                    <a href="supervision_start.php?edit=true" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับเพื่อแก้ไขข้อมูล
                    </a>
                </div>
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