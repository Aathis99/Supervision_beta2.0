<link rel="stylesheet" href="css/styles.css">
<?php
// ⭐️ เริ่ม Session และตรวจสอบการล็อกอิน
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['is_logged_in']) || $_SESSION['is_logged_in'] !== true) {
    header("Location: login.php"); // ถ้ายังไม่ล็อกอิน ให้ส่งกลับไปหน้า login.php
    exit;
}
// 1. เชื่อมต่อฐานข้อมูล
require_once 'config/db_connect.php';

// 2. ดึงข้อมูลตัวชี้วัดและคำถามทั้งหมดในครั้งเดียวด้วย JOIN
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
// test
// 3. จัดกลุ่มข้อมูลให้อยู่ในรูปแบบที่ใช้งานง่าย
$indicators = [];
if ($result) {
  while ($row = $result->fetch_assoc()) {
    $indicators[$row['indicator_id']]['title'] = $row['indicator_title'];
    if ($row['question_id']) { // ตรวจสอบว่ามีคำถามหรือไม่
      $indicators[$row['indicator_id']]['questions'][] = $row;
    }
  }
}

// ดึงข้อมูลจาก Session มาใช้
$inspection_data = $_SESSION['inspection_data'] ?? [];

// ⭐️ ดึงข้อมูลครั้งที่นิเทศที่เคยถูกประเมินไปแล้วของอาจารย์คนนี้
$used_times = [];
if (isset($inspection_data['t_pid'])) {
    $t_pid = $inspection_data['t_pid'];
    // เตรียม Query เพื่อดึงครั้งที่นิเทศที่เคยมีอยู่แล้ว
    $stmt_check = $conn->prepare("SELECT inspection_time FROM supervision_sessions WHERE teacher_t_pid = ?");
    $stmt_check->bind_param("s", $t_pid);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    while ($row_check = $result_check->fetch_assoc()) {
        $used_times[] = $row_check['inspection_time'];
    }
    $stmt_check->close();
}
?>
<!-- ไม่ต้องมี <html> <head> <body> เพราะไฟล์นี้จะถูก include -->

