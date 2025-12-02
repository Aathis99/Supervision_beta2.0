   <?php
    ?>

   <hr>
   <div class="card-body">
       <h5 class="card-title fw-bold">ข้อมูลผู้รับนิเทศ</h5>
       <hr>
       <div class="row g-3">

           <div class="col-md-6">
               <label for="teacher_name_input" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>
               <input list="teacher_names_list" id="teacher_name_input" name="teacher_name"
                   class="form-control search-field" value="<?php echo htmlspecialchars($inspection_data['teacher_name'] ?? ''); ?>"
                   placeholder="-- พิมพ์เพื่อค้นหา --">

               <datalist id="teacher_names_list">
                   <?php
                    if ($result_teachers) {
                        // ข้อมูลจะถูกเติมโดย JavaScript
                    }
                    ?>
               </datalist>
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
                   <a href="history.php" class="btn btn-secondary btn-lg">
                       <i class="fas fa-arrow-left"></i> ย้อนกลับ
                   </a>
               </div>
               <div class="col-auto">
                   <button type="submit" class="btn btn-success btn-lg">
                       ดำเนินการต่อ
                   </button>
               </div>
           </div>

       </div>

       </form>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        populateTeacherDatalist(); // เรียกฟังก์ชันเพื่อเติมรายชื่อครู

        const teacherInput = document.getElementById('teacher_name_input');
        const teacherList = document.getElementById('teacher_names_list');

        // ตรวจจับการเลือกหรือพิมพ์ในช่องชื่อ
        teacherInput.addEventListener('input', function(e) {
            const inputValue = e.target.value;
            let selectedPid = null;

            // วนลูปตรวจสอบว่าสิ่งที่พิมพ์ ตรงกับตัวเลือกใน Datalist หรือไม่
            for (const option of teacherList.options) {
                if (option.value === inputValue) {
                    selectedPid = option.getAttribute('data-pid');
                    break;
                }
            }

            if (selectedPid) {
                fetchTeacherData(selectedPid);
            } else {
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
    function populateTeacherDatalist() {
        const datalist = document.getElementById('teacher_names_list');
        fetch('fetch_teacher.php?action=get_names')
            .then(response => response.json())
            .then(result => {
                // ⭐️ แก้ไข: ตรวจสอบว่า request สำเร็จและมีข้อมูล data ที่เป็น array
                if (result.success && Array.isArray(result.data)) {
                    result.data.forEach(teacher => {
                        const option = document.createElement('option');
                        option.value = teacher.full_name_display;
                        option.setAttribute('data-pid', teacher.t_pid);
                        datalist.appendChild(option);
                    });
                }

                // หลังจากเติมรายชื่อเสร็จ, ตรวจสอบว่ามีค่าที่เคยเลือกไว้หรือไม่
                const initialTeacherName = document.getElementById('teacher_name_input').value;
                if (initialTeacherName) {
                    for (const option of datalist.options) {
                        if (option.value === initialTeacherName) {
                            fetchTeacherData(option.getAttribute('data-pid'));
                            break;
                        }
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