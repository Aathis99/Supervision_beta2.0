<?php
session_start(); // ⭐️ เริ่มต้น session เพื่อใช้งาน $_SESSION
require_once 'config/db_connect.php'; // ⭐️ เรียกใช้ไฟล์เชื่อมต่อฐานข้อมูล

// ตรวจสอบว่านี่เป็นการเข้าชมครั้งแรกในเซสชันนี้หรือไม่
if (!isset($_SESSION['visited'])) {
    // ถ้ามี session login ค้างอยู่จากครั้งก่อน ให้เคลียร์ออก
    unset($_SESSION['is_logged_in']);
    unset($_SESSION['user_id']); // หากมีการใช้ user_id ใน session ก็ควรเคลียร์ด้วย
    $_SESSION['visited'] = true; // ตั้งค่าว่าเคยเข้าชมแล้วในเซสชันนี้
}

// รับค่าการค้นหา (ถ้ามี)
$search_name = $_GET['search_name'] ?? '';
$results = []; // ⭐️ เตรียม array สำหรับเก็บผลลัพธ์

// --- START: ดึงข้อมูลสำหรับ Dashboard ที่จะส่งให้ learning_group_chart.php ---
// ⭐️ SQL สำหรับดึงข้อมูลจำนวนครูที่ถูกนิเทศในแต่ละกลุ่มสาระฯ
$sql_lg_supervision = "
    SELECT 
        t.learning_group,
        COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count
    FROM supervision_sessions ss
    JOIN teacher t ON ss.teacher_t_pid = t.t_pid
    WHERE t.learning_group IS NOT NULL AND t.learning_group != ''
    GROUP BY t.learning_group
    ORDER BY supervised_teacher_count DESC;
";

$lg_supervision_data = []; // กำหนดค่าเริ่มต้นเป็น array ว่าง
$result_lg = $conn->query($sql_lg_supervision);
if ($result_lg) {
    $lg_supervision_data = $result_lg->fetch_all(MYSQLI_ASSOC);
}

// เตรียมข้อมูลสำหรับ Chart.js
$lg_chart_labels = json_encode(array_column($lg_supervision_data, 'learning_group'));
$lg_chart_values = json_encode(array_column($lg_supervision_data, 'supervised_teacher_count'));

// 🎨 กำหนดสีใน PHP เพื่อใช้ในกราฟ
$background_colors = [
    'rgba(255, 193, 7, 0.7)', 'rgba(23, 162, 184, 0.7)', 'rgba(40, 167, 69, 0.7)',
    'rgba(108, 117, 125, 0.7)', 'rgba(220, 53, 69, 0.7)', 'rgba(75, 192, 192, 0.7)',
    'rgba(153, 102, 255, 0.7)', 'rgba(255, 159, 64, 0.7)', 'rgba(46, 204, 113, 0.7)',
    'rgba(255, 99, 132, 0.7)', 'rgba(54, 162, 235, 0.7)'
];
$js_background_colors = json_encode($background_colors);
// --- END: ดึงข้อมูลสำหรับ Dashboard ---

// SQL พื้นฐานสำหรับดึงข้อมูล
// ⭐️ ดึงข้อมูลที่จำเป็นตามภาพ: วันที่, ชื่อครู, โรงเรียน, ชื่อผู้นิเทศ, รายวิชา, เวลา, ปุ่มดูรายงาน
// ⭐️ ปรับปรุง SQL: ใช้ Subquery เพื่อหาการนิเทศครั้งล่าสุดของแต่ละคน แล้วค่อย JOIN ข้อมูลที่เหลือ
$sql = "SELECT
            t.t_pid AS teacher_t_pid,
            CONCAT(t.PrefixName, t.fname, ' ', t.lname) AS teacher_full_name,
            t.adm_name AS teacher_position,
            s_school.SchoolName AS t_school,
            (SELECT COUNT(*) FROM supervision_sessions WHERE teacher_t_pid = t.t_pid) AS supervision_count
        FROM
            (
                SELECT 
                    teacher_t_pid, 
                    MAX(supervision_date) AS max_date
                FROM supervision_sessions
                GROUP BY teacher_t_pid
            ) AS latest_sessions
        JOIN
            supervision_sessions ss_latest ON latest_sessions.teacher_t_pid = ss_latest.teacher_t_pid AND latest_sessions.max_date = ss_latest.supervision_date
        LEFT JOIN
            teacher t ON ss_latest.teacher_t_pid = t.t_pid
        LEFT JOIN
            school s_school ON t.school_id = s_school.school_id
        ";

$params = [];
$types = '';

// ⭐️ เงื่อนไขการค้นหา: จะทำการค้นหาก็ต่อเมื่อ $search_name ไม่ใช่ค่าว่างเท่านั้น ⭐️
if (!empty($search_name)) {
    // จัดการกับช่องว่างที่อาจมีหลายช่องติดกัน ให้เหลือเพียงช่องว่างเดียว
    $normalized_search = preg_replace('/\s+/', ' ', $search_name);
    // กรณีมีการค้นหา: เพิ่ม WHERE clause
    $search_term = "%" . $normalized_search . "%";
    $sql .= " WHERE CONCAT(t.fname, ' ', t.lname) LIKE ? OR t.adm_name LIKE ?";
    $params = [$search_term, $search_term];
    $types = "ss";
}