<!-- แบบฟอร์มหลักที่รวมทุกอย่าง -->
<form id="evaluationForm" method="POST" action="save_kpi_data.php" enctype="multipart/form-data" onsubmit="return validateKpiForm()">

  <!-- ================================================== -->
  <!-- ===== ส่วนแสดงข้อมูลและกรอกข้อมูลการนิเทศ (ย้ายมาที่นี่) ===== -->
  <!-- ================================================== -->
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
  <div class="row g-3 mt-2 mb-4">
    <div class="col-md-6">
      <label for="subject_code" class="form-label fw-bold">รหัสวิชา</label>
      <input type="text" id="subject_code" name="subject_code" class="form-control" placeholder="เช่น ท0001" required>
    </div>
    <div class="col-md-6">
      <label for="subject_name" class="form-label fw-bold">ชื่อวิชา</label>
      <input type="text" id="subject_name" name="subject_name" class="form-control" placeholder="เช่น ภาษาไทย" required>
    </div>
    <div class="col-md-6">
      <label for="inspection_time" class="form-label fw-bold">ครั้งที่นิเทศ</label>
      <select id="inspection_time" name="inspection_time" class="form-select" required>
        <option value="" disabled selected>-- เลือกครั้งที่นิเทศ --</option>
        <?php for ($i = 1; $i <= 9; $i++): 
            $is_disabled = in_array($i, $used_times);
            $option_text = $i . ($is_disabled ? ' (ประเมินแล้ว)' : '');
        ?>
          <option value="<?php echo $i; ?>" <?php echo $is_disabled ? 'disabled' : ''; ?>>
            <?php echo $option_text; ?>
          </option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-md-6">
          <label for="supervision_date" class="form-label fw-bold">วันที่การนิเทศ</label>
          <input type="date" id="supervision_date" name="supervision_date" class="form-control" required>
      </div>
  </div>

  <hr class="my-5">

  <!-- ================================================== -->
  <!-- ===== ส่วนของตัวชี้วัดและคำถาม (ของเดิม) ===== -->
  <!-- ================================================== -->

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
                   /> <label class="form-check-label" for="q<?php echo $question_id; ?>-<?php echo $i; ?>"><?php echo $i; ?></label>
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
                placeholder="กรอกความคิดเห็นของคุณที่นี่..."></textarea>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <!-- ส่วนสำหรับ "ข้อเสนอแนะเพิ่มเติม" ของแต่ละตัวชี้วัด -->
      <div class="card mb-4">
        <div class="card-body p-4">
          <div class="mb-3">
            <label for="indicator_suggestion_<?php echo $indicator_id; ?>" class="form-label fw-bold">ข้อเสนอแนะ</label>
            <textarea class="form-control" id="indicator_suggestion_<?php echo $indicator_id; ?>" name="indicator_suggestions[<?php echo $indicator_id; ?>]" rows="3" placeholder="กรอกข้อเสนอแนะ..."></textarea>
          </div>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <!-- ================================================== -->
  <!-- ===== ส่วนของข้อเสนอแนะภาพรวม (ของเดิม) ===== -->
  <!-- ================================================== -->
  <div class="card mt-4 border-primary">
    <div class="card-header bg-primary text-white fw-bold">ข้อเสนอแนะเพิ่มเติม</div>
    <div class="card-body">
      <textarea class="form-control" id="overall_suggestion" name="overall_suggestion" rows="4" placeholder="กรอกข้อเสนอแนะเพิ่มเติมเกี่ยวกับการนิเทศครั้งนี้..."></textarea>
    </div>
  </div>

  <!-- ================================================== -->
  <!-- ===== ส่วนอัปโหลดรูปภาพ (เพิ่มเข้ามาใหม่) ===== -->
  <!-- ================================================== -->
  <div class="card mt-4 border-info">
      <div class="card-header bg-info text-dark fw-bold">
          <i class="fas fa-images"></i> รูปภาพประกอบการนิเทศ (สูงสุด 2 รูป)
      </div>
      <div class="card-body">
          <div class="upload-form">
              <p>เลือกไฟล์รูปภาพ (JPG, PNG, GIF):</p>
              <input type="file" id="image_upload_input" class="form-control" name="image_upload[]" accept="image/jpeg,image/png,image/gif" multiple>
              <div class="form-text">คุณสามารถเลือกหลายไฟล์พร้อมกันได้ (โดยการกด Ctrl/Cmd ค้างไว้)</div>

              <!-- ส่วนสำหรับแสดงตัวอย่างรูปภาพ -->
              <div id="image-preview-container" class="image-gallery mt-3">
                  <!-- รูปตัวอย่างจะถูกเพิ่มที่นี่โดย JavaScript -->
              </div>
          </div>
      </div>
  </div>

  <div class="d-flex justify-content-center my-4">
    <button type="submit" class="btn btn-success fs-5 btn-hover-blue px-4 py-2">
      บันทึกข้อมูล
    </button>
  </div>
</form>

<style>
    /* สไตล์สำหรับส่วนแสดงรูปภาพและปุ่มลบ (ถ้ามี) */
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
    /* สไตล์สำหรับปุ่มลบรูปภาพตัวอย่าง */
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

<!-- ⭐️ ปุ่มสำหรับเลื่อนลงล่างสุด (สไตล์ Bootstrap 5) ⭐️ -->
<button onclick="scrollToBottom()" class="btn btn-primary rounded-pill position-fixed bottom-0 end-0 m-3 shadow" title="เลื่อนลงล่างสุด" style="z-index: 99;">
  <i class="fas fa-arrow-down"></i>
</button>

<!-- ⭐️ [ตัวเลือกเสริม] ปุ่มสำหรับเลื่อนขึ้นบนสุด (สไตล์ Bootstrap 5) ⭐️ -->
<button onclick="scrollToTop()" id="scrollToTopBtn" class="btn btn-secondary rounded-pill position-fixed bottom-0 end-0 m-3 shadow" title="เลื่อนขึ้นบนสุด" style="z-index: 99; margin-bottom: 80px !important; display: none;">
  <i class="fas fa-arrow-up"></i>
</button>

