<?php
/** Layout สำหรับหน้า Login / Register (ยังไม่ได้เข้าสู่ระบบ) */
function auth_page_start(string $title): void
{ ?>
<!DOCTYPE html>
<html lang="th">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= e(APP_NAME) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml">
</head>
<body class="auth-body">
  <div class="auth-wrap">
    <section class="auth-hero">
      <img src="assets/images/logo.svg" alt="" width="56" height="56">
      <h1>Smart Study Scheduler</h1>
      <p>ระบบเว็บช่วยจัดตารางอ่านหนังสืออัตโนมัติโดยใช้อัลกอริทึมพันธุกรรม (Genetic Algorithm)</p>
      <ol class="flow">
        <li><b>Input</b> วิชา วันสอบ เวลาว่าง</li>
        <li><b>Constraint</b> ชั่วโมงสูงสุด เวลาพัก</li>
        <li><b>Genetic Algorithm</b> ค้นหาตารางที่ดีที่สุด</li>
        <li><b>Schedule</b> ปฏิทิน + ติดตามความก้าวหน้า</li>
      </ol>
    </section>
    <section class="auth-card card">
      <?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
<?php }

function auth_page_end(): void
{ ?>
    </section>
  </div>
</body>
</html>
<?php }
