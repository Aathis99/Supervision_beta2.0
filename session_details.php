<?php
// ไฟล์: session_details.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php';

// ตรวจสอบสถานะล็อกอิน
$is_supervisor = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// 1) รับค่า teacher_pid จาก POST หรือ GET
$teacher_pid = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teacher_pid = $_POST['teacher_pid'];
} elseif (isset($_GET['teacher_pid'])) {
    // Fallback for existing links, but new links should use POST.
    $teacher_pid = $_GET['teacher_pid'];
}

if ($teacher_pid === null) {
    die('<div class="alert alert-danger mt-5 text-center">ไม่พบรหัสประจำตัวผู้รับการนิเทศ</div>');
}

$results      = [];
$teacher_info = null;

// 2) ดึงข้อมูลครู
$sql_teacher = "SELECT 
                    CONCAT(t.PrefixName, t.fname, ' ', t.lname) AS teacher_full_name, 
                    s.SchoolName,
                    t.adm_name AS teacher_position, 
                    CASE
                        WHEN t.learning_group like '%ภาษาไทย%' THEN 'กลุ่มสาระการเรียนรู้ภาษาไทย'
                        WHEN t.learning_group like '%คณิตศาสตร์%' THEN 'กลุ่มสาระการเรียนรู้คณิตศาสตร์'
                        WHEN t.learning_group REGEXP 'วิทยาศาสตร์|เคมี|ฟิสิกส์|ชีววิทยา|คอมพิวเตอร์|เทคโนโลยี|โลก ดาราศาสตร์' THEN 'กลุ่มสาระการเรียนรู้วิทยาศาสตร์และเทคโนโลยี'
                        WHEN t.learning_group like '%สังคม%' OR t.learning_group like '%ประวัติศาสตร์%' OR t.learning_group like '%ภูมิศาสตร์%' THEN 'กลุ่มสาระการเรียนรู้สังคมศึกษา ศาสนา และวัฒนธรรม'
                        WHEN t.learning_group REGEXP 'สุขศึกษา|พลศึกษา|พละ' THEN 'กลุ่มสาระการเรียนรู้สุขศึกษาและพลศึกษา'
                        WHEN t.learning_group REGEXP 'ศิลป|ดนตรี|นาฎศิลป์|ทัศนศิลป์' THEN 'กลุ่มสาระการเรียนรู้ศิลปะ'
                        WHEN t.learning_group REGEXP 'การงาน|เกษตร|คหกรรม|อุตสาหกรรม|พณิชยกรรม|บริหารธุรกิจ' THEN 'กลุ่มสาระการเรียนรู้การงานอาชีพ'
                        WHEN t.learning_group REGEXP 'อังกฤษ|จีน|ญี่ปุ่น|ฝรั่งเศส|เกาหลี|ต่างประเทศ' THEN 'กลุ่มสาระการเรียนรู้ภาษาต่างประเทศ'
                        WHEN t.learning_group like '%แนะแนว%' THEN 'กิจกรรมพัฒนาผู้เรียน' 
                        ELSE 'อื่นๆ' 
                    END AS learning_group
                FROM teacher t
                LEFT JOIN school s ON t.school_id = s.school_id
                WHERE t.t_pid = ?";

$stmt_teacher = $conn->prepare($sql_teacher);
$stmt_teacher->bind_param("s", $teacher_pid);
$stmt_teacher->execute();
$result_teacher = $stmt_teacher->get_result();

if ($result_teacher->num_rows > 0) {
    $teacher_info = $result_teacher->fetch_assoc();
} else {
    die('<div class="alert alert-danger mt-5 text-center">ไม่พบข้อมูลครูในระบบ</div>');
}
$stmt_teacher->close();

