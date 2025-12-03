<?php
// ไฟล์: forms/satisfaction_form.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db_connect.php';

// 1. รับค่า Composite Key จาก URL
$s_pid = $_GET['s_pid'] ?? null;
$t_pid = $_GET['t_pid'] ?? null;
$sub_code = $_GET['sub_code'] ?? null;
$time = $_GET['time'] ?? null;

if (!$s_pid || !$t_pid || !$sub_code || !$time) {
    die('<div class="alert alert-danger mt-5 text-center">ข้อมูลที่จำเป็นสำหรับการประเมินไม่ครบถ้วน</div>');
}

// 2. ดึงข้อมูลการนิเทศเพื่อแสดงผล และตรวจสอบสถานะ
$sql_session = "SELECT 
                    ss.supervision_date,
                    ss.subject_name,
                    CONCAT(sp.PrefixName, sp.fname, ' ', sp.lname) AS supervisor_full_name,
                    CONCAT(t.PrefixName, t.fname, ' ', t.lname) AS teacher_full_name,
                    (CASE WHEN EXISTS (SELECT 1 FROM satisfaction_answers sa WHERE sa.supervisor_p_id = ss.supervisor_p_id AND sa.teacher_t_pid = ss.teacher_t_pid AND sa.subject_code = ss.subject_code AND sa.inspection_time = ss.inspection_time) THEN 1 ELSE 0 END) AS status
                FROM supervision_sessions ss
                JOIN supervisor sp ON ss.supervisor_p_id = sp.p_id
                JOIN teacher t ON ss.teacher_t_pid = t.t_pid
                WHERE ss.supervisor_p_id = ? AND ss.teacher_t_pid = ? AND ss.subject_code = ? AND ss.inspection_time = ?";

$stmt_session = $conn->prepare($sql_session);
$stmt_session->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
$stmt_session->execute();
$result_session = $stmt_session->get_result();
$session_info = $result_session->fetch_assoc();
$stmt_session->close();

if (!$session_info) {
    die('<div class="alert alert-danger mt-5 text-center">ไม่พบข้อมูลการนิเทศที่ต้องการประเมิน</div>');
}

