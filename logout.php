<?php
session_start(); // เริ่มต้น session

// 1. ลบตัวแปร session ทั้งหมด
$_SESSION = array();

// 2. ลบ session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 3. ทำลาย session
session_destroy();

// 4. Redirect กลับไปหน้าหลัก
header("Location: index.php");
exit();
?>