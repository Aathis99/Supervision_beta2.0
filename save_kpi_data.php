<?php
// /forms/save_kpi_data.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db_connect.php';

// ฟังก์ชันสำหรับ Redirect พร้อมข้อความ
function redirect_with_flash_message($message, $location = 'history.php')
{
    $_SESSION['flash_message'] = $message;
    header("Location: " . $location);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!isset($_SESSION['inspection_data'])) {
        redirect_with_flash_message("Session หมดอายุหรือไม่พบข้อมูลการนิเทศ กรุณาเริ่มต้นใหม่", "supervision_start.php");
    }

    // 1. รับข้อมูลหลักจากฟอร์มและ Session
    $supervisor_p_id = $_SESSION['inspection_data']['s_p_id'] ?? null;
    $teacher_t_pid = $_SESSION['inspection_data']['t_pid'] ?? null;
    $subject_code = $_POST['subject_code'] ?? null;
    $subject_name = $_POST['subject_name'] ?? null;
    $inspection_time = $_POST['inspection_time'] ?? null;
    $inspection_date = $_POST['supervision_date'] ?? null; // ใช้ชื่อ supervision_date จากฟอร์ม
    $overall_suggestion = $_POST['overall_suggestion'] ?? '';

    // 2. รับข้อมูลการประเมิน (Ratings & Comments)
    $ratings = $_POST['ratings'] ?? [];
    $comments = $_POST['comments'] ?? [];

    // 3. รับข้อเสนอแนะรายตัวชี้วัด
    $indicator_suggestions = $_POST['indicator_suggestions'] ?? [];

    // ตรวจสอบข้อมูลที่จำเป็น
    if (!$supervisor_p_id || !$teacher_t_pid || !$subject_code || !$subject_name || !$inspection_time || !$inspection_date) {
        redirect_with_flash_message("ข้อผิดพลาด: ข้อมูลหลักไม่ครบถ้วน (รหัสผู้นิเทศ, รหัสครู, รหัสวิชา, ชื่อวิชา, ครั้งที่, วันที่)");
    }

    // เริ่ม Transaction
    $conn->begin_transaction();

    try {
        // === ส่วนที่ 1: บันทึกข้อมูลใน supervision_sessions (ถ้ามีอยู่แล้วจะทำการอัปเดต) ===
        $sql_session = "INSERT INTO supervision_sessions 
                        (supervisor_p_id, teacher_t_pid, subject_code, subject_name, inspection_time, inspection_date, overall_suggestion, supervision_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        subject_name = VALUES(subject_name), 
                        inspection_date = VALUES(inspection_date), 
                        overall_suggestion = VALUES(overall_suggestion), 
                        supervision_date = NOW()";

        $stmt_session = $conn->prepare($sql_session);
        if ($stmt_session === false) {
            throw new Exception("Prepare failed (session): " . $conn->error);
        }
        $stmt_session->bind_param("ssssiss", $supervisor_p_id, $teacher_t_pid, $subject_code, $subject_name, $inspection_time, $inspection_date, $overall_suggestion);
        
        if (!$stmt_session->execute()) {
            throw new Exception("Execute failed (session): " . $stmt_session->error);
        }
        $stmt_session->close();

        // === ส่วนที่ 2: บันทึกข้อมูลใน kpi_answers (ถ้ามีอยู่แล้วจะทำการอัปเดต) ===
        // ⭐️ FIX: เปลี่ยนมาใช้ ON DUPLICATE KEY UPDATE เพื่อแก้ปัญหา Primary Key และรองรับการแก้ไขข้อมูล
        $sql_answer = "INSERT INTO kpi_answers 
                        (question_id, rating_score, comment, supervisor_p_id, teacher_t_pid, subject_code, inspection_time) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE 
                        rating_score = VALUES(rating_score),
                        comment = VALUES(comment)";
        $stmt_answer = $conn->prepare($sql_answer);
        if ($stmt_answer === false) {
            throw new Exception("Prepare failed (answers): " . $conn->error);
        }

        foreach ($ratings as $question_id => $rating_score) {
            $comment_text = $comments[$question_id] ?? ''; // ใช้ค่าว่างหากไม่มี comment
            $stmt_answer->bind_param("iissssi", $question_id, $rating_score, $comment_text, $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time);
            if (!$stmt_answer->execute()) {
                throw new Exception("Execute failed (answer for question $question_id): " . $stmt_answer->error);
            }
        }
        $stmt_answer->close();

        // === ส่วนที่ 3: บันทึกข้อมูลใน kpi_indicator_suggestions (ถ้ามีอยู่แล้วจะทำการอัปเดต) ===
        // ⭐️ FIX: เปลี่ยนมาใช้ ON DUPLICATE KEY UPDATE เพื่อให้สอดคล้องกับ kpi_answers
        $sql_suggestion = "INSERT INTO kpi_indicator_suggestions 
                           (indicator_id, suggestion_text, supervisor_p_id, teacher_t_pid, subject_code, inspection_time) 
                           VALUES (?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE
                           suggestion_text = VALUES(suggestion_text)";
        $stmt_suggestion = $conn->prepare($sql_suggestion);
        if ($stmt_suggestion === false) {
            throw new Exception("Prepare failed (suggestions): " . $conn->error);
        }

        foreach ($indicator_suggestions as $indicator_id => $suggestion_text) {
            // ⭐️ FIX: ตรวจสอบ suggestion_text ที่ผ่านการ trim() แล้ว
            $trimmed_suggestion = trim($suggestion_text);
            if (!empty($trimmed_suggestion)) {
                $stmt_suggestion->bind_param("issssi", $indicator_id, $suggestion_text, $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time);
                if (!$stmt_suggestion->execute()) {
                    throw new Exception("Execute failed (suggestion for indicator $indicator_id): " . $stmt_suggestion->error);
                }
            }
        }
        $stmt_suggestion->close();

        // Commit Transaction
        $conn->commit();

        // ล้าง session และ redirect
        unset($_SESSION['inspection_data']);
        redirect_with_flash_message('บันทึกข้อมูลการนิเทศเรียบร้อยแล้ว');

    } catch (Exception $e) {
        // Rollback Transaction
        $conn->rollback();
        error_log($e->getMessage());
        redirect_with_flash_message("เกิดข้อผิดพลาดร้ายแรงในการบันทึกข้อมูล: " . $e->getMessage());
    } finally {
        $conn->close();
    }

} else {
    header("Location: index.php");
    exit();
}
?>