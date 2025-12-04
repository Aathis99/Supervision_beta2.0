<?php
// forms/quickwin_form.php
session_start();
// ⭐️ ตรวจสอบ Path การเชื่อมต่อฐานข้อมูลให้ถูกต้อง
// สมมติว่าไฟล์ db_connect.php อยู่ในโฟลเดอร์ด้านนอกหนึ่งระดับ
require_once '../config/db_connect.php'; 

// ------------------------------------------------
// A) ตรวจสอบข้อมูลใน Session
// ------------------------------------------------
if (!isset($_SESSION['inspection_data']) || $_SESSION['inspection_data']['evaluation_type'] !== 'quickwin_form') {
    // หากไม่มีข้อมูลหรือไม่ได้เลือกฟอร์มที่ถูกต้อง ให้เด้งกลับไปหน้าหลัก
    header('Location: ../index.php'); 
    exit();
}

// ดึงข้อมูลที่เก็บไว้ใน Session
$data = $_SESSION['inspection_data'];

// กำหนดตัวแปรสำหรับแสดงผล
$supervisor_name = $data['supervisor_name'] ?? 'ไม่ระบุผู้นิเทศ';
$teacher_name = $data['teacher_name'] ?? 'ไม่ระบุผู้รับนิเทศ';
$supervisor_pid = $data['s_p_id'] ?? ''; // ⭐️ แก้ไข: ดึง s_p_id จาก session
$teacher_pid = $data['t_pid'] ?? '';       // ⭐️ แก้ไข: ดึง t_pid จาก session

// ------------------------------------------------
// B) ดึงข้อมูลหัวข้อการนิเทศจากตาราง quickwin_options
// ------------------------------------------------
// ดึง id และ OptionText จากตาราง quickwin_options
$sql_options = "SELECT OptionID, OptionText FROM quickwin_options ORDER BY OptionID ASC";
$result_options = $conn->query($sql_options);

$options = [];
if ($result_options) {
    while($row = $result_options->fetch_assoc()) {
        $options[] = $row;
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกการนิเทศแบบจุดเน้น (Quick Win)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>


<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <a href="javascript:history.back()" style="position: absolute; top: 15px; left: 15px; z-index: 1030;" class="btn btn-danger"><i class="fas fa-arrow-left"></i> ย้อนกลับ</a>
            <div class="card shadow-lg">
                <div class="card-header bg-danger text-white text-center">
                    <i class="fas fa-bullseye"></i> 
                    <span class="fw-bold">แบบฟอร์มบันทึกการนิเทศนโยบายและจุดเน้น (Quick Win)</span>
                </div>
                <div class="card-body">
                    
                    <h5 class="card-title text-primary">ข้อมูลที่เลือก</h5>
                    <div class="alert alert-primary">
                        <strong>ผู้นิเทศ:</strong> <?php echo htmlspecialchars($supervisor_name); ?><br>
                        <strong>ผู้รับนิเทศ:</strong> <?php echo htmlspecialchars($teacher_name); ?>
                    </div>
                    <hr>

                    <form action="save_quickwin_data.php" method="POST">
                        
                        <input type="hidden" name="supervisor_pid" value="<?php echo htmlspecialchars($supervisor_pid); ?>">
                        <input type="hidden" name="teacher_pid" value="<?php echo htmlspecialchars($teacher_pid); ?>">

                        <div class="mb-4">
                            <label for="quickwin_option" class="form-label fw-bold text-danger fs-5">
                                <i class="fas fa-list-check"></i> เลือกหัวข้อจุดเน้นที่จะนิเทศ:
                            </label>
                            <select class="form-select form-select-lg" id="quickwin_option" name="option_id" required>
                                <option value="" selected disabled>--- กรุณาเลือกหัวข้อจุดเน้น ---</option>
                                <?php if (!empty($options)): ?>
                                    <?php foreach ($options as $option): ?>
                                        <option value="<?php echo htmlspecialchars($option['OptionID']); ?>">
                                            <?php echo htmlspecialchars($option['OptionID'] . '. ' . $option['OptionText']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option disabled>ไม่พบข้อมูลหัวข้อการนิเทศในตาราง quickwin_options</option>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="observation_notes" class="form-label">หรือ อื่นๆ</label>
                            <textarea class="form-control" id="observation_notes" name="observation_notes" rows="5" required></textarea>
                        </div>

                        <div class="d-grid gap-2 mt-4">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> บันทึกข้อมูลการนิเทศ
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>