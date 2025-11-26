   <?php
    // teacher_form.php
    // ตรวจสอบการเชื่อมต่อ
    if (!isset($conn)) {
        // หาก $conn ไม่มีอยู่ ให้ทำการเชื่อมต่อใหม่ (ไม่ควรเกิดขึ้นถ้า index.php ทำงานถูกต้อง)
        require_once 'config/db_connect.php';
    }

    // ดึงรายชื่อกลุ่มสาระการเรียนรู้สำหรับ Datalist จาก view_teacher_core_groups
    $sql_groups = "SELECT DISTINCT core_learning_group FROM view_teacher_core_groups WHERE core_learning_group IS NOT NULL AND core_learning_group != '' COLLATE utf8mb4_general_ci ORDER BY core_learning_group ASC";
    $result_groups = $conn->query($sql_groups);


    // ดึงรายชื่อครูสำหรับ Datalist
    $sql_teachers = "SELECT CONCAT(IFNULL(PrefixName,''), ' ', Fname, ' ', Lname) AS full_name_display FROM teacher ORDER BY Fname ASC";
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
                   placeholder="-- พิมพ์เพื่อค้นหา --"
                   onchange="fetchTeacherData(this.value)">

               <datalist id="teacher_names_list">
                   <?php
                    if ($result_teachers) {
                        while ($row_teacher = $result_teachers->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars(trim($row_teacher['full_name_display'])) . '">';
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
               <input list="learning_groups_list" id="learning_group" name="learning_group" class="form-control display-field" placeholder="--" readonly>
               <datalist id="learning_groups_list">
                   <?php
                    if ($result_groups) {
                        while ($row_group = $result_groups->fetch_assoc()) {
                            echo '<option value="' . htmlspecialchars($row_group['core_learning_group']) . '">';
                        }
                    }
                    ?>
               </datalist>
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
                   <button type="submit" class="btn btn-success btn-lg">
                       ดำเนินการต่อ
                   </button>
               </div>
           </div>

       </div>

       </form>

       <script>
           // ฟังก์ชันสำหรับดึงข้อมูลผู้รับนิเทศเมื่อมีการเลือกชื่อ
           function fetchTeacherData(selectedName) {
               const tidField = document.getElementById('t_pid');
               const admNameField = document.getElementById('adm_name');
               const learningGroupField = document.getElementById('learning_group');
               const schoolNameField = document.getElementById('school_name'); // เพิ่มตัวแปรสำหรับ input โรงเรียน

               tidField.value = '';
               admNameField.value = '';
               learningGroupField.value = '';
               schoolNameField.value = ''; // ล้างค่าเดิม

               if (selectedName) {
                   fetch(`fetch_teacher.php?full_name=${encodeURIComponent(selectedName)}`)
                       .then(response => response.json())
                       .then(result => {
                           if (result.success) {
                               tidField.value = result.data.t_pid;
                               admNameField.value = result.data.adm_name;
                               learningGroupField.value = result.data.learning_group;
                               schoolNameField.value = result.data.school_name; // นำข้อมูลโรงเรียนมาแสดง
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

               if (supervisorName === '' || teacherName === '') {
                   alert('โปรดเลือกข้อมูลผู้นิเทศและผู้รับนิเทศให้ครบถ้วนก่อนดำเนินการต่อ');
                   return false; // หยุดการส่งฟอร์ม
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