<?php
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
