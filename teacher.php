<?php
// ชิ้นส่วนข้อมูล "ผู้รับนิเทศ"
// ใช้ตัวแปร $inspection_data จาก supervision_start.php
?>

<hr>
<div class="card-body">
    <h5 class="card-title fw-bold">ข้อมูลผู้รับนิเทศ</h5>
    <hr>
    <div class="row g-3">

        <div class="col-md-6">
            <label for="teacher_name_input" class="form-label fw-bold">ชื่อผู้รับนิเทศ</label>

            <div style="position: relative;">
                <div class="input-group">
                    <input id="teacher_name_input" name="teacher_name"
                           class="form-control"
                           value="<?php echo htmlspecialchars($inspection_data['teacher_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                           placeholder="-- พิมพ์ชื่อ-สกุล แล้วกดค้นหา --"
                           autocomplete="off">

                    <button class="btn btn-primary" type="button" id="search_teacher_button">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                </div>

                <!-- กล่องผลลัพธ์ -->
                <div id="teacher_results"
                     style="border:1px solid #ccc; background:#fff; width:100%;
                            display:none; position:absolute; z-index:999;
                            max-height:180px; overflow-y:auto; border-radius:4px;">
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <label for="t_pid" class="form-label fw-bold">เลขบัตรประจำตัวประชาชน</label>
            <input type="text" id="t_pid" name="t_pid"
                   class="form-control display-field" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="adm_name" class="form-label fw-bold">วิทยฐานะ</label>
            <input type="text" id="adm_name" name="adm_name"
                   class="form-control display-field" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="learning_group" class="form-label fw-bold">กลุ่มสาระการเรียนรู้</label>
            <input type="text" id="learning_group" name="learning_group"
                   class="form-control display-field" placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="school_name" class="form-label fw-bold">โรงเรียน</label>
            <input type="text" id="school_name" name="school_name"
                   class="form-control display-field" placeholder="--" readonly>
        </div>
    </div>
</div>

<script>
// เก็บ list ครูทั้งหมด
let allTeachers = [];

/**
 * เรียกจาก supervision_start.php หลัง DOM โหลด
 * เพื่อผูก event ให้ช่องค้นหาครู
 */
function initTeacherSearch() {
    const teacherInput = document.getElementById('teacher_name_input');
    const resultBox    = document.getElementById('teacher_results');
    const searchBtn    = document.getElementById('search_teacher_button');

    if (!teacherInput || !resultBox || !searchBtn) return;

    // โหลดรายชื่อครูทั้งหมด
    populateTeacherList(teachers => allTeachers = teachers);

    // ฟังก์ชันค้นหา (ใช้ได้ทั้งปุ่มค้นหาและ Enter)
    function runTeacherSearch() {
        const searchTerm = teacherInput.value.trim().toLowerCase();

        if (!searchTerm) {
            alert("กรุณากรอกชื่อก่อนค้นหา");
            return;
        }

        const results = allTeachers
            .filter(t => t.full_name_display.toLowerCase().includes(searchTerm))
            .slice(0, 5);

        resultBox.innerHTML = "";

        if (results.length === 0) {
            resultBox.style.display = "none";
            alert("ไม่พบรายชื่อที่ค้นหา");
            return;
        }

        results.forEach(teacher => {
            const item = document.createElement('div');
            item.textContent = teacher.full_name_display;
            item.style.padding = "8px";
            item.style.cursor = "pointer";

            item.addEventListener('mouseover', () => item.style.background = "#f0f0f0");
            item.addEventListener('mouseout',  () => item.style.background = "white");

            item.addEventListener('click', () => {
                teacherInput.value = teacher.full_name_display;
                resultBox.style.display = "none";
                fetchTeacherData(teacher.t_pid);
            });

            resultBox.appendChild(item);
        });

        resultBox.style.display = "block";
    }

    // คลิกปุ่มค้นหา
    searchBtn.addEventListener('click', runTeacherSearch);

    // กด Enter ในช่องชื่อ -> ค้นหา
    teacherInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') {
            e.preventDefault();
            runTeacherSearch();
        }
    });

    // พิมพ์ใหม่ -> เคลียร์ข้อมูลด้านขวา
    teacherInput.addEventListener('input', () => {
        clearTeacherData();
        resultBox.style.display = "none";
    });
}

// ดึง list ครูทั้งหมดจาก server
function populateTeacherList(callback) {
    fetch("fetch_teacher.php?action=get_names")
        .then(res => res.json())
        .then(data => {
            if (data.success) callback(data.data);
        })
        .catch(err => console.error("Error loading teacher list:", err));
}

// ล้างช่องรายละเอียดครูด้านขวา
function clearTeacherData() {
    document.getElementById('t_pid').value          = "";
    document.getElementById('adm_name').value       = "";
    document.getElementById('learning_group').value = "";
    document.getElementById('school_name').value    = "";
}

// ดึงข้อมูลครูจาก PID แล้วเติมลงช่องแสดงผล
function fetchTeacherData(pid) {
    clearTeacherData();

    fetch("fetch_teacher.php?t_pid=" + encodeURIComponent(pid))
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('t_pid').value          = data.data.t_pid;
                document.getElementById('adm_name').value       = data.data.adm_name;
                document.getElementById('learning_group').value = data.data.learning_group;
                document.getElementById('school_name').value    = data.data.school_name;
            }
        })
        .catch(err => console.error("Teacher fetch error:", err));
}
</script>
