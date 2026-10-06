<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_layout.php';

if (current_user_id()) { header('Location: dashboard.php'); exit; }

$name = $email = '';
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (!csrf_valid($_POST['csrf'] ?? null)) $errors[] = 'แบบฟอร์มหมดอายุ กรุณาลองใหม่';
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'กรุณากรอกชื่อ (ไม่เกิน 100 ตัวอักษร)';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'อีเมลไม่ถูกต้อง';
    if (strlen($password) < 8) $errors[] = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
    if ($password !== $confirm) $errors[] = 'รหัสผ่านยืนยันไม่ตรงกัน';

    if (!$errors) {
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $st->execute([$email]);
        if ((int)$st->fetchColumn()) $errors[] = 'อีเมลนี้ถูกใช้สมัครแล้ว';
    }
    if (!$errors) {
        $pdo = db();
        $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)')
            ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
        login_user((int)$pdo->lastInsertId());
        flash('success', 'สมัครสมาชิกสำเร็จ! เริ่มจากกำหนดเวลาว่างและเพิ่มรายวิชา');
        header('Location: availability.php');
        exit;
    }
}

auth_page_start('สมัครสมาชิก');
?>
<h2>สมัครสมาชิก</h2>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>
<form method="post" class="form" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>ชื่อ-นามสกุล
    <input type="text" name="name" value="<?= e($name) ?>" required maxlength="100" autocomplete="name" autofocus>
  </label>
  <label>อีเมล
    <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email">
  </label>
  <div class="grid-2">
    <label>รหัสผ่าน
      <input type="password" name="password" required minlength="8" autocomplete="new-password">
    </label>
    <label>ยืนยันรหัสผ่าน
      <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
    </label>
  </div>
  <button class="btn btn-primary btn-block" type="submit">สมัครสมาชิก</button>
</form>
<p class="muted center">มีบัญชีแล้ว? <a href="login.php">เข้าสู่ระบบ</a></p>
<?php auth_page_end();
