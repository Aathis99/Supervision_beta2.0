<?php
// ไฟล์: supervision_start.php
session_start();

// ถ้ากลับมาแก้ไข ใช้ค่าจาก Session
if (isset($_GET['edit']) && $_GET['edit'] === 'true' && isset($_SESSION['inspection_data'])) {
    $inspection_data = $_SESSION['inspection_data'];
} else {
    // ล้างเฉพาะข้อมูลการนิเทศเก่า ไม่ยุ่งกับ session อื่น (เช่น login)
    unset($_SESSION['inspection_data']);
    $inspection_data = null;
}

// ถ้าต้องใช้ฐานข้อมูลที่หน้านี้ ให้ include ไว้ (ไม่มีก็ไม่เป็นไร)
require_once 'config/db_connect.php';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบบันทึกข้อมูลนิเทศ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>

<div class="container my-4">
    <div class="main-card card">
        <div class="form-header card-header text-center">
            <i class="fas fa-file-alt"></i>
            <span class="fw-bold">แบบบันทึกข้อมูลผู้นิเทศ และ ผู้รับนิเทศ</span>
        </div>

        <!-- 🔴 ฟอร์มหลักตัวเดียว ครอบทั้งผู้นิเทศ + ผู้รับนิเทศ -->
        <form method="POST"
              action="summary.php"
              enctype="multipart/form-data"
              onsubmit="return validateSelection(event)">

            <?php
            // 1) ส่วนข้อมูลผู้นิเทศ (ชิ้นส่วน ไม่มี <form> ซ้อน)
            require 'supervisor.php';

            // 2) ส่วนข้อมูลผู้รับนิเทศ (ชิ้นส่วน ไม่มี <form> ซ้อน)
            require 'teacher.php';
            ?>

            <hr>

            <!-- ปุ่มเลือกแบบฟอร์ม และปุ่มย้อนกลับ/ดำเนินการต่อ -->
            <div class="card-body">
                <div class="row g-3 mt-4 justify-content-center">
                    <div class="mt-4 mb-4">
                        <?php require_once 'forms/form_selector.php'; ?>
                    </div>

                    <div class="col-auto">
                        <a href="index.php" class="btn btn-danger">
                            <i class="fas fa-arrow-left"></i> ย้อนกลับ
                        </a>
                    </div>

                    <div class="col-auto">
                        <button type="submit"
                                class="btn btn-success btn-l">
                            ดำเนินการต่อ
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <!-- 🔴 ปิดฟอร์มที่นี่เท่านั้น -->
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>

<script>
    // ตรวจสอบว่าครูถูกเลือกจาก list แล้วก่อนส่งฟอร์ม
    function validateSelection(e) {
        const teacherName = document.getElementById('teacher_name_input')?.value.trim() || '';
        const teacherPid  = document.getElementById('t_pid')?.value.trim() || '';

        if (teacherName === '' || teacherPid === '') {
            alert('โปรดเลือกผู้รับนิเทศจากรายชื่อที่ระบบแนะนำ');
            e.preventDefault();
            return false;
        }
        return true;
    }

    // เรียก init ต่าง ๆ หลัง DOM โหลด
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof populateSupervisorDropdown === 'function') {
            populateSupervisorDropdown();
        }
        if (typeof initTeacherSearch === 'function') {
            initTeacherSearch();
        }
    });
</script>

</body>
</html>
