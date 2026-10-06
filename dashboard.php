<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$pageScripts = ['charts.js', 'dashboard.js'];
$topbarActions = '<a class="btn btn-primary" href="generate.php">⚙ Generate Study Schedule</a>';
require __DIR__ . '/includes/header.php';
?>
<?php foreach (take_flashes() as $f): ?>
  <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>

<p class="greeting">สวัสดี <b><?= e($user['name']) ?></b> · <span id="todayLabel"></span></p>

<section class="kpi-row" id="kpis">
  <div class="kpi skeleton"></div><div class="kpi skeleton"></div><div class="kpi skeleton"></div><div class="kpi skeleton"></div>
</section>

<div class="grid-dash">
  <section class="card">
    <div class="card-head">
      <h2>Today's Study</h2>
      <a href="schedule.php" class="link">ดูตารางทั้งหมด →</a>
    </div>
    <div id="todayList" class="today-list"><p class="muted">กำลังโหลด…</p></div>
  </section>

  <section class="card">
    <div class="card-head"><h2>ความก้าวหน้ารายวิชา</h2><a href="progress.php" class="link">รายละเอียด →</a></div>
    <div id="subjectProgress"></div>
  </section>

  <section class="card">
    <div class="card-head"><h2>แผนการอ่าน 7 วันข้างหน้า</h2><span class="muted small">ชั่วโมงต่อวัน</span></div>
    <div id="weekChart" class="chart-box"></div>
  </section>

  <section class="card">
    <div class="card-head"><h2>วันสอบที่ใกล้มาถึง</h2><a href="subjects.php" class="link">จัดการวิชา →</a></div>
    <ul id="examList" class="exam-list"></ul>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