// 3) ดึงประวัติ (นิเทศปกติ + Quick Win)
//    Quick Win ใช้ key: t_id + p_id + supervision_date
$sql_history = "
    SELECT * FROM (
        SELECT 
            ss.supervisor_p_id,
            ss.teacher_t_pid,
            ss.subject_code,
            ss.inspection_time,
            'normal' AS session_type,
            ss.supervision_date,
            ss.inspection_time AS time_info,
            ss.subject_name AS topic,
            CONCAT(sp.PrefixName, sp.fname, ' ', sp.lname) AS supervisor_full_name,
            (CASE WHEN EXISTS (
                SELECT 1 FROM satisfaction_answers sa 
                WHERE sa.supervisor_p_id = ss.supervisor_p_id 
                  AND sa.teacher_t_pid   = ss.teacher_t_pid 
                  AND sa.subject_code    = ss.subject_code 
                  AND sa.inspection_time = ss.inspection_time
            ) THEN 1 ELSE 0 END) AS status,
            NULL AS qw_t_id,
            NULL AS qw_p_id,
            NULL AS qw_date
        FROM supervision_sessions ss
        LEFT JOIN supervisor sp ON ss.supervisor_p_id = sp.p_id
        WHERE ss.teacher_t_pid = ?

        UNION ALL

        SELECT 
            NULL AS supervisor_p_id,
            qw.t_id AS teacher_t_pid,
            NULL AS subject_code,
            NULL AS inspection_time,
            'quickwin' AS session_type,
            qw.supervision_date,
            '-' AS time_info,
            qo.OptionText AS topic,
            CONCAT(sp.PrefixName, sp.fname, ' ', sp.lname) AS supervisor_full_name,
            (CASE WHEN EXISTS (
                SELECT 1 
                FROM quickwin_satisfaction_answers qsa
                WHERE qsa.t_id             = qw.t_id
                  AND qsa.p_id             = qw.p_id
                  AND qsa.supervision_date = qw.supervision_date
            ) THEN 1 ELSE 0 END) AS status,
            qw.t_id             AS qw_t_id,
            qw.p_id             AS qw_p_id,
            qw.supervision_date AS qw_date
        FROM quick_win qw
        LEFT JOIN supervisor      sp ON qw.p_id   = sp.p_id
        LEFT JOIN quickwin_options qo ON qw.options = qo.OptionID
        WHERE qw.t_id = ?
    ) AS history
    ORDER BY supervision_date DESC
";

$stmt = $conn->prepare($sql_history);

// ✅ ถ้า prepare ไม่ผ่าน ให้โชว์ error ชัด ๆ แทนที่จะไป bind_param ต่อแล้วพัง
if ($stmt === false) {
    die("เกิดข้อผิดพลาดในคำสั่ง SQL (history): " . htmlspecialchars($conn->error));
}

