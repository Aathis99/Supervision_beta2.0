<?php
header('Content-Type: application/json'); // กำหนดให้ Content-Type เป็น JSON
include 'config/db_connect.php'; // ไฟล์เชื่อมต่อฐานข้อมูล

// ----------------------------------------------------------------------
// โหมด 1: ดึงข้อมูลเฉพาะบุคคลเมื่อเลือกชื่อจาก Dropdown (full_name)
// ----------------------------------------------------------------------
if (isset($_GET['full_name'])) {
    // แยกชื่อและนามสกุลออกจากชื่อเต็มที่ถูกส่งมาจาก AJAX (สมมติ: "ชื่อ นามสกุล")
    $full_name_parts = explode(' ', $_GET['full_name']);
    
    // ตรวจสอบและทำความสะอาดเฉพาะส่วนที่ 2 (ชื่อ) และส่วนสุดท้าย (นามสกุล)
    $supervisor_fname = $conn->real_escape_string($full_name_parts[1]); 
    $supervisor_lname = $conn->real_escape_string(end($full_name_parts)); 
    
    // คำสั่ง SQL เพื่อดึงข้อมูล: ต้องใช้ Fname และ Lname ในการค้นหา (WHERE)
    $sql = "SELECT p_id, OfficeName, position FROM supervisor 
            WHERE Fname = '$supervisor_fname' AND Lname = '$supervisor_lname'";
            
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // ส่งข้อมูลกลับไปในรูปแบบที่ JavaScript คาดหวัง (p_id, OfficeName, position)
        echo json_encode(['success' => true, 'data' => $row]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลบุคลากรในระบบ']);
    }

// ----------------------------------------------------------------------
// โหมด 2: ดึงรายชื่อทั้งหมด (Full Name + Prefix) สำหรับ Dropdown (action=get_names)
// ----------------------------------------------------------------------
} else if (isset($_GET['action']) && $_GET['action'] == 'get_names') {
    // ใช้ CONCAT ของ MySQL เพื่อรวม คำนำหน้า (prefixName), ชื่อ (Fname), และ นามสกุล (Lname)
    $sql_names = "SELECT CONCAT(prefixName, ' ', Fname, ' ', Lname) AS full_name_display 
                  FROM supervisor 
                  ORDER BY Fname ASC"; 
    
    $result_names = $conn->query($sql_names);
    
    $names = [];
    while ($row = $result_names->fetch_assoc()) {
        // ดึงชื่อเต็มที่ CONCAT แล้วมาใส่ใน Array
        $names[] = $row['full_name_display']; 
    }
    echo json_encode($names);

// ----------------------------------------------------------------------
// โหมด 3: ไม่พบพารามิเตอร์ที่ต้องการ
// ----------------------------------------------------------------------
} else {
    echo json_encode(['success' => false, 'message' => 'ไม่มีพารามิเตอร์การค้นหา']);
}

$conn->close();
?>