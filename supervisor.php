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
        
        <form method="POST" action="summary.php" onsubmit="return validateSelection(event);"> 
        
            <div class="card-body">
                <h5 class="card-title fw-bold">ข้อมูลผู้นิเทศ</h5>
                <hr>
                <input type="hidden" id="supervisor_id" name="supervisor_pid">
                
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label for="supervisor_name" class="form-label fw-bold">ชื่อผู้นิเทศ</label>
                        <select id="supervisor_name" name="supervisor_name" class="form-select search-field" onchange="fetchPersonnelData()">
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
                });
            })
            .catch(error => console.error('Error fetching supervisor names:', error));
    }

    function fetchPersonnelData() {
        const selectedName = document.getElementById('supervisor_name').value; 
        const pidField = document.getElementById('p_id');
        const agencyField = document.getElementById('agency'); 
        const positionField = document.getElementById('position');
        // ⭐️ FIX: ตัวแปรสำหรับ input ที่ซ่อนไว้
        const supervisorIdHiddenField = document.getElementById('supervisor_id'); 

        pidField.value = '';
        agencyField.value = ''; 
        positionField.value = '';
        // ⭐️ FIX: ล้างค่า input ที่ซ่อนไว้
        supervisorIdHiddenField.value = ''; 

        if (selectedName) {
            // ⭐️ NOTE: สมมติว่า fetch_supervisor.php สามารถดึงข้อมูลบุคลากรตามชื่อได้
            fetch(`fetch_supervisor.php?full_name=${encodeURIComponent(selectedName)}`) 
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        pidField.value = result.data.p_id;
                        agencyField.value = result.data.OfficeName; 
                        positionField.value = result.data.position;
                        // ⭐️ FIX: อัพเดตค่าใน input ที่ซ่อนไว้
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