<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'ความก้าวหน้าในการอ่าน';
$activeNav = 'progress';
$pageScripts = ['charts.js', 'progress.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="kpi-row" id="kpis">
  <div class="kpi skeleton"></div><div class="kpi skeleton"></div><div class="kpi skeleton"></div><div class="kpi skeleton"></div>
</section>

<div class="grid-progress">
  <section class="card">
    <div class="card-head"><h2>รายวิชา</h2><span class="muted small">คลิกเพื่อดู/ติ๊กหัวข้อ</span></div>
    <div id="subjectBars"></div>
  </section>

  <div class="stack">
    <section class="card">
      <div class="card-head"><h2>ชั่วโมงที่อ่านจริง 14 วันล่าสุด</h2></div>
      <div id="dailyChart" class="chart-box"></div>
    </section>

    <section class="card">
      <div class="card-head"><h2>บันทึกการอ่านเพิ่มเติม</h2></div>
      <form id="logForm" class="form" novalidate>
        <div class="grid-2">
          <label>วิชา <select name="subject_id" required></select></label>
          <label>หัวข้อ <select name="topic_id"><option value="">—</option></select></label>
          <label>วันที่ <input type="date" name="study_date" value="<?= e(today()) ?>" max="<?= e(today()) ?>" required></label>
          <label>จำนวนนาที <input type="number" name="minutes" min="5" max="720" step="5" value="60" required></label>
        </div>
        <label>บันทึก <input name="note" maxlength="255" placeholder="(ไม่บังคับ)"></label>
        <button class="btn btn-primary" type="submit">บันทึก</button>
      </form>
    </section>
  </div>
</div>

<section class="card">
  <div class="card-head"><h2>ประวัติการอ่าน</h2></div>
  <div class="table-wrap"><table class="table" id="logTable">
    <thead><tr><th>วันที่</th><th>วิชา</th><th>หัวข้อ</th><th class="num">นาที</th><th>ที่มา</th><th>บันทึก</th><th></th></tr></thead>
    <tbody></tbody>
  </table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
