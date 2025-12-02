<link rel="stylesheet" href="css/styles.css">
<?php
// ⭐️ เริ่ม Session และตรวจสอบการล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
  header("Location: login.php");
  exit;
}

// 1. เชื่อมต่อฐานข้อมูล
require_once 'config/db_connect.php';

// ดึงข้อมูลจาก Session
$inspection_data = $_SESSION['inspection_data'] ?? [];
$supervisor_id = $inspection_data['s_p_id'] ?? '';
$teacher_id = $inspection_data['t_pid'] ?? '';

// 2. ดึงข้อมูลตัวชี้วัดและคำถาม (Logic เดิมที่ดีอยู่แล้ว)
$sql = "SELECT 
            ind.id AS indicator_id, 
            ind.title AS indicator_title,
            q.id AS question_id,
            q.question_text
        FROM 
            kpi_indicators ind
        LEFT JOIN 
            kpi_questions q ON ind.id = q.indicator_id
        ORDER BY 
            ind.display_order ASC, q.display_order ASC";

$result = $conn->query($sql);

// 3. จัดกลุ่มข้อมูล + ⭐️ เพิ่มตัวนับจำนวนคำถาม ($total_questions_count)
$indicators = [];
$total_questions_count = 0; // ตัวแปรนี้สำคัญมาก เอาไว้ใช้ใน JavaScript

if ($result) {
  while ($row = $result->fetch_assoc()) {
    $indicators[$row['indicator_id']]['title'] = $row['indicator_title'];
    if ($row['question_id']) {
      $indicators[$row['indicator_id']]['questions'][] = $row;
      $total_questions_count++; // นับเพิ่มทีละ 1 เมื่อเจอคำถาม
    }
  }
}

// ดึงประวัติการนิเทศ (Logic เดิม)
$history_info = [];
if (!empty($supervisor_id) && !empty($teacher_id)) {
  $stmt_check = $conn->prepare("SELECT inspection_time, subject_code FROM supervision_sessions WHERE supervisor_p_id = ? AND teacher_t_pid = ?");
  $stmt_check->bind_param("ss", $supervisor_id, $teacher_id);
  $stmt_check->execute();
  $result_check = $stmt_check->get_result();
  while ($row_check = $result_check->fetch_assoc()) {
    $history_info[$row_check['inspection_time']][] = $row_check['subject_code'];
  }
  $stmt_check->close();
}
?>

<form id="evaluationForm" method="POST" action="save_kpi_data.php" enctype="multipart/form-data" onsubmit="return validateKpiForm()">

  <h4 class="fw-bold text-primary">ข้อมูลผู้นิเทศ</h4>
  <div class="row mb-4">
    <div class="col-md-6">
      <strong>ชื่อผู้นิเทศ:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'ไม่มีข้อมูล'); ?>
    </div>
    <div class="col-md-6">
      <strong>ผู้รับการนิเทศ:</strong> <?php echo htmlspecialchars($inspection_data['teacher_name'] ?? 'ไม่มีข้อมูล'); ?>
    </div>
  </div>

  <hr class="my-4">

  <h4 class="fw-bold text-success">กรอกข้อมูลการนิเทศ</h4>

  <div class="alert alert-info py-2">
    <small><i class="fas fa-info-circle"></i> ท่านสามารถเลือก "ครั้งที่นิเทศ" ซ้ำกับเดิมได้ หากเป็นการนิเทศใน <strong>รหัสวิชาอื่น</strong></small>
  </div>

  <div class="row g-3 mt-2 mb-4">
    <div class="col-md-6">
      <label for="subject_code" class="form-label fw-bold">รหัสวิชา</label>
      <input type="text" id="subject_code" name="subject_code" class="form-control" placeholder="เช่น ท0001" value="ท0001" required>
    </div>
    <div class="col-md-6">
      <label for="subject_name" class="form-label fw-bold">ชื่อวิชา</label>
      <input type="text" id="subject_name" name="subject_name" class="form-control" placeholder="เช่น ภาษาไทย" value="ภาษาไทย" required>
    </div>
    <div class="col-md-6">
      <label for="inspection_time" class="form-label fw-bold">ครั้งที่นิเทศ</label>
      <select id="inspection_time" name="inspection_time" class="form-select" required>
        <option value="" disabled selected>-- เลือกครั้งที่นิเทศ --</option>
        <?php for ($i = 1; $i <= 9; $i++):
          $history_text = "";
          if (isset($history_info[$i])) {
            $subjects = implode(', ', $history_info[$i]);
            $history_text = " (เคยนิเทศ: $subjects)";
          }
        ?>
          <option value="<?php echo $i; ?>">
            <?php echo $i . $history_text; ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-6">
      <label for="supervision_date" class="form-label fw-bold">วันที่การนิเทศ</label>
      <input type="date" id="supervision_date" name="supervision_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
    </div>
  </div>

  <hr class="my-5">

  <?php foreach ($indicators as $indicator_id => $indicator_data) : ?>
    <div class="section-header mb-3">
      <h2 class="h5"><?php echo htmlspecialchars($indicator_data['title']); ?></h2>
    </div>

    <?php if (!empty($indicator_data['questions'])) : ?>
      <?php foreach ($indicator_data['questions'] as $question) :
        $question_id = $question['question_id'];
      ?>
        <div class="card mb-3">
          <div class="card-body p-4">
            <div class="mb-3">
              <label class="form-label-question" for="rating_<?php echo $question_id; ?>">
                <?php echo htmlspecialchars($question['question_text']); ?>
              </label>
            </div>
            <p>เลือกคะแนนตามความพึงพอใจของคุณ</p>

            <?php for ($i = 3; $i >= 0; $i--) : ?>
              <div class="form-check form-check-inline">
                <input
                  class="form-check-input"
                  type="radio"
                  name="ratings[<?php echo $question_id; ?>]"
                  id="q<?php echo $question_id; ?>-<?php echo $i; ?>"
                  value="<?php echo $i; ?>"
                  required
                  <?php echo ($i == 3) ? 'checked' : ''; ?> /> <label class="form-check-label" for="q<?php echo $question_id; ?>-<?php echo $i; ?>"><?php echo $i; ?></label>
              </div>
            <?php endfor; ?>

            <hr class="my-4" />
            <div class="mb-3">
              <label for="comments_<?php echo $question_id; ?>" class="form-label">ข้อค้นพบ</label>
              <textarea
                class="form-control"
                id="comments_<?php echo $question_id; ?>"
                name="comments[<?php echo $question_id; ?>]"
                rows="3"
                placeholder="กรอกความคิดเห็นของคุณที่นี่...">ทดสอบกรอกข้อความในข้อค้นพบ</textarea>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <div class="card mb-4">
        <div class="card-body p-4">
          <div class="mb-3">
            <label for="indicator_suggestion_<?php echo $indicator_id; ?>" class="form-label fw-bold">ข้อเสนอแนะ</label>
            <textarea class="form-control" id="indicator_suggestion_<?php echo $indicator_id; ?>" name="indicator_suggestions[<?php echo $indicator_id; ?>]" rows="3" placeholder="กรอกข้อเสนอแนะ...">ทดสอบกรอกข้อความในข้อเสนอแนะ</textarea>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <div class="card mt-4 border-primary">
    <div class="card-header bg-primary text-white fw-bold">ข้อเสนอแนะเพิ่มเติม</div>
    <div class="card-body">
      <textarea class="form-control" id="overall_suggestion" name="overall_suggestion" rows="4" placeholder="กรอกข้อเสนอแนะเพิ่มเติมเกี่ยวกับการนิเทศครั้งนี้...">ทดสอบกรอกข้อความในข้อเสนอแนะเพิ่มเติม</textarea>
    </div>
  </div>

  <div class="d-flex justify-content-center my-4">
    <button type="submit" class="btn btn-success fs-5 btn-hover-blue px-4 py-2">
      บันทึกข้อมูล
    </button>
  </div>
