<?php
// /forms/save_kpi_data.php

// 1. ตรวจสอบ Session ก่อนเริ่มทำงานเสมอ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db_connect.php';

// ฟังก์ชันสำหรับ Redirect
function redirect_with_flash_message($message, $location = 'history.php')
{
    $_SESSION['flash_message'] = $message;
    header("Location: " . $location);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ตรวจสอบว่ามีข้อมูลการนิเทศใน Session หรือไม่
    if (!isset($_SESSION['inspection_data'])) {
        redirect_with_flash_message("Session หมดอายุหรือไม่พบข้อมูลการนิเทศ กรุณาเริ่มต้นใหม่", "supervision_start.php");
    }

    // --- ส่วนที่ 1: รับค่าตัวแปรและ Sanitize ข้อมูล ---
    $supervisor_p_id = $_SESSION['inspection_data']['s_p_id'] ?? null;
    $teacher_t_pid = $_SESSION['inspection_data']['t_pid'] ?? null;

    $subject_code = $_POST['subject_code'] ?? null;
    $subject_name = $_POST['subject_name'] ?? null;
    $inspection_time = isset($_POST['inspection_time']) ? intval($_POST['inspection_time']) : null; // แปลงเป็น int
    $inspection_date = $_POST['supervision_date'] ?? date('Y-m-d'); // ใช้วันปัจจุบันถ้าไม่ส่งมา
    $overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

    // รับ Array ข้อมูลคะแนนและข้อเสนอแนะ
    // สำคัญ: ต้องมั่นใจว่าในหน้า Form ตั้งชื่อ name="ratings[ID]" และ name="indicator_suggestions[ID]"
    $ratings = $_POST['ratings'] ?? [];
    $comments = $_POST['comments'] ?? [];
    $indicator_suggestions = $_POST['indicator_suggestions'] ?? [];

    // ตรวจสอบข้อมูลบังคับ (Validation)
    if (!$supervisor_p_id || !$teacher_t_pid || !$subject_code || !$inspection_time) {
        redirect_with_flash_message("ข้อผิดพลาด: ข้อมูลหลักไม่ครบถ้วน (Supervisor, Teacher, Subject, Time)");
    }

    // --- ส่วนที่ 2: เริ่ม Transaction ---
    $conn->begin_transaction();

    try {
        // ==================================================================================
        // A. ตาราง supervision_sessions (ข้อมูลหลัก)
        // ==================================================================================
        $sql_session = "INSERT INTO supervision_sessions 
                        (supervisor_p_id, teacher_t_pid, subject_code, subject_name, inspection_time, inspection_date, overall_suggestion, supervision_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        subject_name = VALUES(subject_name), 
                        inspection_date = VALUES(inspection_date), 
                        overall_suggestion = VALUES(overall_suggestion), 
                        supervision_date = NOW()";

        $stmt_session = $conn->prepare($sql_session);
        if (!$stmt_session) throw new Exception("Prepare failed (session): " . $conn->error);

        $stmt_session->bind_param("ssssiss", $supervisor_p_id, $teacher_t_pid, $subject_code, $subject_name, $inspection_time, $inspection_date, $overall_suggestion);

        if (!$stmt_session->execute()) throw new Exception("Execute failed (session): " . $stmt_session->error);
        $stmt_session->close();


        // ==================================================================================
        // B. ตาราง kpi_answers (คะแนนรายข้อ)
        // ==================================================================================
        if (!empty($ratings)) {
            $sql_answer = "INSERT INTO kpi_answers 
                           (question_id, rating_score, comment, supervisor_p_id, teacher_t_pid, subject_code, inspection_time) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)
                           ON DUPLICATE KEY UPDATE 
                           rating_score = VALUES(rating_score),
                           comment = VALUES(comment)";

            $stmt_answer = $conn->prepare($sql_answer);
            if (!$stmt_answer) throw new Exception("Prepare failed (answers): " . $conn->error);

            foreach ($ratings as $question_id => $rating_score) {
                $q_id = intval($question_id);
                $score = intval($rating_score);
                $comment_text = trim($comments[$question_id] ?? ''); // ดึง comment ตาม ID คำถาม

                // Bind: iissssi (int, int, string, string, string, string, int)
                $stmt_answer->bind_param("iissssi", $q_id, $score, $comment_text, $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time);

                if (!$stmt_answer->execute()) {
                    throw new Exception("Error saving answer QID: $q_id - " . $stmt_answer->error);
                }
            }
            $stmt_answer->close();
        }


        // ==================================================================================
        // C. ตาราง kpi_indicator_suggestions (ข้อเสนอแนะรายตัวชี้วัด)
        // ==================================================================================
        if (!empty($indicator_suggestions)) {
            $sql_suggestion = "INSERT INTO kpi_indicator_suggestions 
                               (indicator_id, suggestion_text, supervisor_p_id, teacher_t_pid, subject_code, inspection_time) 
                               VALUES (?, ?, ?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE
                               suggestion_text = VALUES(suggestion_text)";

            $stmt_suggestion = $conn->prepare($sql_suggestion);
            if (!$stmt_suggestion) throw new Exception("Prepare failed (suggestions): " . $conn->error);

            foreach ($indicator_suggestions as $indicator_id => $suggestion_text) {
                $ind_id = intval($indicator_id);
                $text = trim($suggestion_text);

                // 🔥 แก้ไข: เอาเงื่อนไข if (!empty($text)) ออก 
                // เพื่อให้สามารถบันทึกค่าว่างได้ (กรณี User ลบข้อความออก เราต้อง Update เป็นค่าว่างด้วย)

                // Bind: issssi (int, string, string, string, string, int)
                $stmt_suggestion->bind_param("issssi", $ind_id, $text, $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time);

                if (!$stmt_suggestion->execute()) {
                    throw new Exception("Error saving suggestion IndID: $ind_id - " . $stmt_suggestion->error);
                }
            }
            $stmt_suggestion->close();
        }

        // --- ส่วนที่ 3: จบการทำงาน (Commit) ---
        $conn->commit();

        // Clear Data
        unset($_SESSION['inspection_data']);

        redirect_with_flash_message('บันทึกข้อมูลการนิเทศเรียบร้อยแล้ว');
    } catch (Exception $e) {
        $conn->rollback(); // ยกเลิกทั้งหมดถ้ามี error
        error_log("Save KPI Error: " . $e->getMessage()); // บันทึก error ลง log ของ server
        redirect_with_flash_message("เกิดข้อผิดพลาด: " . $e->getMessage());
    } finally {
        $conn->close();
    }
} else {
    header("Location: index.php");
    exit();
}
