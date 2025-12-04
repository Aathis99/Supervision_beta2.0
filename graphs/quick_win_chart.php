<?php
// ไฟล์: graphs/quick_win_chart.php
// แสดงกราฟสรุปจำนวนการนิเทศแบบจุดเน้น (Quick Win) แยกตามโรงเรียน

// ตรวจสอบการเชื่อมต่อฐานข้อมูล
// หาก $conn ไม่มีอยู่, ไม่ใช่ object ของ mysqli, หรือการเชื่อมต่อถูกปิดไปแล้ว ให้เชื่อมต่อใหม่
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    require '../config/db_connect.php';
}

// ----------------------------------------
// 1) ดึงข้อมูลจาก quick_win + teacher + school
// ----------------------------------------
$sql = "
    SELECT 
        s.SchoolName,
        COUNT(*) AS supervision_count
    FROM quick_win qw
    INNER JOIN teacher t ON qw.t_id = t.t_pid
    LEFT  JOIN school  s ON t.school_id = s.school_id
    GROUP BY s.school_id
    ORDER BY supervision_count DESC, s.SchoolName ASC
";

$result = $conn->query($sql);

$dashboard_data = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $dashboard_data[] = $row;
    }
}

// ถ้าไม่มีข้อมูลเลย ให้แจ้งเตือนแล้วจบ
if (empty($dashboard_data)) {
    echo "<div class='alert alert-warning text-center mt-3'>
            ไม่มีข้อมูลสำหรับแสดงผลกราฟ Quick Win
          </div>";
    return;
}

// ----------------------------------------
// 2) เตรียมข้อมูลสำหรับ Chart.js
// ----------------------------------------
$labels       = [];
$data_values  = [];

foreach ($dashboard_data as $row) {
    $labels[]      = $row['SchoolName'];
    $data_values[] = (int)$row['supervision_count'];
}

$chart_labels = json_encode($labels, JSON_UNESCAPED_UNICODE);
$chart_values = json_encode($data_values);

// สีสำหรับแต่ละโรงเรียน (วนลูปใช้ซ้ำได้)
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

$background_colors = [];
$count = count($dashboard_data);
for ($i = 0; $i < $count; $i++) {
    $background_colors[] = $colors[$i % count($colors)];
}
$js_background_colors = json_encode($background_colors);

?>

<div class="card shadow-sm mt-4">
    <div class="card-header card-header-custom text-center" style="background-color: #6f42c1;">
        <h2 class="h4 mb-0">
            <i class="fas fa-trophy"></i> 
            สรุปจำนวนการนิเทศ (Quick Win) ในแต่ละโรงเรียน
        </h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">
                    กราฟแสดงจำนวนครั้งที่ได้รับการนิเทศ
                </h5>
                <canvas id="quickWinSchoolChart"></canvas>
            </div>

            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <table class="table table-striped table-hover table-bordered">
                    <thead style="background-color: #6f42c1; color: white;">
                        <tr class="text-center">
                            <th scope="col">โรงเรียน</th>
                            <th scope="col">จำนวนครั้งที่นิเทศ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_data as $data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($data['SchoolName']); ?></td>
                                <td class="text-center"><?php echo (int)$data['supervision_count']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const quickWinCtx  = document.getElementById('quickWinSchoolChart').getContext('2d');
    const labels       = <?php echo $chart_labels; ?>;
    const dataValues   = <?php echo $chart_values; ?>;
    const bgColors     = <?php echo $js_background_colors; ?>;

    new Chart(quickWinCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'จำนวนครั้งที่นิเทศ',
                data: dataValues,
                backgroundColor: bgColors,
                borderColor: bgColors,   // ใช้สีเดียวกันเป็นขอบ
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                // ถ้าใช้ plugin datalabels อยู่ ให้เปิดค่านี้ได้
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
