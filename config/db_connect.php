<?php
// ข้อมูลการเชื่อมต่อ
$servername = "localhost";
$username = "root"; // เปลี่ยนเป็นชื่อผู้ใช้ MySQL ของคุณ
$password = ""; // เปลี่ยนเป็นรหัสผ่าน MySQL ของคุณ
$dbname = "sesa_db"; // เปลี่ยนเป็นชื่อฐานข้อมูลที่คุณสร้าง

// สร้างการเชื่อมต่อ
$conn = new mysqli($servername, $username, $password, $dbname);

// ตรวจสอบการเชื่อมต่อ
if ($conn->connect_error) {
    // แสดงข้อความ error หากเชื่อมต่อไม่ได้
    die("Connection failed: " . $conn->connect_error);
}
// การเชื่อมต่อสำเร็จแล้ว
// echo "Connected successfully"; 
?>