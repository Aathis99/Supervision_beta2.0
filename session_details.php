<?php
// ไฟล์: session_details.php
// ⭐️ 1. เริ่ม Session แต่ไม่บังคับล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php';

// ⭐️ แก้ไข: ตรวจสอบว่ามีการล็อกอินหรือไม่ (ถ้าล็อกอินอยู่ ให้ถือว่าเป็นผู้นิเทศ)
$is_supervisor = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

// 1. ตรวจสอบว่ามี teacher_pid ส่งมาหรือไม่
// ⭐️ แก้ไข: เปลี่ยนจาก $_GET เป็น $_POST ⭐️
if (!isset($_POST['teacher_pid']) || empty($_POST['teacher_pid'])) {
    die("ไม่พบรหัสประจำตัวผู้รับการนิเทศ");
}

$teacher_pid = $_POST['teacher_pid']; // ⭐️ แก้ไข: เปลี่ยนจาก $_GET เป็น $_POST ⭐️
$results = [];
$teacher_info = null;

// 2. ดึงข้อมูลพื้นฐานของครู (ชื่อ และ โรงเรียน) จากข้อมูลการนิเทศล่าสุด
//    เพื่อให้แน่ใจว่ามีข้อมูลแสดง แม้ t_pid จะไม่มีในตาราง teacher โดยตรง
$stmt_teacher = $conn->prepare(
    "SELECT 
        CONCAT(t.PrefixName, t.fname, ' ', t.lname) AS teacher_full_name, 
        s.SchoolName,
        t.adm_name AS teacher_position,
        vtcg.core_learning_group AS learning_group
     FROM supervision_sessions ss
     LEFT JOIN teacher t ON ss.teacher_t_pid = t.t_pid
     LEFT JOIN school s ON t.school_id = s.school_id
     LEFT JOIN view_teacher_core_groups vtcg ON t.t_pid = vtcg.t_pid
     WHERE ss.teacher_t_pid = ?
     ORDER BY ss.supervision_date DESC
     LIMIT 1"
);
$stmt_teacher->bind_param("s", $teacher_pid);
$stmt_teacher->execute();
$result_teacher = $stmt_teacher->get_result();

if ($result_teacher->num_rows > 0) {
    $teacher_info = $result_teacher->fetch_assoc();
}
$stmt_teacher->close();


