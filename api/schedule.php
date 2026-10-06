<?php
/**
 * Schedule API — สร้างตารางด้วย GA, ดู/ปรับแก้ตาราง, บันทึกการอ่าน
 *
 *   GET  ?action=get&from=Y-m-d&to=Y-m-d
 *   GET  ?action=versions
 *   POST {action:"generate", start_date, population_size, generations, ...}
 *   POST {action:"create_item"|"update_item"|"delete_item"|"check_item"|"set_status"|"activate", ...}
 */
require_once __DIR__ . '/../includes/study_service.php';

function owned_item(int $uid, int $itemId): array
{
    $st = db()->prepare('SELECT i.*, TIME_FORMAT(i.start_time,"%H:%i") start_time, TIME_FORMAT(i.end_time,"%H:%i") end_time
                         FROM schedule_items i JOIN schedules s ON s.schedule_id = i.schedule_id
                         WHERE i.schedule_item_id = ? AND s.user_id = ?');
    $st->execute([$itemId, $uid]);
    $it = $st->fetch();
    if (!$it) throw new ApiError('ไม่พบ session', 404);
    return $it;
}

/** รวมค่าที่ส่งมากับค่าเดิม + ตรวจรูปแบบ */
function item_fields(int $uid, array $in, array $base = []): array
{
    $merged = array_merge($base, array_filter($in, fn($v) => $v !== null));
    $subject = owned_subject($uid, (int)($merged['subject_id'] ?? 0));
    $date = v_date($merged, 'date', 'วันที่');
    $start = v_time($merged, 'start_time', 'เวลาเริ่ม');
    if (!empty($merged['end_time'])) {
        $end = v_time($merged, 'end_time', 'เวลาสิ้นสุด');
    } else {
        $len = (int)(current_user()['session_minutes'] ?? 60);
        $end = StudyProblem::toTime(min(23 * 60 + 59, StudyProblem::toMinutes($start) + $len));
    }
    if ($end <= $start) throw new ApiError('เวลาสิ้นสุดต้องมากกว่าเวลาเริ่ม', 422, ['end_time' => 'ต้องมากกว่าเวลาเริ่ม']);

    $topicId = $merged['topic_id'] ?? null;
    $topicId = ($topicId === '' || $topicId === null) ? null : (int)$topicId;
    if ($topicId !== null) {
        $t = db()->prepare('SELECT COUNT(*) FROM topics WHERE topic_id = ? AND subject_id = ?');
        $t->execute([$topicId, $subject['subject_id']]);
        if (!(int)$t->fetchColumn()) $topicId = null;   // หัวข้อไม่ใช่ของวิชานี้ (เช่นเปลี่ยนวิชา)
    }
    return [
        'subject_id' => (int)$subject['subject_id'], 'topic_id' => $topicId,
        'date' => $date, 'start_time' => $start, 'end_time' => $end,
    ];
}

/** คำเตือนทั้งหมดหลังการแก้ไข: ข้อจำกัด + เวลาอ่านที่เหลือของวิชาที่ได้รับผลกระทบ */
function warnings_after(int $uid, int $scheduleId, ?array $item, ?int $itemId, array $subjectIds): array
{
    $w = $item ? validate_item($uid, $scheduleId, $item, $itemId) : [];
    foreach (array_unique($subjectIds) as $sid) {
        if ($n = remaining_notice($uid, (int)$sid)) $w[] = $n;
    }
    return $w;
}

api_run([
    'get' => function (int $uid, array $in) {
        $from = v_date($in, 'from', 'from', false) ?? date('Y-m-d', strtotime('monday this week'));
        $to = v_date($in, 'to', 'to', false) ?? date('Y-m-d', strtotime("$from +6 day"));
        $schedule = active_schedule($uid);
        $pdo = db();

        $subjects = $pdo->prepare('SELECT subject_id, subject_name, color, exam_date FROM subjects WHERE user_id = ? ORDER BY subject_name');
        $subjects->execute([$uid]);
        $subjects = $subjects->fetchAll();
        $topics = $pdo->prepare('SELECT t.topic_id, t.subject_id, t.topic_name, t.status FROM topics t
                                 JOIN subjects s ON s.subject_id = t.subject_id WHERE s.user_id = ? ORDER BY t.sort_order, t.topic_id');
        $topics->execute([$uid]);
        $bySubject = [];
        foreach ($topics->fetchAll() as $t) $bySubject[$t['subject_id']][] = $t;
        foreach ($subjects as &$s) {
            $s['subject_id'] = (int)$s['subject_id'];
            $s['topics'] = $bySubject[$s['subject_id']] ?? [];
        }
        unset($s);

        $avail = $pdo->prepare('SELECT day_of_week, TIME_FORMAT(start_time,"%H:%i") start_time, TIME_FORMAT(end_time,"%H:%i") end_time, preference
                                FROM availability WHERE user_id = ?');
        $avail->execute([$uid]);
        $blocked = $pdo->prepare('SELECT specific_date, day_of_week, TIME_FORMAT(start_time,"%H:%i") start_time, TIME_FORMAT(end_time,"%H:%i") end_time, reason
                                  FROM unavailable_times WHERE user_id = ?');
        $blocked->execute([$uid]);

        return [
            'from' => $from, 'to' => $to, 'today' => today(),
            'schedule' => $schedule ? [
                'schedule_id'  => (int)$schedule['schedule_id'],
                'fitness'      => (float)$schedule['fitness'],
                'generations'  => (int)$schedule['generations'],
                'execution_ms' => (int)$schedule['execution_ms'],
                'start_date'   => $schedule['start_date'],
                'end_date'     => $schedule['end_date'],
                'created_at'   => $schedule['created_at'],
                'detail'       => json_decode($schedule['fitness_detail'] ?? 'null', true),
            ] : null,
            'items' => $schedule ? schedule_items((int)$schedule['schedule_id'], $from, $to) : [],
            'subjects' => $subjects,
            'availability' => $avail->fetchAll(),
            'blocked' => $blocked->fetchAll(),
            'settings' => [
                'session_minutes' => (int)current_user()['session_minutes'],
                'daily_max_hours' => (float)current_user()['daily_max_hours'],
            ],
        ];
    },

    'versions' => function (int $uid) {
        $st = db()->prepare('SELECT s.schedule_id, s.start_date, s.end_date, s.fitness, s.generations, s.execution_ms, s.is_active,
                                    s.created_at, s.parameters, (SELECT COUNT(*) FROM schedule_items i WHERE i.schedule_id = s.schedule_id) items
                             FROM schedules s WHERE s.user_id = ? ORDER BY s.schedule_id DESC LIMIT 20');
        $st->execute([$uid]);
        return array_map(fn($r) => $r + ['params' => json_decode($r['parameters'] ?? 'null', true)], $st->fetchAll());
    },

    'latest_run' => function (int $uid) {
        $s = active_schedule($uid);
        if (!$s || !$s['fitness_history']) return null;
        return [
            'schedule_id' => (int)$s['schedule_id'], 'fitness' => (float)$s['fitness'], 'generations' => (int)$s['generations'],
            'execution_ms' => (int)$s['execution_ms'], 'created_at' => $s['created_at'],
            'params' => json_decode($s['parameters'], true), 'detail' => json_decode($s['fitness_detail'], true),
            'history' => json_decode($s['fitness_history'], true),
        ];
    },

    'generate' => function (int $uid, array $in) {
        $start = v_date($in, 'start_date', 'วันเริ่มต้น', false) ?? today();
        if ($start < today()) throw new ApiError('วันเริ่มต้นต้องไม่อยู่ในอดีต', 422);
        $params = [];
        foreach (array_keys(GeneticAlgorithm::DEFAULTS) as $k) {
            if (isset($in[$k]) && $in[$k] !== '') $params[$k] = $in[$k];
        }
        return generate_schedule($uid, $params, $start);
    },

    'activate' => function (int $uid, array $in) {
        $st = db()->prepare('SELECT schedule_id FROM schedules WHERE schedule_id = ? AND user_id = ?');
        $st->execute([(int)($in['schedule_id'] ?? 0), $uid]);
        $id = $st->fetchColumn();
        if (!$id) throw new ApiError('ไม่พบตาราง', 404);
        db()->prepare('UPDATE schedules SET is_active = (schedule_id = ?) WHERE user_id = ?')->execute([$id, $uid]);
        return ['schedule_id' => (int)$id];
    },

    'check_item' => function (int $uid, array $in) {
        $schedule = ensure_active_schedule($uid);
        $itemId = !empty($in['schedule_item_id']) ? (int)$in['schedule_item_id'] : null;
        $base = $itemId ? owned_item($uid, $itemId) : [];
        $f = item_fields($uid, $in, $base);
        return ['warnings' => validate_item($uid, (int)$schedule['schedule_id'], $f, $itemId)];
    },

    'create_item' => function (int $uid, array $in) {
        $schedule = ensure_active_schedule($uid);
        $f = item_fields($uid, $in);
        $pdo = db();
        $pdo->prepare('INSERT INTO schedule_items (schedule_id, subject_id, topic_id, date, start_time, end_time, is_manual, is_locked)
                       VALUES (?, ?, ?, ?, ?, ?, 1, ?)')
            ->execute([$schedule['schedule_id'], $f['subject_id'], $f['topic_id'], $f['date'], $f['start_time'], $f['end_time'], !empty($in['is_locked']) ? 1 : 0]);
        $id = (int)$pdo->lastInsertId();
        return ['schedule_item_id' => $id,
                'warnings' => warnings_after($uid, (int)$schedule['schedule_id'], $f, $id, [$f['subject_id']])];
    },

    'update_item' => function (int $uid, array $in) {
        $item = owned_item($uid, (int)($in['schedule_item_id'] ?? 0));
        $f = item_fields($uid, $in, $item);
        $locked = array_key_exists('is_locked', $in) ? (!empty($in['is_locked']) ? 1 : 0) : (int)$item['is_locked'];
        db()->prepare('UPDATE schedule_items SET subject_id=?, topic_id=?, date=?, start_time=?, end_time=?, is_manual=1, is_locked=?
                       WHERE schedule_item_id = ?')
            ->execute([$f['subject_id'], $f['topic_id'], $f['date'], $f['start_time'], $f['end_time'], $locked, $item['schedule_item_id']]);
        // ถ้าเคยบันทึกว่าอ่านแล้ว ให้ progress ตามวิชา/หัวข้อใหม่
        db()->prepare('UPDATE study_progress SET subject_id=?, topic_id=?, study_date=? WHERE schedule_item_id=?')
            ->execute([$f['subject_id'], $f['topic_id'], $f['date'], $item['schedule_item_id']]);
        return ['schedule_item_id' => (int)$item['schedule_item_id'],
                'warnings' => warnings_after($uid, (int)$item['schedule_id'], $f, (int)$item['schedule_item_id'],
                                             [$f['subject_id'], (int)$item['subject_id']])];
    },

    'delete_item' => function (int $uid, array $in) {
        $item = owned_item($uid, (int)($in['schedule_item_id'] ?? 0));
        db()->prepare('DELETE FROM schedule_items WHERE schedule_item_id = ?')->execute([$item['schedule_item_id']]);
        if ($item['topic_id']) refresh_topic_status((int)$item['topic_id']);
        return ['deleted' => (int)$item['schedule_item_id'],
                'warnings' => warnings_after($uid, (int)$item['schedule_id'], null, null, [(int)$item['subject_id']])];
    },

    // อ่านแล้ว / ยังไม่ได้อ่าน / ข้าม
    'set_status' => function (int $uid, array $in) {
        $item = owned_item($uid, (int)($in['schedule_item_id'] ?? 0));
        $status = v_enum($in, 'status', ['planned', 'done', 'skipped'], 'planned');
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE schedule_items SET status = ? WHERE schedule_item_id = ?')->execute([$status, $item['schedule_item_id']]);
        $pdo->prepare('DELETE FROM study_progress WHERE schedule_item_id = ?')->execute([$item['schedule_item_id']]);
        if ($status === 'done') {
            $minutes = StudyProblem::toMinutes($item['end_time']) - StudyProblem::toMinutes($item['start_time']);
            $pdo->prepare('INSERT INTO study_progress (user_id, subject_id, topic_id, schedule_item_id, study_date, minutes, note)
                           VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$uid, $item['subject_id'], $item['topic_id'], $item['schedule_item_id'], $item['date'], $minutes,
                           mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null]);
        }
        $pdo->commit();
        if ($item['topic_id']) refresh_topic_status((int)$item['topic_id']);
        $notice = remaining_notice($uid, (int)$item['subject_id']);
        return ['status' => $status, 'warnings' => $notice ? [$notice] : []];
    },
], ['get', 'versions', 'latest_run']);
