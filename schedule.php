<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'ตารางอ่านหนังสือ';
$activeNav = 'schedule';
$pageScripts = ['schedule.js'];
$topbarActions = '<button class="btn" id="addItemBtn">+ เพิ่ม Session</button>'
               . '<a class="btn btn-primary" href="generate.php">↻ Regenerate</a>';
require __DIR__ . '/includes/header.php';
?>
<div class="cal-toolbar">
  <div class="btn-group">
    <button class="btn" id="prevBtn" aria-label="ก่อนหน้า">‹</button>
    <button class="btn" id="todayBtn">วันนี้</button>
    <button class="btn" id="nextBtn" aria-label="ถัดไป">›</button>
  </div>
  <h2 id="rangeLabel"></h2>
  <div class="btn-group" role="tablist" aria-label="มุมมอง">
    <button class="btn active" data-view="week" role="tab">สัปดาห์</button>
    <button class="btn" data-view="day" role="tab">วัน</button>
    <button class="btn" data-view="list" role="tab">รายการ</button>
  </div>
</div>

<div id="scheduleMeta" class="schedule-meta"></div>
<div id="legend" class="legend"></div>
<div id="calendar" class="calendar-wrap"><p class="muted">กำลังโหลด…</p></div>
<p class="hint">💡 ลาก session ไปวางช่องเวลาอื่นเพื่อเลื่อนเวลาอ่าน · คลิก session เพื่อแก้ไข/บันทึกว่าอ่านแล้ว · คลิกช่องว่างเพื่อเพิ่ม session</p>

<!-- Modal: แก้ไข / เพิ่ม Session -->
<dialog id="itemModal" class="modal">
  <form method="dialog" id="itemForm" class="form" novalidate>
    <h2 id="itemModalTitle">Session</h2>
    <input type="hidden" name="schedule_item_id">
    <label>วิชา
      <select name="subject_id" required></select>
    </label>
    <label>หัวข้อ
      <select name="topic_id"><option value="">ทบทวน (Review)</option></select>
    </label>
    <div class="grid-3">
      <label>วันที่ <input type="date" name="date" required></label>
      <label>เริ่ม <input type="time" name="start_time" required></label>
      <label>สิ้นสุด <input type="time" name="end_time" required></label>
    </div>
    <label class="check"><input type="checkbox" name="is_locked" value="1"> 🔒 ล็อก session นี้ (GA จะไม่เปลี่ยนเมื่อ Regenerate)</label>
    <div id="itemWarnings"></div>
    <div class="status-row" id="statusRow">
      <span class="muted">สถานะ:</span>
      <button type="button" class="chip" data-status="planned">ยังไม่ได้อ่าน</button>
      <button type="button" class="chip" data-status="done">✓ อ่านแล้ว</button>
      <button type="button" class="chip" data-status="skipped">ข้าม</button>
    </div>
    <div class="modal-actions">
      <button type="button" class="btn btn-danger-ghost" id="deleteItemBtn">ลบ Session</button>
      <span class="spacer"></span>
      <button type="button" class="btn" data-close>ยกเลิก</button>
      <button type="submit" class="btn btn-primary" value="save">บันทึก</button>
    </div>
  </form>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
