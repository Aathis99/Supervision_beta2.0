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
// 2. รับค่าและประมวลผลข้อมูล (Logic ใหม่)
// ==========================================

// รับ 4 คีย์หลัก
$s_pid = $_REQUEST['s_pid'] ?? null;
$t_pid = $_REQUEST['t_pid'] ?? null;
$sub_code = $_REQUEST['sub_code'] ?? null;
$time = $_REQUEST['time'] ?? null;

if (empty($s_pid) || empty($t_pid) || empty($sub_code) || empty($time)) {
    die("ข้อมูลไม่ครบถ้วน (Missing required parameters)");
}

// 2.1 บันทึก Log เพื่อจองลำดับ (ถ้ามีอยู่แล้วจะข้ามด้วย IGNORE)
$sql_log = "INSERT IGNORE INTO certificate_log 
            (supervisor_p_id, teacher_t_pid, subject_code, inspection_time, generated_at) 
            VALUES (?, ?, ?, ?, NOW())";
$stmt_log = $conn->prepare($sql_log);
$stmt_log->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
$stmt_log->execute();
$stmt_log->close();

// 2.2 คำนวณเลขที่เกียรติบัตร (Running Number) จากลำดับเวลา
$sql_rank = "SELECT COUNT(*) as cert_no 
             FROM certificate_log 
             WHERE generated_at <= (
                 SELECT generated_at FROM certificate_log 
                 WHERE supervisor_p_id = ? 
                   AND teacher_t_pid = ? 
                   AND subject_code = ? 
                   AND inspection_time = ?
             )";
$stmt_rank = $conn->prepare($sql_rank);
$stmt_rank->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
$stmt_rank->execute();
$res_rank = $stmt_rank->get_result();
$row_rank = $res_rank->fetch_assoc();
$certificate_running_no = $row_rank['cert_no'] ?? 0; // ได้ตัวเลขลำดับมาแล้ว
$stmt_rank->close();

// 2.3 ดึงข้อมูลรายละเอียดการนิเทศ
$sql = "SELECT s.*, 
               CONCAT(t.PrefixName, '' , t.fname, ' ', t.lname) AS teacher_full_name, 
               sc.SchoolName
        FROM supervision_sessions s
        LEFT JOIN teacher t ON s.teacher_t_pid = t.t_pid
        LEFT JOIN school sc ON t.school_id = sc.school_id
        WHERE s.supervisor_p_id = ? 
          AND s.teacher_t_pid = ? 
          AND s.subject_code = ? 
          AND s.inspection_time = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssi", $s_pid, $t_pid, $sub_code, $time);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("ไม่พบข้อมูลการนิเทศ");
}
$session = $result->fetch_assoc();

// เตรียมตัวแปรสำหรับแสดงผล
$teacher_name = $session['teacher_full_name'];
$school_name = $session['SchoolName'];
$issue_date_parts = toThaiDate($session['satisfaction_date'] ?? date('Y-m-d')); // ใช้วันปัจจุบันถ้ายังไม่มี

// ==========================================
// 3. สร้าง PDF (Layout เดิมแบบ ctest.png)
// ==========================================

// Create new PDF document (Landscape)
$pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetTitle('เกียรติบัตรการนิเทศ');

// Remove header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(0, 0, 0, true); // ตั้งขอบเป็น 0 เพื่อให้รูปเต็มจอ
$pdf->SetAutoPageBreak(false, 0);

// Add a page
$pdf->AddPage();

// --- Background Image ---
$img_file = 'images/ctest.png'; // ⚠️ ตรวจสอบว่าไฟล์นี้มีอยู่จริงในโฟลเดอร์ images
if (file_exists($img_file)) {
    $pdf->Image($img_file, 0, 0, 297, 210, '', '', '', false, 300, '', false, false, 0);
}

// --- Font Setup ---
// ใช้วิธี AddFont แบบระบุ Path (ตามที่คุณเคยใช้และแจ้งว่า work)
$fontPath = __DIR__ . '/fonts/';
// ตรวจสอบว่ามีไฟล์ php ในโฟลเดอร์นี้จริงหรือไม่ก่อนเรียก
if (file_exists($fontPath . 'thsarabun.php')) {
    $pdf->AddFont('thsarabun', '', $fontPath . 'thsarabun.php');
    $pdf->AddFont('thsarabun', 'B', $fontPath . 'thsarabunb.php');
} else {
    // ถ้าไม่มี ให้ใช้ helvetica แทนเพื่อไม่ให้ error (Fallback)
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
$pdf->SetXY(241, 11); 
$pdf->Cell(0, 0, $reference_number, 0, 1, 'L');

// --- ส่วนที่ 2: ชื่อครู (ตรงกลาง) ---
$pdf->SetFont('thsarabun', 'B', 34);
$pdf->SetY(75);  
$pdf->Cell(0, 0, $teacher_name, 0, 1, 'C', 0, '', 0);

// --- ส่วนที่ 3: โรงเรียน ---
$school_line = "ครู โรงเรียน {$school_name}";
$pdf->SetFont('thsarabun', 'B', 28);
$pdf->SetY(90);
$pdf->Cell(0, 0, $school_line, 0, 1, 'C', 0, '', 0);

// --- ส่วนที่ 4: วันที่ (ด้านล่าง) ---
$pdf->SetFont('thsarabun', 'B', 24);
// จัดรูปแบบวันที่แบบเว้นวรรคสวยงาม
$date_text = "ให้ไว้ ณ วันที่   " . $issue_date_parts['day'] . "   เดือน   " . $issue_date_parts['month'] . "   พ.ศ.   " . $issue_date_parts['year'];

$pdf->SetXY(90, 157); // ตำแหน่งตามโค้ดเดิมของคุณ
$pdf->Cell(0, 0, $date_text, 0, 1, 'L');

// ส่งออกไฟล์
$pdf->Output('certificate.pdf', 'I');
?>