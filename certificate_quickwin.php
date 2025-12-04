<?php
session_start();
require_once 'config/db_connect.php';
require_once('vendor/tecnickcom/tcpdf/tcpdf.php'); 

// ==========================================
// 1. ฟังก์ชันแปลงตัวเลขและวันที่เป็นไทย
// ==========================================
function toThaiNumber($number) {
    $arabic_numerals = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $thai_numerals = ['๐', '๑', '๒', '๓', '๔', '๕', '๖', '๗', '๘', '๙'];
    return str_replace($arabic_numerals, $thai_numerals, (string)$number);
}

function toThaiDate($dateStr) {
    $thai_months = [
        'January' => 'มกราคม', 'February' => 'กุมภาพันธ์', 'March' => 'มีนาคม',
        'April' => 'เมษายน', 'May' => 'พฤษภาคม', 'June' => 'มิถุนายน',
        'July' => 'กรกฎาคม', 'August' => 'สิงหาคม', 'September' => 'กันยายน',
        'October' => 'ตุลาคม', 'November' => 'พฤศจิกายน', 'December' => 'ธันวาคม'
    ];
    $date = new DateTime($dateStr);
    $day = toThaiNumber($date->format('j'));
    $month = $thai_months[$date->format('F')];
    $year = toThaiNumber((int)$date->format('Y') + 543);
    return ['day' => $day, 'month' => $month, 'year' => $year];
}

// ==========================================
// 2. รับค่าและประมวลผลข้อมูล (Logic สำหรับ Quick Win)
// ==========================================

// รับ 3 คีย์หลักของ Quick Win
$p_id = $_REQUEST['p_id'] ?? null;
$t_id = $_REQUEST['t_id'] ?? null;
$date = $_REQUEST['date'] ?? null;

if (empty($p_id) || empty($t_id) || empty($date)) {
    die("ข้อมูลไม่ครบถ้วน (Missing required parameters for Quick Win)");
}

// 2.1 บันทึก Log เพื่อจองลำดับ (ถ้ามีอยู่แล้วจะข้ามด้วย IGNORE)
// ใช้ตาราง certificate_log เดิม แต่บางฟิลด์จะเป็น NULL
$sql_log = "INSERT IGNORE INTO certificate_log 
            (supervisor_p_id, teacher_t_pid, inspection_time, generated_at) 
            VALUES (?, ?, ?, NOW())";
$stmt_log = $conn->prepare($sql_log);
$stmt_log->bind_param("sss", $p_id, $t_id, $date); // inspection_time จะเก็บวันที่ของ quickwin
$stmt_log->execute();
$stmt_log->close();

// 2.2 คำนวณเลขที่เกียรติบัตร (Running Number) จากลำดับเวลา
$sql_rank = "SELECT COUNT(*) as cert_no 
             FROM certificate_log 
             WHERE generated_at <= (
                 SELECT generated_at FROM certificate_log 
                 WHERE supervisor_p_id = ? 
                   AND teacher_t_pid = ? 
                   AND inspection_time = ?
             )";
$stmt_rank = $conn->prepare($sql_rank);
$stmt_rank->bind_param("sss", $p_id, $t_id, $date);
$stmt_rank->execute();
$res_rank = $stmt_rank->get_result();
$row_rank = $res_rank->fetch_assoc();
$certificate_running_no = $row_rank['cert_no'] ?? 0;
$stmt_rank->close();

// 2.3 ดึงข้อมูลรายละเอียด Quick Win
$sql = "SELECT 
               CONCAT(t.PrefixName, '' , t.fname, ' ', t.lname) AS teacher_full_name, 
               sc.SchoolName,
               qw.supervision_date
        FROM quick_win qw
        LEFT JOIN teacher t ON qw.t_id = t.t_pid
        LEFT JOIN school sc ON t.school_id = sc.school_id
        WHERE qw.p_id = ? 
          AND qw.t_id = ? 
          AND qw.supervision_date = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $p_id, $t_id, $date);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("ไม่พบข้อมูล Quick Win");
}
$session = $result->fetch_assoc();

// เตรียมตัวแปรสำหรับแสดงผล
$teacher_name = $session['teacher_full_name'];
$school_name = $session['SchoolName'];
$issue_date_parts = toThaiDate($session['supervision_date'] ?? date('Y-m-d'));

// ==========================================
// 3. สร้าง PDF (Layout เดิมแบบ ctest.png)
// ==========================================

// Create new PDF document (Landscape)
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('เกียรติบัตรการนิเทศ (จุดเน้น)');

// Remove header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true);
$pdf->SetAutoPageBreak(false, 0);

// Add a page
$pdf->AddPage();

// --- Background Image ---
$img_file = 'images/qw_cer.png';
if (file_exists($img_file)) {
    $pdf->Image($img_file, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
}

// --- Font Setup ---
$fontPath = __DIR__ . '/fonts/';
if (file_exists($fontPath . 'thsarabun.php')) {
    $pdf->AddFont('thsarabun', '', $fontPath . 'thsarabun.php');
    $pdf->AddFont('thsarabun', 'B', $fontPath . 'thsarabunb.php');
} else {
    $pdf->SetFont('helvetica', '', 12);
}

// ตั้งค่าสีตัวอักษร (สีน้ำเงินเข้ม #000033)
$pdf->SetTextColor(8, 13, 86);

// --- ส่วนที่ 1: เลขที่อ้างอิง (มุมขวาบน) ---
$ref_prefix = 'ศน.';
$ref_running_no = toThaiNumber(str_pad($certificate_running_no, 4, '0', STR_PAD_LEFT));
$ref_year = toThaiNumber((int)date('Y') + 543);
$reference_number = "{$ref_prefix}{$ref_running_no}/{$ref_year}";

$pdf->SetFont('thsarabun', 'B', 22);
$pdf->SetXY(241, 13); 
$pdf->Cell(0, 0, $reference_number, 0, 1, 'L');

// --- ส่วนที่ 2: ชื่อครู (ตรงกลาง) ---
$pdf->SetFont('thsarabun', 'B', 34);
$pdf->SetY(70);  
$pdf->Cell(0, 0, $teacher_name, 0, 1, 'C', 0, '', 0);

// --- ส่วนที่ 3: โรงเรียน ---
$school_line = "ครู โรงเรียน {$school_name}";
$pdf->SetFont('thsarabun', 'B', 28);
$pdf->SetY(85);
$pdf->Cell(0, 0, $school_line, 0, 1, 'C', 0, '', 0);

// --- ส่วนที่ 4: วันที่ (ด้านล่าง) ---
$pdf->SetFont('thsarabun', 'B', 24);
$date_text = "ให้ไว้ ณ วันที่   " . $issue_date_parts['day'] . "   เดือน   " . $issue_date_parts['month'] . "   พ.ศ.   " . $issue_date_parts['year'];

$pdf->SetXY(90, 145);
$pdf->Cell(0, 0, $date_text, 0, 1, 'L');

// ส่งออกไฟล์
$pdf->Output('certificate_quickwin.pdf', 'I');
?>