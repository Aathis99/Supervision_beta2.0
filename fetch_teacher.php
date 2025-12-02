<?php
// fetch_teacher.php (ฉบับแก้ไข: ใช้ CONCAT ค้นหาชื่อเต็ม)
// fetch_teacher.php (ฉบับแก้ไข: ตัดปัญหาเรื่อง View และ Case Sensitivity)
// fetch_teacher.php (ฉบับแก้ไข: แก้ไขโครงสร้าง if-else และ Logic การทำงาน)
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
if (isset($_GET['t_pid']) && !empty(trim($_GET['t_pid']))) {
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
 
        // ส่งคีย์ที่ตรงกับ ID ใน teacher.php (t_pid, adm_name, learning_group, และ school_name ที่เพิ่มเข้ามา)
        echo json_encode(['success' => true, 'data' => [
            't_pid' => $row['t_pid'], // คีย์สำหรับเลขบัตรประชาชน
            'adm_name' => $row['adm_name'], 
            'learning_group' => $row['core_learning_group'],
            'school_name' => $row['SchoolName']
        ]]);
    } else {
        echo json_encode(['success' => false, 'message' => 'ไม่พบข้อมูลครูคนนี้ในระบบ (PID: ' . $t_pid . ')']);
    }

// ----------------------------------------------------------------------
// โหมด 2: ดึงรายชื่อเต็มสำหรับ Datalist (action=get_names)
// ----------------------------------------------------------------------
} else if (isset($_GET['action']) && $_GET['action'] == 'get_names') {
    // ใช้ CONCAT() เพื่อรวมคำนำหน้า ชื่อ และนามสกุล (โค้ดส่วนนี้ถูกต้องแล้ว)
    // ⭐️ แก้ไข: เพิ่มการ SELECT t_pid เพื่อให้ Frontend สามารถนำไปใช้งานต่อได้
    $sql_names = "SELECT t_pid, CONCAT(IFNULL(PrefixName, ''), ' ', Fname, ' ', Lname) AS full_name_display 
                  FROM teacher 
                  ORDER BY Fname ASC";
    
    $result_names = $conn->query($sql_names);

    if (!$result_names) {
        echo json_encode(['success' => false, 'message' => 'SQL Error: ' . $conn->error]);
        exit;
    }

    $names = [];
    if ($result_names->num_rows > 0) {
        while ($row = $result_names->fetch_assoc()) {
            $names[] = $row; // ส่งกลับทั้ง t_pid และ full_name_display
        }
    }
    // ส่งข้อมูลกลับไปในรูปแบบ JSON array ของ object ครู
    echo json_encode(['success' => true, 'data' => $names]); 

// ----------------------------------------------------------------------
// โหมดเริ่มต้น/ไม่ถูกต้อง
// ----------------------------------------------------------------------
} else {
    echo json_encode(['success' => false, 'message' => 'รูปแบบการเรียกข้อมูลไม่ถูกต้อง']);
}

$conn->close();