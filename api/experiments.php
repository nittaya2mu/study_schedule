<?php
/**
 * Experiments API — รันการทดลองประเมิน GA จากหน้าเว็บ
 *   POST {action:"run", experiment:"population"|"mutation"|"generations"|"baseline", runs, source:"user"|"sample"}
 */
require_once __DIR__ . '/../includes/study_service.php';
require_once __DIR__ . '/../genetic/experiments.php';
require_once __DIR__ . '/../genetic/sample_problem.php';

api_run([
    'run' => function (int $uid, array $in) {
        @set_time_limit(300);
        $exp = v_enum($in, 'experiment', ['population', 'mutation', 'generations', 'baseline'], 'baseline');
        $runs = v_int($in, 'runs', 'จำนวนรอบ', 1, 10, 3);
        $source = v_enum($in, 'source', ['user', 'sample'], 'sample');

        $input = $source === 'user' ? build_problem_input($uid, today()) : sample_problem_input(today());
        $problem = new StudyProblem($input);
        if (!$problem->isSolvable()) {
            throw new ApiError('ข้อมูลไม่พอสำหรับการทดลอง (ต้องมีวิชาที่ยังไม่สอบและเวลาว่าง) — ลองใช้ชุดข้อมูลตัวอย่าง', 422);
        }
        $runner = new ExperimentRunner($problem, [], $runs);
        $out = $exp === 'baseline' ? $runner->baseline() : $runner->preset($exp);
        $out['experiment'] = $exp;
        $out['problem'] = [
            'source' => $source, 'subjects' => $problem->subjectCount(), 'slots' => $problem->slotCount(),
            'days' => $problem->dayCount(), 'required_hours' => $problem->requiredHours(), 'capacity_hours' => $problem->capacityHours(),
        ];
        $out['defaults'] = GeneticAlgorithm::DEFAULTS;
        return $out;
    },
], []);
