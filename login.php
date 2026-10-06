<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth_layout.php';

if (current_user_id()) { header('Location: dashboard.php'); exit; }

$email = '';
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!csrf_valid($_POST['csrf'] ?? null)) {
        $error = 'แบบฟอร์มหมดอายุ กรุณาลองใหม่';
    } else {
        $st = db()->prepare('SELECT user_id, password FROM users WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();
        if ($u && password_verify($password, $u['password'])) {
            if (password_needs_rehash($u['password'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE users SET password = ? WHERE user_id = ?')
                    ->execute([password_hash($password, PASSWORD_DEFAULT), $u['user_id']]);
            }
            login_user((int)$u['user_id']);
            header('Location: dashboard.php');
            exit;
        }
        $error = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง';
    }
}

auth_page_start('เข้าสู่ระบบ');
?>
<h2>เข้าสู่ระบบ</h2>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form" novalidate>
  <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
  <label>อีเมล
    <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="email" autofocus>
  </label>
  <label>รหัสผ่าน
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <button class="btn btn-primary btn-block" type="submit">เข้าสู่ระบบ</button>
</form>
<p class="muted center">ยังไม่มีบัญชี? <a href="register.php">สมัครสมาชิก</a></p>
<p class="hint center">บัญชีทดลอง: <code>demo@example.com</code> / <code>demo1234</code></p>
<?php auth_page_end();
