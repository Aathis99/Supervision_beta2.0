<?php
// ไฟล์: graphs/quick_win_chart.php
// หน้าที่: แสดงกราฟสรุปจำนวนการนิเทศ (Quick Win) แยกตามโรงเรียน

require_once '../config/db_connect.php'; // เรียกใช้การเชื่อมต่อฐานข้อมูล

try {
    // สร้างการเชื่อมต่อ PDO (หากยังไม่มีใน db_connect.php หรือเพื่อความชัวร์)
    if (!isset($pdo)) {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // ---------------------------------------------------------------------------
    // ⭐️ แก้ไข SQL: ดึงข้อมูลจากตาราง quick_win เชื่อมกับ teacher และ school
    // ---------------------------------------------------------------------------
    $query = "
        SELECT 
            s.SchoolName, 
            COUNT(qw.t_id) AS supervision_count
        FROM 
            quick_win qw
        INNER JOIN 
            teacher t ON qw.t_id = t.t_pid
        INNER JOIN 
            school s ON t.school_id = s.school_id
        GROUP BY 
            s.school_id, s.SchoolName
        ORDER BY 
            supervision_count DESC
    ";

    $stmt = $pdo->query($query);
    $qw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมตัวแปรสำหรับ Chart.js
    $labels = [];
    $data_values = [];

    foreach ($qw_data as $row) {
        $labels[] = $row['SchoolName'];
        $data_values[] = (int)$row['supervision_count'];
    }

    // แปลงข้อมูลเป็น JSON
    $chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $chart_values = json_encode($data_values);

    // กำหนดชุดสี (Theme สีม่วงสำหรับ Quick Win)
    $colors = [
        '#6f42c1',
        '#e83e8c',
        '#d63384',
        '#fd7e14',
        '#ffc107',
        '#28a745',
        '#20c997',
        '#17a2b8',
        '#0dcaf0',
        '#6610f2'
    ];

    // วนลูปสีให้ครบจำนวนข้อมูล
    $background_colors = [];
    $data_count = count($qw_data);
    for ($i = 0; $i < $data_count; $i++) {
        $background_colors[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($background_colors);
} catch (PDOException $e) {
    // กรณี Error ให้กำหนดค่าว่างไว้ เพื่อไม่ให้กระทบส่วนแสดงผล
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</div>';
    $qw_data = [];
    $chart_labels = '[]';
    $chart_values = '[]';
    $js_background_colors = '[]';
}

// เช็คว่ามีข้อมูลหรือไม่ (เพื่อซ่อนกราฟถ้าไม่มีข้อมูล)
if (empty($qw_data)) {
    echo "<div class='alert alert-info text-center'>ยังไม่มีข้อมูลการนิเทศแบบ Quick Win</div>";
    return; // จบการทำงานของไฟล์นี้ถ้ายั่งไม่มีข้อมูล
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #6f42c1;">
        <h2 class="h4 mb-0"><i class="fas fa-trophy"></i> สรุปจำนวนการนิเทศ (Quick Win) ในแต่ละโรงเรียน</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">กราฟแสดงจำนวนครั้งที่ได้รับการนิเทศ</h5>
                <canvas id="quickWinSchoolChart"></canvas>
            </div>

            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-primary" style="background-color: #6f42c1; color: white;">
                            <tr class="text-center">
                                <th scope="col">โรงเรียน</th>
                                <th scope="col">จำนวนครั้งที่นิเทศ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qw_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($data['SchoolName']); ?></td>
                                    <td class="text-center"><?php echo $data['supervision_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- กราฟ Quick Win (Bar Chart) ---
        const ctx = document.getElementById('quickWinSchoolChart').getContext('2d');

        // รับค่าตัวแปร PHP ที่เตรียมไว้
        const labels = <?php echo $chart_labels; ?>;
        const data = <?php echo $chart_values; ?>;
        const bgColors = <?php echo $js_background_colors; ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'จำนวนครั้งที่นิเทศ (Quick Win)',
                    data: data,
                    backgroundColor: bgColors,
                    borderColor: bgColors.map(c => c.replace('0.7', '1')), // ปรับสีขอบให้เข้มขึ้นเล็กน้อย
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        } // บังคับให้แกน Y เป็นจำนวนเต็ม
                    }
                },
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }, // ซ่อน Legend เพราะมีชุดข้อมูลเดียว
                    datalabels: {
                        anchor: 'end',
                        align: 'top',
                        color: '#363636',
                        font: {
                            weight: 'bold'
                        }
                    }
                }
            }
        });
    });
</script>