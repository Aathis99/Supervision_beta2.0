   <?php
    // teacher_form.php


    // ดึงรายชื่อครูสำหรับ Datalist
    // ⭐️ แก้ไข: ดึง t_pid มาด้วยเพื่อใช้เป็น key ที่แม่นยำ
    $sql_teachers = "SELECT t_pid, CONCAT(IFNULL(PrefixName,''), ' ', Fname, ' ', Lname) AS full_name_display FROM teacher ORDER BY Fname ASC";
    $result_teachers = $conn->query($sql_teachers);
    ?>

   <hr>
   <div class="card-body">
       <h5 class="card-title fw-bold">ข้อมูลผู้รับนิเทศ</h5>
       <hr>
       <div class="row g-3">

           <div class="col-md-6">
               <label for="teacher_name_input" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>
               <input list="teacher_names_list" id="teacher_name_input" name="teacher_name"
                   class="form-control search-field"
                   placeholder="-- พิมพ์เพื่อค้นหา --">

               <datalist id="teacher_names_list">
                   <?php
                    if ($result_teachers) {
                        while ($row_teacher = $result_teachers->fetch_assoc()) {
                            // ⭐️ แก้ไข: เพิ่ม data-pid เข้าไปใน option เพื่อใช้ในการค้นหา
                            echo '<option value="' . htmlspecialchars(trim($row_teacher['full_name_display'])) . '" data-pid="' . htmlspecialchars($row_teacher['t_pid']) . '">';
                        }
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
           // ⭐️ แก้ไข: เปลี่ยนมาใช้ Event Listener เพื่อดึงข้อมูลจาก data-pid
           document.addEventListener('DOMContentLoaded', function() {
               const teacherInput = document.getElementById('teacher_name_input');
               const teacherList = document.getElementById('teacher_names_list');

               teacherInput.addEventListener('input', function(e) {
                   const inputValue = e.target.value;
                   let selectedPid = null;

                   // วนลูปหา option ที่ตรงกับค่าที่ป้อน
                   for (const option of teacherList.options) {
                       if (option.value === inputValue) {
                           selectedPid = option.getAttribute('data-pid');
                           break;
                       }
                   }

                   // ถ้าเจอ pid ให้เรียกฟังก์ชัน fetch
                   if (selectedPid) {
                       fetchTeacherData(selectedPid);
                   }
               });
           });

           // ⭐️ แก้ไข: ฟังก์ชัน fetchTeacherData จะรับ t_pid แทน selectedName
           function fetchTeacherData(teacherPid) {
               const tidField = document.getElementById('t_pid');
               const admNameField = document.getElementById('adm_name');
               const learningGroupField = document.getElementById('learning_group');
               const schoolNameField = document.getElementById('school_name');

               tidField.value = '';
               admNameField.value = '';
               learningGroupField.value = '';
               schoolNameField.value = '';

               if (teacherPid) {
                   // ⭐️ แก้ไข: ส่ง t_pid ไปแทน full_name
                   fetch(`fetch_teacher.php?t_pid=${encodeURIComponent(teacherPid)}`)
                       .then(response => response.json())
                       .then(result => {
                           if (result.success) {
                               tidField.value = result.data.t_pid;
                               admNameField.value = result.data.adm_name;
                               learningGroupField.value = result.data.learning_group;
                               schoolNameField.value = result.data.school_name;
                           } else {
                               console.error(result.message);
                           }
                       })
                       .catch(error => {
                           console.error('AJAX Error:', error);
                       });
               }
           }

           function validateSelection() {
               const supervisorName = document.getElementById('supervisor_name').value.trim();
               const teacherName = document.getElementById('teacher_name_input').value.trim();
               const teacherPid = document.getElementById('t_pid').value.trim(); // ⭐️ เพิ่ม: ดึงค่า t_pid

               if (supervisorName === '' || teacherName === '') {
                   alert('โปรดเลือกข้อมูลผู้นิเทศและผู้รับนิเทศให้ครบถ้วนก่อนดำเนินการต่อ');
                   return false; // หยุดการส่งฟอร์ม
               }
               if (teacherPid === '') { // ⭐️ เพิ่ม: ตรวจสอบว่า t_pid มีค่าหรือไม่
                   alert('โปรดเลือกชื่อผู้รับนิเทศจากรายการที่มีอยู่ให้ถูกต้อง');
                   return false;
               }

               // หากมีการเลือกแบบฟอร์มแล้ว (จากโค้ดที่คุณย้ายมา) ให้ตรวจสอบต่อ
               // หากต้องการให้บังคับเลือกแบบฟอร์ม   ด้วย ให้เพิ่ม Logic ตรงนี้
               // เช่น:
               // const formSelected = document.querySelector('input[name="evaluation_type"]:checked');
               // if (!formSelected) {
               //     alert('โปรดเลือกแบบฟอร์มประเมินก่อนดำเนินการต่อ');
               //     return false;
               // }

               return true; // อนุญาตให้ส่งฟอร์ม
           }
           // ⭐️ สิ้นสุดฟังก์ชัน validateSelection() ⭐️
       </script>