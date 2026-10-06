<?php
/**
 * ExperimentRunner — ชุดการทดลองสำหรับประเมิน GA (หัวข้อ 14 ของ proposal)
 *
 *   Experiment 1 : Population Size     (20, 50, 100)
 *   Experiment 2 : Mutation Rate       (0.01, 0.05, 0.10, 0.20)
 *   Experiment 3 : Number of Generations (50, 100, 200, 500)
 *   Experiment 4 : เปรียบเทียบกับ Baseline (Random, Rule-based, GA)
 *
 * แต่ละ configuration รันซ้ำหลายรอบด้วย seed ต่างกัน แล้วรายงานค่าเฉลี่ย ± SD
 * ใช้ได้ทั้งจาก CLI (genetic/benchmark.php) และจากหน้าเว็บ (api/experiments.php)
 */
require_once __DIR__ . '/genetic_algorithm.php';

class ExperimentRunner
{
    public const PRESETS = [
        'population'  => ['param' => 'population_size', 'values' => [20, 50, 100]],
        'mutation'    => ['param' => 'mutation_rate',   'values' => [0.01, 0.05, 0.10, 0.20]],
        'generations' => ['param' => 'generations',     'values' => [50, 100, 200, 500]],
    ];

    public function __construct(
        private StudyProblem $problem,
        private array $baseParams = [],
        private int $runs = 5,
        private int $seedBase = 1000
    ) {
        $this->runs = max(1, min(30, $runs));
    }

    /** Experiment 1–3: เปลี่ยนพารามิเตอร์ทีละตัว */
    public function sweep(string $param, array $values): array
    {
        $rows = [];
        foreach ($values as $v) {
            $fit = []; $time = []; $viol = []; $gens = []; $curves = [];
            for ($r = 0; $r < $this->runs; $r++) {
                $ga = new GeneticAlgorithm($this->problem, array_merge($this->baseParams, [
                    $param => $v, 'seed' => $this->seedBase + $r,
                ]));
                $res = $ga->run();
                $fit[]  = $res['fitness'];
                $time[] = $res['execution_ms'];
                $viol[] = $res['detail']['stats']['constraint_violations'];
                $gens[] = $res['generations'];
                $curves[] = array_column($res['history'], 'best');
            }
            $rows[] = [
                'value'        => $v,
                'fitness_mean' => round(self::mean($fit), 3),
                'fitness_sd'   => round(self::sd($fit), 3),
                'fitness_max'  => round(max($fit), 3),
                'time_ms_mean' => round(self::mean($time), 1),
                'violations'   => round(self::mean($viol), 2),
                'generations'  => round(self::mean($gens), 1),
                'curve'        => self::meanCurve($curves),
            ];
        }
        return ['param' => $param, 'runs' => $this->runs, 'rows' => $rows];
    }

    public function preset(string $name): array
    {
        $p = self::PRESETS[$name] ?? null;
        if (!$p) throw new InvalidArgumentException("Unknown experiment: $name");
        return $this->sweep($p['param'], $p['values']);
    }

    /** Experiment 4: Random vs Rule-based vs GA */
    public function baseline(): array
    {
        $methods = ['Random' => [], 'Rule-based' => [], 'Genetic Algorithm' => []];
        for ($r = 0; $r < $this->runs; $r++) {
            $methods['Random'][] = (new RandomScheduler($this->problem))->run($this->seedBase + $r);
            if ($r === 0) $methods['Rule-based'][] = (new RuleBasedScheduler($this->problem))->run(); // deterministic
            $methods['Genetic Algorithm'][] = (new GeneticAlgorithm($this->problem, array_merge(
                $this->baseParams, ['seed' => $this->seedBase + $r]
            )))->run();
        }

        $rows = [];
        foreach ($methods as $name => $results) {
            $fit = array_column($results, 'fitness');
            $stats = array_map(fn($x) => $x['detail']['stats'], $results);
            $scores = array_map(fn($x) => $x['detail']['scores'], $results);
            $row = [
                'method'          => $name,
                'runs'            => count($results),
                'fitness_mean'    => round(self::mean($fit), 3),
                'fitness_sd'      => round(self::sd($fit), 3),
                'time_ms_mean'    => round(self::mean(array_column($results, 'execution_ms')), 1),
                'violations'      => round(self::mean(array_column($stats, 'constraint_violations')), 2),
                'scheduled_hours' => round(self::mean(array_column($stats, 'scheduled_hours')), 2),
                'required_hours'  => $stats[0]['required_hours'],
                'subjects_complete' => round(self::mean(array_column($stats, 'subjects_complete')), 2),
                'subjects_total'  => $stats[0]['subjects_total'],
                'scores'          => [],
            ];
            foreach (array_keys(FitnessCalculator::WEIGHTS) as $k) {
                $row['scores'][$k] = round(self::mean(array_column($scores, $k)), 4);
            }
            $rows[] = $row;
        }
        return ['runs' => $this->runs, 'rows' => $rows];
    }

    // ------------------------------------------------------------------
    public static function mean(array $xs): float
    {
        return $xs ? array_sum($xs) / count($xs) : 0.0;
    }

    public static function sd(array $xs): float
    {
        $n = count($xs);
        if ($n < 2) return 0.0;
        $m = self::mean($xs);
        $s = 0.0;
        foreach ($xs as $x) $s += ($x - $m) ** 2;
        return sqrt($s / ($n - 1));
    }

    /** ค่าเฉลี่ย best fitness ในแต่ละ generation (runs ที่หยุดก่อนจะใช้ค่าสุดท้ายต่อ) */
    private static function meanCurve(array $curves): array
    {
        $len = max(array_map('count', $curves));
        $out = [];
        for ($g = 0; $g < $len; $g++) {
            $sum = 0.0;
            foreach ($curves as $c) $sum += $c[min($g, count($c) - 1)];
            $out[] = round($sum / count($curves), 3);
        }
        return $out;
    }
}