// 3. ดึงคำถามจากฐานข้อมูล
$sql_questions = "SELECT id, question_text FROM satisfaction_questions ORDER BY display_order ASC";
$result_questions = $conn->query($sql_questions);
$questions = [];
if ($result_questions && $result_questions->num_rows > 0) {
    while ($row = $result_questions->fetch_assoc()) {
        $questions[] = $row;
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แบบประเมินความพึงพอใจ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>

<body>
    <div class="container my-5">
        <div class="card shadow-lg">
            <div class="card-header bg-warning text-dark text-center">
                <h4 class="mb-0"><i class="fas fa-star"></i> แบบประเมินความพึงพอใจการนิเทศ</h4>
            </div>
            <div class="card-body p-4">

                <div class="alert alert-info">
                    <div class="row">
                        <div class="col-md-6"><strong>ผู้รับการนิเทศ:</strong> <?php echo htmlspecialchars($session_info['teacher_full_name']); ?></div>
                        <div class="col-md-6"><strong>ผู้นิเทศ:</strong> <?php echo htmlspecialchars($session_info['supervisor_full_name']); ?></div>
                        <div class="col-md-6"><strong>วิชา/หัวข้อ:</strong> <?php echo htmlspecialchars($session_info['subject_name']); ?></div>
                        <div class="col-md-6"><strong>วันที่นิเทศ:</strong> <?php echo (new DateTime($session_info['supervision_date']))->format('d/m/Y'); ?></div>
                    </div>
                </div>

                <?php if ($session_info['status'] == 1): ?>
                    <div class="alert alert-success text-center">
                        <h5 class="alert-heading"><i class="fas fa-check-circle"></i> ท่านได้ทำการประเมินเรียบร้อยแล้ว</h5>
                        <p>ขอขอบคุณสำหรับความคิดเห็นของท่าน</p>

                        <div class="d-flex justify-content-center gap-2 mt-3">
                            <a href="../session_details.php?teacher_pid=<?php echo urlencode($t_pid); ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> กลับไปหน้าประวัติ
                            </a>

                            <a href="../certificate.php?s_pid=<?php echo htmlspecialchars($s_pid); ?>&t_pid=<?php echo htmlspecialchars($t_pid); ?>&sub_code=<?php echo htmlspecialchars($sub_code); ?>&time=<?php echo htmlspecialchars($time); ?>"
                                target="_blank"
                                class="btn btn-success">
                                <i class="fas fa-print"></i> พิมพ์เกียรติบัตร
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- แบบฟอร์มหลัก -->
                    <form id="satisfactionForm" method="POST" action="save_satisfaction.php">
                        <!-- ส่ง Composite Key ไปกับฟอร์ม -->
                        <input type="hidden" name="s_pid" value="<?php echo htmlspecialchars($s_pid); ?>">
                        <input type="hidden" name="t_pid" value="<?php echo htmlspecialchars($t_pid); ?>">
                        <input type="hidden" name="sub_code" value="<?php echo htmlspecialchars($sub_code); ?>">
                        <input type="hidden" name="time" value="<?php echo htmlspecialchars($time); ?>">

                        <p class="mb-2"><strong>คำชี้แจง :</strong> โปรดเลือกระดับความพึงพอใจที่ตรงกับความพึงพอใจของท่านมากที่สุด เกณฑ์การประเมินความพึงพอใจ
                            มี 5 ระดับ ดังนี้ <br>5 หมายถึง มากที่สุด, 4 หมายถึงมาก, 3 หมายถึงปานกลาง, 2 หมายถึง น้อย, 1 หมายถึง น้อยที่สุด
                        </p>
                        <hr>

                        <?php if (empty($questions)): ?>
                            <div class="alert alert-warning">ไม่พบข้อคำถามในระบบ</div>
                        <?php else: ?>
                            <?php foreach ($questions as $question) : ?>
                                <div class="card mb-3">
                                    <div class="card-body p-4">
                                        <div class="mb-3">
                                            <label class="form-label-question" for="rating_<?php echo $question['id']; ?>">
                                                <?php echo htmlspecialchars($question['question_text']); ?>
                                            </label>
                                        </div>

                                        <div class="d-flex justify-content-center flex-wrap">
                                            <?php for ($i = 5; $i >= 1; $i--) : ?>
                                                <div class="form-check form-check-inline mx-2">
                                                    <input
                                                        class="form-check-input"
                                                        type="radio"
                                                        name="ratings[<?php echo $question['id']; ?>]"
                                                        id="q<?php echo $question['id']; ?>-<?php echo $i; ?>"
                                                        value="<?php echo $i; ?>"
                                                        required
                                                        <?php echo ($i == 5) ? 'checked' : ''; // ให้คะแนน 5 เป็นค่าเริ่มต้น 
                                                        ?> />
                                                    <label class="form-check-label" for="q<?php echo $question['id']; ?>-<?php echo $i; ?>"><?php echo $i; ?></label>
                                                </div>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- ส่วนสำหรับ "ข้อเสนอแนะเพิ่มเติม" -->
                        <div class="card mt-4 border-primary">
                            <div class="card-header bg-primary text-white fw-bold">
                                <i class="fas fa-lightbulb"></i> ข้อเสนอแนะเพิ่มเติมเพื่อการพัฒนาระบบ
                            </div>
                            <div class="card-body">
                                <textarea
                                    class="form-control"
                                    id="overall_suggestion"
                                    name="overall_suggestion"
                                    rows="4"
                                    placeholder="กรอกข้อเสนอแนะของคุณที่นี่..."></textarea>
                            </div>
                        </div>

                        <!-- ปุ่มบันทึกข้อมูล -->
                        <div class="d-flex justify-content-center my-4">
                            <button type="submit" class="btn btn-success fs-5 px-4 py-2" <?php echo empty($questions) ? 'disabled' : ''; ?>>
                                <i class="fas fa-save"></i> บันทึกผลการประเมิน
                            </button>
                        </div>
                    </form>
                <?php endif; ?>

            </div>
            <!-- <div class="card-footer text-center">
                <a href="../session_details.php?teacher_pid=<?php echo urlencode($t_pid); ?>" class="btn btn-secondary"><i class="fas fa-chevron-left"></i> กลับไปหน้ารายละเอียดประวัติ</a>
            </div> -->
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>