<?php
/**
 * Topics API — หัวข้อ/บทที่ต้องอ่านของแต่ละวิชา
 *   GET  ?action=list&subject_id=1
 *   POST {action:create|update|delete|set_status, ...}
 */
require_once __DIR__ . '/../includes/study_service.php';

function owned_topic(int $uid, int $topicId): array
{
    $st = db()->prepare('SELECT t.* FROM topics t JOIN subjects s ON s.subject_id = t.subject_id
                         WHERE t.topic_id = ? AND s.user_id = ?');
    $st->execute([$topicId, $uid]);
    $t = $st->fetch();
    if (!$t) throw new ApiError('ไม่พบหัวข้อ', 404);
    return $t;
}

api_run([
    'list' => function (int $uid, array $in) {
        $s = owned_subject($uid, (int)($in['subject_id'] ?? 0));
        $st = db()->prepare('SELECT * FROM topics WHERE subject_id = ? ORDER BY sort_order, topic_id');
        $st->execute([$s['subject_id']]);
        return $st->fetchAll();
    },

    'create' => function (int $uid, array $in) {
        $s = owned_subject($uid, (int)($in['subject_id'] ?? 0));
        $name = mb_substr(v_required($in, 'topic_name', 'ชื่อหัวข้อ'), 0, 150);
        $hours = v_float($in, 'estimated_hours', 'ชั่วโมงโดยประมาณ', 0.5, 100, 1);
        $pdo = db();
        $order = $pdo->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM topics WHERE subject_id = ?');
        $order->execute([$s['subject_id']]);
        $pdo->prepare('INSERT INTO topics (subject_id, topic_name, estimated_hours, sort_order) VALUES (?, ?, ?, ?)')
            ->execute([$s['subject_id'], $name, $hours, (int)$order->fetchColumn()]);
        return ['topic_id' => (int)$pdo->lastInsertId()];
    },

    'update' => function (int $uid, array $in) {
        $t = owned_topic($uid, (int)($in['topic_id'] ?? 0));
        $name = mb_substr(v_required($in, 'topic_name', 'ชื่อหัวข้อ'), 0, 150);
        $hours = v_float($in, 'estimated_hours', 'ชั่วโมงโดยประมาณ', 0.5, 100);
        $order = isset($in['sort_order']) ? v_int($in, 'sort_order', 'ลำดับ', 0, 1000) : (int)$t['sort_order'];
        db()->prepare('UPDATE topics SET topic_name = ?, estimated_hours = ?, sort_order = ? WHERE topic_id = ?')
            ->execute([$name, $hours, $order, $t['topic_id']]);
        return ['topic_id' => (int)$t['topic_id']];
    },

    'set_status' => function (int $uid, array $in) {
        $t = owned_topic($uid, (int)($in['topic_id'] ?? 0));
        $status = v_enum($in, 'status', ['not_started', 'in_progress', 'done'], 'not_started');
        db()->prepare('UPDATE topics SET status = ? WHERE topic_id = ?')->execute([$status, $t['topic_id']]);
        return ['topic_id' => (int)$t['topic_id'], 'status' => $status];
    },

    'delete' => function (int $uid, array $in) {
        $t = owned_topic($uid, (int)($in['topic_id'] ?? 0));
        db()->prepare('DELETE FROM topics WHERE topic_id = ?')->execute([$t['topic_id']]);
        return ['deleted' => (int)$t['topic_id']];
    },
]);
