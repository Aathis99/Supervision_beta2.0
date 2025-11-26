<?php
// ไฟล์: satisfaction_dashboard.php
require_once '../config/db_connect.php';

$form_titles = [
    1 => "แบบบันทึกการจัดการเรียนรู้และการจัดการชั้นเรียน",
    3 => "แบบกรอกข้อมูลผู้รับการนิเทศนโยบายและจุดเน้นของสำนักงานเขตพื้นที่ (Quick Win)",
];

$form_type = isset($_GET['form_type']) ? (int)$_GET['form_type'] : 1;
$page_title = $form_titles[$form_type] ?? "สรุปผลความพึงพอใจ";

// --- การดึงข้อมูลสำหรับกราฟความพึงพอใจ (เลือกตาม form_type) ---
$satisfaction_data = [];
$sql = "";

if ($form_type == 3) {
    // --- 3. การดึงข้อมูลสำหรับ "Quick Win" (Form 3) ---
    // ดึงข้อมูลจาก view_quick_win_dashboard และนับจำนวนครั้งตามโรงเรียน
    $sql = "SELECT
                school_name AS SchoolName,
                COUNT(*) AS supervision_count
            FROM
                view_quick_win_dashboard_all_events
            GROUP BY
                school_name
            ORDER BY
                supervision_count DESC";
} else { // Default to form_type = 1 (หรืออื่นๆ)
    $sql = "SELECT
                q.id AS question_id, 
                q.question_text,
                AVG(ans.rating) AS average_score,
                COUNT(ans.id) AS response_count
            FROM
                satisfaction_questions q
            LEFT JOIN
                satisfaction_answers ans ON q.id = ans.question_id 
            GROUP BY
                q.id, q.question_text
            ORDER BY
                q.display_order ASC";
}

if ($sql) {
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $item_number = 1;
        while ($row = $result->fetch_assoc()) {
            if ($form_type == 1) {
                $row['question_text_with_number'] = $item_number . '. ' . $row['question_text'];
                $item_number++;
            }
            $satisfaction_data[] = $row;
        }
    }
}

// --- ส่วนดึงข้อมูลสำหรับกราฟสรุปต่างๆ ---
$school_supervision_data = [];
$position_supervision_data = [];
$lg_supervised_teacher_data = [];