<script>
  // ⭐️ ดึง Element ของปุ่มเลื่อนขึ้นมา ⭐️
  const scrollToTopBtn = document.getElementById("scrollToTopBtn");

  // JavaScript Function สำหรับตรวจสอบฟอร์มก่อนบันทึก
  function validateKpiForm() {
    const subjectCode = document.getElementById('subject_code').value;
    const subjectName = document.getElementById('subject_name').value;
    const inspectionTime = document.getElementById('inspection_time').value;
    const supervisionDate = document.getElementById('supervision_date').value;

    // ตรวจสอบว่ากรอกข้อมูลการนิเทศครบหรือไม่
    if (!subjectCode || !subjectName || !inspectionTime || !supervisionDate) {
      alert('กรุณากรอกข้อมูลการนิเทศ (รหัสวิชา, ชื่อวิชา, ครั้งที่, วันที่) ให้ครบถ้วน');
      // เลื่อนหน้าจอไปยังช่องที่กรอกไม่ครบช่องแรก
      document.getElementById('subject_code').focus();
      return false;
    }

    // หากทุกอย่างถูกต้อง สามารถส่งฟอร์มได้
    return true;
  }

  // ⭐️ ฟังก์ชันสำหรับเลื่อนลงล่างสุดแบบทันที ⭐️
  function scrollToBottom() {
    window.scrollTo(0, document.body.scrollHeight);
  }

  // ⭐️ ฟังก์ชันสำหรับเลื่อนขึ้นบนสุดแบบทันที ⭐️
  function scrollToTop() {
    window.scrollTo(0, 0);
  }

  // ⭐️ ฟังก์ชันสำหรับแสดง/ซ่อนปุ่มเลื่อนขึ้นบนสุด ⭐️
  window.onscroll = function() {
    // ถ้าเลื่อนลงมามากกว่า 100px จากด้านบนสุด ให้แสดงปุ่ม
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
      scrollToTopBtn.style.display = "block";
    } else {
      // ถ้าน้อยกว่า ก็ซ่อนปุ่ม
      scrollToTopBtn.style.display = "none";
    }
  };

  // --- JavaScript สำหรับการแสดงตัวอย่างรูปภาพ ---
  const fileInput = document.getElementById('image_upload_input');
  const previewContainer = document.getElementById('image-preview-container');
  const dataTransfer = new DataTransfer(); // Object สำหรับเก็บไฟล์ที่ยังคงอยู่

  fileInput.addEventListener('change', handleFileSelect);

  function handleFileSelect(event) {
      const files = event.target.files;

      // เพิ่มไฟล์ใหม่เข้าไปใน DataTransfer
      for (const file of files) {
          dataTransfer.items.add(file);
      }

      // อัปเดตไฟล์ใน input และแสดงตัวอย่าง
      fileInput.files = dataTransfer.files;
      updatePreview();
  }

  function updatePreview() {
      previewContainer.innerHTML = ''; // ล้างตัวอย่างเก่า

      for (let i = 0; i < dataTransfer.files.length; i++) {
          const file = dataTransfer.files[i];
          const reader = new FileReader();

          reader.onload = function(e) {
              // สร้าง container สำหรับรูปและปุ่มลบ
              const previewItem = document.createElement('div');
              previewItem.className = 'image-item';

              // สร้างรูปภาพ
              const img = document.createElement('img');
              img.src = e.target.result;

              // สร้างปุ่มลบ
              const removeBtn = document.createElement('button');
              removeBtn.innerHTML = '&times;'; // เครื่องหมายกากบาท
              removeBtn.className = 'remove-preview-btn';
              removeBtn.title = 'ลบรูปนี้';
              removeBtn.onclick = function() {
                  // สร้าง DataTransfer ใหม่โดยไม่รวมไฟล์ที่ถูกลบ
                  const newFiles = new DataTransfer();
                  for (const f of dataTransfer.files) {
                      if (f !== file) {
                          newFiles.items.add(f);
                      }
                  }
                  dataTransfer.items.clear(); // ล้างของเก่า
                  for (const f of newFiles.files) dataTransfer.items.add(f); // ใส่ของใหม่กลับเข้าไป
                  fileInput.files = dataTransfer.files; // อัปเดตไฟล์ใน input
                  updatePreview(); // วาดตัวอย่างใหม่
              };

              previewItem.appendChild(img);
              previewItem.appendChild(removeBtn);
              previewContainer.appendChild(previewItem);
          }

          reader.readAsDataURL(file);
      }
  }
</script>