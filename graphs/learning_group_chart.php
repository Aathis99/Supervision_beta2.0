<?php
// ไฟล์: learning_group_chart.php
// ส่วนแสดงผลของกราฟสรุปการนิเทศตามกลุ่มสาระการเรียนรู้
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
                            <?php foreach ($lg_supervision_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($data['learning_group'] ?? ''); ?></td>
                                    <td class="text-center"><?php echo $data['supervised_teacher_count'] ?? 0; ?></td>
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
document.addEventListener('DOMContentLoaded', function () {
    // --- กราฟสรุปการนิเทศตามกลุ่มสาระ (Bar Chart) ---
    const lgCtx = document.getElementById('learningGroupChart').getContext('2d');
    
    // ตรวจสอบและตั้งค่าเริ่มต้นให้กับตัวแปร PHP ที่ถูกส่งมา เพื่อป้องกันข้อผิดพลาด JavaScript
    const chartLabels = <?php echo $lg_chart_labels ?? '[]'; ?>;
    const chartValues = <?php echo $lg_chart_values ?? '[]'; ?>;
    const backgroundColors = <?php echo $js_background_colors ?? '["#ffc107", "#0d6efd", "#198754", "#6f42c1", "#dc3545"]'; ?>;

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
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
            responsive: true,
            plugins: { legend: { display: false }, datalabels: { anchor: 'end', align: 'top', color: '#363636', font: { weight: 'bold' } } }
        }
    });
});
</script>