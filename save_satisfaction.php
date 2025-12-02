<?php
// ไฟล์: save_satisfaction.php
session_start();
require_once 'config/db_connect.php'; // ⭐️ FIX: เปลี่ยนเป็น '../config/db_connect.php' เพราะไฟล์นี้อยู่ใน forms/

function redirect_with_flash_message($message, $type = 'danger', $location = '../history.php') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_message_type'] = $type;
    header("Location: " . $location);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_flash_message("Invalid request method.");
}

// ⭐️ FIX: รับค่า Composite Key และข้อมูลอื่นๆ จาก POST โดยตรง
$s_pid = $_POST['s_pid'] ?? null;
$t_pid = $_POST['t_pid'] ?? null;
$sub_code = $_POST['sub_code'] ?? null;
$time = $_POST['time'] ?? null;
$ratings = $_POST['ratings'] ?? [];
$overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

// ตรวจสอบข้อมูลบังคับ
if (!$s_pid || !$t_pid || !$sub_code || !$time || empty($ratings)) {
    redirect_with_flash_message("ข้อมูลที่จำเป็นสำหรับการบันทึกไม่ครบถ้วน กรุณาลองใหม่อีกครั้ง");
}

// เริ่มต้น Transaction
$conn->begin_transaction();

try {
    // ⭐️ FIX: ตรวจสอบสถานะการประเมินโดยใช้ Composite Key
    $stmt_check = $conn->prepare("SELECT 1 FROM satisfaction_answers WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ? LIMIT 1");
    $stmt_check->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    $stmt_check->close();

    if ($result_check->num_rows > 0) {
        throw new Exception("การนิเทศครั้งนี้ได้รับการประเมินความพึงพอใจไปแล้ว");
    }

    // 1. ⭐️ FIX: บันทึกคะแนนแต่ละข้อลงตาราง satisfaction_answers โดยใช้ Composite Key
    $sql_answer = "INSERT INTO satisfaction_answers 
                   (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, question_id, rating) 
                   VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_answer = $conn->prepare($sql_answer);
    if (!$stmt_answer) throw new Exception("Prepare failed (answers): " . $conn->error);

    foreach ($ratings as $question_id => $rating) {
        $q_id = (int)$question_id;
        $rate_score = (int)$rating;
        // Bind: sssiii (string, string, string, int, int, int)
        $stmt_answer->bind_param("sssiii", $s_pid, $t_pid, $sub_code, $time, $q_id, $rate_score);
        if (!$stmt_answer->execute()) {
            throw new Exception("Execute failed (answer QID: $q_id): " . $stmt_answer->error);
        }
    }
    $stmt_answer->close();

    // 2. ⭐️ FIX: อัปเดตตาราง supervision_sessions เพื่อเก็บข้อเสนอแนะ และวันที่ประเมิน โดยใช้ Composite Key
    $sql_session_update = "UPDATE supervision_sessions 
                           SET satisfaction_suggestion = ?, 
                               satisfaction_date = NOW()
                           WHERE supervisor_p_id = ? AND teacher_t_pid = ? AND subject_code = ? AND inspection_time = ?";
    $stmt_session = $conn->prepare($sql_session_update);
    if (!$stmt_session) throw new Exception("Prepare failed (session update): " . $conn->error);

    // Bind: ssssi (string, string, string, string, int)
    $stmt_session->bind_param("ssssi", $overall_suggestion, $s_pid, $t_pid, $sub_code, $time);
    if (!$stmt_session->execute()) {
        throw new Exception("Execute failed (session update): " . $stmt_session->error);
    }
    $stmt_session->close();

    // ยืนยัน Transaction
    $conn->commit();

    // ⭐️ FIX: สร้าง URL สำหรับ Redirect กลับไปที่หน้าฟอร์มเดิมเพื่อแสดงข้อความสำเร็จ
    $redirect_url = "satisfaction_form.php?" . http_build_query([
        's_pid' => $s_pid,
        't_pid' => $t_pid,
        'sub_code' => $sub_code,
        'time' => $time
    ]);

    // ไม่จำเป็นต้องใช้ flash message แล้ว เพราะหน้า form จะตรวจสอบสถานะและแสดงผลเอง
    header("Location: " . $redirect_url);
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Save Satisfaction Error: " . $e->getMessage()); // บันทึก error ลง log ของ server
    // ส่งกลับไปหน้าหลักพร้อมข้อความ error
    redirect_with_flash_message("ไม่สามารถบันทึกข้อมูลได้: " . $e->getMessage());
}

$conn->close();
?>