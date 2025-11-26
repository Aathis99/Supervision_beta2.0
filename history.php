<?php
// ไฟล์: history.php
// ⭐️ 1. เริ่ม Session แต่ไม่บังคับล็อกอิน
// ทำให้ทุกคนสามารถเข้าดูหน้านี้ได้
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php';

// ตรวจสอบค่า search_name: ถ้ามีค่าเข้ามา ให้ Trim ถ้าไม่มีค่า (หรือเข้าหน้าครั้งแรก) ให้เป็นค่าว่าง
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$results = [];

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
            <h2 class="card-title text-center mb-4"><i class="fas fa-history"></i> ประวัติการนิเทศ</h2>

            <form method="GET" action="history.php" class="mb-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="ค้นหาด้วยชื่อครู หรือ ตำแหน่ง..." name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
                    <a href="history.php" class="btn btn-secondary" title="แสดงรายการทั้งหมด">
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

            <div class="table-responsive">
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