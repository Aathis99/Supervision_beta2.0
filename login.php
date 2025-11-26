<?php
session_start(); // เริ่ม Session

// ถ้าล็อกอินอยู่แล้ว ให้ redirect ไปหน้าหลัก
if (isset($_SESSION['user_id'])) {
    header("Location: history.php"); // หรือหน้าที่คุณต้องการให้ไปหลังล็อกอิน
    exit();
}

// ดึงข้อความ error มาแสดง (ถ้ามี)
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // ลบข้อความออกจาก session หลังแสดงผล
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบสำหรับผู้นิเทศ</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            /* --- ส่วนสำหรับใส่ภาพพื้นหลัง --- */
            /* แก้ไข: เปลี่ยนจาก background-color เป็น background-image */
            background-image: url('images/login_bg.png'); /* ⭐️ แก้ไข path รูปภาพตรงนี้ได้เลยครับ */
            background-size: cover; /* ทำให้ภาพเต็มหน้าจอ */
            background-position: center; /* จัดภาพให้อยู่กึ่งกลาง */
            background-repeat: no-repeat; /* ไม่ให้ภาพซ้ำ */
            background-attachment: fixed; /* ทำให้ภาพพื้นหลังคงที่เมื่อเลื่อนหน้าจอ */
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            border: none;
            border-radius: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="card-body p-4 p-md-5">
            <h3 class="card-title text-center mb-4 fw-bold">สำหรับผู้นิเทศ</h3>

            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form action="login_process.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="3509900553730" required>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" value="3730" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-sign-in-alt me-2"></i>เข้าสู่ระบบ
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>