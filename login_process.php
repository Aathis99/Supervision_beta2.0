<?php
session_start();
require_once 'config/db_connect.php'; // ต้องมีไฟล์เชื่อมต่อฐานข้อมูล

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? ''); // ⭐️ เพิ่ม trim() เพื่อตัดช่องว่าง
    $password = trim($_POST['password'] ?? ''); // ⭐️ เพิ่ม trim() เพื่อตัดช่องว่าง

    if (empty($username) || empty($password)) {
        $_SESSION['error_message'] = "กรุณากรอก Username และ Password";
        header("Location: login.php");
        exit();
    }

    // --- ส่วนตรรกะการตรวจสอบตามที่คุณต้องการ ---
    // 1. ตรวจสอบว่า password ที่กรอกมาคือเลข 4 ตัวท้ายของ username (p_id) หรือไม่
    $expected_password = substr($username, -4);

    if ($password !== $expected_password) {
        $_SESSION['error_message'] = "Username หรือ Password ไม่ถูกต้อง";
        header("Location: login.php");
        exit();
    }

    // 2. ตรวจสอบว่า p_id (username) นี้มีอยู่ในฐานข้อมูลหรือไม่
    // --- แก้ไข: เปลี่ยนไปค้นหาในตาราง `supervisor` และรวมชื่อ-นามสกุล ---
    $sql = "SELECT p_id, CONCAT(IFNULL(PrefixName, ''), Fname, ' ', Lname) AS full_name 
            FROM supervisor 
            WHERE TRIM(p_id) = ?"; // ⭐️ เพิ่ม TRIM() ใน SQL เพื่อความแม่นยำ
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        // --- ล็อกอินสำเร็จ ---
        $user = $result->fetch_assoc();

        // เก็บข้อมูลผู้ใช้ลงใน Session
        $_SESSION['user_id'] = $user['p_id'];
        $_SESSION['user_name'] = $user['full_name']; // ใช้ full_name ที่เรา CONCAT ขึ้นมา
        $_SESSION['is_logged_in'] = true;

        // ส่งผู้ใช้ไปยังหน้าหลักของระบบ (เช่น index.php หรือ kpi_form.php)
        header("Location: index.php"); // <-- เปลี่ยนเป็นหน้าที่ต้องการ
        exit();
    } else {
        // --- ไม่พบผู้ใช้ในระบบ ---
        $_SESSION['error_message'] = "Username หรือ Password ไม่ถูกต้อง";
        header("Location: login.php");
        exit();
    }
}
?>