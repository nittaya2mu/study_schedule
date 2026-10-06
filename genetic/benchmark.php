<?php
/**
 * CLI: รัน GA / การทดลองกับชุดข้อมูลตัวอย่าง โดยไม่ต้องใช้ฐานข้อมูล
 *
 *   php genetic/benchmark.php                       # รัน GA 1 ครั้ง + แสดงตาราง
 *   php genetic/benchmark.php all --runs=5          # Experiment 1–4
 *   php genetic/benchmark.php population|mutation|generations|baseline [--runs=N]
 *   php genetic/benchmark.php all --csv=results     # เขียนผลเป็นไฟล์ CSV ด้วย
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/experiments.php';
require_once __DIR__ . '/sample_problem.php';

$args = array_slice($argv, 1);
$opts = ['runs' => 5, 'csv' => null, 'start' => '2026-10-05'];
$cmd = 'run';
foreach ($args as $a) {
    if (preg_match('/^--(\w+)=(.*)$/', $a, $m)) $opts[$m[1]] = $m[2];
    else $cmd = $a;
}

$problem = new StudyProblem(sample_problem_input($opts['start']));
printf("Problem: %d subjects, %d slots over %d days | required %.1f h, capacity %.1f h\n\n",
    $problem->subjectCount(), $problem->slotCount(), $problem->dayCount(),
    $problem->requiredHours(), $problem->capacityHours());

if ($cmd === 'run') {
    $ga = new GeneticAlgorithm($problem, ['seed' => 42]);
    $res = $ga->run();
    printf("GA fitness %.2f in %d generations (%d ms, %s)\n", $res['fitness'], $res['generations'], $res['execution_ms'], $res['termination']);
    print_r($res['detail']['scores']);
    print_r($res['detail']['penalties']);
    print_r($res['detail']['stats']);
    $day = '';
    foreach ($res['sessions'] as $s) {
        if ($s['date'] !== $day) { $day = $s['date']; echo "\n", date('D d M', strtotime($day)), "\n"; }
        printf("  %s–%s  %-24s %s\n", $s['start_time'], $s['end_time'], $s['subject_name'], $s['topic_name']);
    }
    exit;
}

$runner = new ExperimentRunner($problem, [], (int)$opts['runs']);
$todo = $cmd === 'all' ? ['population', 'mutation', 'generations', 'baseline'] : [$cmd];

foreach ($todo as $exp) {
    echo str_repeat('=', 72), "\n";
    if ($exp === 'baseline') {
        echo "Experiment 4 — Baseline comparison ({$opts['runs']} runs)\n";
        $out = $runner->baseline();
        printf("%-20s %10s %8s %10s %10s %10s\n", 'Method', 'Fitness', 'SD', 'Time(ms)', 'Violations', 'Hours');
        $csv = [['method', 'fitness_mean', 'fitness_sd', 'time_ms', 'violations', 'scheduled_hours']];
        foreach ($out['rows'] as $r) {
            printf("%-20s %10.2f %8.2f %10.1f %10.2f %10.2f\n", $r['method'], $r['fitness_mean'], $r['fitness_sd'], $r['time_ms_mean'], $r['violations'], $r['scheduled_hours']);
            $csv[] = [$r['method'], $r['fitness_mean'], $r['fitness_sd'], $r['time_ms_mean'], $r['violations'], $r['scheduled_hours']];
        }
    } else {
        $out = $runner->preset($exp);
        echo "Experiment — {$out['param']} ({$out['runs']} runs each)\n";
        printf("%-12s %10s %8s %10s %10s %10s\n", 'Value', 'Fitness', 'SD', 'Max', 'Time(ms)', 'Violations');
        $csv = [['value', 'fitness_mean', 'fitness_sd', 'fitness_max', 'time_ms', 'violations']];
        foreach ($out['rows'] as $r) {
            printf("%-12s %10.2f %8.2f %10.2f %10.1f %10.2f\n", $r['value'], $r['fitness_mean'], $r['fitness_sd'], $r['fitness_max'], $r['time_ms_mean'], $r['violations']);
            $csv[] = [$r['value'], $r['fitness_mean'], $r['fitness_sd'], $r['fitness_max'], $r['time_ms_mean'], $r['violations']];
        }
    }
    if ($opts['csv']) {
        $file = "{$opts['csv']}_{$exp}.csv";
        $fh = fopen($file, 'w');
        foreach ($csv as $line) fputcsv($fh, $line);
        fclose($fh);
        echo "→ saved $file\n";
    }
    echo "\n";
}
