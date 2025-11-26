<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// ตั้งค่า HTTP Headers เพื่อป้องกันการแคชหน้าเว็บโดยเบราว์เซอร์
// ทำให้เมื่อกดปุ่ม Back จะไม่สามารถย้อนกลับมาดูหน้าที่ต้อง Login ได้หลังจาก Logout ไปแล้ว
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ตรวจสอบว่าผู้ใช้ล็อกอินอยู่หรือไม่
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    // ถ้ายังไม่ได้ล็อกอิน ให้ redirect ไปยังหน้าหลัก (ซึ่งจะแสดงปุ่ม Login)
    header("Location: index.php");
    exit(); // จบการทำงานของสคริปต์ทันที
}
?>