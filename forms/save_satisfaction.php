<?php
// forms/save_satisfaction.php
session_start();

// 1. ⭐️ แก้ไข Path การเชื่อมต่อฐานข้อมูล (ถอยกลับ 1 ชั้น)
require_once '../config/db_connect.php';

function redirect_with_flash_message($message, $type = 'danger', $location = '../history.php')
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $type;
    header("Location: " . $location);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_flash_message("Invalid request method.");
}

// รับค่าจาก Form
$s_pid = $_POST['s_pid'] ?? null;
$t_pid = $_POST['t_pid'] ?? null;
$sub_code = $_POST['sub_code'] ?? null;
$time = $_POST['time'] ?? null;
$ratings = $_POST['ratings'] ?? [];
$overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

// ตรวจสอบข้อมูล
if (!$s_pid || !$t_pid || !$sub_code || !$time || empty($ratings)) {
    redirect_with_flash_message("ข้อมูลที่จำเป็นสำหรับการบันทึกไม่ครบถ้วน กรุณาลองใหม่อีกครั้ง");
}

$conn->begin_transaction();

try {
    // 2. ⭐️ เพิ่ม Logic: ถ้าเคยประเมินแล้ว ให้ลบของเก่าออกก่อน (เพื่อให้แก้ไขคะแนนได้) 
    // หรือถ้าต้องการห้ามประเมินซ้ำ ให้ใช้โค้ดเดิมของคุณ (Check แล้ว Throw Exception)
    // แต่ในที่นี้ผมแนะนำให้ใช้ DELETE แล้ว INSERT ใหม่ เพื่อรองรับการแก้ไขครับ

    // ลบคะแนนเก่า (ถ้ามี)
    $stmt_del = $conn->prepare("DELETE FROM satisfaction_answers WHERE supervisor_p_id=? AND teacher_t_pid=? AND subject_code=? AND inspection_time=?");
    $stmt_del->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
    $stmt_del->execute();
    $stmt_del->close();

    // เตรียม INSERT คะแนนใหม่
    $sql_answer = "INSERT INTO satisfaction_answers 
                   (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, question_id, rating) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_answer = $conn->prepare($sql_answer);
    if (!$stmt_answer) throw new Exception("Prepare failed: " . $conn->error);

    foreach ($ratings as $question_id => $rating) {
        $q_id = (int)$question_id;
        $rate_score = (int)$rating;
        $stmt_answer->bind_param("sssiii", $s_pid, $t_pid, $sub_code, $time, $q_id, $rate_score);
        if (!$stmt_answer->execute()) {
            throw new Exception("Execute failed: " . $stmt_answer->error);
        }
    }
    $stmt_answer->close();

    // อัปเดตข้อเสนอแนะในตารางหลัก
    $sql_session_update = "UPDATE supervision_sessions 
                           SET satisfaction_suggestion = ?, 
                               satisfaction_date = NOW(),
                               satisfaction_submitted = 1 
                           WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?";
    $stmt_session = $conn->prepare($sql_session_update);
    $stmt_session->bind_param("ssssi", $overall_suggestion, $s_pid, $t_pid, $sub_code, $time);
    $stmt_session->execute();
    $stmt_session->close();

    $conn->commit();

    // Redirect กลับไปหน้า Form เดิม
    $redirect_url = "satisfaction_form.php?" . http_build_query([
        's_pid' => $s_pid,
        't_pid' => $t_pid,
        'sub_code' => $sub_code,
        'time' => $time
    ]);

    // ส่ง Session Success Message (ถ้ามีระบบแสดงผล)
    $_SESSION['flash_message'] = "บันทึกการประเมินเรียบร้อยแล้ว";
    $_SESSION['flash_message_type'] = "success";

    header("Location: " . $redirect_url);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("Save Error: " . $e->getMessage());
    redirect_with_flash_message("เกิดข้อผิดพลาด: " . $e->getMessage());
}

$conn->close();
