<?php
// ไฟล์: position_supervision_chart.php
// ส่วนแสดงผลของกราฟสรุปการนิเทศตามตำแหน่งครู
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
                            <?php foreach ($position_supervision_data as $data): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($data['teacher_position']); ?></td>
                                    <td class="text-center"><?php echo $data['supervised_teacher_count']; ?></td>
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
    // --- กราฟสรุปการนิเทศตามตำแหน่งครู (Doughnut Chart) ---
    const positionCtx = document.getElementById('positionSupervisionChart').getContext('2d');
    new Chart(positionCtx, {
        type: 'doughnut',
        data: {
            labels: <?php echo $position_chart_labels; ?>,
            datasets: [{
                label: 'จำนวนผู้รับการนิเทศ (คน)',
                data: <?php echo $position_chart_values; ?>,
                backgroundColor: <?php echo $js_background_colors; ?>,
                borderColor: <?php echo $js_background_colors; ?>.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }, // ปิด legend ของ Chart.js เพราะเราสร้างเอง
                datalabels: {
                    color: '#fff',
                    font: { weight: 'bold', size: 14 }
                }
            }
        }
    });
});
</script>