if ($form_type == 3) {
    // สำหรับ Quick Win: ดึงข้อมูลสรุปจาก view_quick_win_dashboard_all_events
    // 1. สรุปรายโรงเรียน (ใช้ข้อมูลเดียวกับกราฟหลัก)
    $school_supervision_data = $satisfaction_data;

    // 2. สรุปตามตำแหน่ง
    $sql_position = "SELECT position_rank AS teacher_position, COUNT(*) AS supervised_teacher_count FROM view_quick_win_dashboard_all_events WHERE position_rank IS NOT NULL AND position_rank COLLATE utf8_unicode_ci != '' GROUP BY position_rank ORDER BY supervised_teacher_count DESC";
    $result_pos = $conn->query($sql_position);
    if ($result_pos) $position_supervision_data = $result_pos->fetch_all(MYSQLI_ASSOC);

    // 3. สรุปตามกลุ่มสาระ
    $sql_lg = "SELECT core_learning_group AS learning_group, COUNT(*) AS supervised_teacher_count FROM view_quick_win_dashboard_all_events WHERE core_learning_group IS NOT NULL AND core_learning_group COLLATE utf8mb4_unicode_ci != '' GROUP BY core_learning_group ORDER BY supervised_teacher_count DESC";
    $result_lg = $conn->query($sql_lg);
    if ($result_lg) $lg_supervised_teacher_data = $result_lg->fetch_all(MYSQLI_ASSOC);

} else {
    // สำหรับ Form 1: ดึงข้อมูลสรุปจากตาราง supervision_sessions
    // 1. สรุปรายโรงเรียน
    $sql_school_supervision = "SELECT
                                    s.SchoolName,
                                    COUNT(ss.id) AS supervision_count
                                FROM
                                    supervision_sessions ss
                                JOIN
                                    teacher t ON ss.teacher_t_pid = t.t_pid
                                JOIN
                                    school s ON t.school_id = s.school_id
                                GROUP BY
                                    s.SchoolName
                                HAVING
                                    COUNT(ss.id) > 0
                                ORDER BY
                                    supervision_count DESC";
    $result_school = $conn->query($sql_school_supervision);
    if ($result_school) $school_supervision_data = $result_school->fetch_all(MYSQLI_ASSOC);

    // 2. สรุปตามตำแหน่ง
    $sql_position_supervision = "SELECT
                                    t.adm_name AS teacher_position,
                                    COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count
                                FROM
                                    supervision_sessions ss
                                JOIN
                                    teacher t ON ss.teacher_t_pid = t.t_pid
                                WHERE
                                    t.adm_name IS NOT NULL AND t.adm_name COLLATE utf8_unicode_ci != ''
                                GROUP BY
                                    t.adm_name
                                ORDER BY
                                    supervised_teacher_count DESC";
    $result_position = $conn->query($sql_position_supervision);
    if ($result_position) $position_supervision_data = $result_position->fetch_all(MYSQLI_ASSOC);

    // 3. สรุปตามกลุ่มสาระ
    $sql_lg_supervised_teachers = "SELECT
                                        vtcg.core_learning_group AS learning_group,
                                        COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count
                                    FROM
                                        supervision_sessions ss
                                    JOIN
                                        view_teacher_core_groups vtcg ON ss.teacher_t_pid = vtcg.t_pid
                                WHERE vtcg.core_learning_group IS NOT NULL AND vtcg.core_learning_group COLLATE utf8mb4_unicode_ci != ''
                                    GROUP BY
                                        vtcg.core_learning_group
                                    ORDER BY
                                        supervised_teacher_count DESC";
    $result_lg = $conn->query($sql_lg_supervised_teachers);
    if ($result_lg) $lg_supervised_teacher_data = $result_lg->fetch_all(MYSQLI_ASSOC);
}

$conn->close();


// เตรียมข้อมูลสำหรับ Chart.js
// สำหรับกราฟวงกลม (ใช้ข้อมูล Form 1)
if ($form_type == 1) {
    $chart_labels = json_encode(array_column($satisfaction_data, 'question_text_with_number'));
    $scores = array_map(fn($score) => $score ?? 0, array_column($satisfaction_data, 'average_score'));
    $chart_values = json_encode($scores);
} else { // สำหรับ form_type = 3
    $chart_labels = json_encode(array_column($satisfaction_data, 'SchoolName'));
    $chart_values = json_encode(array_column($satisfaction_data, 'supervision_count'));
}

// เตรียมข้อมูลสำหรับกราฟสรุปการนิเทศแต่ละโรงเรียน
$school_chart_labels = json_encode(array_column($school_supervision_data, 'SchoolName'));
$school_chart_values = json_encode(array_column($school_supervision_data, 'supervision_count'));

// เตรียมข้อมูลสำหรับกราฟสรุปตามตำแหน่ง (เพิ่มใหม่)
$position_chart_labels = json_encode(array_column($position_supervision_data, 'teacher_position'));
$position_chart_values = json_encode(array_column($position_supervision_data, 'supervised_teacher_count'));

// เตรียมข้อมูลสำหรับกราฟสรุปจำนวนครูที่ได้รับการนิเทศตามกลุ่มสาระ (แก้ไขตาม request)
$lg_chart_labels = json_encode(array_column($lg_supervised_teacher_data, 'learning_group'));
$lg_chart_values = json_encode(array_column($lg_supervised_teacher_data, 'supervised_teacher_count'));