</form>

<style>
  /* สไตล์เดิมที่ให้มา */
  .image-gallery {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 20px;
  }

  .image-item {
    border: 1px solid #ddd;
    padding: 10px;
    border-radius: 5px;
    text-align: center;
    position: relative;
  }

  .image-item img {
    max-width: 200px;
    max-height: 200px;
    display: block;
    margin-bottom: 10px;
  }

  .delete-btn {
    color: #fff;
    background-color: #dc3545;
    border: none;
    padding: 5px 10px;
    border-radius: 4px;
    text-decoration: none;
    cursor: pointer;
    font-size: 0.8rem;
  }

  .delete-btn:hover {
    background-color: #c82333;
  }

  .remove-preview-btn {
    position: absolute;
    top: 5px;
    right: 15px;
    background-color: rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    width: 25px;
    height: 25px;
    border: none;
    font-weight: bold;
  }
</style>

<button onclick="scrollToBottom()" class="btn btn-primary rounded-pill position-fixed bottom-0 end-0 m-3 shadow" title="เลื่อนลงล่างสุด" style="z-index: 99;">
  <i class="fas fa-arrow-down"></i>
</button>

<button onclick="scrollToTop()" id="scrollToTopBtn" class="btn btn-secondary rounded-pill position-fixed bottom-0 end-0 m-3 shadow" title="เลื่อนขึ้นบนสุด" style="z-index: 99; margin-bottom: 80px !important; display: none;">
  <i class="fas fa-arrow-up"></i>
</button>

<script>
  const scrollToTopBtn = document.getElementById("scrollToTopBtn");

  // ⭐️ รับค่าจำนวนคำถามทั้งหมดจาก PHP มาเก็บไว้ในตัวแปร JS
  const totalQuestions = <?php echo $total_questions_count; ?>;

  function validateKpiForm() {
    const subjectCode = document.getElementById('subject_code').value;
    const subjectName = document.getElementById('subject_name').value;
    const inspectionTime = document.getElementById('inspection_time').value;
    const supervisionDate = document.getElementById('supervision_date').value;

    // 1. ตรวจสอบข้อมูลหลัก
    if (!subjectCode || !subjectName || !inspectionTime || !supervisionDate) {
      alert('กรุณากรอกข้อมูลการนิเทศ (รหัสวิชา, ชื่อวิชา, ครั้งที่, วันที่) ให้ครบถ้วน');
      document.getElementById('subject_code').focus();
      return false;
    }

    // ⭐️ 2. ตรวจสอบว่าตอบคะแนนครบทุกข้อหรือไม่ (Logic ใหม่)
    const checkedRadios = document.querySelectorAll('input[type="radio"]:checked');
    if (checkedRadios.length < totalQuestions) {
      alert('คุณยังตอบคำถามไม่ครบ (ตอบไปแล้ว ' + checkedRadios.length + '/' + totalQuestions + ' ข้อ)');
      return false;
    }

    // ยืนยันก่อนส่ง
    return confirm('ยืนยันการบันทึกข้อมูลใช่หรือไม่?');
  }

  function scrollToBottom() {
    window.scrollTo(0, document.body.scrollHeight);
  }

  function scrollToTop() {
    window.scrollTo(0, 0);
  }

  window.onscroll = function() {
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
      scrollToTopBtn.style.display = "block";
    } else {
      scrollToTopBtn.style.display = "none";
    }
  };

  /* ปิด JS รูปภาพไว้ตามเดิม เพราะ HTML ส่วน input file ถูกซ่อนอยู่ */
</script>