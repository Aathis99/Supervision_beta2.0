<?php
// fetch_teacher.php (ฉบับแก้ไข: ใช้ CONCAT ค้นหาชื่อเต็ม)

header('Content-Type: application/json');
require_once 'config/db_connect.php'; 

// ----------------------------------------------------------------------
// โหมด 1: ดึงข้อมูลเฉพาะบุคคลเมื่อเลือกชื่อ (full_name)
// ----------------------------------------------------------------------
if (isset($_GET['full_name'])) {
    
    $full_name = trim($_GET['full_name']);
    $full_name_search = $conn->real_escape_string($full_name); 
    
    // ⭐️ FIX SQL: ค้นหาจากชื่อเต็มที่ถูก CONCAT() ในฐานข้อมูล
    // ตรวจสอบให้แน่ใจว่าการ CONCAT นี้ตรงกับที่คุณใช้สร้าง Datalist ใน teacher.php
    $sql = "SELECT t.t_pid, t.adm_name, v.core_learning_group, s.SchoolName
            FROM teacher t
            LEFT JOIN school s ON t.school_id = s.school_id
            LEFT JOIN view_teacher_core_groups v ON t.t_pid = v.t_pid
            WHERE CONCAT(IFNULL(t.PrefixName, ''), ' ', t.Fname, ' ', t.Lname) = '$full_name_search'";
            
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        // ส่งคีย์ที่ตรงกับ ID ใน teacher.php (t_pid, adm_name, learning_group, และ school_name ที่เพิ่มเข้ามา)
        echo json_encode(['success' => true, 'data' => [
            't_pid' => $row['t_pid'], // คีย์สำหรับเลขบัตรประชาชน
            'adm_name' => $row['adm_name'], 
            'learning_group' => $row['core_learning_group'],
            'school_name' => $row['SchoolName']
        ]]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลครูคนนี้ในระบบ']);
    }

// ----------------------------------------------------------------------
// โหมด 2: ดึงรายชื่อเต็มสำหรับ Datalist (action=get_names)
// ----------------------------------------------------------------------
} else if (isset($_GET['action']) && $_GET['action'] == 'get_names') {
    // ใช้ CONCAT() เพื่อรวมคำนำหน้า ชื่อ และนามสกุล (โค้ดส่วนนี้ถูกต้องแล้ว)
    $sql_names = "SELECT CONCAT(IFNULL(PrefixName, ''), ' ', Fname, ' ', Lname) AS full_name_display 
                  FROM teacher 
                  ORDER BY Fname ASC"; 
    
    $result_names = $conn->query($sql_names);
    
    $names = [];
    if ($result_names) {
        while ($row = $result_names->fetch_assoc()) {
            $names[] = trim($row['full_name_display']); 
        }
    }
    echo json_encode($names);

// ----------------------------------------------------------------------
// โหมดเริ่มต้น/ไม่ถูกต้อง
// ----------------------------------------------------------------------
} else {
    echo json_encode(['success' => false, 'message' => 'รูปแบบการเรียกข้อมูลไม่ถูกต้อง']);
}
?>