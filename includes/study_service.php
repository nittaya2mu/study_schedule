<?php
/**
 * StudyService — Business logic + ตัวเชื่อม GA ↔ Database
 *
 *   Database ──(build_problem_input)──▶ StudyProblem ──▶ GeneticAlgorithm ──▶ save_schedule ──▶ Database
 *
 * ฟังก์ชันในไฟล์นี้ถูกเรียกจาก api/*.php เท่านั้น (หน้าเว็บไม่เรียก GA โดยตรง)
 */
require_once __DIR__ . '/api.php';
require_once __DIR__ . '/../genetic/genetic_algorithm.php';

// =====================================================================
// Progress
// =====================================================================

/** สรุปความก้าวหน้ารายวิชา + ภาพรวม */
function progress_summary(int $userId): array
{
    $pdo = db();
    $today = today();
    $schedule = active_schedule($userId);

    $subjects = $pdo->prepare('SELECT * FROM subjects WHERE user_id = ? ORDER BY exam_date, subject_name');
    $subjects->execute([$userId]);
    $subjects = $subjects->fetchAll();

    $done = $pdo->prepare('SELECT subject_id, SUM(minutes) m FROM study_progress WHERE user_id = ? GROUP BY subject_id');
    $done->execute([$userId]);
    $doneMin = array_column($done->fetchAll(), 'm', 'subject_id');

    $plannedMin = [];
    if ($schedule) {
        $st = $pdo->prepare("SELECT subject_id, SUM(TIME_TO_SEC(TIMEDIFF(end_time, start_time)))/60 m
                             FROM schedule_items WHERE schedule_id = ? AND status = 'planned' AND date >= ?
                             GROUP BY subject_id");
        $st->execute([$schedule['schedule_id'], $today]);
        $plannedMin = array_column($st->fetchAll(), 'm', 'subject_id');
    }

    $topics = $pdo->prepare('SELECT t.subject_id, COUNT(*) total, SUM(t.status = "done") done
                             FROM topics t JOIN subjects s ON s.subject_id = t.subject_id
                             WHERE s.user_id = ? GROUP BY t.subject_id');
    $topics->execute([$userId]);
    $topicStats = [];
    foreach ($topics->fetchAll() as $r) $topicStats[$r['subject_id']] = $r;

    $rows = []; $tot = ['total' => 0, 'done' => 0, 'planned' => 0, 'topics' => 0, 'topics_done' => 0];
    foreach ($subjects as $s) {
        $id = $s['subject_id'];
        $total = (float)$s['total_hours'];
        $completed = round(($doneMin[$id] ?? 0) / 60, 2);
        $planned = round(($plannedMin[$id] ?? 0) / 60, 2);
        $remaining = max(0, round($total - $completed, 2));
        $rows[] = [
            'subject_id'      => (int)$id,
            'subject_name'    => $s['subject_name'],
            'color'           => $s['color'],
            'priority'        => $s['priority'],
            'difficulty'      => (int)$s['difficulty'],
            'exam_date'       => $s['exam_date'],
            'days_until_exam' => days_between($today, $s['exam_date']),
            'total_hours'     => $total,
            'completed_hours' => $completed,
            'remaining_hours' => $remaining,
            'planned_hours'   => $planned,
            'unplanned_hours' => max(0, round($remaining - $planned, 2)),
            'percent'         => $total > 0 ? min(100, round($completed / $total * 100, 1)) : 0,
            'topics_total'    => (int)($topicStats[$id]['total'] ?? 0),
            'topics_done'     => (int)($topicStats[$id]['done'] ?? 0),
        ];
        $tot['total'] += $total;
        $tot['done'] += min($completed, $total);
        $tot['planned'] += $planned;
        $tot['topics'] += (int)($topicStats[$id]['total'] ?? 0);
        $tot['topics_done'] += (int)($topicStats[$id]['done'] ?? 0);
    }

    $upcoming = array_values(array_filter($rows, fn($r) => $r['days_until_exam'] >= 0));
    return [
        'subjects' => $rows,
        'overall'  => [
            'subjects'          => count($rows),
            'total_hours'       => round($tot['total'], 1),
            'completed_hours'   => round($tot['done'], 1),
            'remaining_hours'   => round(max(0, $tot['total'] - $tot['done']), 1),
            'planned_hours'     => round($tot['planned'], 1),
            'percent'           => $tot['total'] > 0 ? round($tot['done'] / $tot['total'] * 100, 1) : 0,
            'topics_total'      => $tot['topics'],
            'topics_done'       => $tot['topics_done'],
            'topic_percent'     => $tot['topics'] > 0 ? round($tot['topics_done'] / $tot['topics'] * 100, 1) : 0,
            'next_exam'         => $upcoming[0] ?? null,
        ],
    ];
}

/** อัปเดตสถานะหัวข้อตามเวลาที่อ่านจริง (อ่านครบ estimated_hours → done) */
function refresh_topic_status(int $topicId): void
{
    $pdo = db();
    $st = $pdo->prepare('SELECT t.estimated_hours, t.status, COALESCE(SUM(p.minutes),0) m
                         FROM topics t LEFT JOIN study_progress p ON p.topic_id = t.topic_id
                         WHERE t.topic_id = ? GROUP BY t.topic_id');
    $st->execute([$topicId]);
    $t = $st->fetch();
    if (!$t) return;
    $need = (float)$t['estimated_hours'] * 60;
    $status = $t['m'] >= $need ? 'done' : ($t['m'] > 0 ? 'in_progress' : 'not_started');
    if ($status !== $t['status']) {
        $pdo->prepare('UPDATE topics SET status = ? WHERE topic_id = ?')->execute([$status, $topicId]);
    }
}

// =====================================================================
// Schedule
// =====================================================================

function active_schedule(int $userId): ?array
{
    $st = db()->prepare('SELECT * FROM schedules WHERE user_id = ? AND is_active = 1 ORDER BY schedule_id DESC LIMIT 1');
    $st->execute([$userId]);
    return $st->fetch() ?: null;
}

/** สร้างตารางว่าง (สำหรับผู้ใช้ที่ยังไม่เคย generate แต่ต้องการเพิ่ม session เอง) */
function ensure_active_schedule(int $userId): array
{
    $s = active_schedule($userId);
    if ($s) return $s;
    db()->prepare('INSERT INTO schedules (user_id, start_date, end_date, parameters) VALUES (?, ?, ?, ?)')
        ->execute([$userId, today(), today(), json_encode(['manual' => true])]);
    return active_schedule($userId);
}

function schedule_items(int $scheduleId, ?string $from = null, ?string $to = null): array
{
    $sql = 'SELECT i.schedule_item_id, i.subject_id, i.topic_id, i.date, TIME_FORMAT(i.start_time, "%H:%i") start_time,
                   TIME_FORMAT(i.end_time, "%H:%i") end_time, i.status, i.is_manual, i.is_locked,
                   s.subject_name, s.color, s.exam_date, t.topic_name
            FROM schedule_items i
            JOIN subjects s ON s.subject_id = i.subject_id
            LEFT JOIN topics t ON t.topic_id = i.topic_id
            WHERE i.schedule_id = ?';
    $args = [$scheduleId];
    if ($from) { $sql .= ' AND i.date >= ?'; $args[] = $from; }
    if ($to)   { $sql .= ' AND i.date <= ?'; $args[] = $to; }
    $sql .= ' ORDER BY i.date, i.start_time';
    $st = db()->prepare($sql);
    $st->execute($args);
    $items = $st->fetchAll();
    foreach ($items as &$it) {
        foreach (['schedule_item_id', 'subject_id', 'is_manual', 'is_locked'] as $k) $it[$k] = (int)$it[$k];
        $it['topic_id'] = $it['topic_id'] !== null ? (int)$it['topic_id'] : null;
    }
    return $items;
}

/**
 * ดึงข้อมูลจากฐานข้อมูลมาสร้าง input ของ StudyProblem
 * - ชั่วโมงที่ต้องอ่าน = total_hours − ชั่วโมงที่อ่านแล้ว − session ที่ล็อกไว้ในอนาคต
 * - หัวข้อที่อ่านจบแล้วจะไม่ถูกจัดซ้ำ
 */
function build_problem_input(int $userId, string $startDate, array $keptItems = []): array
{
    $pdo = db();
    $user = $pdo->prepare('SELECT * FROM users WHERE user_id = ?');
    $user->execute([$userId]);
    $user = $user->fetch();

    $doneMin = $pdo->prepare('SELECT subject_id, SUM(minutes) m FROM study_progress WHERE user_id = ? GROUP BY subject_id');
    $doneMin->execute([$userId]);
    $doneMin = array_column($doneMin->fetchAll(), 'm', 'subject_id');

    $topicMin = $pdo->prepare('SELECT topic_id, SUM(minutes) m FROM study_progress WHERE user_id = ? AND topic_id IS NOT NULL GROUP BY topic_id');
    $topicMin->execute([$userId]);
    $topicMin = array_column($topicMin->fetchAll(), 'm', 'topic_id');

    // session ที่เก็บไว้ (ล็อก) ในอนาคต ถือว่าจัดแล้ว และเป็นช่วงเวลาที่ไม่ว่าง
    $lockedMin = []; $blocked = [];
    foreach ($keptItems as $it) {
        if ($it['date'] < $startDate) continue;
        if ($it['status'] === 'planned') {
            $lockedMin[$it['subject_id']] = ($lockedMin[$it['subject_id']] ?? 0)
                + (StudyProblem::toMinutes($it['end_time']) - StudyProblem::toMinutes($it['start_time']));
        }
        $blocked[] = ['date' => $it['date'], 'day' => null, 'start' => $it['start_time'], 'end' => $it['end_time']];
    }

    $subjects = $pdo->prepare('SELECT * FROM subjects WHERE user_id = ? AND exam_date > ? ORDER BY exam_date');
    $subjects->execute([$userId, $startDate]);
    $topicsSt = $pdo->prepare('SELECT * FROM topics WHERE subject_id = ? AND status <> "done" ORDER BY sort_order, topic_id');

    $subjectInput = [];
    foreach ($subjects->fetchAll() as $s) {
        $id = $s['subject_id'];
        $required = (float)$s['total_hours'] - ($doneMin[$id] ?? 0) / 60 - ($lockedMin[$id] ?? 0) / 60;
        $topicsSt->execute([$id]);
        $topics = [];
        foreach ($topicsSt->fetchAll() as $t) {
            $left = (float)$t['estimated_hours'] - ($topicMin[$t['topic_id']] ?? 0) / 60;
            if ($left > 0.01) $topics[] = ['id' => (int)$t['topic_id'], 'name' => $t['topic_name'], 'hours' => $left];
        }
        $subjectInput[] = [
            'id' => (int)$id, 'name' => $s['subject_name'], 'color' => $s['color'],
            'difficulty' => (int)$s['difficulty'], 'priority' => $s['priority'],
            'exam_date' => $s['exam_date'], 'required_hours' => round(max(0, $required), 2),
            'topics' => $topics,
        ];
    }

    $avail = $pdo->prepare('SELECT day_of_week, TIME_FORMAT(start_time,"%H:%i") s, TIME_FORMAT(end_time,"%H:%i") e, preference
                            FROM availability WHERE user_id = ?');
    $avail->execute([$userId]);
    $availability = array_map(fn($a) => [
        'day' => (int)$a['day_of_week'], 'start' => $a['s'], 'end' => $a['e'], 'preference' => (int)$a['preference'],
    ], $avail->fetchAll());

    $un = $pdo->prepare('SELECT specific_date, day_of_week, TIME_FORMAT(start_time,"%H:%i") s, TIME_FORMAT(end_time,"%H:%i") e
                         FROM unavailable_times WHERE user_id = ?');
    $un->execute([$userId]);
    foreach ($un->fetchAll() as $u) {
        $blocked[] = ['date' => $u['specific_date'], 'day' => $u['day_of_week'] !== null ? (int)$u['day_of_week'] : null,
                      'start' => $u['s'], 'end' => $u['e']];
    }

    return [
        'start_date'   => $startDate,
        'now_minutes'  => $startDate === today() ? (int)date('G') * 60 + (int)date('i') : null,
        'subjects'     => $subjectInput,
        'availability' => $availability,
        'blocked'      => $blocked,
        'settings'     => [
            'session_minutes' => (int)$user['session_minutes'],
            'break_minutes'   => (int)$user['break_minutes'],
            'daily_max_hours' => (float)$user['daily_max_hours'],
            'max_consecutive' => (int)$user['max_consecutive'],
        ],
    ];
}

/** session ในตารางปัจจุบันที่ต้องเก็บไว้ตอน regenerate: อดีต / ทำแล้ว / ข้าม / ล็อก */
function kept_items(?array $schedule, string $startDate): array
{
    if (!$schedule) return [];
    $st = db()->prepare('SELECT schedule_item_id, subject_id, date, TIME_FORMAT(start_time,"%H:%i") start_time,
                                TIME_FORMAT(end_time,"%H:%i") end_time, status
                         FROM schedule_items
                         WHERE schedule_id = ? AND (date < ? OR status <> "planned" OR is_locked = 1)');
    $st->execute([$schedule['schedule_id'], $startDate]);
    return $st->fetchAll();
}

/**
 * Generate Study Schedule : Load data → GA → Save
 */
function generate_schedule(int $userId, array $params, string $startDate): array
{
    @set_time_limit(120);
    $current = active_schedule($userId);
    $kept = kept_items($current, $startDate);

    $input = build_problem_input($userId, $startDate, $kept);
    $problem = new StudyProblem($input);
    if ($problem->subjectCount() === 0) {
        throw new ApiError('ไม่มีวิชาที่ต้องจัดตาราง (ตรวจสอบว่ามีวิชาที่ยังไม่สอบและยังเหลือชั่วโมงอ่าน)', 422);
    }
    if ($problem->slotCount() === 0) {
        throw new ApiError('ไม่มีช่วงเวลาว่างก่อนวันสอบ กรุณาเพิ่มเวลาว่างในหน้า Availability', 422);
    }

    $ga = new GeneticAlgorithm($problem, $params);
    $result = $ga->run();

    // Baseline เพื่อแสดงให้เห็นว่า GA ดีกว่าวิธีพื้นฐานอย่างไร
    $random = (new RandomScheduler($problem))->run($result['params']['seed'] ?? null);
    $rule = (new RuleBasedScheduler($problem))->run();

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE schedules SET is_active = 0 WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('INSERT INTO schedules (user_id, start_date, end_date, fitness, generations, execution_ms,
                              parameters, fitness_detail, fitness_history, is_active)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)')
            ->execute([
                $userId, $problem->startDate, $problem->endDate, $result['fitness'], $result['generations'],
                $result['execution_ms'], json_encode($result['params']),
                json_encode($result['detail'], JSON_UNESCAPED_UNICODE),
                json_encode(downsample($result['history'], 250)),
            ]);
        $scheduleId = (int)$pdo->lastInsertId();

        // ย้าย session ที่เก็บไว้มายังตารางใหม่ (คง id เดิม เพื่อให้ progress ยังเชื่อมกันอยู่)
        if ($kept) {
            $ids = array_column($kept, 'schedule_item_id');
            $in = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE schedule_items SET schedule_id = ? WHERE schedule_item_id IN ($in)")
                ->execute(array_merge([$scheduleId], $ids));
        }

        $ins = $pdo->prepare('INSERT INTO schedule_items (schedule_id, subject_id, topic_id, date, start_time, end_time)
                              VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($result['sessions'] as $s) {
            $ins->execute([$scheduleId, $s['subject_id'], $s['topic_id'], $s['date'], $s['start_time'], $s['end_time']]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'schedule_id'  => $scheduleId,
        'fitness'      => $result['fitness'],
        'detail'       => $result['detail'],
        'generations'  => $result['generations'],
        'termination'  => $result['termination'],
        'execution_ms' => $result['execution_ms'],
        'evaluations'  => $result['evaluations'],
        'params'       => $result['params'],
        'history'      => downsample($result['history'], 250),
        'sessions'     => count($result['sessions']),
        'kept'         => count($kept),
        'problem'      => [
            'start_date' => $problem->startDate, 'end_date' => $problem->endDate,
            'subjects' => $problem->subjectCount(), 'slots' => $problem->slotCount(), 'days' => $problem->dayCount(),
            'required_hours' => $problem->requiredHours(), 'capacity_hours' => $problem->capacityHours(),
        ],
        'baselines' => [
            ['method' => 'Random', 'fitness' => $random['fitness'], 'violations' => $random['detail']['stats']['constraint_violations'], 'execution_ms' => $random['execution_ms']],
            ['method' => 'Rule-based', 'fitness' => $rule['fitness'], 'violations' => $rule['detail']['stats']['constraint_violations'], 'execution_ms' => $rule['execution_ms']],
            ['method' => 'Genetic Algorithm', 'fitness' => $result['fitness'], 'violations' => $result['detail']['stats']['constraint_violations'], 'execution_ms' => $result['execution_ms']],
        ],
    ];
}

function downsample(array $rows, int $max): array
{
    $n = count($rows);
    if ($n <= $max) return $rows;
    $out = [];
    $step = ($n - 1) / ($max - 1);
    for ($i = 0; $i < $max; $i++) $out[] = $rows[(int)round($i * $step)];
    return $out;
}

// =====================================================================
// Schedule adjustment — ตรวจสอบเมื่อผู้ใช้แก้ไขตาราง
// =====================================================================

/**
 * คืนรายการคำเตือน [['level' => danger|warning|info, 'message' => ...]]
 */
function validate_item(int $userId, int $scheduleId, array $item, ?int $excludeId = null): array
{
    $pdo = db();
    $w = [];
    $start = StudyProblem::toMinutes($item['start_time']);
    $end = StudyProblem::toMinutes($item['end_time']);
    $dow = (int)date('N', strtotime($item['date']));
    $subject = owned_subject($userId, (int)$item['subject_id']);

    // 1) เวลาชนกัน
    $st = $pdo->prepare('SELECT i.schedule_item_id, s.subject_name, TIME_FORMAT(i.start_time,"%H:%i") st, TIME_FORMAT(i.end_time,"%H:%i") en
                         FROM schedule_items i JOIN subjects s ON s.subject_id = i.subject_id
                         WHERE i.schedule_id = ? AND i.date = ? AND i.start_time < ? AND i.end_time > ? AND i.schedule_item_id <> ?');
    $st->execute([$scheduleId, $item['date'], $item['end_time'], $item['start_time'], $excludeId ?? 0]);
    foreach ($st->fetchAll() as $c) {
        $w[] = ['level' => 'danger', 'type' => 'conflict',
                'message' => "ตารางใหม่มีเวลาชนกันกับ {$c['subject_name']} ({$c['st']}–{$c['en']})"];
    }

    // 2) หลัง/ในวันสอบ
    if ($item['date'] >= $subject['exam_date']) {
        $w[] = ['level' => 'danger', 'type' => 'exam',
                'message' => "session นี้อยู่ในวันสอบหรือหลังวันสอบวิชา {$subject['subject_name']} ({$subject['exam_date']})"];
    }

    // 3) นอกช่วงเวลาว่าง
    $st = $pdo->prepare('SELECT COUNT(*) FROM availability WHERE user_id = ? AND day_of_week = ? AND start_time <= ? AND end_time >= ?');
    $st->execute([$userId, $dow, $item['start_time'], $item['end_time']]);
    if (!(int)$st->fetchColumn()) {
        $w[] = ['level' => 'warning', 'type' => 'availability', 'message' => 'อยู่นอกช่วงเวลาว่างที่ตั้งไว้ในหน้า Availability'];
    }

    // 4) ช่วงเวลาที่ไม่สะดวก
    $st = $pdo->prepare('SELECT reason FROM unavailable_times WHERE user_id = ?
                         AND (specific_date = ? OR (specific_date IS NULL AND day_of_week = ?))
                         AND start_time < ? AND end_time > ?');
    $st->execute([$userId, $item['date'], $dow, $item['end_time'], $item['start_time']]);
    foreach ($st->fetchAll() as $u) {
        $w[] = ['level' => 'warning', 'type' => 'blocked', 'message' => 'ตรงกับช่วงเวลาที่ไม่สะดวกอ่าน' . ($u['reason'] ? " ({$u['reason']})" : '')];
    }

    // 5) เกินชั่วโมงสูงสุดต่อวัน
    $st = $pdo->prepare('SELECT COALESCE(SUM(TIME_TO_SEC(TIMEDIFF(end_time,start_time))),0)/60 FROM schedule_items
                         WHERE schedule_id = ? AND date = ? AND schedule_item_id <> ? AND status <> "skipped"');
    $st->execute([$scheduleId, $item['date'], $excludeId ?? 0]);
    $dayMin = (float)$st->fetchColumn() + ($end - $start);
    $user = current_user();
    $max = (float)($user['daily_max_hours'] ?? 24);
    if ($dayMin / 60 > $max + 1e-6) {
        $w[] = ['level' => 'warning', 'type' => 'overload',
                'message' => sprintf('วันนี้อ่านรวม %.1f ชม. เกินชั่วโมงสูงสุดต่อวัน (%.1f ชม.)', $dayMin / 60, $max)];
    }

    if ($item['date'] < today()) {
        $w[] = ['level' => 'info', 'type' => 'past', 'message' => 'session นี้อยู่ในอดีต'];
    }
    return $w;
}

/** ข้อความแจ้งเวลาอ่านที่เหลือก่อนสอบของวิชา */
function remaining_notice(int $userId, int $subjectId): ?array
{
    foreach (progress_summary($userId)['subjects'] as $s) {
        if ($s['subject_id'] !== $subjectId || $s['days_until_exam'] < 0) continue;
        if ($s['unplanned_hours'] > 0) {
            return ['level' => 'warning', 'type' => 'remaining', 'message' => sprintf(
                'วิชา %s เหลือเวลาอ่านอีก %s ชั่วโมงก่อนสอบ แต่จัดลงตารางไว้เพียง %s ชั่วโมง (สอบในอีก %d วัน)',
                $s['subject_name'], fmt_h($s['remaining_hours']), fmt_h($s['planned_hours']), $s['days_until_exam'])];
        }
        return ['level' => 'info', 'type' => 'remaining', 'message' => sprintf(
            'วิชา %s เหลือเวลาอ่านอีก %s ชั่วโมงก่อนสอบ (สอบในอีก %d วัน)',
            $s['subject_name'], fmt_h($s['remaining_hours']), $s['days_until_exam'])];
    }
    return null;
}

function fmt_h(float $h): string
{
    return rtrim(rtrim(number_format($h, 1), '0'), '.');
}