// 🎨 Define colors in PHP to be used in both legend and chart
$background_colors = [
    'rgba(255, 99, 132, 0.7)',
    'rgba(54, 162, 235, 0.7)',
    'rgba(255, 206, 86, 0.7)',
    'rgba(75, 192, 192, 0.7)',
    'rgba(153, 102, 255, 0.7)',
    'rgba(255, 159, 64, 0.7)',
    'rgba(46, 204, 113, 0.7)',
    'rgba(231, 76, 60, 0.7)',
    'rgba(142, 68, 173, 0.7)',
    'rgba(26, 188, 156, 0.7)',
    'rgba(241, 196, 15, 0.7)',
    'rgba(52, 73, 94, 0.7)'
];
$js_background_colors = json_encode($background_colors);

?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $page_title; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- ⭐️ 4. เพิ่มปลั๊กอินสำหรับแสดงข้อมูลบนกราฟ (Datalabels) -->
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <style>
        body {
            background-image: url('../images/bg001.jpg');
            background-size: cover;
            background-repeat: no-repeat;
            background-position: center center;
            background-attachment: fixed;
            /* Fallback color in case the image fails to load */
            background-color: #f8f9fa; 
        }
        .card-header-custom {
            background-color: #17a2b8; /* Bootstrap info color */
            color: white;
        }
        /* ⭐️ สไตล์สำหรับคำอธิบายสัญลักษณ์ที่สร้างเอง */
        .custom-legend .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .custom-legend .legend-color-box {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            border: 1px solid #ddd;
        }
        /* เพิ่มระยะห่างระหว่างการ์ด */
        .chart-card {
            margin-top: 1.5rem;
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <?php
        ?>
        <h1 class="text-center mb-4">Dashboard สรุปผลการนิเทศ</h1>

        <!-- Dropdown สำหรับเลือกฟอร์ม -->
        <div class="row justify-content-center mb-4">
            <div class="col-md-6">
                <div class="input-group">
                    <label class="input-group-text" for="formTypeSelect"><i class="fas fa-chart-pie"></i>&nbsp;เลือกชุดข้อมูล</label>
                    <select class="form-select" id="formTypeSelect" onchange="location = this.value;">
                        <option value="satisfaction_dashboard.php?form_type=1" <?php echo ($form_type == 1) ? 'selected' : ''; ?>><?php echo $form_titles[1]; ?></option>
                        <option value="satisfaction_dashboard.php?form_type=3" <?php echo ($form_type == 3) ? 'selected' : ''; ?>><?php echo $form_titles[3]; ?></option>
                    </select>
                </div>
            </div>
        </div>

        <!-- แถวที่ 1: กราฟหลักตามฟอร์มที่เลือก -->
        <?php if ($form_type == 1): ?>
            <div class="row">
                <div class="col-lg-12 chart-card">
                    <?php $dashboard_data = $satisfaction_data; include 'satisfaction_pie_chart.php'; ?>
                </div>
            </div>
        <?php elseif ($form_type == 3): ?>
            <div class="row">
                <div class="col-lg-12 chart-card">
                    <?php /* ส่งข้อมูลไปที่ไฟล์ใหม่ */ ?>
                    <?php $dashboard_data = $satisfaction_data; include 'quick_win_chart.php'; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ================================================================== -->
        <!-- ส่วนแสดงกราฟสรุปต่างๆ (แสดงทุก form_type แต่ข้อมูลข้างในต่างกัน) -->
        <!-- ================================================================== -->

        <!-- แถวที่ 2: กราฟกลุ่มสาระ -->
        <div class="row">
            <div class="col-lg-12 chart-card">
                <?php $lg_supervision_data = $lg_supervised_teacher_data; include 'learning_group_chart.php'; ?>
            </div>
        </div>

        <!-- แถวที่ 3: กราฟตำแหน่ง -->
        <div class="row">
            <div class="col-lg-12 chart-card">
                <?php include 'position_supervision_chart.php'; ?>
            </div>
        </div>

        <!-- แถวที่ 4: กราฟรายโรงเรียน (สำหรับ form_type=1) -->
        <?php if ($form_type == 1): ?>
            <?php include 'school_supervision_chart.php'; ?>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ลงทะเบียนปลั๊กอิน Datalabels กับ Chart.js ให้ทำงานกับทุกกราฟ
        Chart.register(ChartDataLabels);
    </script>
</body>
</html>
        ?>