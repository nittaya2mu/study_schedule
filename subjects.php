<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'รายวิชา';
$activeNav = 'subjects';
$pageScripts = ['subjects.js'];
$topbarActions = '<button class="btn btn-primary" id="addSubjectBtn">+ เพิ่มรายวิชา</button>';
require __DIR__ . '/includes/header.php';
?>
<div class="split">
  <section>
    <div class="toolbar">
      <input type="search" id="subjectSearch" placeholder="ค้นหารายวิชา…" aria-label="ค้นหารายวิชา">
      <select id="subjectSort" aria-label="เรียงลำดับ">
        <option value="exam">เรียงตามวันสอบ</option>
        <option value="priority">เรียงตามความสำคัญ</option>
        <option value="difficulty">เรียงตามความยาก</option>
        <option value="name">เรียงตามชื่อ</option>
      </select>
    </div>
    <div id="subjectList" class="subject-grid"><p class="muted">กำลังโหลด…</p></div>
  </section>

  <aside class="card detail-panel" id="detailPanel" hidden>
    <div class="card-head">
      <h2 id="detailTitle">รายละเอียด</h2>
      <button class="icon-btn" id="closeDetail" aria-label="ปิด">✕</button>
    </div>
    <div id="detailBody"></div>
  </aside>
</div>

<!-- Modal: เพิ่ม/แก้ไขรายวิชา -->
<dialog id="subjectModal" class="modal">
  <form method="dialog" id="subjectForm" class="form" novalidate>
    <h2 id="subjectModalTitle">เพิ่มรายวิชา</h2>
    <input type="hidden" name="subject_id">
    <label>ชื่อวิชา (Subject)
      <input name="subject_name" required maxlength="150" placeholder="เช่น Data Structures">
    </label>
    <div class="grid-2">
      <label>วันสอบ (Exam Date)
        <input type="date" name="exam_date" required>
      </label>
      <label>ชั่วโมงที่ต้องอ่าน (Study Hours)
        <input type="number" name="total_hours" min="0.5" max="500" step="0.5" required value="10">
      </label>
    </div>
    <div class="grid-2">
      <label>ความยาก (Difficulty): <output id="diffOut">3</output>/5
        <input type="range" name="difficulty" min="1" max="5" value="3">
      </label>
      <label>ความสำคัญ (Priority)
        <select name="priority">
          <option value="High">High — สำคัญมาก</option>
          <option value="Medium" selected>Medium — ปานกลาง</option>
          <option value="Low">Low — สำคัญน้อย</option>
        </select>
      </label>
    </div>
    <fieldset class="color-field">
      <legend>สี (Color)</legend>
      <div class="swatches" id="swatches"></div>
      <input type="color" name="color" value="#2a78d6" aria-label="เลือกสีเอง">
    </fieldset>
    <label>คำอธิบาย
      <textarea name="description" rows="2" maxlength="2000" placeholder="(ไม่บังคับ)"></textarea>
    </label>
    <div id="topicsInit">
      <label>หัวข้อ (Topics) — คั่นด้วยบรรทัดใหม่, ใส่ชั่วโมงหลัง <code>|</code> ได้
        <textarea name="topics_text" rows="4" placeholder="Tree | 4&#10;Graph | 4&#10;Sorting | 2"></textarea>
      </label>
    </div>
    <p class="form-error" id="subjectFormError" hidden></p>
    <div class="modal-actions">
      <button type="button" class="btn" data-close>ยกเลิก</button>
      <button type="submit" class="btn btn-primary" value="save">บันทึก</button>
    </div>
  </form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
