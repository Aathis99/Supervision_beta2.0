<?php
// ไฟล์: quick_win_chart.php
// ส่วนแสดงผลของกราฟสรุปการนิเทศ (Quick Win) แต่ละโรงเรียน

// ตรวจสอบว่ามีข้อมูลส่งมาหรือไม่
if (!isset($dashboard_data) || empty($dashboard_data)) {
    echo "<div class='alert alert-warning text-center'>ไม่มีข้อมูลสำหรับแสดงผลกราฟ Quick Win</div>";
    return; // หยุดการทำงานถ้าไม่มีข้อมูล
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

            <!-- ส่วนของตารางข้อมูล -->
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-primary" style="background-color: #6f42c1; color: white;">
                        <tr class="text-center">
                            <th scope="col">โรงเรียน</th>
                            <th scope="col">จำนวนครั้งที่นิเทศ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dashboard_data as $data): ?>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // --- กราฟสรุปการนิเทศ Quick Win แต่ละโรงเรียน (Bar Chart) ---
    const quickWinCtx = document.getElementById('quickWinSchoolChart').getContext('2d');
    const labels = <?php echo $chart_labels; ?>;
    const dataValues = <?php echo $chart_values; ?>;
    const bgColors = <?php echo $js_background_colors; ?>;

    new Chart(quickWinCtx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'จำนวนครั้งที่นิเทศ',
                data: dataValues,
                backgroundColor: bgColors,
                borderColor: bgColors.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            responsive: true,
            plugins: {
                legend: { display: false }, 
                datalabels: { anchor: 'end', align: 'top', color: '#363636', font: { weight: 'bold' } } 
            }
        }
    });
});
</script>