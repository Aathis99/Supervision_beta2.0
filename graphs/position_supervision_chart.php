<?php
// ไฟล์: graphs/position_supervision_chart.php

require_once '../config/db_connect.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ---------------------------------------------------------------------------
    // SQL Query: ดึงข้อมูลจำนวนผู้รับการนิเทศ แยกตามตำแหน่ง (adm_name)
    // จากตาราง supervision_sessions (การนิเทศทั้งหมด) และ teacher (ข้อมูลครู)
    // ---------------------------------------------------------------------------
    $query = "
        SELECT
            t.adm_name AS teacher_position,
            COUNT(DISTINCT ss.teacher_t_pid) AS supervised_teacher_count
        FROM
            supervision_sessions ss
        INNER JOIN
            teacher t ON ss.teacher_t_pid = t.t_pid
        WHERE
            t.adm_name IS NOT NULL AND t.adm_name != ''
        GROUP BY
            t.adm_name
        ORDER BY
            supervised_teacher_count DESC;
    ";

    $stmt = $pdo->query($query);
    $position_supervision_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // เตรียมข้อมูลสำหรับ Chart.js
    $labels = [];
    $data_values = [];

    foreach ($position_supervision_data as $row) {
        $labels[] = $row['teacher_position'];
        $data_values[] = (int)$row['supervised_teacher_count'];
    }

    // แปลงเป็น JSON สำหรับ JavaScript
    $position_chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
    $position_chart_values = json_encode($data_values);

    // ชุดสีสำหรับกราฟ (Doughnut Chart ใช้หลายสี)
    $colors = [
        '#28a745',
        '#17a2b8',
        '#ffc107',
        '#dc3545',
        '#6610f2',
        '#e83e8c',
        '#fd7e14',
        '#20c997',
        '#007bff',
        '#6c757d',
        '#343a40'
    ];
    // ตัดชุดสีให้เท่ากับจำนวนข้อมูลที่มี
    $js_background_colors = json_encode(array_slice($colors, 0, count($position_supervision_data)));
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล: ' . $e->getMessage() . '</div>';
    $position_supervision_data = [];
    $position_chart_labels = '[]';
    $position_chart_values = '[]';
    $js_background_colors = '[]';
}
?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #28a745;">
        <h2 class="h4 mb-0"><i class="fas fa-user-graduate"></i> สรุปจำนวนผู้รับการนิเทศตามตำแหน่ง</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">กราฟแสดงสัดส่วนผู้รับการนิเทศ (คน)</h5>
                <canvas id="positionSupervisionChart"></canvas>
            </div>
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-bordered">
                        <thead class="table-success">
                            <tr class="text-center">
                                <th scope="col">ตำแหน่ง</th>
                                <th scope="col">จำนวน (คน)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($position_supervision_data) > 0): ?>
                                <?php foreach ($position_supervision_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['teacher_position']); ?></td>
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
        // --- กราฟสรุปการนิเทศตามตำแหน่งครู (Doughnut Chart) ---
        const positionCtx = document.getElementById('positionSupervisionChart').getContext('2d');

        // รับค่าจาก PHP
        const chartLabels = <?php echo $position_chart_labels; ?>;
        const chartValues = <?php echo $position_chart_values; ?>;
        const backgroundColors = <?php echo $js_background_colors; ?>;

        new Chart(positionCtx, {
            type: 'doughnut',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'จำนวนผู้รับการนิเทศ (คน)',
                    data: chartValues,
                    backgroundColor: backgroundColors,
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: true, // เปิด Legend สำหรับ Doughnut Chart เพื่อให้อ่านง่ายขึ้น
                        position: 'bottom'
                    },
                    datalabels: {
                        color: '#fff',
                        font: {
                            weight: 'bold',
                            size: 14
                        },
                        formatter: (value, ctx) => {
                            // แสดงเปอร์เซ็นต์ หรือซ่อนถ้าค่าน้อยเกินไป
                            let sum = 0;
                            let dataArr = ctx.chart.data.datasets[0].data;
                            dataArr.map(data => {
                                sum += data;
                            });
                            let percentage = (value * 100 / sum).toFixed(1) + "%";
                            if (value === 0) return "";
                            return value; // แสดงจำนวนคน
                        }
                    }
                }
            }
        });
    });
</script>