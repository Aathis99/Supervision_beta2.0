<?php
session_start();
require_once 'config/db_connect.php';
require_once 'check_login.php'; // ตรวจสอบการล็อกอิน

// รับค่าการค้นหา (ถ้ามี)
$search_name = $_GET['search_name'] ?? '';
$results = [];

// SQL พื้นฐานสำหรับดึงข้อมูลครู
$sql = "SELECT
            t.t_pid,
            t.PrefixName,
            t.fname,
            t.lname,
            t.adm_name,
            t.school_id,
            s.SchoolName
        FROM teacher t
        LEFT JOIN school s ON t.school_id = s.school_id
        ";

$params = [];
$types = '';

// เงื่อนไขการค้นหา
if (!empty($search_name)) {
    $normalized_search = preg_replace('/\s+/', ' ', $search_name);
    $search_term = "%" . $normalized_search . "%";
    $sql .= " WHERE (CONCAT(IFNULL(t.PrefixName,''), t.fname, ' ', t.lname) LIKE ? 
              OR t.adm_name LIKE ? 
              OR s.SchoolName LIKE ?)";
    $params = [$search_term, $search_term, $search_term];
    $types = "sss";
}

$sql .= " ORDER BY t.fname, t.lname";

// ถ้าไม่มีการค้นหา ให้แสดงแค่ 10 รายการ
if (empty($search_name)) {
    $sql .= " LIMIT 10";
}

// เตรียมและดำเนินการสอบถาม
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $results[] = $row;
    }
}
$stmt->close();

