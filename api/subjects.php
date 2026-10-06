<?php
/**
 * Subjects API — CRUD รายวิชา
 *   GET  ?action=list
 *   GET  ?action=get&subject_id=1         (รวมหัวข้อ)
 *   POST {action:create|update|delete, ...}
 */
require_once __DIR__ . '/../includes/study_service.php';

function subject_fields(array $in): array
{
    return [
        'subject_name' => mb_substr(v_required($in, 'subject_name', 'ชื่อวิชา'), 0, 150),
        'difficulty'   => v_int($in, 'difficulty', 'ความยาก', 1, 5, 3),
        'priority'     => v_enum($in, 'priority', ['Low', 'Medium', 'High'], 'Medium'),
        'exam_date'    => v_date($in, 'exam_date', 'วันสอบ'),
        'total_hours'  => v_float($in, 'total_hours', 'ชั่วโมงที่ต้องอ่าน', 0.5, 500),
        'color'        => v_color($in, 'color'),
        'description'  => mb_substr(trim((string)($in['description'] ?? '')), 0, 2000),
    ];
}

/** บันทึกหัวข้อที่ส่งมาพร้อมฟอร์มวิชา (เฉพาะตอนสร้าง: topics = [{topic_name, estimated_hours}]) */
function insert_topics(int $subjectId, array $topics): void
{
    $st = db()->prepare('INSERT INTO topics (subject_id, topic_name, estimated_hours, sort_order) VALUES (?, ?, ?, ?)');
    $order = 1;
    foreach ($topics as $t) {
        $name = trim((string)($t['topic_name'] ?? ''));
        if ($name === '') continue;
        $hours = is_numeric($t['estimated_hours'] ?? null) ? max(0.5, min(100, (float)$t['estimated_hours'])) : 1;
        $st->execute([$subjectId, mb_substr($name, 0, 150), $hours, $order++]);
    }
}

api_run([
    'list' => function (int $uid) {
        $progress = [];
        foreach (progress_summary($uid)['subjects'] as $p) $progress[$p['subject_id']] = $p;
        $st = db()->prepare('SELECT * FROM subjects WHERE user_id = ? ORDER BY exam_date, subject_name');
        $st->execute([$uid]);
        return array_map(function ($s) use ($progress) {
            $p = $progress[$s['subject_id']] ?? [];
            return [
                'subject_id'      => (int)$s['subject_id'],
                'subject_name'    => $s['subject_name'],
                'difficulty'      => (int)$s['difficulty'],
                'priority'        => $s['priority'],
                'exam_date'       => $s['exam_date'],
                'total_hours'     => (float)$s['total_hours'],
                'color'           => $s['color'],
                'description'     => $s['description'],
                'days_until_exam' => $p['days_until_exam'] ?? null,
                'completed_hours' => $p['completed_hours'] ?? 0,
                'planned_hours'   => $p['planned_hours'] ?? 0,
                'percent'         => $p['percent'] ?? 0,
                'topics_total'    => $p['topics_total'] ?? 0,
                'topics_done'     => $p['topics_done'] ?? 0,
            ];
        }, $st->fetchAll());
    },

    'get' => function (int $uid, array $in) {
        $s = owned_subject($uid, (int)($in['subject_id'] ?? 0));
        $t = db()->prepare('SELECT t.*, COALESCE((SELECT SUM(minutes) FROM study_progress p WHERE p.topic_id = t.topic_id),0)/60 AS studied_hours
                            FROM topics t WHERE subject_id = ? ORDER BY sort_order, topic_id');
        $t->execute([$s['subject_id']]);
        $s['topics'] = $t->fetchAll();
        return $s;
    },

    'create' => function (int $uid, array $in) {
        $f = subject_fields($in);
        $pdo = db();
        $pdo->prepare('INSERT INTO subjects (user_id, subject_name, difficulty, priority, exam_date, total_hours, color, description)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $f['subject_name'], $f['difficulty'], $f['priority'], $f['exam_date'], $f['total_hours'], $f['color'], $f['description']]);
        $id = (int)$pdo->lastInsertId();
        if (!empty($in['topics']) && is_array($in['topics'])) insert_topics($id, $in['topics']);
        return ['subject_id' => $id];
    },

    'update' => function (int $uid, array $in) {
        $s = owned_subject($uid, (int)($in['subject_id'] ?? 0));
        $f = subject_fields($in);
        db()->prepare('UPDATE subjects SET subject_name=?, difficulty=?, priority=?, exam_date=?, total_hours=?, color=?, description=?
                       WHERE subject_id = ?')
            ->execute([$f['subject_name'], $f['difficulty'], $f['priority'], $f['exam_date'], $f['total_hours'], $f['color'], $f['description'], $s['subject_id']]);
        return ['subject_id' => (int)$s['subject_id']];
    },

    'delete' => function (int $uid, array $in) {
        $s = owned_subject($uid, (int)($in['subject_id'] ?? 0));
        db()->prepare('DELETE FROM subjects WHERE subject_id = ?')->execute([$s['subject_id']]);
        return ['deleted' => (int)$s['subject_id']];
    },
]);
