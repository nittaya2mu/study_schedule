<?php
/**
 * Availability API — เวลาว่าง, เวลาที่ไม่สะดวก, การตั้งค่าการอ่าน
 *   GET  ?action=list
 *   POST {action:save_weekly, windows:[{day_of_week,start_time,end_time,preference}]}
 *   POST {action:add_blocked|delete_blocked|save_settings, ...}
 */
require_once __DIR__ . '/../includes/study_service.php';

api_run([
    'list' => function (int $uid) {
        $pdo = db();
        $a = $pdo->prepare('SELECT availability_id, day_of_week, TIME_FORMAT(start_time,"%H:%i") start_time,
                                   TIME_FORMAT(end_time,"%H:%i") end_time, preference
                            FROM availability WHERE user_id = ? ORDER BY day_of_week, start_time');
        $a->execute([$uid]);
        $b = $pdo->prepare('SELECT unavailable_id, specific_date, day_of_week, TIME_FORMAT(start_time,"%H:%i") start_time,
                                   TIME_FORMAT(end_time,"%H:%i") end_time, reason
                            FROM unavailable_times WHERE user_id = ? ORDER BY specific_date IS NULL DESC, day_of_week, specific_date, start_time');
        $b->execute([$uid]);
        $u = current_user();
        return [
            'windows' => array_map(fn($r) => $r + ['day_of_week' => (int)$r['day_of_week'], 'preference' => (int)$r['preference']], $a->fetchAll()),
            'blocked' => $b->fetchAll(),
            'settings' => [
                'daily_target_hours' => (float)$u['daily_target_hours'],
                'daily_max_hours'    => (float)$u['daily_max_hours'],
                'session_minutes'    => (int)$u['session_minutes'],
                'break_minutes'      => (int)$u['break_minutes'],
                'max_consecutive'    => (int)$u['max_consecutive'],
            ],
        ];
    },

    // แทนที่ช่วงเวลาว่างทั้งสัปดาห์ด้วยชุดใหม่
    'save_weekly' => function (int $uid, array $in) {
        $windows = is_array($in['windows'] ?? null) ? $in['windows'] : [];
        $clean = [];
        foreach ($windows as $i => $w) {
            $day = v_int($w, 'day_of_week', 'วัน', 1, 7);
            $s = v_time($w, 'start_time', 'เวลาเริ่ม');
            $e = v_time($w, 'end_time', 'เวลาสิ้นสุด');
            if ($e <= $s) throw new ApiError(DAY_NAMES[$day] . " {$s}–{$e}: เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม", 422);
            foreach ($clean as $c) {
                if ($c[0] === $day && $s < $c[2] && $e > $c[1]) {
                    throw new ApiError(DAY_NAMES[$day] . ": ช่วงเวลา {$s}–{$e} ซ้อนกับ {$c[1]}–{$c[2]}", 422);
                }
            }
            $clean[] = [$day, $s, $e, v_int($w, 'preference', 'ระดับความสะดวก', 1, 3, 2)];
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM availability WHERE user_id = ?')->execute([$uid]);
        $ins = $pdo->prepare('INSERT INTO availability (user_id, day_of_week, start_time, end_time, preference) VALUES (?, ?, ?, ?, ?)');
        foreach ($clean as $c) $ins->execute([$uid, ...$c]);
        $pdo->commit();
        return ['saved' => count($clean)];
    },

    'add_blocked' => function (int $uid, array $in) {
        $date = v_date($in, 'specific_date', 'วันที่', false);
        $day = $date ? null : v_int($in, 'day_of_week', 'วัน', 1, 7);
        $s = v_time($in, 'start_time', 'เวลาเริ่ม');
        $e = v_time($in, 'end_time', 'เวลาสิ้นสุด');
        if ($e <= $s) throw new ApiError('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม', 422);
        $pdo = db();
        $pdo->prepare('INSERT INTO unavailable_times (user_id, specific_date, day_of_week, start_time, end_time, reason) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $date, $day, $s, $e, mb_substr(trim((string)($in['reason'] ?? '')), 0, 150) ?: null]);
        return ['unavailable_id' => (int)$pdo->lastInsertId()];
    },

    'delete_blocked' => function (int $uid, array $in) {
        $st = db()->prepare('DELETE FROM unavailable_times WHERE unavailable_id = ? AND user_id = ?');
        $st->execute([(int)($in['unavailable_id'] ?? 0), $uid]);
        if (!$st->rowCount()) throw new ApiError('ไม่พบรายการ', 404);
        return ['deleted' => true];
    },

    'save_settings' => function (int $uid, array $in) {
        $target = v_float($in, 'daily_target_hours', 'ชั่วโมงที่ต้องการอ่านต่อวัน', 0.5, 16);
        $max = v_float($in, 'daily_max_hours', 'ชั่วโมงสูงสุดต่อวัน', 0.5, 16);
        if ($target > $max) throw new ApiError('ชั่วโมงที่ต้องการต่อวันต้องไม่เกินชั่วโมงสูงสุดต่อวัน', 422, ['daily_target_hours' => 'ต้องไม่เกินชั่วโมงสูงสุด']);
        db()->prepare('UPDATE users SET daily_target_hours=?, daily_max_hours=?, session_minutes=?, break_minutes=?, max_consecutive=? WHERE user_id=?')
            ->execute([
                $target, $max,
                v_int($in, 'session_minutes', 'ความยาว session', 15, 240),
                v_int($in, 'break_minutes', 'เวลาพัก', 0, 120),
                v_int($in, 'max_consecutive', 'จำนวน session ติดกัน', 1, 6),
                $uid,
            ]);
        return ['saved' => true];
    },
]);
