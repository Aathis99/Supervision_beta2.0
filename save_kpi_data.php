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
    $teacher_t_pid   = $_SESSION['inspection_data']['t_pid'] ?? null;

    $subject_code      = $_POST['subject_code']      ?? null;
    $subject_name      = $_POST['subject_name']      ?? null;
    $inspection_time   = isset($_POST['inspection_time']) ? intval($_POST['inspection_time']) : null; // แปลงเป็น int
    $inspection_date   = $_POST['supervision_date']  ?? date('Y-m-d'); // ใช้วันปัจจุบันถ้าไม่ส่งมา
    $overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

    // รับ Array ข้อมูลคะแนนและข้อเสนอแนะ
    $ratings               = $_POST['ratings']               ?? [];
    $comments              = $_POST['comments']              ?? [];
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
                            subject_name       = VALUES(subject_name), 
                            inspection_date    = VALUES(inspection_date), 
                            overall_suggestion = VALUES(overall_suggestion), 
                            supervision_date   = NOW()";

        $stmt_session = $conn->prepare($sql_session);
        if (!$stmt_session) {
            throw new Exception("Prepare failed (session): " . $conn->error);
        }

        $stmt_session->bind_param(
            "ssssiss",
            $supervisor_p_id,
            $teacher_t_pid,
            $subject_code,
            $subject_name,
            $inspection_time,
            $inspection_date,
            $overall_suggestion
        );

        if (!$stmt_session->execute()) {
            throw new Exception("Execute failed (session): " . $stmt_session->error);
        }
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
                               comment      = VALUES(comment)";

            $stmt_answer = $conn->prepare($sql_answer);
            if (!$stmt_answer) {
                throw new Exception("Prepare failed (answers): " . $conn->error);
            }

            foreach ($ratings as $question_id => $rating_score) {
                $q_id   = intval($question_id);
                $score  = intval($rating_score);
                $comment_text = trim($comments[$question_id] ?? '');

                // Bind: iissssi (int, int, string, string, string, string, int)
                $stmt_answer->bind_param(
                    "iissssi",
                    $q_id,
                    $score,
                    $comment_text,
                    $supervisor_p_id,
                    $teacher_t_pid,
                    $subject_code,
                    $inspection_time
                );

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
            if (!$stmt_suggestion) {
                throw new Exception("Prepare failed (suggestions): " . $conn->error);
            }

            foreach ($indicator_suggestions as $indicator_id => $suggestion_text) {
                $ind_id = intval($indicator_id);
                $text   = trim($suggestion_text);

                // Bind: issssi (int, string, string, string, string, int)
                $stmt_suggestion->bind_param(
                    "issssi",
                    $ind_id,
                    $text,
                    $supervisor_p_id,
                    $teacher_t_pid,
                    $subject_code,
                    $inspection_time
                );

                if (!$stmt_suggestion->execute()) {
                    throw new Exception("Error saving suggestion IndID: $ind_id - " . $stmt_suggestion->error);
                }
            }
            $stmt_suggestion->close();
        }

        // ==================================================================================
        // D. อัปโหลดและบันทึกรูปภาพลงตาราง images (ผูกกับ session นี้)
        // ==================================================================================
        // หมายเหตุ: แบบฟอร์มมี <input type="file" name="images[]" ...> และ enctype="multipart/form-data"
        $uploadDir = 'uploads/'; // โฟลเดอร์เก็บไฟล์ (ต้องให้เขียนได้ และมี path ตรงกับที่หน้า report ใช้)

        if (!is_dir($uploadDir)) {
            // สร้างโฟลเดอร์ถ้ายังไม่มี
            if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                throw new Exception("ไม่สามารถสร้างโฟลเดอร์อัปโหลดรูปภาพได้: " . $uploadDir);
            }
        }

        $uploaded_files = [];

        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $names  = $_FILES['images']['name'];
            $tmp    = $_FILES['images']['tmp_name'];
            $errors = $_FILES['images']['error'];

            // ป้องกันเกิน 2 รูป (ซ้ำกับฝั่ง JS แต่ตรวจซ้ำด้าน server)
            $maxFiles = 2;
            $countFiles = min(count($names), $maxFiles);

            for ($i = 0; $i < $countFiles; $i++) {

                if ($errors[$i] !== UPLOAD_ERR_OK) {
                    continue; // ข้ามถ้าอัปโหลดผิดพลาด
                }

                $origName = $names[$i];
                $tmpName  = $tmp[$i];

                $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                // อนุญาตเฉพาะ jpg / jpeg / png
                if (!in_array($ext, ['jpg', 'jpeg', 'png'])) {
                    continue;
                }

                // สร้างชื่อไฟล์ใหม่ให้ไม่ซ้ำ
                $newName = time() . '_' . uniqid() . '.' . $ext;
                $target  = $uploadDir . $newName;

                if (move_uploaded_file($tmpName, $target)) {
                    $uploaded_files[] = $newName;
                }
            }
        }

        if (!empty($uploaded_files)) {
            $sql_img = "INSERT INTO images
                            (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, file_name)
                        VALUES (?, ?, ?, ?, ?)";

            $stmt_img = $conn->prepare($sql_img);
            if (!$stmt_img) {
                throw new Exception("Prepare failed (images): " . $conn->error);
            }

            foreach ($uploaded_files as $file_name) {
                $stmt_img->bind_param(
                    "sssis",
                    $supervisor_p_id,
                    $teacher_t_pid,
                    $subject_code,
                    $inspection_time,
                    $file_name
                );

                if (!$stmt_img->execute()) {
                    throw new Exception("Error saving image: " . $stmt_img->error);
                }
            }

            $stmt_img->close();
        }

        // --- ส่วนที่ 3: จบการทำงาน (Commit) ---
        $conn->commit();

        // ล้างข้อมูล session inspection_data เพื่อไม่ให้ส่งซ้ำ
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
