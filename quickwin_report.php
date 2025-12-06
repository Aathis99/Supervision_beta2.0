<?php
// ไฟล์: quickwin_report.php
session_start();
require_once 'config/db_connect.php';

// รับ Composite Key จาก POST (หลัก) หรือ GET (สำรอง)
$p_id = $_POST['p_id'] ?? $_GET['p_id'] ?? null;
$t_id = $_POST['t_id'] ?? $_GET['t_id'] ?? null;
$date = $_POST['date'] ?? $_GET['date'] ?? null;

if (!$p_id || !$t_id || !$date) {
    die("ข้อมูลการประเมินจุดเน้นไม่ครบถ้วน");
}

// ========================
// 1. ข้อมูลการประเมินจุดเน้น (Quick Win)
// ========================
$sql_info = "SELECT
                qw.*,
                qo.OptionText AS topic,
                /* ข้อมูลครู */
                t.PrefixName AS t_prefix, t.fname AS t_fname, t.lname AS t_lname,
                t.t_pid, t.adm_name AS t_position,
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
                END AS learning_group,
                s_school.SchoolName AS t_school,
                /* ข้อมูลผู้นิเทศ */
                sp.PrefixName AS s_prefix, sp.fname AS s_fname, sp.lname AS s_lname,
                sp.p_id AS s_pid, sp.RankName AS s_rank, sp.OfficeName AS s_office
            FROM quick_win qw
            LEFT JOIN teacher          t        ON qw.t_id = t.t_pid
            LEFT JOIN school           s_school ON t.school_id = s_school.school_id
            LEFT JOIN supervisor       sp       ON qw.p_id = sp.p_id
            LEFT JOIN quickwin_options qo       ON qw.options = qo.OptionID
            WHERE qw.p_id = ?
              AND qw.t_id = ?
              AND qw.supervision_date = ?";

$stmt = $conn->prepare($sql_info);
if (!$stmt) {
    die("SQL Error: " . $conn->error);
}
$stmt->bind_param("sss", $p_id, $t_id, $date);
$stmt->execute();
$result_info = $stmt->get_result();
$info = $result_info->fetch_assoc();
$stmt->close();

if (!$info) {
    die("ไม่พบข้อมูลการประเมินจุดเน้นสำหรับรหัสนี้");
}

// ตัดส่วน Query ผลการประเมินความพึงพอใจออกตามที่ท่านต้องการ

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายงานผลการประเมินจุดเน้น (Quick Win)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/report_style.css">
</head>

<body>
    <div class="container">
        <div class="report-container" style="position: relative;">

            <div class="text-center mb-5" style="margin-bottom: 25px !important;">
                <img src="images/logo.png" alt="โลโก้กระทรวงศึกษาธิการ" style="max-width: 80px; margin-bottom: 10px;">
                <p style="margin-bottom: 0; font-weight: bold; font-size: 0.95rem;">รายงานผลการประเมินจุดเน้น (Quick Win) ภาคเรียนที่ ๒ ปีการศึกษา ๒๕๖๘</p>
                <p style="margin-bottom: 0; font-weight: bold; font-size: 0.9rem;">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาลำปาง ลำพูน</p>
            </div>

            <h5 class="header-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้รับการประเมิน</h5>
            <div class="row mb-3">
                <div class="col-6">
                    <strong>ชื่อ-นามสกุล:</strong>
                    <?php echo $info['t_prefix'] . $info['t_fname'] . ' ' . $info['t_lname']; ?>
                </div>
                <div class="col-6">
                    <strong>สังกัด (โรงเรียน):</strong>
                    <?php echo $info['t_school']; ?>
                </div>
                <div class="col-6">
                    <strong>ตำแหน่ง/วิทยฐานะ:</strong>
                    <?php echo $info['t_position']; ?>
                </div>
                <div class="col-6">
                    <strong>กลุ่มสาระการเรียนรู้:</strong>
                    <?php echo $info['learning_group'] ?? '-'; ?>
                </div>
            </div>

            <h5 class="header-title"><i class="fas fa-user-check"></i> ข้อมูลผู้ประเมิน</h5>
            <div class="row mb-3">
                <div class="col-6">
                    <strong>ชื่อ-นามสกุล:</strong>
                    <?php echo $info['s_prefix'] . $info['s_fname'] . ' ' . $info['s_lname']; ?>
                </div>
                <div class="col-6">
                    <strong>วิทยฐานะ/ตำแหน่ง:</strong>
                    <?php echo $info['s_rank']; ?> (<?php echo $info['s_office']; ?>)
                </div>
            </div>

            <h5 class="header-title"><i class="fas fa-clipboard-list"></i> ข้อมูลการประเมินจุดเน้น (Quick Win)</h5>
            <div class="info-box">
                <div class="row mb-2">
                    <div class="col-12">
                        <strong>หัวข้อจุดเน้นที่เลือก:</strong><br>
                        <span class="text-primary fw-bold" style="font-size: 1.1rem;">
                            <?php echo !empty($info['topic']) ? $info['topic'] : '- ไม่ระบุ -'; ?>
                        </span>
                    </div>
                </div>

                <!-- <?php if (!empty($info['option_other'])): ?>
                    <div class="row mb-2">
                        <div class="col-12">
                            <strong>รายละเอียดเพิ่มเติม (Other Options):</strong><br>
                            <?php echo nl2br(htmlspecialchars($info['option_other'])); ?>
                        </div>
                    </div>
                <?php endif; ?> -->

                <div class="row">
                    <div class="col-12">
                        <strong>วันที่ประเมิน:</strong> <?php echo date('d/m/Y', strtotime($info['supervision_date'])); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($info['satisfaction_suggestion'])): ?>
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-dark fw-bold">
                        <i class="fas fa-lightbulb"></i> ข้อเสนอแนะ
                    </div>
                    <div class="card-body">
                        <p class="card-text">
                            <?php echo nl2br(htmlspecialchars($info['satisfaction_suggestion'])); ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="text-center mt-5 no-print">
                <form method="POST" action="session_details.php" style="display:inline;">
                    <input type="hidden" name="teacher_pid" value="<?php echo htmlspecialchars($t_id); ?>">
                    <button type="submit" class="btn btn-danger me-2">
                        <i class="fas fa-arrow-left"></i> ย้อนกลับ
                    </button>
                </form>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> พิมพ์รายงาน
                </button>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>