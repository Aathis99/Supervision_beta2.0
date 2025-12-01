<?php
// fetch_teacher.php

header('Content-Type: application/json; charset=utf-8');
require_once 'config/db_connect.php'; 

// ตรวจสอบการเชื่อมต่อ Database
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database Connection Failed: ' . $conn->connect_error]);
    exit;
}

// ----------------------------------------------------------------------
// โหมด 1: ดึงข้อมูลครูรายบุคคล (เมื่อเลือกชื่อจาก Dropdown/Datalist)
// ⭐️ แก้ไข: เปลี่ยนเป็นรับ t_pid เพื่อความแม่นยำ
// ----------------------------------------------------------------------
if (isset($_GET['t_pid'])) {
    
    $t_pid = $conn->real_escape_string($_GET['t_pid']);
    
    // SQL query to fetch teacher details by full name
    $sql = "SELECT 
                t.t_pid, 
                t.adm_name, 
                s.SchoolName,
                vtcg.core_learning_group AS learning_group_name
            FROM teacher t
            LEFT JOIN school s ON t.school_id = s.school_id
            LEFT JOIN view_teacher_core_groups vtcg ON t.t_pid = vtcg.t_pid
            WHERE t.t_pid = '$t_pid'
            LIMIT 1";
            
            
    $result = $conn->query($sql);
    
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
        exit;
    }
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => [
            't_pid' => $row['t_pid'], 
            'adm_name' => $row['adm_name'], 
            'learning_group' => $row['learning_group_name'] ?? 'ไม่ระบุกลุ่มสาระ', // Use null coalescing operator for default value
            'school_name' => $row['SchoolName']
        ]]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลครูสำหรับรหัส: ' . $t_pid]);
    }

// ----------------------------------------------------------------------
// โหมด 2: ดึงรายชื่อครูทั้งหมด (สำหรับสร้างตัวเลือกใน Datalist)
// ----------------------------------------------------------------------
} else if (isset($_GET['action']) && $_GET['action'] == 'get_names') {
    
    $sql_names = "SELECT t_pid, CONCAT(IFNULL(PrefixName, ''), ' ', Fname, ' ', Lname) AS full_name_display 
                  FROM teacher 
                  WHERE school_id IS NOT NULL
                  ORDER BY Fname ASC"; 
                  
    
    $result_names = $conn->query($sql_names);
    
    if (!$result_names) {
        echo json_encode([]);
        exit;
    }

    $names = [];
    if ($result_names->num_rows > 0) {
        while ($row = $result_names->fetch_assoc()) {
            $names[] = $row;
        }
    }

    echo json_encode($names);

} else {
    echo json_encode(['success' => false, 'message' => 'ไม่มีพารามิเตอร์การค้นหาที่ถูกต้อง']);
}

$conn->close();
?>