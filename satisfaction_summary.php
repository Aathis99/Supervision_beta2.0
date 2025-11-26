<?php
// ไฟล์: satisfaction_summary.php
session_start();
require_once 'config/db_connect.php';

// 1. ตรวจสอบว่ามี session_id ส่งมาหรือไม่
if (!isset($_GET['session_id']) || empty($_GET['session_id'])) {
    die("ไม่พบรหัสการนิเทศ กรุณาเข้าถึงผ่านหน้าประวัติ");
}
$session_id = intval($_GET['session_id']);

// 2. ดึงข้อมูลการนิเทศเพื่อแสดงผลและเก็บใน Session
$sql_info = "SELECT
                ss.id AS session_id,
                CONCAT(t.PrefixName, t.fname, ' ', t.lname) AS teacher_name,
                CONCAT(sp.PrefixName, sp.fname, ' ', sp.lname) AS supervisor_name
            FROM supervision_sessions ss
            LEFT JOIN teacher t ON ss.teacher_t_pid = t.t_pid
            LEFT JOIN supervisor sp ON ss.supervisor_p_id = sp.p_id
            WHERE ss.id = ?";

$stmt = $conn->prepare($sql_info);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result = $stmt->get_result();
$session_info = $result->fetch_assoc();
$stmt->close();

if (!$session_info) {
    die("ไม่พบข้อมูลการนิเทศสำหรับรหัสนี้");
}

// 3. เก็บข้อมูลที่จำเป็นลง Session เพื่อให้ form เข้าถึงได้
$_SESSION['satisfaction_data'] = [
    'session_id' => $session_info['session_id'],
    'teacher_name' => $session_info['teacher_name'],
    'supervisor_name' => $session_info['supervisor_name']
];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบประเมินความพึงพอใจ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="main-card card my-5">
        <div class="form-header card-header text-center bg-info text-white">
            <i class="fas fa-smile-beam"></i> <span class="fw-bold">แบบประเมินความพึงพอใจต่อระบบ</span>
        </div>
        <div class="card-body">
            <?php if (empty($session_info)): ?>
                <div class="alert alert-danger text-center">
                    <p>ไม่พบข้อมูลการนิเทศ</p>
                    <a href="history.php" class="btn btn-danger">กลับไปหน้าประวัติ</a>
                </div>
            <?php else: ?>
                <?php
                // 4. รวมฟอร์มประเมินความพึงพอใจเข้ามาแสดงผล
                include 'forms/satisfaction_form.php';
                ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>