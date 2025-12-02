<?php
session_start();
require_once '../config/db_connect.php';
require_once '../check_login.php'; // ตรวจสอบการล็อกอิน

// ตั้งค่า header เพื่อระบุว่าการตอบกลับเป็น JSON
header('Content-Type: application/json');

// สร้าง array สำหรับการตอบกลับ
$response = ['success' => false, 'message' => ''];

// ตรวจสอบว่า request method เป็น POST หรือไม่
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

// รับข้อมูลจาก POST request
$t_pid = $_POST['t_pid'] ?? null;
$prefixName = $_POST['PrefixName'] ?? '';
$fname = $_POST['fname'] ?? '';
$lname = $_POST['lname'] ?? '';
$adm_name = $_POST['adm_name'] ?? '';
$school_id = $_POST['school_id'] ?? null;

// ตรวจสอบข้อมูลที่จำเป็น
if (empty($t_pid) || empty($fname) || empty($lname) || empty($school_id)) {
    $response['message'] = 'ข้อมูลไม่ครบถ้วน: รหัสครู, ชื่อ, นามสกุล และโรงเรียนเป็นข้อมูลที่จำเป็น';
    echo json_encode($response);
    exit;
}

// เตรียมคำสั่ง SQL สำหรับการอัปเดตข้อมูล
$sql = "UPDATE teacher SET 
            PrefixName = ?, 
            fname = ?, 
            lname = ?, 
            adm_name = ?, 
            school_id = ? 
        WHERE t_pid = ?";

$stmt = $conn->prepare($sql);

// ตรวจสอบว่า prepare statement สำเร็จหรือไม่
if ($stmt === false) {
    $response['message'] = 'เกิดข้อผิดพลาดในการเตรียมคำสั่ง SQL: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$stmt->bind_param("ssssii", $prefixName, $fname, $lname, $adm_name, $school_id, $t_pid);

if ($stmt->execute()) {
    $response['success'] = true;
} else {
    $response['message'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error;
}

$stmt->close();
$conn->close();

echo json_encode($response);
?>