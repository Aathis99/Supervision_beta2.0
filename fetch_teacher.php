<?php
// fetch_teacher.php (ฉบับแก้ไข: ตัดปัญหาเรื่อง View และ Case Sensitivity)

header('Content-Type: application/json');
require_once 'config/db_connect.php'; 

// ตรวจสอบการเชื่อมต่อ Database
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database Connection Failed: ' . $conn->connect_error]);
    exit;
}

// ----------------------------------------------------------------------
// โหมด 1: ดึงข้อมูลเฉพาะบุคคลเมื่อเลือกชื่อ (รับค่าเป็น t_pid)
// ----------------------------------------------------------------------
if (isset($_GET['t_pid'])) {
    
    $t_pid = trim($_GET['t_pid']);
    $t_pid_search = $conn->real_escape_string($t_pid); 
    
    // ⭐️ แก้ไข SQL: ยกเลิกการ JOIN view_teacher_core_groups 
    // และใส่ Logic การจัดกลุ่มสาระลงไปตรงนี้แทน (ตัดปัญหาเรื่อง View บน Server พัง)
    $sql = "SELECT 
                t.t_pid, 
                t.adm_name, 
                s.SchoolName,
                CASE 
                    WHEN t.learning_group like '%ภาษาไทย%' THEN 'กลุ่มสาระการเรียนรู้ภาษาไทย'
                    WHEN t.learning_group like '%คณิตศาสตร์%' THEN 'กลุ่มสาระการเรียนรู้คณิตศาสตร์'
                    WHEN t.learning_group REGEXP 'วิทยาศาสตร์|เคมี|ฟิสิกส์|ชีววิทยา|คอมพิวเตอร์|เทคโนโลยี|โลก ดาราศาสตร์' THEN 'กลุ่มสาระการเรียนรู้วิทยาศาสตร์และเทคโนโลยี'
                    WHEN t.learning_group like '%สังคม%' OR t.learning_group like '%ประวัติศาสตร์%' OR t.learning_group like '%ภูมิศาสตร์%' THEN 'กลุ่มสาระการเรียนรู้สังคมศึกษา ศาสนา และวัฒนธรรม'
                    WHEN t.learning_group REGEXP 'สุขศึกษา|พลศึกษา|พละ' THEN 'กลุ่มสาระการเรียนรู้สุขศึกษาและพลศึกษา'
                    WHEN t.learning_group REGEXP 'ศิลป|ดนตรี|นาฎศิลป์|ทัศนศิลป์' THEN 'กลุ่มสาระการเรียนรู้ศิลปะ'
                    WHEN t.learning_group REGEXP 'การงาน|เกษตร|คหกรรม|อุตสาหกรรม|พณิชยกรรม|บริหารธุรกิจ' THEN 'กลุ่มสาระการเรียนรู้การงานอาชีพ'
                    WHEN t.learning_group REGEXP 'อังกฤษ|จีน|ญี่ปุ่น|ฝรั่งเศส|เกาหลี|ต่างประเทศ' THEN 'กลุ่มสาระการเรียนรู้ภาษาต่างประเทศ'
                    WHEN t.learning_group like '%แนะแนว%' THEN 'กิจกรรมพัฒนาผู้เรียน' 
                    ELSE 'อื่นๆ' 
                END AS core_learning_group
            FROM teacher t
            LEFT JOIN school s ON t.school_id = s.school_id
            WHERE t.t_pid = '$t_pid_search'";
            
    $result = $conn->query($sql);

    // เพิ่มการดักจับ Error ของ SQL
    if (!$result) {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
        exit;
    }

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        
        echo json_encode(['success' => true, 'data' => [
            't_pid' => $row['t_pid'], 
            'adm_name' => $row['adm_name'], 
            'learning_group' => $row['core_learning_group'],
            'school_name' => $row['SchoolName']
        ]]);
        
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลครูคนนี้ในระบบ (PID: ' . $t_pid . ')']);
    }

// ----------------------------------------------------------------------
// โหมด 2: ดึงรายชื่อเต็มสำหรับ Datalist
// ----------------------------------------------------------------------
} else if (isset($_GET['action']) && $_GET['action'] == 'get_names') {
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

} else {
    echo json_encode(['success' => false, 'message' => 'รูปแบบการเรียกข้อมูลไม่ถูกต้อง']);
}
?>