<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'ข้อมูลส่วนตัว';
$activeNav = 'profile';
$pageScripts = ['profile.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="grid-2 align-start">
  <section class="card">
    <div class="card-head"><h2>ข้อมูลบัญชี</h2></div>
    <form id="profileForm" class="form" novalidate>
      <label>ชื่อ-นามสกุล <input name="name" value="<?= e($user['name']) ?>" required maxlength="100"></label>
      <label>อีเมล <input type="email" name="email" value="<?= e($user['email']) ?>" required></label>
      <p class="muted small">สมัครสมาชิกเมื่อ <?= e(date('d/m/Y', strtotime($user['created_at']))) ?></p>
      <button class="btn btn-primary" type="submit">บันทึก</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><h2>เปลี่ยนรหัสผ่าน</h2></div>
    <form id="passwordForm" class="form" novalidate>
      <label>รหัสผ่านปัจจุบัน <input type="password" name="current_password" required autocomplete="current-password"></label>
      <label>รหัสผ่านใหม่ (อย่างน้อย 8 ตัว) <input type="password" name="new_password" minlength="8" required autocomplete="new-password"></label>
      <label>ยืนยันรหัสผ่านใหม่ <input type="password" name="confirm" minlength="8" required autocomplete="new-password"></label>
      <button class="btn btn-primary" type="submit">เปลี่ยนรหัสผ่าน</button>
    </form>
  </section>

  <section class="card">
    <div class="card-head"><h2>การตั้งค่าการอ่าน</h2><a class="link" href="availability.php">แก้ไข →</a></div>
    <dl class="dl">
      <dt>ชั่วโมงที่ต้องการอ่านต่อวัน</dt><dd><?= e((float)$user['daily_target_hours']) ?> ชม.</dd>
      <dt>ชั่วโมงสูงสุดต่อวัน</dt><dd><?= e((float)$user['daily_max_hours']) ?> ชม.</dd>
      <dt>ความยาว session / เวลาพัก</dt><dd><?= e($user['session_minutes']) ?> / <?= e($user['break_minutes']) ?> นาที</dd>
      <dt>อ่านวิชาเดียวติดกันสูงสุด</dt><dd><?= e($user['max_consecutive']) ?> session</dd>
    </dl>
  </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
