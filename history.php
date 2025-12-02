<?php
// ไฟล์: history.php
// ⭐️ 1. เริ่ม Session 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php';

// ตรวจสอบค่า search_name
$search_name = isset($_GET['search_name']) ? trim($_GET['search_name']) : '';
$results = [];

// ⭐️ SQL ใหม่: รวมข้อมูลจาก supervision_sessions และ quick_win ⭐️
// หลักการ:
// 1. เลือกครู (teacher) ที่มีประวัติอยู่ในตาราง supervision_sessions หรือ quick_win
// 2. ใช้ Subquery นับจำนวนแยกแต่ละประเภท และหาผลรวม
// 3. ใช้ Subquery หาวันที่ล่าสุดของแต่ละประเภท แล้วใช้ GREATEST เพื่อหาอันที่ล่าสุดที่สุดมาเรียงลำดับ

$sql = "SELECT 
            t.t_pid AS teacher_t_pid,
            CONCAT(IFNULL(t.PrefixName,''), t.fname, ' ', t.lname) AS teacher_full_name,
            t.adm_name AS teacher_position,
            s_school.SchoolName AS t_school,
            
            -- นับจำนวนการนิเทศปกติ
            (SELECT COUNT(*) FROM supervision_sessions WHERE teacher_t_pid = t.t_pid) AS count_normal,
            
            -- นับจำนวน Quick Win
            (SELECT COUNT(*) FROM quick_win WHERE t_id = t.t_pid) AS count_quickwin,
            
            -- หาวันที่ล่าสุด (เปรียบเทียบระหว่าง ปกติ กับ Quick Win)
            GREATEST(
                IFNULL((SELECT MAX(supervision_date) FROM supervision_sessions WHERE teacher_t_pid = t.t_pid), '0000-00-00'),
                IFNULL((SELECT MAX(supervision_date) FROM quick_win WHERE t_id = t.t_pid), '0000-00-00')
            ) AS latest_date

        FROM teacher t
        LEFT JOIN school s_school ON t.school_id = s_school.school_id
        WHERE 
            -- เงื่อนไข: ต้องมีประวัติอย่างใดอย่างหนึ่ง
            (
                t.t_pid IN (SELECT teacher_t_pid FROM supervision_sessions) 
                OR 
                t.t_pid IN (SELECT t_id FROM quick_win)
            )
        ";

$params = [];
$types = '';

// ⭐️ เงื่อนไขการค้นหา ⭐️
if (!empty($search_name)) {
    $normalized_search = preg_replace('/\s+/', ' ', $search_name);
    $search_term = "%" . $normalized_search . "%";
    
    // ค้นหาจากชื่อ หรือ ตำแหน่ง
    $sql .= " AND (CONCAT(IFNULL(t.PrefixName,''), t.fname, ' ', t.lname) LIKE ? OR t.adm_name LIKE ?)";
    $params = [$search_term, $search_term];
    $types = "ss";
}

// ⭐️ เรียงลำดับจากวันที่ล่าสุด (ไม่ว่าจะมาจากตารางไหน) ⭐️
$sql .= " ORDER BY latest_date DESC";

// ⭐️ จำกัดผลลัพธ์ ⭐️
if (empty($search_name)) {
    $sql .= " LIMIT 10"; // ปรับเป็น 10 รายการเพื่อให้เห็นข้อมูลชัดขึ้น (เดิม 5)
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
    <title>ประวัติการนิเทศ (รวม Quick Win)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .table-custom th {
            background-color: #007bff;
            color: white;
            vertical-align: middle;
        }
        .table-custom td {
            vertical-align: middle;
        }
        .badge-qw {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <h2 class="card-title text-center mb-4">
                <i class="fas fa-history"></i> ประวัติการนิเทศ และ Quick Win
            </h2>

            <form method="GET" action="history.php" class="mb-4">
                <div class="input-group">
                    <input type="text" class="form-control" placeholder="ค้นหาด้วยชื่อครู หรือ ตำแหน่ง..." name="search_name" value="<?php echo htmlspecialchars($search_name); ?>">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i> ค้นหา</button>
                    <a href="history.php" class="btn btn-secondary" title="แสดงรายการทั้งหมด">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
                <small class="form-text text-muted">แสดงข้อมูลล่าสุดรวมทั้งการนิเทศปกติและ Quick Win</small>
            </form>

            <?php if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true): ?>
                <div class="d-flex justify-content-end align-items-center mb-3 gap-2">
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
                            <th scope="col" class="text-center">ประวัติการนิเทศ (ครั้ง)</th>
                            <!-- <th scope="col" class="text-center">วันที่ล่าสุด</th> -->
                            <th scope="col" class="text-center" style="width: 10%;">เพิ่มเติม</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)) : ?>
                            <tr>
                                <td colspan="6" class="text-center text-danger fw-bold">
                                    <?php echo !empty($search_name) ? "ไม่พบข้อมูลที่ตรงกับการค้นหา: \"" . htmlspecialchars($search_name) . "\"" : "ไม่พบประวัติการนิเทศในระบบ"; ?>
                                </td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($results as $row) : 
                                // คำนวณผลรวม
                                $total_count = $row['count_normal'] + $row['count_quickwin'];
                                // จัดรูปแบบวันที่
                                $show_date = ($row['latest_date'] == '0000-00-00') ? '-' : date('d/m/Y', strtotime($row['latest_date']));
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['teacher_full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['t_school']); ?></td>
                                    <td><?php echo htmlspecialchars($row['teacher_position']); ?></td>
                                    
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill fs-6"><?php echo $total_count; ?></span>
                                        <br>
                                        <small class="text-muted" style="font-size: 0.8rem;">
                                            (ปกติ: <?php echo $row['count_normal']; ?>, QW: <?php echo $row['count_quickwin']; ?>)
                                        </small>
                                    </td>

                                    <!-- <td class="text-center"><?php echo $show_date; ?></td> -->

                                    <td class="text-center">
                                        <form action="session_details.php" method="POST" style="display: inline;">
                                            <input type="hidden" name="teacher_pid" value="<?php echo $row['teacher_t_pid']; ?>">
                                            <button type="submit" class="btn btn-sm btn-info" title="ดูรายละเอียดประวัติทั้งหมด">
                                                <i class="fas fa-search-plus"></i> รายละเอียด
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
        document.addEventListener('DOMContentLoaded', function() {
            <?php
            if (isset($_SESSION['flash_message'])) {
                echo "alert('" . addslashes($_SESSION['flash_message']) . "');";
                unset($_SESSION['flash_message']);
            }
            ?>
        });
    </script>
</body>
</html>