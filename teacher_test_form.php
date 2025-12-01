<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ฟอร์มทดสอบดึงข้อมูลครู</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
</head>

<body>

    <div class="container mt-5">
        <div class="main-card card">
            <div class="form-header card-header text-center bg-primary text-white">
                <i class="fas fa-vial"></i> <span class="fw-bold">ฟอร์มทดสอบดึงข้อมูลผู้รับนิเทศ (ครู)</span>
            </div>
            
            <div class="card-body">
                <h5 class="card-title fw-bold">ข้อมูลผู้รับนิเทศ</h5>
                <p>ฟอร์มนี้ใช้ Dropdown (<code class="text-danger">&lt;select&gt;</code>) เพื่อทดสอบการดึงข้อมูลจาก <code class="text-primary">fetch_teacher.php</code></p>
                <hr>
                
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label for="teacher_name_select" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>
                        <select id="teacher_name_select" name="teacher_name_select" class="form-select search-field">
                            <option value="">-- กรุณาเลือกชื่อ --</option>
                            </select>
                    </div>

                    <div class="col-md-6">
                        <label for="t_pid" class="form-label fw-bold">เลขบัตรประจำตัวประชาชน</label>
                        <input type="text" id="t_pid" name="t_pid" class="form-control display-field" placeholder="--" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="adm_name" class="form-label fw-bold">วิทยฐานะ</label>
                        <input type="text" id="adm_name" name="adm_name" class="form-control display-field" placeholder="--" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="learning_group" class="form-label fw-bold">กลุ่มสาระการเรียนรู้</label>
                        <input id="learning_group" name="learning_group" class="form-control display-field" placeholder="--" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="school_name" class="form-label fw-bold">โรงเรียน</label>
                        <input type="text" id="school_name" name="school_name" class="form-control display-field" placeholder="--" readonly>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // ฟังก์ชันสำหรับดึงรายชื่อครูทั้งหมดมาใส่ใน Dropdown
    function populateTeacherDropdown() {
        const selectElement = document.getElementById('teacher_name_select');
        
        if (!selectElement) return;

        // 1. เรียก fetch_teacher.php เพื่อดึงรายชื่อทั้งหมด
        fetch('fetch_teacher.php?action=get_names')
            .then(response => response.json())
            .then(teachers => {
                // teachers คือ array ของ object [{t_pid: "...", full_name: "..."}, ...]
                teachers.forEach(teacher => {
                    const option = document.createElement('option');
                    option.value = teacher.t_pid; // value ของ option คือ t_pid
                    option.textContent = teacher.full_name; // ข้อความที่แสดงคือ ชื่อเต็ม
                    selectElement.appendChild(option);
                });
            })
            .catch(error => console.error('เกิดข้อผิดพลาดในการดึงรายชื่อครู:', error));
    }

    // ฟังก์ชันสำหรับดึงข้อมูลรายละเอียดของครูที่ถูกเลือก
    function fetchTeacherDetails() {
        const selectedPid = document.getElementById('teacher_name_select').value; 
        
        // ดึง element ของ input field ทั้งหมด
        const pidField = document.getElementById('t_pid');
        const admNameField = document.getElementById('adm_name');
        const learningGroupField = document.getElementById('learning_group');
        const schoolNameField = document.getElementById('school_name');

        // ล้างค่าในช่อง input ทุกครั้งที่มีการเปลี่ยนแปลง
        pidField.value = '';
        admNameField.value = '';
        learningGroupField.value = '';
        schoolNameField.value = '';

        if (selectedPid) {
            // 2. เรียก fetch_teacher.php อีกครั้งโดยส่ง t_pid ไปเพื่อดึงข้อมูลเฉพาะบุคคล
            fetch(`fetch_teacher.php?t_pid=${encodeURIComponent(selectedPid)}`) 
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        pidField.value = result.data.t_pid;
                        admNameField.value = result.data.adm_name;
                        learningGroupField.value = result.data.learning_group;
                        schoolNameField.value = result.data.school_name;
                    }
                })
                .catch(error => console.error('เกิดข้อผิดพลาดในการดึงข้อมูลครู:', error));
        }
    }
    
    // เรียกใช้ฟังก์ชันเพื่อเติมรายชื่อเมื่อหน้าเว็บโหลดเสร็จ
    document.addEventListener('DOMContentLoaded', populateTeacherDropdown); 
    // เพิ่ม Event Listener ให้กับ Dropdown เพื่อเรียกใช้ฟังก์ชัน fetchTeacherDetails เมื่อมีการเลือก
    document.getElementById('teacher_name_select').addEventListener('change', fetchTeacherDetails);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>