<?php
// ไฟล์: supervision_report.php
session_start(); // ⭐️ เริ่ม session เพื่อใช้แสดงข้อความ
require_once 'config/db_connect.php';

// ตรวจสอบค่า session_id
if (!isset($_REQUEST['session_id']) || empty($_REQUEST['session_id'])) { // ⭐️ ใช้ $_REQUEST เพื่อรับทั้ง GET และ POST
    die("ไม่พบรหัสการนิเทศ");
}
$session_id = intval($_REQUEST['session_id']);

// --- ส่วนจัดการการลบรูปภาพ (เพิ่มเข้ามาใหม่) ---
$uploadDir = 'uploads/';
if (isset($_GET['delete_image'])) {
    $imageId = filter_var($_GET['delete_image'], FILTER_VALIDATE_INT);

    if ($imageId) {
        try {
            $conn->begin_transaction();
            // ค้นหาชื่อไฟล์จากฐานข้อมูลก่อนลบ (ตรวจสอบว่าเป็นของ session นี้จริง)
            $stmt = $conn->prepare("SELECT file_name FROM images WHERE id = ? AND session_id = ?");
            $stmt->bind_param("ii", $imageId, $session_id);
            $stmt->execute();
            $image = $stmt->get_result()->fetch_assoc();

            if ($image) {
                // ลบไฟล์รูปภาพออกจากเซิร์ฟเวอร์
                $filePath = $uploadDir . $image['file_name'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                // ลบข้อมูลออกจากฐานข้อมูล
                $deleteStmt = $conn->prepare("DELETE FROM images WHERE id = ?");
                $deleteStmt->bind_param("i", $imageId);
                $deleteStmt->execute();
                $conn->commit();
            }
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
    // Redirect กลับมาที่หน้ารายงานเดิมเพื่อเคลียร์ query string
    header("Location: supervision_report.php?session_id=" . $session_id);
    exit();
}

// 1. ดึงข้อมูลการนิเทศ (Supervision Info + Teacher + Supervisor)
// ใช้ JOIN เพื่อดึงข้อมูลจากหลายตารางพร้อมกัน
$sql_info = "SELECT 
                ss.*,
                /* ข้อมูลครู */
                t.PrefixName AS t_prefix, t.fname AS t_fname, t.lname AS t_lname, 
                t.t_pid, t.adm_name AS t_position, vtcg.core_learning_group AS learning_group,
                s_school.SchoolName AS t_school,
                /* ข้อมูลผู้นิเทศ */
                sp.PrefixName AS s_prefix, sp.fname AS s_fname, sp.lname AS s_lname, 
                sp.p_id AS s_pid, sp.RankName AS s_rank, sp.OfficeName AS s_office
            FROM supervision_sessions ss
            LEFT JOIN teacher t ON ss.teacher_t_pid = t.t_pid
            LEFT JOIN view_teacher_core_groups vtcg ON t.t_pid = vtcg.t_pid
            LEFT JOIN school s_school ON t.school_id = s_school.school_id
            LEFT JOIN supervisor sp ON ss.supervisor_p_id = sp.p_id
            WHERE ss.id = ?";

$stmt = $conn->prepare($sql_info);
$stmt->bind_param("i", $session_id);
$stmt->execute();
$result_info = $stmt->get_result();
$info = $result_info->fetch_assoc();

if (!$info) {
    die("ไม่พบข้อมูลการนิเทศสำหรับรหัสนี้");
}

// 2. ดึงคะแนนและข้อค้นพบ (KPI Answers)
$sql_answers = "SELECT 
                    q.question_text, 
                    ans.rating_score, 
                    ans.comment,
                    ind.title AS indicator_title,
                    ind.id AS indicator_id
                FROM kpi_answers ans
                JOIN kpi_questions q ON ans.question_id = q.id
                JOIN kpi_indicators ind ON q.indicator_id = ind.id
                WHERE ans.session_id = ?
                ORDER BY ind.display_order, q.display_order";

$stmt_ans = $conn->prepare($sql_answers);
$stmt_ans->bind_param("i", $session_id);
$stmt_ans->execute();
$result_ans = $stmt_ans->get_result();

// จัดกลุ่มข้อมูลตามตัวชี้วัด
$kpi_data = [];
$total_score = 0;
$count_questions = 0;

while ($row = $result_ans->fetch_assoc()) {
    $kpi_data[$row['indicator_id']]['title'] = $row['indicator_title'];
    $kpi_data[$row['indicator_id']]['questions'][] = $row;

    $total_score += $row['rating_score'];
    $count_questions++;
}

// 3. ดึงข้อเสนอแนะเพิ่มเติม (Suggestions)
$sql_sugg = "SELECT indicator_id, suggestion_text FROM kpi_indicator_suggestions WHERE session_id = ?";
$stmt_sugg = $conn->prepare($sql_sugg);
$stmt_sugg->bind_param("i", $session_id);
$stmt_sugg->execute();
$result_sugg = $stmt_sugg->get_result();

$suggestions = [];
while ($row = $result_sugg->fetch_assoc()) {
    $suggestions[$row['indicator_id']] = $row['suggestion_text'];
}

// 4. ดึงรูปภาพประกอบ (เพิ่มเข้ามาใหม่)
$sql_images = "SELECT id, file_name FROM images WHERE session_id = ? ORDER BY uploaded_on DESC";
$stmt_images = $conn->prepare($sql_images);
$stmt_images->bind_param("i", $session_id);
$stmt_images->execute();
$result_images = $stmt_images->get_result();
$uploadedImages = [];
while ($row = $result_images->fetch_assoc()) {
    $uploadedImages[] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title>รายงานผลการนิเทศ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="css/report_style.css"> </head>

<body>

    <div class="container">
        <div class="report-container" style="position: relative;"> 

            <div class="text-center mb-5" style="margin-bottom: 25px !important;"> 
                <img src="images/logo.png" alt="โลโก้กระทรวงศึกษาธิการ" style="max-width: 80px; margin-bottom: 10px;">
                
                <p style="margin-bottom: 0; font-weight: bold; font-size: 0.95rem;">แบบบันทึกการจัดการเรียนรู้และการจัดการเรียนการสอน ภาคเรียนที่ ๒ ปีการศึกษา ๒๕๖๘</p>
                <p style="margin-bottom: 0; font-weight: bold; font-size: 0.9rem;">สำนักงานเขตพื้นที่การศึกษามัธยมศึกษาลำปาง ลำพูน</p>
            </div>
            
            <h5 class="header-title"><i class="fas fa-user-tie"></i> ข้อมูลผู้รับนิเทศ</h5>
            <div class="row mb-3">
                <div class="col-6">
                    <strong>ชื่อ-นามสกุล:</strong> <?php echo $info['t_prefix'] . $info['t_fname'] . ' ' . $info['t_lname']; ?>
                </div>
                <div class="col-6">
                    <strong>สังกัด (โรงเรียน):</strong> <?php echo $info['t_school']; ?>
                </div>
                <div class="col-6">
                    <strong>ตำแหน่ง/วิทยฐานะ:</strong> <?php echo $info['t_position']; ?>
                </div>
                <div class="col-6">
                    <strong>กลุ่มสาระการเรียนรู้:</strong> <?php echo $info['learning_group'] ?? '-'; ?>
                </div>
            </div>

            <h5 class="header-title"><i class="fas fa-user-check"></i> ข้อมูลผู้นิเทศ</h5>
            <div class="row mb-3">
                <div class="col-6">
                    <strong>ชื่อ-นามสกุล:</strong> <?php echo $info['s_prefix'] . $info['s_fname'] . ' ' . $info['s_lname']; ?>
                </div>
                <div class="col-6">
                    <strong>วิทยฐานะ/ตำแหน่ง:</strong> <?php echo $info['s_rank']; ?> (<?php echo $info['s_office']; ?>)
                </div>
            </div>

            <h5 class="header-title"><i class="fas fa-clipboard-list"></i> ข้อมูลการนิเทศ</h5>
            <div class="info-box">
                <div class="row">
                    <div class="col-6"><strong>รหัสวิชา:</strong> <?php echo $info['subject_code']; ?></div>
                    <div class="col-6"><strong>ชื่อวิชา:</strong> <?php echo $info['subject_name']; ?></div>
                </div>
                <div class="row mt-2"> <div class="col-6"><strong>ครั้งที่นิเทศ:</strong> <?php echo $info['inspection_time']; ?></div>
                    <div class="col-6"><strong>วันที่:</strong> <?php echo date('d/m/Y', strtotime($info['supervision_date'])); ?></div>
                </div>
            </div>

            <h5 class="header-title mt-4"><i class="fas fa-star"></i> ผลการประเมินตามตัวชี้วัด (KPI)</h5>

            <div class="table-responsive">
                <table class="table table-bordered table-kpi">
                    <thead> 
                        <tr>
                            <th style="width: 40%;">ประเด็นคำถาม</th>
                            <th style="width: 10%; text-align: center;">คะแนน</th>
                            <th style="width: 50%;">ข้อค้นพบ / ความคิดเห็น</th>
                        </tr>
                    </thead>
                    
                    <?php foreach ($kpi_data as $ind_id => $data): ?>
                        <tbody style="page-break-inside: avoid;"> 
                            <tr class="indicator-title-row">
                                <td colspan="3"><?php echo $data['title']; ?></td>
                            </tr>

                            <?php foreach ($data['questions'] as $q): ?>
                                <tr>
                                    <td><?php echo $q['question_text']; ?></td>
                                    <td class="text-center">
                                        <?php
                                        $score = $q['rating_score'];
                                        $class = 'badge-2';
                                        if ($score == 3) $class = 'badge-3';
                                        elseif ($score == 1) $class = 'badge-1';
                                        elseif ($score == 0) $class = 'badge-0';
                                        ?>
                                        <span class="badge score-badge <?php echo $class; ?>"><?php echo htmlspecialchars($score); ?></span>
                                    </td>
                                    <td><?php echo !empty($q['comment']) ? nl2br(htmlspecialchars($q['comment'])) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>

                            <?php if (isset($suggestions[$ind_id]) && !empty($suggestions[$ind_id])): ?>
                                <tr>
                                    <td colspan="3" class="kpi-suggestion-cell"> 
                                        <i class="fas fa-comment-dots"></i> <strong>ข้อเสนอแนะเพิ่มเติม:</strong>
                                        <?php echo nl2br(htmlspecialchars($suggestions[$ind_id])); ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody> 
                    <?php endforeach; ?>
                    
                    <tbody>
                        <tr style="background-color: white;"> 
                            <td class="text-end"><strong>คะแนนรวมทั้งหมด</strong></td>
                            <td class="text-center fw-bold"><?php echo $total_score; ?> / <?php echo $count_questions * 3; ?></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($info['overall_suggestion'])): ?>
                <div class="card mt-4 border-info">
                    <div class="card-header bg-info text-dark fw-bold">
                        <i class="fas fa-lightbulb"></i> ข้อเสนอแนะเพิ่มเติม
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo nl2br(htmlspecialchars($info['overall_suggestion'])); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($uploadedImages)): ?>
                <div class="mt-4">
                    <h5 class="header-title"><i class="fas fa-images"></i> รูปภาพประกอบการนิเทศ</h5>
                    <div class="d-flex flex-wrap gap-3 image-gallery-print">
                        <?php foreach ($uploadedImages as $img): ?>
                            <div class="text-center image-item image-item-print">
                                <a href="<?= htmlspecialchars($uploadDir . $img['file_name']) ?>" target="_blank">
                                    <img src="<?= htmlspecialchars($uploadDir . $img['file_name']) ?>" alt="Uploaded Image" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>


            <div class="text-center mt-5 no-print">
                <a href="history.php" class="btn btn-secondary me-2"><i class="fas fa-list-alt"></i> กลับไปหน้าประวัติ</a>
                <button onclick="window.print()" class="btn btn-secondary"><i class="fas fa-print"></i> พิมพ์รายงาน</button>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>