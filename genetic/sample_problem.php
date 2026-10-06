<?php
/**
 * ชุดข้อมูลตัวอย่าง (ตรงกับ demo data ใน database/schema.sql)
 * ใช้ทดสอบ GA จาก CLI ได้โดยไม่ต้องมีฐานข้อมูล
 */
function sample_problem_input(?string $startDate = null): array
{
    $start = $startDate ?? date('Y-m-d');
    $d = fn(int $days) => date('Y-m-d', strtotime("$start +$days day"));

    return [
        'start_date' => $start,
        'subjects' => [
            ['id' => 1, 'name' => 'Data Structures', 'difficulty' => 4, 'priority' => 'High', 'exam_date' => $d(16), 'required_hours' => 15, 'color' => '#2a78d6',
             'topics' => [['id' => 1, 'name' => 'Array & Linked List', 'hours' => 3], ['id' => 2, 'name' => 'Stack & Queue', 'hours' => 2], ['id' => 3, 'name' => 'Tree', 'hours' => 4], ['id' => 4, 'name' => 'Graph', 'hours' => 4], ['id' => 5, 'name' => 'Sorting', 'hours' => 2]]],
            ['id' => 2, 'name' => 'Database Systems', 'difficulty' => 3, 'priority' => 'High', 'exam_date' => $d(12), 'required_hours' => 10, 'color' => '#eb6834',
             'topics' => [['id' => 6, 'name' => 'ER Model', 'hours' => 2], ['id' => 7, 'name' => 'Relational Algebra', 'hours' => 2], ['id' => 8, 'name' => 'SQL', 'hours' => 4], ['id' => 9, 'name' => 'Normalization', 'hours' => 2]]],
            ['id' => 3, 'name' => 'Artificial Intelligence', 'difficulty' => 5, 'priority' => 'Medium', 'exam_date' => $d(24), 'required_hours' => 12, 'color' => '#1baf7a',
             'topics' => [['id' => 10, 'name' => 'Search Algorithms', 'hours' => 3], ['id' => 11, 'name' => 'Genetic Algorithm', 'hours' => 3], ['id' => 12, 'name' => 'Machine Learning Basics', 'hours' => 4], ['id' => 13, 'name' => 'Neural Networks', 'hours' => 2]]],
            ['id' => 4, 'name' => 'Discrete Mathematics', 'difficulty' => 3, 'priority' => 'Medium', 'exam_date' => $d(20), 'required_hours' => 8, 'color' => '#4a3aa7',
             'topics' => [['id' => 14, 'name' => 'Logic & Proofs', 'hours' => 3], ['id' => 15, 'name' => 'Sets & Relations', 'hours' => 2], ['id' => 16, 'name' => 'Graph Theory', 'hours' => 3]]],
            ['id' => 5, 'name' => 'Software Engineering', 'difficulty' => 2, 'priority' => 'Low', 'exam_date' => $d(28), 'required_hours' => 6, 'color' => '#e34948',
             'topics' => [['id' => 17, 'name' => 'Requirement Engineering', 'hours' => 2], ['id' => 18, 'name' => 'UML', 'hours' => 2], ['id' => 19, 'name' => 'Testing', 'hours' => 2]]],
        ],
        'availability' => [
            ['day' => 1, 'start' => '18:00', 'end' => '21:00', 'preference' => 3],
            ['day' => 2, 'start' => '18:00', 'end' => '21:00', 'preference' => 2],
            ['day' => 4, 'start' => '18:00', 'end' => '22:00', 'preference' => 2],
            ['day' => 5, 'start' => '18:00', 'end' => '20:00', 'preference' => 1],
            ['day' => 6, 'start' => '09:00', 'end' => '12:00', 'preference' => 3],
            ['day' => 6, 'start' => '13:00', 'end' => '16:00', 'preference' => 2],
            ['day' => 7, 'start' => '13:00', 'end' => '17:00', 'preference' => 2],
        ],
        'blocked' => [
            ['date' => null, 'day' => 7, 'start' => '15:00', 'end' => '16:00'],
        ],
        'settings' => [
            'session_minutes' => 60, 'break_minutes' => 15, 'daily_max_hours' => 4, 'max_consecutive' => 2,
        ],
    ];
}