// 3. ดึงประวัติการนิเทศทั้งหมดของครูคนนี้ โดยเรียงจากล่าสุดไปเก่าสุด
$sql = "SELECT
            ss.id AS session_id,
            ss.supervision_date,
            ss.inspection_time,
            CONCAT(sp.PrefixName, sp.fname, ' ', sp.lname) AS supervisor_full_name,
            ss.satisfaction_submitted
        FROM
            supervision_sessions ss
        LEFT JOIN
            supervisor sp ON ss.supervisor_p_id = sp.p_id
        WHERE
            ss.teacher_t_pid = ?
        ORDER BY
            ss.supervision_date DESC, ss.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $teacher_pid);
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
    <title>ประวัติการนิเทศของ <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <h2 class="card-title text-center mb-4"><i class="fas fa-user-clock"></i> ประวัติการนิเทศ</h2>

            <div class="row mb-4">
                <div class="col-md-6">
                    <h5><strong>ผู้รับการนิเทศ:</strong> <?php echo htmlspecialchars($teacher_info['teacher_full_name']); ?></h5>
                </div>
                <div class="col-md-6">
                    <h5><strong>โรงเรียน:</strong> <?php echo htmlspecialchars($teacher_info['SchoolName']); ?></h5>
                </div>
                <div class="col-md-6">
                    <h5><strong>ตำแหน่ง:</strong> <?php echo htmlspecialchars($teacher_info['teacher_position']); ?></h5>
                </div>
                <div class="col-md-6">
                    <h5><strong>กลุ่มสาระฯ:</strong> <?php echo htmlspecialchars($teacher_info['learning_group']); ?></h5>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-primary">
                        <tr class="text-center">
                            <th scope="col" style="width: 20%;">วันที่และเวลา</th>
                            <th scope="col" style="width: 5%;">ครั้งที่</th>
                            <th scope="col" style="width: 20%;">ผู้นิเทศ</th>
                            <th scope="col" style="width: 12%;">รายงาน</th>
                            <?php if (!$is_supervisor): // ⭐️ ถ้าไม่ใช่ผู้นิเทศ ให้แสดงคอลัมน์ประเมิน 
                            ?>
                                <th scope="col" style="width: 12%;">ประเมิน</th>
                            <?php endif; ?>
                            <th scope="col" style="width: 12%;">เกียรติบัตร</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)) : ?>
                            <tr>
                                <?php // ⭐️ ปรับ colspan ตามการแสดงผล 
                                ?>
                                <td colspan="<?php echo $is_supervisor ? '5' : '6'; ?>" class="text-center text-danger fw-bold">ไม่พบประวัติการนิเทศสำหรับครูท่านนี้</td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ($results as $row) : ?>
                                <tr class="text-center">
                                    <td><?php echo (new DateTime($row['supervision_date']))->format('d/m/Y H:i'); ?> น.</td>
                                    <td><?php echo htmlspecialchars($row['inspection_time']); ?></td>
                                    <td><?php echo htmlspecialchars($row['supervisor_full_name']); ?></td>
                                    <td>
                                        <?php // ปุ่มดูรายงาน 
                                        ?>
                                        <form method="POST" action="supervision_report.php" style="display:inline;">
                                            <input type="hidden" name="session_id" value="<?php echo $row['session_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-primary" title="ดูรายงานผลการนิเทศ"><i class="fas fa-file-alt"></i> ดูรายงาน</button>
                                        </form>
                                    </td>
                                    <?php if (!$is_supervisor): // ⭐️ ถ้าไม่ใช่ผู้นิเทศ ให้แสดงคอลัมน์ประเมิน 
                                    ?>
                                        <td>
                                            <?php // ปุ่มประเมินจะแสดงเมื่อไม่ใช่ผู้นิเทศเท่านั้น 
                                            ?>
                                            <?php if ($row['satisfaction_submitted'] == 0): // ถ้ายังไม่ประเมิน (0) ให้แสดงปุ่มประเมิน 
                                            ?>
                                                <a href="satisfaction_summary.php?session_id=<?php echo $row['session_id']; ?>" class="btn btn-sm btn-success" title="ประเมินความพึงพอใจ"><i class="fas fa-smile-beam"></i> ประเมิน</a>
                                            <?php else: // ถ้าประเมินแล้ว (1) ให้แสดงปุ่มที่กดไม่ได้ 
                                            ?>
                                                <button class="btn btn-sm btn-secondary" disabled title="ประเมินความพึงพอใจแล้ว"><i class="fas fa-check-circle"></i> ประเมินแล้ว</button>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <?php // --- ปุ่มพิมพ์เกียรติบัตร (กลับไปใช้ไอคอน) --- 
                                        ?>
                                        <?php if ($row['satisfaction_submitted'] == 1): // ถ้าประเมินแล้ว (1) ให้กดพิมพ์ได้ 
                                        ?>
                                            <form method="POST" action="certificate.php" style="display:inline;" target="_blank">
                                                <input type="hidden" name="session_id" value="<?php echo $row['session_id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success" title="พิมพ์เกียรติบัตร"><i class="fas fa-award"></i></button>
                                            </form>
                                        <?php else: // ถ้ายังไม่ประเมิน (0) ให้แสดงปุ่มที่กดไม่ได้ 
                                        ?>
                                            <button class="btn btn-sm btn-danger" disabled title="ต้องให้ผู้รับการนิเทศประเมินความพึงพอใจก่อน"><i class="fas fa-award"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="text-center mt-4">
                <a href="history.php" class="btn btn-secondary"><i class="fas fa-chevron-left"></i> กลับไปหน้าประวัติ</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>