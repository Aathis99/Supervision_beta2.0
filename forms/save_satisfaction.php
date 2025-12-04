<?php
// forms/save_satisfaction.php
session_start();

require_once '../config/db_connect.php';

function redirect_with_flash_message($message, $type = 'danger', $location = '../history.php')
{
    $_SESSION['flash_message']      = $message;
    $_SESSION['flash_message_type'] = $type;
    header("Location: " . $location);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirect_with_flash_message("Invalid request method.");
}

// โหมดการประเมิน
$mode = $_POST['mode'] ?? 'normal';

// ข้อมูลร่วม
$ratings            = $_POST['ratings'] ?? [];
$overall_suggestion = trim($_POST['overall_suggestion'] ?? '');

if (empty($ratings)) {
    redirect_with_flash_message("กรุณาให้คะแนนประเมินอย่างน้อย 1 ข้อ");
}

$conn->begin_transaction();

try {

    // ---------------------------------------------------------
    // [NORMAL] นิเทศปกติ
    // ---------------------------------------------------------
    if ($mode === 'normal') {

        $s_pid    = $_POST['s_pid']    ?? null;
        $t_pid    = $_POST['t_pid']    ?? null;
        $sub_code = $_POST['sub_code'] ?? null;
        $time     = $_POST['time']     ?? null;

        if (!$s_pid || !$t_pid || !$sub_code || !$time) {
            redirect_with_flash_message("ข้อมูลที่จำเป็นสำหรับการบันทึกไม่ครบถ้วน (normal)");
        }

        // ลบคะแนนเก่า
        $stmt_del = $conn->prepare("
            DELETE FROM satisfaction_answers
            WHERE supervisor_p_id = ?
              AND teacher_t_pid   = ?
              AND subject_code    = ?
              AND inspection_time = ?
        ");
        if (!$stmt_del) {
            throw new Exception("Prepare DELETE (normal) failed: " . $conn->error);
        }
        $stmt_del->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
        $stmt_del->execute();
        $stmt_del->close();

        // insert คะแนนใหม่
        $sql_answer = "INSERT INTO satisfaction_answers
                       (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, question_id, rating)
                       VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_answer = $conn->prepare($sql_answer);
        if (!$stmt_answer) {
            throw new Exception("Prepare INSERT (normal) failed: " . $conn->error);
        }

        foreach ($ratings as $question_id => $rating) {
            $q_id       = (int)$question_id;
            $rate_score = (int)$rating;

            $stmt_answer->bind_param("sssiii", $s_pid, $t_pid, $sub_code, $time, $q_id, $rate_score);
            if (!$stmt_answer->execute()) {
                throw new Exception("Execute INSERT (normal) failed: " . $stmt_answer->error);
            }
        }
        $stmt_answer->close();

        // เก็บข้อเสนอแนะลง supervision_sessions
        $sql_session_update = "UPDATE supervision_sessions
                               SET satisfaction_suggestion = ?,
                                   satisfaction_date       = NOW(),
                                   satisfaction_submitted  = 1
                               WHERE supervisor_p_id = ?
                                 AND teacher_t_pid   = ?
                                 AND subject_code    = ?
                                 AND inspection_time = ?";
        $stmt_session = $conn->prepare($sql_session_update);
        if (!$stmt_session) {
            throw new Exception("Prepare UPDATE supervision_sessions failed: " . $conn->error);
        }
        $stmt_session->bind_param("ssssi", $overall_suggestion, $s_pid, $t_pid, $sub_code, $time);
        $stmt_session->execute();
        $stmt_session->close();

        // ข้อมูลสำหรับ redirect
        $redirect_target = 'satisfaction_form.php';
        $redirect_params = [
            'mode' => 'normal', 's_pid' => $s_pid, 't_pid' => $t_pid,
            'sub_code' => $sub_code, 'time' => $time
        ];
        
    // ---------------------------------------------------------
    // [QUICK WIN] จุดเน้น (quickwin)
    // ---------------------------------------------------------
    } elseif ($mode === 'quickwin') {

        $t_id             = $_POST['t_id']             ?? null;  // จาก quick_win.t_id
        $p_id             = $_POST['p_id']             ?? null;  // จาก quick_win.p_id
        $supervision_date = $_POST['supervision_date'] ?? null;  // จาก quick_win.supervision_date

        if (!$t_id || !$p_id || !$supervision_date) {
            redirect_with_flash_message("ข้อมูลที่จำเป็นสำหรับการบันทึกไม่ครบถ้วน (quickwin)");
        }

        // 1) ลบคะแนนเก่าใน quickwin_satisfaction_answers (ถ้ามี)
        $stmt_del = $conn->prepare("
            DELETE FROM quickwin_satisfaction_answers
            WHERE t_id             = ?
              AND p_id             = ?
              AND supervision_date = ?
        ");
        if (!$stmt_del) {
            throw new Exception("Prepare DELETE (quickwin) failed: " . $conn->error);
        }
        $stmt_del->bind_param("sss", $t_id, $p_id, $supervision_date);
        $stmt_del->execute();
        $stmt_del->close();

        // 2) insert คะแนนใหม่ลง quickwin_satisfaction_answers
        $sql_answer = "INSERT INTO quickwin_satisfaction_answers
                       (t_id, p_id, supervision_date, question_id, rating)
                       VALUES (?, ?, ?, ?, ?)";
        $stmt_answer = $conn->prepare($sql_answer);
        if (!$stmt_answer) {
            throw new Exception("Prepare INSERT (quickwin) failed: " . $conn->error);
        }

        foreach ($ratings as $question_id => $rating) {
            $q_id       = (int)$question_id;
            $rate_score = (int)$rating;

            $stmt_answer->bind_param("sssii", $t_id, $p_id, $supervision_date, $q_id, $rate_score);
            if (!$stmt_answer->execute()) {
                throw new Exception("Execute INSERT (quickwin) failed: " . $stmt_answer->error);
            }
        }
        $stmt_answer->close();

        // 3) ✅ เก็บข้อเสนอแนะรวมของ Quick Win ลง quick_win ด้วย
        $sql_qw_update = "UPDATE quick_win
                          SET satisfaction_suggestion = ?,
                              satisfaction_date      = NOW(),
                              satisfaction_submitted = 1
                          WHERE t_id = ? AND p_id = ? AND supervision_date = ?";
        $stmt_qw = $conn->prepare($sql_qw_update);
        if (!$stmt_qw) {
            throw new Exception("Prepare UPDATE quick_win failed: " . $conn->error);
        }
        $stmt_qw->bind_param("ssss", $overall_suggestion, $t_id, $p_id, $supervision_date);
        $stmt_qw->execute();
        $stmt_qw->close();

        // ข้อมูลสำหรับ redirect
        $redirect_target = 'satisfaction_form.php';
        $redirect_params = [
            'mode' => 'quickwin', 't_id' => $t_id, 'p_id' => $p_id,
            'date' => $supervision_date
        ];
        
    } else {
        throw new Exception("Unknown mode: " . $mode);
    }

    // ---------------------------------------------------------
    // commit & redirect with POST
    // ---------------------------------------------------------
    $conn->commit();

    $_SESSION['flash_message']      = "บันทึกการประเมินเรียบร้อยแล้ว";
    $_SESSION['flash_message_type'] = "success";

    // Use a self-submitting form to redirect with POST data
    // This avoids exposing sensitive IDs in the URL
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Redirecting...</title>
    </head>
    <body>
        <form id="redirectForm" action="' . htmlspecialchars($redirect_target) . '" method="post">';
    
    foreach ($redirect_params as $name => $value) {
        echo '<input type="hidden" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '">';
    }
    
    echo '  </form>
        <script type="text/javascript">
            // Store flash message in sessionStorage to be picked up by the next page
            // because a standard redirect won\'t carry over the PHP session for the flash message
            // when we are doing a POST redirect like this.
            sessionStorage.setItem("flash_message", "' . addslashes($_SESSION['flash_message']) . '");
            sessionStorage.setItem("flash_message_type", "' . addslashes($_SESSION['flash_message_type']) . '");
            
            document.getElementById("redirectForm").submit();
        </script>
        <p>Redirecting... If you are not redirected automatically, <a href="javascript:document.getElementById(\'redirectForm\').submit();">click here</a>.</p>
    </body>
    </html>';

    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Save Error: " . $e->getMessage());
    redirect_with_flash_message("เกิดข้อผิดพลาด: " . $e->getMessage());
}

$conn->close();
