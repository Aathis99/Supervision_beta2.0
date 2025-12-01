<?php
header('Content-Type: application/json');
require_once 'config/db_connect.php'; 

// ----------------------------------------------------------------------
// โหมด 1: ดึงข้อมูลเฉพาะบุคคลเมื่อเลือกชื่อจาก Dropdown (รับค่าเป็น full_name)
// ----------------------------------------------------------------------
if (isset($_GET['full_name'])) {
    
    // แยกส่วนประกอบของชื่อเต็ม (คำนำหน้า ชื่อ นามสกุล)
    $full_name_parts = explode(' ', $_GET['full_name'], 3); // แบ่งเป็น 3 ส่วน
    
    // ตรวจสอบว่ามีส่วนประกอบครบหรือไม่ (อย่างน้อย ชื่อ และ นามสกุล)
    if (count($full_name_parts) >= 2) {
        // สมมติว่าส่วนที่ 2 คือชื่อ และส่วนสุดท้ายคือนามสกุล
        $teacher_fname = $conn->real_escape_string($full_name_parts[1]);
        $teacher_lname = isset($full_name_parts[2]) ? $conn->real_escape_string($full_name_parts[2]) : '';

        // คำสั่ง SQL เพื่อดึงข้อมูล: ใช้ Fname และ Lname ในการค้นหา
        // JOIN กับตาราง school และ view_teacher_core_groups
        $sql = "SELECT t.t_pid, t.adm_name, v.core_learning_group, s.SchoolName AS school_name
            FROM teacher t
            LEFT JOIN school s ON t.school_id = s.school_id
            LEFT JOIN view_teacher_core_groups v ON t.t_pid = v.t_pid
            WHERE t.Fname = '$teacher_fname' AND t.Lname = '$teacher_lname'";
            
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            // ส่งข้อมูลกลับไปในรูปแบบที่ JavaScript คาดหวัง
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลครูในระบบ']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'รูปแบบชื่อไม่ถูกต้อง']);
    }

// ----------------------------------------------------------------------
// โหมด 2: ดึงรายชื่อเต็มสำหรับ Dropdown (action=get_names)
// ----------------------------------------------------------------------
} else if (isset($_GET['action']) && $_GET['action'] == 'get_names') {
    // ใช้ CONCAT เพื่อรวมคำนำหน้า, ชื่อ, และนามสกุล
    $sql_names = "SELECT CONCAT(IFNULL(PrefixName, ''), ' ', Fname, ' ', Lname) AS full_name_display 
                  FROM teacher 
                  ORDER BY Fname ASC"; 
    
    $result_names = $conn->query($sql_names);
    
    $names = [];
    while ($row = $result_names->fetch_assoc()) {
        $names[] = trim($row['full_name_display']); 
    }
    echo json_encode($names);

// ----------------------------------------------------------------------
// โหมดเริ่มต้น/ไม่ถูกต้อง
// ----------------------------------------------------------------------
} else {
    echo json_encode(['success' => false, 'message' => 'ไม่มีพารามิเตอร์การค้นหา']);
}
?>