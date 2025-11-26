<?php
// ไฟล์: form_selector.php (HTML Fragment)
// โค้ดนี้ถูกรวมเข้าไปใน index.php ภายใต้ div.card
?>
<h5 class="mb-3 text-success">โปรดเลือกแบบฟอร์มสำหรับการดำเนินการต่อ</h5>
<form id="evaluationForm" method="POST">
    <div class="form-check mb-2">
        <input class="form-check-input" type="radio" name="evaluation_type" id="form1" value="kpi_form" required>
        <label class="form-check-label fw-bold" for="form1">
            แบบบันทึกการจัดการเรียนรู้และการจัดการชั้นเรียน
        </label>
    </div>
    <div class="form-check mb-4">
        <input class="form-check-input" type="radio" name="evaluation_type" id="form2" value="policy_form" required>
        <label class="form-check-label fw-bold" for="form2">
            แบบกรอกข้อมูลผู้รับการนิเทศนโยบายและจุดเน้นของสำนักงานเขตพื้นที่
        </label>
    </div>
</form>