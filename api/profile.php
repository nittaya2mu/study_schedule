<?php
/**
 * Profile API — แก้ไขข้อมูลส่วนตัว / เปลี่ยนรหัสผ่าน
 *   GET  ?action=get
 *   POST {action:"update", name, email}
 *   POST {action:"change_password", current_password, new_password}
 */
require_once __DIR__ . '/../includes/api.php';

api_run([
    'get' => fn() => current_user(),

    'update' => function (int $uid, array $in) {
        $name = mb_substr(v_required($in, 'name', 'ชื่อ'), 0, 100);
        $email = strtolower(v_required($in, 'email', 'อีเมล'));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new ApiError('อีเมลไม่ถูกต้อง', 422, ['email' => 'อีเมลไม่ถูกต้อง']);
        $st = db()->prepare('SELECT COUNT(*) FROM users WHERE email = ? AND user_id <> ?');
        $st->execute([$email, $uid]);
        if ((int)$st->fetchColumn()) throw new ApiError('อีเมลนี้ถูกใช้แล้ว', 422, ['email' => 'อีเมลนี้ถูกใช้แล้ว']);
        db()->prepare('UPDATE users SET name = ?, email = ? WHERE user_id = ?')->execute([$name, $email, $uid]);
        return ['saved' => true];
    },

    'change_password' => function (int $uid, array $in) {
        $st = db()->prepare('SELECT password FROM users WHERE user_id = ?');
        $st->execute([$uid]);
        if (!password_verify((string)($in['current_password'] ?? ''), (string)$st->fetchColumn())) {
            throw new ApiError('รหัสผ่านปัจจุบันไม่ถูกต้อง', 422, ['current_password' => 'ไม่ถูกต้อง']);
        }
        $new = (string)($in['new_password'] ?? '');
        if (strlen($new) < 8) throw new ApiError('รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร', 422, ['new_password' => 'อย่างน้อย 8 ตัวอักษร']);
        db()->prepare('UPDATE users SET password = ? WHERE user_id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        return ['saved' => true];
    },
], ['get']);
