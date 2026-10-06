<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'เวลาว่าง & การตั้งค่าการอ่าน';
$activeNav = 'availability';
$pageScripts = ['availability.js'];
require __DIR__ . '/includes/header.php';
?>
<?php foreach (take_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<div class="grid-avail">
  <section class="card">
    <div class="card-head">
      <h2>เวลาว่างประจำสัปดาห์</h2>
      <button class="btn btn-primary" id="saveWeekly">บันทึกเวลาว่าง</button>
    </div>
    <p class="muted small">กำหนดช่วงที่สะดวกอ่านในแต่ละวัน และระดับความสะดวก (★★★ = ช่วงที่มีสมาธิดีที่สุด — GA จะพยายามจัดวิชาลงช่วงนี้ก่อน)</p>
    <div id="weekEditor" class="week-editor"></div>
    <div class="avail-summary" id="availSummary"></div>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-head"><h2>การตั้งค่าการอ่าน</h2></div>
      <form id="settingsForm" class="form" novalidate>
        <div class="grid-2">
          <label>ชั่วโมงที่ต้องการอ่านต่อวัน
            <input type="number" name="daily_target_hours" min="0.5" max="16" step="0.5" required>
          </label>
          <label>ชั่วโมงสูงสุดต่อวัน
            <input type="number" name="daily_max_hours" min="0.5" max="16" step="0.5" required>
          </label>
          <label>ความยาว 1 session (นาที)
            <input type="number" name="session_minutes" min="15" max="240" step="5" required>
          </label>
          <label>เวลาพักระหว่าง session (นาที)
            <input type="number" name="break_minutes" min="0" max="120" step="5" required>
          </label>
          <label>อ่านวิชาเดียวติดกันได้สูงสุด (session)
            <input type="number" name="max_consecutive" min="1" max="6" required>
          </label>
        </div>
        <button class="btn btn-primary" type="submit">บันทึกการตั้งค่า</button>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><h2>ช่วงเวลาที่ไม่สะดวกอ่าน</h2></div>
      <form id="blockedForm" class="form form-inline" novalidate>
        <label>ประเภท
          <select name="kind">
            <option value="weekly">ทุกสัปดาห์</option>
            <option value="date">เฉพาะวันที่</option>
          </select>
        </label>
        <label data-kind="weekly">วัน
          <select name="day_of_week">
            <?php foreach (DAY_NAMES_TH as $n => $d): ?><option value="<?= $n ?>"><?= e($d) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label data-kind="date" hidden>วันที่
          <input type="date" name="specific_date">
        </label>
        <label>เริ่ม <input type="time" name="start_time" required value="12:00"></label>
        <label>ถึง <input type="time" name="end_time" required value="13:00"></label>
        <label class="grow">เหตุผล <input name="reason" maxlength="150" placeholder="เช่น ทำงานพิเศษ, ออกกำลังกาย"></label>
        <button class="btn" type="submit">+ เพิ่ม</button>
      </form>
      <ul id="blockedList" class="plain-list"></ul>
    </section>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
