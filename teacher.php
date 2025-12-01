   <?php
    ?>

   <hr>
   <div class="card-body">
       <h5 class="card-title fw-bold">ข้อมูลผู้รับนิเทศ</h5>
       <hr>
       <div class="row g-3">

           <div class="col-md-6">
               <label for="teacher_name_input" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>
               <select id="teacher_name_input" name="teacher_name" class="form-select search-field">
                   <option value="">-- กรุณาเลือกชื่อผู้รับนิเทศ --</option>
               </select>
               <input type="hidden" id="teacher_pid_hidden" name="t_pid">
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
           function populateTeacherNameDropdown() {
               const selectElement = document.getElementById('teacher_name_input');
               if (!selectElement) return;

               fetch('fetch_teacher.php?action=get_names')
                   .then(response => response.json())
                   .then(names => {
                       names.forEach(name => {
                           const option = document.createElement('option');
                           option.value = name;
                           option.textContent = name;
                           selectElement.appendChild(option);
                       });
                   })
                   .catch(error => console.error('Error fetching teacher names:', error));
           }

           function fetchTeacherData() {
               const selectedName = document.getElementById('teacher_name_input').value;
               const pidField = document.getElementById('t_pid');
               const admNameField = document.getElementById('adm_name');
               const learningGroupField = document.getElementById('learning_group');
               const schoolNameField = document.getElementById('school_name');
               const teacherPidHiddenField = document.getElementById('teacher_pid_hidden');

               // Clear all fields on change
               pidField.value = '';
               admNameField.value = '';
               learningGroupField.value = '';
               schoolNameField.value = '';
               teacherPidHiddenField.value = '';

               if (selectedName) {
                   fetch(`fetch_teacher.php?full_name=${encodeURIComponent(selectedName)}`)
                       .then(response => response.json())
                       .then(result => {
                           if (result.success) {
                               pidField.value = result.data.t_pid;
                               admNameField.value = result.data.adm_name;
                               learningGroupField.value = result.data.core_learning_group;
                               schoolNameField.value = result.data.school_name;
                               teacherPidHiddenField.value = result.data.t_pid; // Update hidden field
                           } else {
                               console.error(result.message);
                           }
                       })
                       .catch(error => console.error('AJAX Error:', error));
               }
           }

           // Add event listeners
           document.addEventListener('DOMContentLoaded', populateTeacherNameDropdown);
           document.getElementById('teacher_name_input').addEventListener('change', fetchTeacherData);

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