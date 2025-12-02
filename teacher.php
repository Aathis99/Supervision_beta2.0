   <?php
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
               <!-- ⭐️ เพิ่มปุ่มย้อนกลับ ⭐️ -->
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
        let allTeachers = []; // ⭐️ ย้ายการประกาศตัวแปรมาไว้ที่นี่

        const searchButton = document.getElementById('search_teacher_button');
        const teacherInput = document.getElementById('teacher_name_input');
        const teacherList = document.getElementById('teacher_names_list');

        // Event listener สำหรับปุ่มค้นหา
        searchButton.addEventListener('click', function() {
            const searchTerm = teacherInput.value.toLowerCase();
            if (!searchTerm) return; // ถ้าไม่ได้พิมพ์อะไรก็ไม่ต้องทำอะไร

            // ⭐️ กรองรายชื่อครูจาก allTeachers และจำกัดผลลัพธ์ 5 คนแรก
            const filteredTeachers = allTeachers
                .filter(teacher => teacher.full_name_display.toLowerCase().includes(searchTerm))
                .slice(0, 5);

            // ⭐️ เพิ่มการแจ้งเตือนหากไม่พบข้อมูล
            if (filteredTeachers.length === 0) {
                alert('ไม่พบรายชื่อครูที่ตรงกับคำค้นหา');
            }

            // ⭐️ ล้าง datalist เดิมแล้วสร้างใหม่จากผลการค้นหา
            teacherList.innerHTML = '';
            filteredTeachers.forEach(teacher => {
                const option = document.createElement('option');
                option.value = teacher.full_name_display;
                option.setAttribute('data-pid', teacher.t_pid);
                teacherList.appendChild(option);
            });

            // ⭐️ ทำให้ input แสดง datalist ที่มีข้อมูลที่กรองแล้ว
            teacherInput.setAttribute('list', 'teacher_names_list');
            // ⭐️ สั่งให้ focus ที่ input อีกครั้งเพื่อให้แน่ใจว่า datalist จะแสดงขึ้นมา
            teacherInput.focus();
        });

        // ⭐️ เรียกฟังก์ชันเพื่อดึงข้อมูลครูหลังจากตั้งค่าทุกอย่างแล้ว
        // และส่งตัวแปร allTeachers เข้าไปเพื่อให้ฟังก์ชันสามารถอัปเดตค่าได้
        populateTeacherDatalist(teachers => { allTeachers = teachers; });


        // Event listener สำหรับการเลือกจาก datalist หรือการพิมพ์
        teacherInput.addEventListener('input', function(e) {
            // ⭐️ ปรับปรุง Logic: ให้ทำงานเมื่อมีการเลือกรายการจาก datalist
            const inputValue = teacherInput.value;
            let selectedTeacher = null;

            // ค้นหาข้อมูลครูที่ตรงกับค่าที่เลือกจาก `allTeachers`
            // ตรวจสอบว่าค่าที่ป้อนตรงกับรายชื่อใน allTeachers หรือไม่
            // ซึ่งจะเกิดขึ้นเมื่อผู้ใช้เลือกรายการจาก datalist
            if (allTeachers && allTeachers.length > 0) {
                selectedTeacher = allTeachers.find(teacher => teacher.full_name_display === inputValue);
            }

            // ถ้าเจอครูที่ตรงกัน (หมายถึงผู้ใช้เลือกรายการแล้ว)
            // ถ้าเจอครูที่ตรงกัน (ผู้ใช้ได้เลือกรายการแล้ว)
            if (selectedTeacher) {
                // ถ้ามีการเลือกรายการที่ถูกต้อง ให้ดึงข้อมูล
                // ดึงข้อมูลครูคนนั้นมาแสดง
                fetchTeacherData(selectedTeacher.t_pid);
                // และซ่อน datalist อีกครั้ง
                // และซ่อน datalist โดยการเอา attribute 'list' ออก
                teacherInput.removeAttribute('list');
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
    // ⭐️ แก้ไขให้รับ callback function เพื่อส่งค่า allTeachers กลับไป
    function populateTeacherDatalist(callback) {
        fetch('fetch_teacher.php?action=get_names')
            .then(response => response.json())
            .then(result => {
                let teachers = [];
                if (result.success && Array.isArray(result.data)) {
                    teachers = result.data;
                    callback(teachers); // ⭐️ ส่งข้อมูลครูกลับไปที่ callback
                }

                // หลังจากเติมรายชื่อเสร็จ, ตรวจสอบว่ามีค่าที่เคยเลือกไว้หรือไม่
                const initialTeacherName = document.getElementById('teacher_name_input').value;
                if (initialTeacherName) {
                    // ⭐️ ค้นหาจาก teachers ที่เพิ่งดึงมา
                    // เพื่อไม่ให้เกิดการแสดงผลที่ไม่ต้องการ
                    const foundTeacher = teachers.find(teacher => teacher.full_name_display === initialTeacherName);
                    if (foundTeacher) {
                        // ถ้าเจอ ให้ดึงข้อมูลครูคนนั้นมาแสดง
                        fetchTeacherData(foundTeacher.t_pid);
                    }
                }
            })
            .catch(error => console.error('Error fetching teacher names:', error));
    }

    // ฟังก์ชันล้างข้อมูล (แยกออกมาเพื่อให้เรียกใช้ได้ง่าย)
    function clearTeacherData() {
        document.getElementById('t_pid').value = '';
        document.getElementById('adm_name').value = '';
        document.getElementById('learning_group').value = '';
        document.getElementById('school_name').value = '';
    }

    // ฟังก์ชันดึงข้อมูล (ปรับปรุงใหม่)
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

    // ฟังก์ชันตรวจสอบก่อนกดปุ่มดำเนินการต่อ (ใช้ Logic เดิมของคุณแต่จะทำงานสมบูรณ์ขึ้น)
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