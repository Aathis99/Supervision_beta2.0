<?php
// ไฟล์: graphs/learning_group_chart.php

require_once '../config/db_connect.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ---------------------------------------------------------------------------
    // แก้ไข SQL: เปลี่ยนจากตาราง quick_win เป็น supervision_sessions เพื่อดึงข้อมูลการนิเทศทั้งหมด
    // ---------------------------------------------------------------------------
    $query = "
        SELECT
            tca.core_learning_group AS learning_group, 
            COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count 
        FROM
            supervision_sessions ss
        INNER JOIN
            teacher t ON ss.teacher_t_pid = t.t_pid
        LEFT JOIN
            teacher_core_assignments tca ON t.t_pid = tca.t_pid
        WHERE
            tca.core_learning_group IS NOT NULL 
        GROUP BY
            tca.core_learning_group
        ORDER BY
            supervised_teacher_count DESC;
    ";

    // 3. รันคำสั่งและดึงข้อมูล
    $stmt = $pdo->query($query);
    $lg_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 4. เตรียมข้อมูลสำหรับ Chart.js
    $lg_chart_labels = [];
    $lg_chart_values = [];

    foreach ($lg_supervision_data as $data) {
        $lg_chart_labels[] = $data['learning_group'];
        $lg_chart_values[] = (int)$data['supervised_teacher_count'];
    }

    // แปลงข้อมูลเป็น JSON เพื่อส่งให้ JavaScript
    $json_chart_labels = json_encode($lg_chart_labels, JSON_UNESCAPED_UNICODE);
    $json_chart_values = json_encode($lg_chart_values, JSON_UNESCAPED_UNICODE);

    // เตรียมสีพื้นหลัง (กำหนดสีให้เพียงพอกับจำนวนข้อมูล)
    $colors = [
        '#ffc107',
        '#0d6efd',
        '#198754',
        '#6f42c1',
        '#dc3545',
        '#0dcaf0',
        '#fd7e14',
        '#20c997',
        '#6610f2',
        '#d63384',
        '#adb5bd'
    ];
    // ตัดอาเรย์สีให้เท่ากับจำนวนข้อมูลที่มี
    $js_background_colors = json_encode(array_slice($colors, 0, count($lg_supervision_data)));
} catch (PDOException $e) {
    // กรณีเชื่อมต่อไม่ได้ ให้แสดง Error หรือค่าว่าง
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage() . '</div>';
    $lg_supervision_data = [];
    $json_chart_labels = '[]';
    $json_chart_values = '[]';
    $js_background_colors = '[]';
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #ffc107;">
        <h2 class="h4 mb-0"><i class="fas fa-book-open"></i> สรุปจำนวนครูที่ได้รับการนิเทศตามกลุ่มสาระ</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">กราฟแสดงจำนวนครูที่ได้รับการนิเทศ (คน)</h5>
                <canvas id="learningGroupChart"></canvas>
            </div>

            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-warning">
                            <tr class="text-center">
                                <th scope="col">กลุ่มสาระการเรียนรู้</th>
                                <th scope="col">จำนวนครู (คน)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($lg_supervision_data) > 0): ?>
                                <?php foreach ($lg_supervision_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['learning_group']); ?></td>
                                        <td class="text-center"><?php echo $data['supervised_teacher_count']; ?></td>
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
        const lgCtx = document.getElementById('learningGroupChart').getContext('2d');

        // รับค่าจาก PHP
        const chartLabels = <?php echo $json_chart_labels; ?>;
        const chartValues = <?php echo $json_chart_values; ?>;
        const backgroundColors = <?php echo $js_background_colors; ?>;

        new Chart(lgCtx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'จำนวนครูที่ได้รับการนิเทศ (คน)',
                    data: chartValues,
                    backgroundColor: backgroundColors,
                    borderColor: backgroundColors.map(color => color.replace('0.7', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1 // บังคับให้แกน Y แสดงเป็นจำนวนเต็ม
                        }
                    },
                    x: {
                        ticks: {
                            autoSkip: false,
                            maxRotation: 45,
                            minRotation: 0
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