<?php
// ไฟล์: save_satisfaction.php
session_start();
require_once 'config/db_connect.php';

function redirect_with_error($message) {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = 'danger'; // 'danger' for bootstrap alert-danger
    header("Location: history.php"); // Redirect to a user-friendly page
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_error("Invalid request method.");
}

if (!isset($_SESSION['satisfaction_data']['session_id']) || !isset($_POST['ratings'])) {
    redirect_with_error("Session หมดอายุหรือไม่พบข้อมูลการนิเทศ กรุณาเริ่มต้นใหม่");
}

$session_id = $_SESSION['satisfaction_data']['session_id'];
$ratings = $_POST['ratings'] ?? [];
$overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

if (empty($ratings)) {
    redirect_with_error("กรุณาให้คะแนนความพึงพอใจอย่างน้อยหนึ่งข้อ");
}

// เริ่มต้น Transaction
$conn->begin_transaction();

try {
    // ⭐️ ตรวจสอบก่อนว่าเคยประเมินไปแล้วหรือยัง เพื่อป้องกันการส่งข้อมูลซ้ำ
    $stmt_check = $conn->prepare("SELECT satisfaction_submitted FROM supervision_sessions WHERE id = ?");
    $stmt_check->bind_param("i", $session_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($result_check && $result_check['satisfaction_submitted'] == 1) {
        throw new Exception("การนิเทศครั้งนี้ได้รับการประเมินความพึงพอใจไปแล้ว");
    }

    // 1. บันทึกคะแนนแต่ละข้อ
    $sql_answer = "INSERT INTO satisfaction_answers (session_id, question_id, rating) VALUES (?, ?, ?)";
    $stmt_answer = $conn->prepare($sql_answer);

    foreach ($ratings as $question_id => $rating) {
        $q_id = (int)$question_id;
        $rate_score = (int)$rating;
        $stmt_answer->bind_param("iii", $session_id, $q_id, $rate_score);
        $stmt_answer->execute();
    }
    $stmt_answer->close();

    // 2. อัปเดตตาราง supervision_sessions เพื่อเก็บข้อเสนอแนะ และสถานะการประเมิน
    // (เพิ่มคอลัมน์ satisfaction_suggestion และ satisfaction_submitted)
    $sql_session_update = "UPDATE supervision_sessions 
                           SET satisfaction_suggestion = ?, 
                               satisfaction_submitted = 1,
                               satisfaction_date = NOW()
                           WHERE id = ?";
    $stmt_session = $conn->prepare($sql_session_update);
    $stmt_session->bind_param("si", $overall_suggestion, $session_id);
    $stmt_session->execute();
    $stmt_session->close();

    // ยืนยัน Transaction
    $conn->commit();

    // ดึง teacher_t_pid เพื่อใช้ในการ redirect กลับไปหน้า session_details.php
    $stmt_get_teacher = $conn->prepare("SELECT teacher_t_pid FROM supervision_sessions WHERE id = ?");
    $stmt_get_teacher->bind_param("i", $session_id);
    $stmt_get_teacher->execute();
    $teacher_pid_result = $stmt_get_teacher->get_result()->fetch_assoc();
    $teacher_pid = $teacher_pid_result['teacher_t_pid'];
    $stmt_get_teacher->close();

    // ล้าง session และเปลี่ยนเส้นทาง
    unset($_SESSION['satisfaction_data']);
    
    // ส่งกลับไปหน้าประวัติพร้อมข้อความสำเร็จ
    $_SESSION['message'] = 'บันทึกข้อมูลความพึงพอใจเรียบร้อยแล้ว';
    $_SESSION['message_type'] = 'success';

    // สร้างฟอร์มเพื่อ POST กลับไปหน้า session_details.php
    echo '<!DOCTYPE html><html><head><title>Redirecting...</title></head><body>';
    echo '<form id="redirectForm" method="POST" action="session_details.php">';
    echo '<input type="hidden" name="teacher_pid" value="' . htmlspecialchars($teacher_pid) . '">';
    echo '</form>';
    echo '<script type="text/javascript">document.getElementById("redirectForm").submit();</script>';
    echo '</body></html>';

    exit();

} catch (Exception $e) {
    $conn->rollback();
    redirect_with_error("ไม่สามารถบันทึกข้อมูลได้: " . $e->getMessage()); // Message will be shown on history.php
}

$conn->close();
?>