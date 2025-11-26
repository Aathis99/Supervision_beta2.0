<?php
// ไฟล์: school_supervision_chart.php
// ส่วนแสดงผลของกราฟสรุปการนิเทศแต่ละโรงเรียน
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

            <!-- ส่วนของตารางข้อมูล -->
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <table class="table table-striped table-hover table-bordered">
                    <thead class="table-primary">
                        <tr class="text-center">
                            <th scope="col">โรงเรียน</th>
                            <th scope="col">จำนวนครั้งที่นิเทศ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($school_supervision_data as $data): ?>
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
document.addEventListener('DOMContentLoaded', function () {
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
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            responsive: true,
            plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'top', color: '#363636', font: { weight: 'bold' } } }
        }
    });
});
</script>