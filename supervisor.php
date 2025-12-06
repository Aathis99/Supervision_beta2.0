<?php
// ชิ้นส่วนข้อมูล "ผู้นิเทศ"
// ใช้ตัวแปร $inspection_data จาก supervision_start.php
?>

<div class="card-body">
    <h5 class="card-title fw-bold">ข้อมูลผู้นิเทศ</h5>
    <hr>

    <input type="hidden" id="supervisor_id" name="s_p_id">

    <div class="row g-3">

        <div class="col-md-6">
            <label for="supervisor_name" class="form-label fw-bold">ชื่อผู้นิเทศ</label>
            <select id="supervisor_name"
                    name="supervisor_name"
                    class="form-select search-field">
                <option value="">-- กรุณาเลือกชื่อผู้นิเทศ --</option>
            </select>
        </div>

        <div class="col-md-6">
            <label for="p_id" class="form-label fw-bold">เลขบัตรประจำตัวประชาชน</label>
            <input type="text" id="p_id" name="s_p_id"
                   class="form-control display-field"
                   placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="agency" class="form-label fw-bold">สังกัด</label>
            <input type="text" id="agency" name="agency"
                   class="form-control display-field"
                   placeholder="--" readonly>
        </div>

        <div class="col-md-6">
            <label for="position" class="form-label fw-bold">ตำแหน่ง</label>
            <input type="text" id="position" name="position"
                   class="form-control display-field"
                   placeholder="--" readonly>
        </div>
    </div>
</div>

<script>
// ค่าที่เคยเลือกไว้ (ถ้ามี)
const preselectedSupervisorName = <?php
    echo json_encode($inspection_data['supervisor_name'] ?? null, JSON_UNESCAPED_UNICODE);
?>;

/**
 * ดึงรายชื่อผู้นิเทศมาเติมใน dropdown (เรียกจาก supervision_start.php)
 */
function populateSupervisorDropdown() {
    const selectElement = document.getElementById('supervisor_name');
    if (!selectElement) return;

    // ล้าง option เดิม ยกเว้นอันแรก
    while (selectElement.options.length > 1) {
        selectElement.remove(1);
    }

    fetch('fetch_supervisor.php?action=get_names')
        .then(response => response.json())
        .then(names => {
            const seen = new Set();

            names.forEach(name => {
                if (seen.has(name)) return;
                seen.add(name);

                const option = document.createElement('option');
                option.value = name;
                option.textContent = name;
                selectElement.appendChild(option);

                if (preselectedSupervisorName && name === preselectedSupervisorName) {
                    option.selected = true;
                }
            });

            // ถ้ามีค่าที่เคยเลือกไว้ ให้ดึงข้อมูลบุคลากรมาเติมช่องอื่น
            if (preselectedSupervisorName) {
                fetchSupervisorData(preselectedSupervisorName);
            }

            // เปลี่ยนชื่อแล้วให้โหลดข้อมูลชุดใหม่
            selectElement.addEventListener('change', function () {
                const selectedName = this.value;
                fetchSupervisorData(selectedName);
            });
        })
        .catch(error => console.error('Error fetching supervisor names:', error));
}

/**
 * ดึงข้อมูลบุคลากรตามชื่อ แล้วเติมในช่องด้านขวา
 */
function fetchSupervisorData(selectedName) {
    const pidField        = document.getElementById('p_id');
    const agencyField     = document.getElementById('agency');
    const positionField   = document.getElementById('position');
    const supervisorIdFld = document.getElementById('supervisor_id');

    if (!selectedName) {
        pidField.value        = '';
        agencyField.value     = '';
        positionField.value   = '';
        supervisorIdFld.value = '';
        return;
    }

    fetch('fetch_supervisor.php?full_name=' + encodeURIComponent(selectedName))
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                pidField.value        = result.data.p_id;
                agencyField.value     = result.data.OfficeName;
                positionField.value   = result.data.position;
                supervisorIdFld.value = result.data.p_id;
            } else {
                console.error(result.message);
            }
        })
        .catch(error => {
            console.error('AJAX Error:', error);
        });
}
</script>