// ดึงข้อมูลโรงเรียนทั้งหมดสำหรับ dropdown
$schools = [];
$school_result = $conn->query("SELECT school_id, SchoolName FROM school ORDER BY SchoolName");
if ($school_result) {
    $schools = $school_result->fetch_all(MYSQLI_ASSOC);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลครู</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .data-view {
            display: inline;
        }

        .data-edit {
            display: none;
        }

        .edit-mode .data-view {
            display: none;
        }

        .edit-mode .data-edit {
            display: block;
        }

        /* ✅ ให้คอลัมน์ชื่อ-สกุล แสดงชื่ออย่างเดียวในโหมดแก้ไข (ซ่อน input 3 ช่อง) */
        .edit-mode td[data-field="name"] .data-view {
            display: inline;
        }

        .edit-mode td[data-field="name"] .data-edit {
            display: none !important;
        }
    </style>
</head>

<body>
    <div class="container mt-5">
        <div class="card shadow-lg p-4">
            <h2 class="card-title text-center mb-4">
                <i class="fas fa-user-edit"></i> จัดการข้อมูลครู
            </h2>

            <form method="GET" action="edit_teacher_list.php" class="mb-4">
                <div class="input-group">
                    <input type="text" class="form-control"
                        placeholder="ค้นหาด้วยชื่อ, ตำแหน่ง หรือโรงเรียน..."
                        name="search_name"
                        value="<?php echo htmlspecialchars($search_name); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                    <a href="edit_teacher_list.php" class="btn btn-secondary" title="ล้างการค้นหา">
                        <i class="fas fa-redo"></i>
                    </a>
                </div>
            </form>

            <div class="d-flex justify-content-end mb-3">
                <a href="index.php" class="btn btn-danger">
                    <i class="fas fa-arrow-left"></i> กลับหน้าหลัก
                </a>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead class="table-primary">
                        <tr>
                            <th>ชื่อ-สกุล</th>
                            <th>ตำแหน่ง</th>
                            <th>โรงเรียน</th>
                            <th class="text-center" style="width: 15%;">การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($results)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-danger">
                                    ไม่พบข้อมูลครู
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($results as $row): ?>
                                <?php
                                // รายการตำแหน่งสำหรับดรอปดาว
                                $positions = [
                                    'ไม่มีวิทยฐานะ',
                                    'ชำนาญการ',
                                    'ชำนาญการพิเศษ',
                                    'ครูเชี่ยวชาญ',
                                    'ครูเชี่ยวชาญพิเศษ'
                                ];
                                $current_pos = $row['adm_name'] ?? '';
                                ?>
                                <tr id="row-<?php echo $row['t_pid']; ?>">
                                    <!-- ชื่อ-สกุล -->
                                    <td data-field="name">
                                        <span class="data-view">
                                            <?php echo htmlspecialchars($row['PrefixName'] . $row['fname'] . ' ' . $row['lname']); ?>
                                        </span>

                                        <!-- input เก็บค่าชื่อไว้สำหรับส่งไปอัปเดต แต่ถูกซ่อนตอน edit -->
                                        <div class="data-edit input-group mt-1">
                                            <input type="text" class="form-control form-control-sm"
                                                name="PrefixName"
                                                value="<?php echo htmlspecialchars($row['PrefixName']); ?>"
                                                placeholder="คำนำหน้า">
                                            <input type="text" class="form-control form-control-sm"
                                                name="fname"
                                                value="<?php echo htmlspecialchars($row['fname']); ?>"
                                                placeholder="ชื่อ">
                                            <input type="text" class="form-control form-control-sm"
                                                name="lname"
                                                value="<?php echo htmlspecialchars($row['lname']); ?>"
                                                placeholder="นามสกุล">
                                        </div>
                                    </td>

                                    <!-- ตำแหน่ง: ดรอปดาว -->
                                    <td data-field="position">
                                        <span class="data-view">
                                            <?php echo htmlspecialchars($row['adm_name']); ?>
                                        </span>
                                        <select class="form-select form-select-sm data-edit" name="adm_name">
                                            <?php foreach ($positions as $pos): ?>
                                                <option value="<?php echo htmlspecialchars($pos); ?>"
                                                    <?php echo ($current_pos === $pos) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($pos); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- โรงเรียน -->
                                    <td data-field="school">
                                        <span class="data-view">
                                            <?php echo htmlspecialchars($row['SchoolName']); ?>
                                        </span>
                                        <select class="form-select form-select-sm data-edit" name="school_id">
                                            <?php foreach ($schools as $school): ?>
                                                <option value="<?php echo $school['school_id']; ?>"
                                                    <?php echo ($school['school_id'] == $row['school_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($school['SchoolName']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>

                                    <!-- ปุ่มจัดการ -->
                                    <td class="text-center" data-field="actions">
                                        <div class="action-view">
                                            <button class="btn btn-sm btn-warning btn-edit"
                                                data-pid="<?php echo $row['t_pid']; ?>">
                                                <i class="fas fa-edit"></i> แก้ไข
                                            </button>
                                        </div>
                                        <div class="action-edit" style="display:none;">
                                            <button class="btn btn-sm btn-success btn-save"
                                                data-pid="<?php echo $row['t_pid']; ?>">
                                                <i class="fas fa-save"></i> บันทึก
                                            </button>
                                            <button class="btn btn-sm btn-danger btn-cancel"
                                                data-pid="<?php echo $row['t_pid']; ?>">
                                                <i class="fas fa-times"></i> ยกเลิก
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // เข้าโหมดแก้ไข
            document.querySelectorAll('.btn-edit').forEach(button => {
                button.addEventListener('click', function() {
                    const pid = this.dataset.pid;
                    const row = document.getElementById('row-' + pid);
                    row.classList.add('edit-mode');

                    row.querySelector('.action-view').style.display = 'none';
                    row.querySelector('.action-edit').style.display = 'block';
                });
            });

            // ยกเลิกการแก้ไข
            document.querySelectorAll('.btn-cancel').forEach(button => {
                button.addEventListener('click', function() {
                    const pid = this.dataset.pid;
                    const row = document.getElementById('row-' + pid);
                    row.classList.remove('edit-mode');

                    row.querySelector('.action-view').style.display = 'block';
                    row.querySelector('.action-edit').style.display = 'none';
                    // ถ้าอยากรีเซ็ตค่า input กลับค่าเดิม ให้ใช้ location.reload();
                });
            });

            // บันทึกข้อมูล
            document.querySelectorAll('.btn-save').forEach(button => {
                button.addEventListener('click', function() {
                    const pid = this.dataset.pid;
                    const row = document.getElementById('row-' + pid);

                    const prefixName = row.querySelector('input[name="PrefixName"]').value;
                    const fname = row.querySelector('input[name="fname"]').value;
                    const lname = row.querySelector('input[name="lname"]').value;
                    const adm_name = row.querySelector('select[name="adm_name"]').value;
                    const school_id = row.querySelector('select[name="school_id"]').value;
                    const school_name = row.querySelector('select[name="school_id"] option:checked').text;

                    const formData = new FormData();
                    formData.append('t_pid', pid);
                    formData.append('PrefixName', prefixName);
                    formData.append('fname', fname);
                    formData.append('lname', lname);
                    formData.append('adm_name', adm_name);
                    formData.append('school_id', school_id);

                    fetch('api/update_teacher.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // อัปเดตข้อมูลใน view
                                row.querySelector('[data-field="name"] .data-view').textContent =
                                    prefixName + fname + ' ' + lname;
                                row.querySelector('[data-field="position"] .data-view').textContent =
                                    adm_name;
                                row.querySelector('[data-field="school"] .data-view').textContent =
                                    school_name;

                                row.classList.remove('edit-mode');
                                row.querySelector('.action-view').style.display = 'block';
                                row.querySelector('.action-edit').style.display = 'none';
                                alert('บันทึกข้อมูลสำเร็จ');
                            } else {
                                alert('เกิดข้อผิดพลาด: ' + data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('เกิดข้อผิดพลาดในการเชื่อมต่อ');
                        });
                });
            });
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>