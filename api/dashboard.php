<?php
/**
 * Dashboard API — ข้อมูลรวมสำหรับหน้า Dashboard
 *   GET ?action=get
 */
require_once __DIR__ . '/../includes/study_service.php';

api_run([
    'get' => function (int $uid) {
        $today = today();
        $schedule = active_schedule($uid);
        $todayItems = $schedule ? schedule_items((int)$schedule['schedule_id'], $today, $today) : [];

        // ภาระการอ่านตามแผน 7 วันข้างหน้า
        $week = [];
        $upcoming = $schedule ? schedule_items((int)$schedule['schedule_id'], $today, date('Y-m-d', strtotime('+6 day'))) : [];
        for ($i = 0; $i < 7; $i++) {
            $d = date('Y-m-d', strtotime("+$i day"));
            $week[$d] = ['date' => $d, 'hours' => 0.0, 'done' => 0.0];
        }
        foreach ($upcoming as $it) {
            $h = (StudyProblem::toMinutes($it['end_time']) - StudyProblem::toMinutes($it['start_time'])) / 60;
            $week[$it['date']]['hours'] += $h;
            if ($it['status'] === 'done') $week[$it['date']]['done'] += $h;
        }

        $summary = progress_summary($uid);
        return [
            'today'       => $today,
            'user'        => ['name' => current_user()['name'], 'daily_target_hours' => (float)current_user()['daily_target_hours']],
            'today_items' => $todayItems,
            'week'        => array_values($week),
            'overall'     => $summary['overall'],
            'subjects'    => $summary['subjects'],
            'schedule'    => $schedule ? [
                'schedule_id' => (int)$schedule['schedule_id'], 'fitness' => (float)$schedule['fitness'],
                'created_at' => $schedule['created_at'], 'end_date' => $schedule['end_date'],
                'generations' => (int)$schedule['generations'],
            ] : null,
        ];
    },
], ['get']);
