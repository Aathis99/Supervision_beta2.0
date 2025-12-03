<?php
    // ส่วน PHP ด้านบนสุด (ถ้ามี)
?>

<hr>
<div class="card-body">
    <h5 class="card-title fw-bold">ข้อมูลผู้รับนิเทศ</h5>
    <hr>
    <div class="row g-3">

        <div class="col-md-6">
            <label for="teacher_name_input" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>
            <div class="input-group">
                <input id="teacher_name_input" name="teacher_name"
                    class="form-control search-field" value="<?php echo htmlspecialchars($inspection_data['teacher_name'] ?? ''); ?>"
                    placeholder="-- พิมพ์ชื่อ-สกุล แล้วกดค้นหา --">
                <button class="btn btn-primary" type="button" id="search_teacher_button"><i class="fas fa-search"></i> ค้นหา</button>
                <datalist id="teacher_names_list">
                    <?php
                    // ข้อมูลจะถูกเติมโดย JavaScript เมื่อกดค้นหา
                    if ($result_teachers) {
                        // ข้อมูลจะถูกเติมโดย JavaScript
                    }
                    ?>
                </datalist>
            </div>
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

    <div class="card-body">
        <div class="row g-3">

        </div>

        <div class="row g-3 mt-4 justify-content-center">
            <div class="mt-4 mb-4">
                <?php require_once 'forms/form_selector.php'; ?>
            </div>
            <div class="col-auto">
                <a href="index.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> ย้อนกลับ
                </a>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-success btn-l" onclick="return validateSelection(event);">
                    ดำเนินการต่อ
                </button>
            </div>
        </div>

    </div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        let allTeachers = []; // ข้อมูลครูทั้งหมด
        
        const searchButton = document.getElementById('search_teacher_button');
        const teacherInput = document.getElementById('teacher_name_input');
        const teacherList = document.getElementById('teacher_names_list');

        // ⭐️⭐️ การแก้ไข: ล้างค่า datalist และลบ list attribute ออกในตอนเริ่มต้น ⭐️⭐️
        // เพื่อป้องกัน Browser แสดงผลอัตโนมัติเมื่อโหลดหน้า
        teacherInput.removeAttribute('list');
        teacherList.innerHTML = '';


        // Event listener สำหรับปุ่มค้นหา
        searchButton.addEventListener('click', function() {
            const searchTerm = teacherInput.value.toLowerCase().trim();
            if (!searchTerm) {
                alert('โปรดพิมพ์ชื่อที่ต้องการค้นหา');
                return;
            } 

            // กรองรายชื่อครูจาก allTeachers
            const filteredTeachers = allTeachers
                .filter(teacher => teacher.full_name_display.toLowerCase().includes(searchTerm))
                .slice(0, 5);

            // เพิ่มการแจ้งเตือนหากไม่พบข้อมูล
            if (filteredTeachers.length === 0) {
                alert('ไม่พบรายชื่อครูที่ตรงกับคำค้นหา');
                teacherInput.removeAttribute('list'); // ล้าง list attribute หากไม่พบข้อมูล
                return;
            }

            // ล้าง datalist เดิมแล้วสร้างใหม่จากผลการค้นหา
            teacherList.innerHTML = '';
            filteredTeachers.forEach(teacher => {
                const option = document.createElement('option');
                option.value = teacher.full_name_display;
                teacherList.appendChild(option);
            });

            // ⭐️ ทำให้ input แสดง datalist ที่มีข้อมูลที่กรองแล้ว (เมื่อกดค้นหาเท่านั้น)
            teacherInput.setAttribute('list', 'teacher_names_list');
            // สั่งให้ focus ที่ input อีกครั้งเพื่อให้แน่ใจว่า datalist จะแสดงขึ้นมา
            teacherInput.focus();
        });

        // เรียกฟังก์ชันเพื่อดึงข้อมูลครูทั้งหมดมาเก็บไว้ใน allTeachers
        populateTeacherDatalist(teachers => { allTeachers = teachers; });


        // Event listener สำหรับการเลือกจาก datalist หรือการพิมพ์
        teacherInput.addEventListener('input', function(e) {
            const inputValue = teacherInput.value;
            let selectedTeacher = null;

            // ⭐️⭐️ การแก้ไขหลัก: บังคับลบ list attribute ทุกครั้งที่มีการพิมพ์ (Input) ⭐️⭐️
            // เพื่อปิดการแสดงผลอัตโนมัติบน PC Browser
            teacherInput.removeAttribute('list'); 


            // ค้นหาข้อมูลครูที่ตรงกับค่าที่เลือกจาก `allTeachers`
            if (allTeachers && allTeachers.length > 0) {
                selectedTeacher = allTeachers.find(teacher => teacher.full_name_display === inputValue);
            }

            // ถ้าเจอครูที่ตรงกัน (หมายถึงผู้ใช้เลือกรายการจาก datalist)
            if (selectedTeacher) {
                // ดึงข้อมูลครูคนนั้นมาแสดง
                fetchTeacherData(selectedTeacher.t_pid);
            } else {
                // ถ้าผู้ใช้กำลังพิมพ์ หรือลบข้อความ แต่ยังไม่ได้เลือก
                // ให้ล้างข้อมูลในช่องอื่นๆ เพื่อป้องกันข้อมูลเก่าค้างอยู่
                clearTeacherData();
            }
        });

        // เพิ่ม: ป้องกันการกด Enter แล้ว Submit ฟอร์มโดยไม่ตั้งใจ
        teacherInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
            }
        });
    });

    // ฟังก์ชันสำหรับดึงรายชื่อครูทั้งหมดมาใส่ใน Datalist
    function populateTeacherDatalist(callback) {
        fetch('fetch_teacher.php?action=get_names')
            .then(response => response.json())
            .then(result => {
                let teachers = [];
                if (result.success && Array.isArray(result.data)) {
                    teachers = result.data;
                    callback(teachers); // ส่งข้อมูลครูกลับไปที่ callback

                    // ล้างค่า datalist เพื่อให้ datalist ว่างเปล่าในตอนเริ่มต้น
                    const teacherList = document.getElementById('teacher_names_list');
                    teacherList.innerHTML = '';
                }

                // หลังจากเติมรายชื่อเสร็จ, ตรวจสอบว่ามีค่าที่เคยเลือกไว้หรือไม่ (สำหรับกรณีแก้ไข)
                const initialTeacherName = document.getElementById('teacher_name_input').value;
                if (initialTeacherName) {
                    const foundTeacher = teachers.find(teacher => teacher.full_name_display === initialTeacherName);
                    if (foundTeacher) {
                        // ถ้าเจอ ให้ดึงข้อมูลครูคนนั้นมาแสดง
                        fetchTeacherData(foundTeacher.t_pid);
                    }
                }
            })
            .catch(error => console.error('Error fetching teacher names:', error));
    }

    // ฟังก์ชันล้างข้อมูล
    function clearTeacherData() {
        document.getElementById('t_pid').value = '';
        document.getElementById('adm_name').value = '';
        document.getElementById('learning_group').value = '';
        document.getElementById('school_name').value = '';
    }

    // ฟังก์ชันดึงข้อมูล
    function fetchTeacherData(teacherPid) {
        // ล้างค่าเก่าก่อนเสมอ
        clearTeacherData();

        if (teacherPid) {
            fetch(`fetch_teacher.php?t_pid=${encodeURIComponent(teacherPid)}`)
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        document.getElementById('t_pid').value = result.data.t_pid;
                        document.getElementById('adm_name').value = result.data.adm_name;
                        document.getElementById('learning_group').value = result.data.learning_group;
                        document.getElementById('school_name').value = result.data.school_name;
                    } else {
                        console.error(result.message);
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        }
    }

    // ฟังก์ชันตรวจสอบก่อนกดปุ่มดำเนินการต่อ
    function validateSelection(event) {
        const supervisorName = document.getElementById('supervisor_name').value.trim();
        const teacherName = document.getElementById('teacher_name_input').value.trim();
        const teacherPid = document.getElementById('t_pid').value.trim(); 

        // เช็คว่ามีการกรอกชื่อไหม
        if (supervisorName === '' || teacherName === '') {
            alert('โปรดเลือกข้อมูลผู้นิเทศและผู้รับนิเทศให้ครบถ้วนก่อนดำเนินการต่อ');
            event.preventDefault(); // หยุดการ submit ฟอร์ม
            return false;
        }
        
        if (teacherPid === '') { 
            alert('ชื่อผู้รับนิเทศไม่ถูกต้อง โปรดเลือกจากรายชื่อที่ระบบแนะนำเท่านั้น');
            event.preventDefault(); // หยุดการ submit ฟอร์ม
            return false;
        }

        return true; 
    }
</script>