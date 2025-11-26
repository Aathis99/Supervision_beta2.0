<?php
// ไฟล์: satisfaction_pie_chart.php
// ส่วนแสดงผลของกราฟสรุปผลความพึงพอใจ
?>
<div class="card shadow-sm">
    <div class="card-header card-header-custom text-center">
        <h2 class="h4 mb-0"><i class="fas fa-chart-pie"></i> สรุปผลความพึงพอใจต่อการนิเทศศึกษา</h2>
    </div>
    <div class="card-body p-4">
        <div class="row align-items-center">
            <!-- ส่วนของกราฟ (ปรับขนาด) -->
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">คะแนนเฉลี่ยแต่ละประเด็น</h5>
                <canvas id="satisfactionChart" style="max-height: 400px;"></canvas>
            </div>

            <!-- ส่วนของตารางข้อมูล (ย้ายขึ้นมาข้างกราฟ) -->
            <div class="col-lg-6">
                <h5 class="card-title text-center mb-3">ตารางสรุปข้อมูลดิบ</h5>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-info">
                            <tr class="text-center">
                                <th scope="col" style="width: 15%;">ข้อที่</th>
                                <th scope="col" style="width: 45%;">คะแนนเฉลี่ย</th>
                                <th scope="col" style="width: 40%;">จำนวนผู้ตอบ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dashboard_data as $data): ?>
                                <tr>
                                    <td class="text-center"><?php echo htmlspecialchars(explode('.', $data['question_text_with_number'])[0]); ?></td>
                                    <td class="text-center"><?php echo number_format($data['average_score'], 2); ?></td>
                                    <td class="text-center"><?php echo $data['response_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ส่วนของคำอธิบายสัญลักษณ์ (Legend) -->
        <div class="row mt-4">
            <div class="col-lg-12 custom-legend">
                <h5 class="card-title mb-3">ประเด็นการประเมิน</h5>
                <div class="row">
                    <?php foreach ($dashboard_data as $index => $data): ?>
                        <div class="col-md-6">
                            <div class="legend-item">
                                <div class="legend-color-box" style="background-color: <?php echo $background_colors[$index % count($background_colors)]; ?>;"></div>
                                <span>
                                    <?php 
                                    // ลบตัวเลขและจุดนำหน้า (เช่น "1. ") ออกจากข้อความ
                                    echo htmlspecialchars(preg_replace('/^\d+\.\s*/', '', $data['question_text_with_number'])); 
                                    ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// เตรียมข้อมูล response_count สำหรับใช้ใน JavaScript
$response_counts = array_column($dashboard_data, 'response_count');
$js_response_counts = json_encode($response_counts);
?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- กราฟสรุปผลความพึงพอใจ (Pie Chart) ---
    const ctx = document.getElementById('satisfactionChart').getContext('2d');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: <?php echo $chart_labels; ?>,
            datasets: [{
                label: 'คะแนนเฉลี่ย',
                data: <?php echo $chart_values; ?>,
                backgroundColor: <?php echo $js_background_colors; ?>,
                borderColor: <?php echo $js_background_colors; ?>.map(color => color.replace('0.7', '1')),
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { 
                    display: false 
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += context.formattedValue; // คะแนนเฉลี่ย
                            return label + ' (ผู้ตอบ: ' + <?php echo $js_response_counts; ?>[context.dataIndex] + ' คน)';
                        }
                    }
                },
                datalabels: {
                    // ใช้ formatter เพื่อดึงเฉพาะเลขข้อจาก label (เช่น "1. ความรวดเร็ว" จะได้ "1")
                    formatter: (value, context) => {
                        return context.chart.data.labels[context.dataIndex].split('.')[0];
                    },
                    color: '#fff', font: { weight: 'bold', size: 14 } }
            }
        }
    });
});
</script>