$stmt->bind_param("ss", $teacher_pid, $teacher_pid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
}
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการนิเทศ - <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .badge-normal {
            background-color: #0d6efd;
            color: white;
        }

        .badge-qw {
            background-color: #ffc107;
            color: black;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <h2 class="card-title text-center mb-4">
                <i class="fas fa-user-clock"></i> รายละเอียดประวัติการนิเทศ
            </h2>

            <div class="card mb-4 border-primary">
                <div class="card-body bg-light">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <strong>ผู้รับการนิเทศ:</strong>
                            <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>โรงเรียน:</strong>
                            <?php echo htmlspecialchars($teacher_info['SchoolName']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>ตำแหน่ง:</strong>
                            <?php echo htmlspecialchars($teacher_info['teacher_position']); ?>
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>กลุ่มสาระฯ:</strong>
                            <?php echo htmlspecialchars($teacher_info['learning_group']); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-primary">
                        <tr class="text-center">
                            <th style="width: 15%;">วันที่</th>
                            <th style="width: 10%;">ประเภท</th>
                            <th style="width: 25%;" class="text-center">หัวข้อ / วิชา</th>
                            <th style="width: 20%;">ผู้นิเทศ</th>
                            <th style="width: 30%;">การดำเนินการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)) : ?>
                            <tr>
                                <td colspan="5" class="text-center text-danger fw-bold p-4">
                                    ไม่พบประวัติการนิเทศสำหรับครูท่านนี้
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($results as $row) : ?>
                                <tr>
                                    <td class="text-center">
                                        <?php echo (new DateTime($row['supervision_date']))->format('d/m/Y H:i'); ?> น.
                                    </td>

                                    <td class="text-center">
                                        <?php if ($row['session_type'] === 'normal'): ?>
                                            <span class="badge badge-normal">นิเทศ</span><br>
                                            <small class="text-muted">ครั้งที่ <?php echo $row['time_info']; ?></small>
                                        <?php else: ?>
                                            <span class="badge badge-qw">จุดเน้น (Quick Win)</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center">
                                        <?php echo htmlspecialchars($row['topic']); ?>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars($row['supervisor_full_name']); ?>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($row['session_type'] === 'normal'): ?>
                                            <!-- ✅ นิเทศปกติ -->
                                            <div class="btn-group" role="group">
                                                <form method="POST" action="supervision_report.php" style="display:inline;">
                                                    <input type="hidden" name="s_pid" value="<?php echo htmlspecialchars($row['supervisor_p_id']); ?>">
                                                    <input type="hidden" name="t_pid" value="<?php echo htmlspecialchars($row['teacher_t_pid']); ?>">
                                                    <input type="hidden" name="sub_code" value="<?php echo htmlspecialchars($row['subject_code']); ?>">
                                                    <input type="hidden" name="time" value="<?php echo htmlspecialchars($row['inspection_time']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-info text-white">
                                                        <i class="fas fa-file-alt"></i> รายงาน
                                                    </button>
                                                </form>

                                                <?php if (!$is_supervisor): ?>
                                                    <?php if ($row['status'] == 0): ?>
                                                        <form method="POST" action="forms/satisfaction_form.php" style="display:inline;">
                                                            <input type="hidden" name="mode" value="normal">
                                                            <input type="hidden" name="s_pid" value="<?php echo htmlspecialchars($row['supervisor_p_id']); ?>">
                                                            <input type="hidden" name="t_pid" value="<?php echo htmlspecialchars($row['teacher_t_pid']); ?>">
                                                            <input type="hidden" name="sub_code" value="<?php echo htmlspecialchars($row['subject_code']); ?>">
                                                            <input type="hidden" name="time" value="<?php echo htmlspecialchars($row['inspection_time']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-star"></i> ประเมิน
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="certificate.php" style="display:inline;">
                                                            <input type="hidden" name="s_pid" value="<?php echo htmlspecialchars($row['supervisor_p_id']); ?>">
                                                            <input type="hidden" name="t_pid" value="<?php echo htmlspecialchars($row['teacher_t_pid']); ?>">
                                                            <input type="hidden" name="sub_code" value="<?php echo htmlspecialchars($row['subject_code']); ?>">
                                                            <input type="hidden" name="time" value="<?php echo htmlspecialchars($row['inspection_time']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-certificate"></i> เกียรติบัตร
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>

                                        <?php else: ?>
                                            <!-- ✅ Quick Win -->
                                            <div class="btn-group" role="group">
                                                <?php if (!$is_supervisor): ?>
                                                    <?php if ($row['status'] == 0): ?>
                                                        <form method="POST" action="forms/satisfaction_form.php" style="display:inline;">
                                                            <input type="hidden" name="mode" value="quickwin">
                                                            <input type="hidden" name="t_id" value="<?php echo htmlspecialchars($row['qw_t_id']); ?>">
                                                            <input type="hidden" name="p_id" value="<?php echo htmlspecialchars($row['qw_p_id']); ?>">
                                                            <input type="hidden" name="date" value="<?php echo htmlspecialchars($row['qw_date']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-warning">
                                                                <i class="fas fa-star"></i> ประเมินจุดเน้น
                                                            </button>
                                                        </form>
                                                    <?php else: ?>
                                                        <button type="button" class="btn btn-sm btn-success" disabled>
                                                            <i class="fas fa-check-circle"></i> ประเมินแล้ว
                                                        </button>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                        <i class="fas fa-info-circle"></i> Quick Win
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-4">
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i> กลับไปหน้าประวัติรวม
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>