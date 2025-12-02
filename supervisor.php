<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>แบบบันทึกข้อมูลนิเทศ</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>

<body>

    <div class="main-card card">
        <div class="form-header card-header text-center">
            <i class="fas fa-file-alt"></i> <span class="fw-bold">แบบบันทึกข้อมูลผู้นิเทศ และ ผู้รับนิเทศ</span>
        </div>
        
            <div class="card-body">
                <h5 class="card-title fw-bold">ข้อมูลผู้นิเทศ</h5>
                <hr>
                <input type="hidden" id="supervisor_id" name="s_p_id">
                
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label for="supervisor_name" class="form-label fw-bold">ชื่อผู้นิเทศ</label>
                        <select id="supervisor_name" name="supervisor_name" class="form-select search-field" onchange="fetchPersonnelData(this.value)">
                            <option value="">-- กรุณาเลือกชื่อผู้นิเทศ --</option>
                            </select>
                    </div>

                    <div class="col-md-6">
                        <label for="p_id" class="form-label fw-bold">เลขบัตรประจำตัวประชาชน</label>
                        <input type="text" id="p_id" name="s_p_id" class="form-control display-field" placeholder="--" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="agency" class="form-label fw-bold">สังกัด</label>
                        <input type="text" id="agency" name="agency" class="form-control display-field" placeholder="--" readonly>
                    </div>

                    <div class="col-md-6">
                        <label for="position" class="form-label fw-bold">ตำแหน่ง</label>
                        <input type="text" id="position" name="position" class="form-control display-field" placeholder="--" readonly>
                    </div>
                </div>
            </div>
            <script>
    // ⭐️ ดึงค่าที่เคยเลือกไว้จาก PHP (ถ้ามี)
    const preselectedSupervisorName = <?php echo json_encode($inspection_data['supervisor_name'] ?? null); ?>;

    function populateNameDropdown() {
        const selectElement = document.getElementById('supervisor_name');
        
        if (!selectElement) return;

        // ⭐️ NOTE: สมมติว่าไฟล์ fetch_supervisor.php มีการดึงชื่อทั้งหมดมา
        fetch('fetch_supervisor.php?action=get_names')
            .then(response => response.json())
            .then(names => {
                names.forEach(name => {
                    const option = document.createElement('option');
                    option.value = name; 
                    option.textContent = name;
                    selectElement.appendChild(option);

                    // ⭐️ ถ้าชื่อนี้ตรงกับที่เคยเลือกไว้ ให้ตั้งเป็น selected
                    if (preselectedSupervisorName && name === preselectedSupervisorName) {
                        option.selected = true;
                    }
                });

                // ⭐️ ถ้ามีค่าที่เคยเลือกไว้ ให้เรียก fetchPersonnelData เพื่อเติมข้อมูลอื่น ๆ
                if (preselectedSupervisorName) {
                    fetchPersonnelData(preselectedSupervisorName);
                }
            })
            .catch(error => console.error('Error fetching supervisor names:', error));
    }

    function fetchPersonnelData(selectedName) {
        const pidField = document.getElementById('p_id');
        const agencyField = document.getElementById('agency'); 
        const positionField = document.getElementById('position');
        const supervisorIdHiddenField = document.getElementById('supervisor_id'); 

        // ⭐️ ไม่ต้องล้างค่าถ้าไม่มี selectedName (เพื่อให้ค่าที่เคยกรอกยังคงอยู่)
        if (!selectedName) return;

        if (selectedName) {
            // ⭐️ NOTE: สมมติว่า fetch_supervisor.php สามารถดึงข้อมูลบุคลากรตามชื่อได้
            fetch(`fetch_supervisor.php?full_name=${encodeURIComponent(selectedName)}`) 
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        pidField.value = result.data.p_id;
                        agencyField.value = result.data.OfficeName; 
                        positionField.value = result.data.position;
                        supervisorIdHiddenField.value = result.data.p_id;
                    } else {
                        console.error(result.message);
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                });
        }
    }
    
    // ⭐️ FIX: เรียกใช้เมื่อ DOM โหลดเสร็จ
    document.addEventListener('DOMContentLoaded', populateNameDropdown); 
</script>