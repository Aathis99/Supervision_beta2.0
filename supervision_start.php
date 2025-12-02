<?php
// ⭐️ เริ่ม Session เพื่อใช้งานข้อมูลที่บันทึกไว้
session_start();

// ⭐️ ตรวจสอบว่าเป็นการกลับมาแก้ไขหรือไม่
if (isset($_GET['edit']) && $_GET['edit'] == 'true' && isset($_SESSION['inspection_data'])) {
    // ถ้าใช่, ให้ใช้ข้อมูลจาก Session
    $inspection_data = $_SESSION['inspection_data'];
} else {
    // ถ้าไม่ใช่ (เข้าหน้าครั้งแรก) หรือไม่มีข้อมูลใน Session, ให้ล้างเฉพาะข้อมูลการนิเทศเก่า และตั้งค่าเป็น null
    // ⭐️ แก้ไข: เปลี่ยนจาก session_unset() เป็นการ unset เฉพาะ key ที่ต้องการ
    // เพื่อป้องกันไม่ให้ข้อมูลการ login หายไป
    unset($_SESSION['inspection_data']);

    $inspection_data = null;
}

// 1. นำเข้าไฟล์เชื่อมต่อฐานข้อมูล
require_once 'config/db_connect.php'; 

// ⭐️ เพิ่มแท็ก FORM ครอบทุกส่วน ⭐️
echo '<form method="POST" action="summary.php" onsubmit="return validateSelection(event)">'; 

// 2. ส่วนเลือกข้อมูลผู้นิเทศ (ต้องไม่มีแท็ก <form> ในไฟล์นี้แล้ว)
require_once 'supervisor.php'; 

// 3. ส่วนเลือกข้อมูลผู้รับนิเทศ (ต้องไม่มีแท็ก <form> ในไฟล์นี้แล้ว)
require_once 'teacher.php'; 

// ⭐️ เพิ่มแท็ก FORM ปิด ⭐️
echo '</form>'; 

?>
    </div> <script>
        // ⭐️ เรียกฟังก์ชัน populateNameDropdown เมื่อหน้าโหลดเสร็จ (จาก supervisor.php)
        window.onload = populateNameDropdown;
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>
