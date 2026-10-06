<?php
/**
 * Progress API — ความก้าวหน้าในการอ่าน
 *   GET  ?action=summary
 *   GET  ?action=logs
 *   POST {action:"log", subject_id, topic_id?, study_date, minutes, note?}
 *   POST {action:"delete_log", progress_id}
 */
require_once __DIR__ . '/../includes/study_service.php';

api_run([
    'summary' => function (int $uid) {
        $summary = progress_summary($uid);
        // ชั่วโมงที่อ่านจริงต่อวัน 14 วันล่าสุด
        $st = db()->prepare('SELECT study_date, SUM(minutes)/60 h FROM study_progress
                             WHERE user_id = ? AND study_date >= ? GROUP BY study_date');
        $from = date('Y-m-d', strtotime('-13 day'));
        $st->execute([$uid, $from]);
        $byDay = array_column($st->fetchAll(), 'h', 'study_date');
        $daily = [];
        for ($i = 0; $i < 14; $i++) {
            $d = date('Y-m-d', strtotime("$from +$i day"));
            $daily[] = ['date' => $d, 'hours' => round((float)($byDay[$d] ?? 0), 2)];
        }
        $summary['daily'] = $daily;
        return $summary;
    },

    'logs' => function (int $uid) {
        $st = db()->prepare('SELECT p.progress_id, p.study_date, p.minutes, p.note, p.schedule_item_id,
                                    s.subject_name, s.color, t.topic_name
                             FROM study_progress p JOIN subjects s ON s.subject_id = p.subject_id
                             LEFT JOIN topics t ON t.topic_id = p.topic_id
                             WHERE p.user_id = ? ORDER BY p.study_date DESC, p.progress_id DESC LIMIT 50');
        $st->execute([$uid]);
        return $st->fetchAll();
    },

    // บันทึกการอ่านที่ไม่ได้อยู่ในตาราง
    'log' => function (int $uid, array $in) {
        $s = owned_subject($uid, (int)($in['subject_id'] ?? 0));
        $topicId = !empty($in['topic_id']) ? (int)$in['topic_id'] : null;
        if ($topicId) {
            $t = db()->prepare('SELECT COUNT(*) FROM topics WHERE topic_id = ? AND subject_id = ?');
            $t->execute([$topicId, $s['subject_id']]);
            if (!(int)$t->fetchColumn()) $topicId = null;
        }
        $date = v_date($in, 'study_date', 'วันที่');
        if ($date > today()) throw new ApiError('ไม่สามารถบันทึกการอ่านล่วงหน้าได้', 422, ['study_date' => 'ต้องไม่เกินวันนี้']);
        $minutes = v_int($in, 'minutes', 'จำนวนนาที', 5, 720);
        db()->prepare('INSERT INTO study_progress (user_id, subject_id, topic_id, study_date, minutes, note) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$uid, $s['subject_id'], $topicId, $date, $minutes, mb_substr(trim((string)($in['note'] ?? '')), 0, 255) ?: null]);
        if ($topicId) refresh_topic_status($topicId);
        $n = remaining_notice($uid, (int)$s['subject_id']);
        return ['warnings' => $n ? [$n] : []];
    },

    'delete_log' => function (int $uid, array $in) {
        $st = db()->prepare('SELECT * FROM study_progress WHERE progress_id = ? AND user_id = ?');
        $st->execute([(int)($in['progress_id'] ?? 0), $uid]);
        $p = $st->fetch();
        if (!$p) throw new ApiError('ไม่พบรายการ', 404);
        db()->prepare('DELETE FROM study_progress WHERE progress_id = ?')->execute([$p['progress_id']]);
        if ($p['schedule_item_id']) {
            db()->prepare('UPDATE schedule_items SET status = "planned" WHERE schedule_item_id = ? AND status = "done"')
                ->execute([$p['schedule_item_id']]);
        }
        if ($p['topic_id']) refresh_topic_status((int)$p['topic_id']);
        return ['deleted' => true];
    },
], ['summary', 'logs']);
