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


        // ... (โค้ดส่วนเดิม) ...
        if (isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {

            // ⭐️ FIX: ใช้ Absolute Path (ระบุพิกัดเต็มๆ จากรากเครื่อง)
            // dirname(__DIR__) จะคืนค่าเป็น /var/www/html/supervision_beta2.0
            $targetDir = dirname(__DIR__) . "/uploads/";

            // 🔍 Debug: ถ้ายังไม่ได้ ให้เอา Comment บรรทัดล่างนี้ออก เพื่อดูว่ามันชี้ไปไหน
            // echo "Trying to upload to: " . $targetDir; exit;

            // เช็คว่ามีโฟลเดอร์ไหม (ถ้าทำตามขั้นตอน Terminal ข้างบนแล้ว โค้ดนี้จะผ่านฉลุย)
            if (!file_exists($targetDir)) {
                if (!mkdir($targetDir, 0777, true)) {
                    throw new Exception("หาโฟลเดอร์ uploads ไม่เจอ และสร้างเองไม่ได้ (ติด Permission)");
                }
            }

            // เช็คสิทธิ์การเขียน (ถ้าทำ chown แล้ว จะผ่านตรงนี้)
            if (!is_writable($targetDir)) {
                throw new Exception("โฟลเดอร์ uploads มีอยู่จริง แต่ PHP เขียนไฟล์ลงไปไม่ได้ (กรุณารัน chown www-data)");
            }

            // ... (ส่วน Loop บันทึกไฟล์ เหมือนเดิม) ...

            // ... (โค้ดส่วนตรวจสอบ Permission และ Loop บันทึก เหมือนเดิม) ...

            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            $count = count($_FILES['images']['name']);

            // SQL: 4 คีย์หลัก + file_name
            $sql_img = "INSERT INTO images (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, file_name) 
                        VALUES (?, ?, ?, ?, ?)";
            $stmt_img = $conn->prepare($sql_img);
            if (!$stmt_img) {
                throw new Exception("Prepare failed (images): " . $conn->error);
            }

            // วนลูปบันทึกทีละรูป
            for ($i = 0; $i < $count; $i++) {
                if (!empty($_FILES['images']['name'][$i])) {

                    // ตรวจสอบ Error จากการอัปโหลดของ PHP
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
                        throw new Exception("FileUpload Error Code: " . $_FILES['images']['error'][$i]);
                    }

                    $fileName = basename($_FILES['images']['name'][$i]);
                    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    if (in_array($fileType, $allowedTypes)) {
                        // ตั้งชื่อไฟล์ใหม่: img_รหัสครู_เวลา_ลำดับ.ext
                        $newFileName = "img_" . $teacher_t_pid . "_" . time() . "_" . $i . "." . $fileType;
                        $targetFilePath = $targetDir . $newFileName;

                        // 1. พยายามย้ายไฟล์
                        if (move_uploaded_file($_FILES['images']['tmp_name'][$i], $targetFilePath)) {
                            // 2. บันทึกลงฐานข้อมูล
                            $stmt_img->bind_param("sssis", $supervisor_p_id, $teacher_t_pid, $subject_code, $inspection_time, $newFileName);

                            // ⭐️ จุดสำคัญ: เช็คว่า Execute สำเร็จไหม
                            if (!$stmt_img->execute()) {
                                // ลบไฟล์ทิ้งถ้าลง Database ไม่สำเร็จ เพื่อไม่ให้มีไฟล์ขยะ
                                @unlink($targetFilePath);
                                throw new Exception("Error saving image to DB: " . $stmt_img->error);
                            }
                        } else {
                            throw new Exception("Failed to move uploaded file: " . $newFileName);
                        }
                    } else {
                        throw new Exception("ไฟล์รูปภาพไม่รองรับนามสกุล: " . $fileType);
                    }
                }
            }
            $stmt_img->close();
        }

        // ... (ไป Commit Transaction ต่อ) ...




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