// ⭐️ เรียงลำดับจากวันที่ล่าสุด ⭐️
$sql .= " ORDER BY latest_sessions.max_date DESC";
// ⭐️ เพิ่มเงื่อนไข: ถ้าไม่มีการค้นหา ให้แสดงแค่ 5 รายการล่าสุด
if (empty($search_name)) {
    $sql .= " LIMIT 5";
}

// เตรียมและดำเนินการสอบถาม
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

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
    <title>ประวัติการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- ⭐️ เพิ่ม Chart.js และ Datalabels Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        /* สไตล์สำหรับตาราง (เพื่อให้อ่านง่ายขึ้น) */
        .table-custom th {
            background-color: #007bff;
            color: white;
            vertical-align: middle;
        }

        .table-custom td {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    <div class="container mt-5">

        <div class="card shadow-lg p-4">
            <!-- ⭐️ ช่องสำหรับใส่ภาพ Banner ⭐️ -->
            <div class="text-center mb-4">
                <!-- ❗️❗️ ให้เปลี่ยน src เป็น path หรือ URL ของรูปภาพ Banner ที่ต้องการ ❗️❗️ -->
                <img src="images\banner001.jpg" class="img-fluid rounded" alt="แบนเนอร์ประวัติการนิเทศ">
            </div>
            
            <!-- ⭐️ ส่วนของ Dashboard ที่เพิ่มเข้ามา -->
            
            <div class="row mb-5">
                <div class="col-12">
                    <?php 
                    // ตรวจสอบว่ามีข้อมูลสำหรับแสดงกราฟหรือไม่ ก่อนที่จะ include
                    if (!empty($lg_supervision_data)) {
                        include 'graphs/learning_group_chart.php'; 
                    }
                    ?>
                </div>
            </div>
           

            <form method="GET" action="index.php#search-results" class="mb-4" id="search-form">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="ค้นหาด้วยชื่อครู หรือ ตำแหน่ง..." name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
                    <a href="index.php#search-results" class="btn btn-secondary" title="แสดงรายการทั้งหมด">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
                <small class="form-text text-muted">หากไม่กรอกข้อมูลและกดปุ่ม 'ค้นหา' จะแสดงรายการทั้งหมด</small>
            </form>

            <!-- ⭐️ 2. เปลี่ยนปุ่มตามสถานะการล็อกอิน -->
            <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true): ?>
                <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
                    <!-- ส่วนของผู้นิเทศ (เมื่อล็อกอิน) -->
                    <a href="supervision_start.php" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> บันทึกการนิเทศ
                    </a>
                    <a href="graphs/satisfaction_dashboard.php" class="btn btn-info">
                        <i class="fas fa-chart-pie"></i> Dashboard
                    </a>
                    <a href="logout.php" class="btn btn-danger">
                        <i class="fas fa-sign-out-alt"></i> ออกจากระบบ
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-end align-items-center mb-3">
                    <a href="login.php" class="btn btn-outline-primary">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                </div>
            <?php endif; ?>

            <div class="table-responsive" id="search-results">
                <table class="table table-striped table-hover table-custom align-middle">
                    <thead>
                        <tr>
                            <th scope="col">ชื่อผู้รับนิเทศ</th>
                            <th scope="col">โรงเรียน</th>
                            <th scope="col">ตำแหน่ง</th>
                            <th scope="col" class="text-center">จำนวนครั้งที่นิเทศ</th>
                            <th scope="col" class="text-center" style="width: 10%;">เพิ่มเติม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)) : ?>
                            <tr>
                                <td colspan="5" class="text-center text-danger fw-bold">
                                    <?php echo !empty($search_name) ? "ไม่พบข้อมูลการนิเทศที่ตรงกับการค้นหา: \"" . htmlspecialchars($search_name) . "\"" : "ไม่พบประวัติการนิเทศที่บันทึกไว้ในระบบ"; ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($results as $row) : ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['teacher_full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['t_school']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher_position']); ?></td>
                                    <td class="text-center">
                                        <?php echo htmlspecialchars($row['supervision_count']); ?>
                                    </td>
                                    <td class="text-center">
                                        <form method="POST" action="session_details.php" style="display:inline;">
                                            <input type="hidden" name="teacher_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                            <button type="submit" class="btn btn-sm btn-info" title="ดูประวัติการนิเทศทั้งหมดของครูท่านนี้">
                                                <i class="fas fa-search-plus"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // ⭐️ เพิ่ม script สำหรับแสดง popup แจ้งเตือน
        // และลงทะเบียน Datalabels Plugin ให้ Chart.js รู้จัก
        Chart.register(ChartDataLabels);


        document.addEventListener('DOMContentLoaded', function() {
            <?php
            if (isset($_SESSION['flash_message'])) {
                // แสดง alert ด้วยข้อความใน session
                echo "alert('" . addslashes($_SESSION['flash_message']) . "');";
                // ล้าง session ออกไปหลังจากแสดงผลแล้ว เพื่อไม่ให้แสดงซ้ำเมื่อรีเฟรช
                unset($_SESSION['flash_message']);
            }
            ?>
        });
    </script>
</body>

</html>