<?php
// ไฟล์: graphs/school_supervision_chart.php

require_once '../config/db_connect.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ---------------------------------------------------------------------------
    // SQL Query: ดึงข้อมูลจำนวนครั้งการนิเทศ แยกตามโรงเรียน
    // ---------------------------------------------------------------------------
    $query = "
        SELECT 
            s.SchoolName, 
            COUNT(*) AS supervision_count
        FROM 
            supervision_sessions ss
        INNER JOIN 
            teacher t ON ss.teacher_t_pid = t.t_pid
        INNER JOIN 
            school s ON t.school_id = s.school_id
        GROUP BY 
            s.school_id, s.SchoolName
        ORDER BY 
            supervision_count DESC
    ";

    $stmt = $pdo->query($query);
    $school_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูลสำหรับ Chart.js
    $labels = [];
    $data_values = [];

    foreach ($school_supervision_data as $row) {
        $labels[] = $row['SchoolName'];
        $data_values[] = (int)$row['supervision_count'];
    }

    $school_chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $school_chart_values = json_encode($data_values);

    // สร้างชุดสีสุ่มให้เพียงพอกับจำนวนโรงเรียน
    $colors = [
        '#007bff',
        '#6610f2',
        '#6f42c1',
        '#e83e8c',
        '#dc3545',
        '#fd7e14',
        '#ffc107',
        '#28a745',
        '#20c997',
        '#17a2b8',
        '#adb5bd',
        '#343a40',
        '#0d6efd',
        '#198754',
        '#ffc107'
    ];
    // ถ้ามีข้อมูลมากกว่าสีที่เตรียมไว้ ให้วนใช้สีเดิมซ้ำ
    $background_colors = [];
    $count = count($school_supervision_data);
    for ($i = 0; $i < $count; $i++) {
        $background_colors[] = $colors[$i % count($colors)];
    }
    $js_background_colors = json_encode($background_colors);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage() . '</div>';
    $school_supervision_data = [];
    $school_chart_labels = '[]';
    $school_chart_values = '[]';
    $js_background_colors = '[]';
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #007bff;">
        <h2 class="h4 mb-0"><i class="fas fa-school"></i> สรุปจำนวนการนิเทศในแต่ละโรงเรียน</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">กราฟแสดงจำนวนครั้งที่ได้รับการนิเทศ</h5>
                <canvas id="schoolSupervisionChart"></canvas>
            </div>

            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-primary">
                            <tr class="text-center">
                                <th scope="col">โรงเรียน</th>
                                <th scope="col">จำนวนครั้งที่นิเทศ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($school_supervision_data) > 0): ?>
                                <?php foreach ($school_supervision_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['SchoolName']); ?></td>
                                        <td class="text-center"><?php echo $data['supervision_count']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="text-center">ไม่พบข้อมูลการนิเทศ</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- กราฟสรุปการนิเทศแต่ละโรงเรียน (Bar Chart) ---
        const schoolCtx = document.getElementById('schoolSupervisionChart').getContext('2d');
        new Chart(schoolCtx, {
            type: 'bar',
            data: {
                labels: <?php echo $school_chart_labels; ?>,
                datasets: [{
                    label: 'จำนวนครั้งที่นิเทศ',
                    data: <?php echo $school_chart_values; ?>,
                    backgroundColor: <?php echo $js_background_colors; ?>,
                    borderColor: <?php echo $js_background_colors; ?>.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
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