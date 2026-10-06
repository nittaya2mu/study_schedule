<?php
/**
 * Genetic Algorithm Engine
 *
 *   Create Initial Population → Calculate Fitness → [ Selection → Crossover → Mutation
 *   → New Population (+Elitism) → Calculate Fitness ] × N → Best Chromosome
 *
 * เงื่อนไขหยุด (Termination):
 *   1) ครบจำนวน generation สูงสุด
 *   2) Fitness ถึงค่าเป้าหมาย (target_fitness)
 *   3) Fitness ไม่ดีขึ้นต่อเนื่อง (stagnation_limit generation)
 */
require_once __DIR__ . '/problem.php';
require_once __DIR__ . '/chromosome.php';
require_once __DIR__ . '/fitness.php';
require_once __DIR__ . '/population.php';
require_once __DIR__ . '/selection.php';
require_once __DIR__ . '/crossover.php';
require_once __DIR__ . '/mutation.php';
require_once __DIR__ . '/baseline.php';

class GeneticAlgorithm
{
    public const DEFAULTS = [
        'population_size'  => 50,
        'generations'      => 200,
        'crossover_rate'   => 0.8,
        'mutation_rate'    => 0.02,
        'tournament_size'  => 3,
        'elitism'          => 2,
        'crossover_method' => 'day_uniform',
        'target_fitness'   => 100,
        'stagnation_limit' => 0,      // 0 = ปิด
        'seed'             => null,
    ];

    /** ขอบเขตค่าพารามิเตอร์ที่อนุญาต (ป้องกันผู้ใช้ใส่ค่าที่ทำให้ server ค้าง) */
    public const LIMITS = [
        'population_size'  => [4, 300],
        'generations'      => [1, 1000],
        'crossover_rate'   => [0.0, 1.0],
        'mutation_rate'    => [0.0, 1.0],
        'tournament_size'  => [2, 10],
        'elitism'          => [0, 20],
        'target_fitness'   => [0, 100],
        'stagnation_limit' => [0, 1000],
    ];

    public array $params;
    private FitnessCalculator $fitness;

    public function __construct(private StudyProblem $problem, array $params = [])
    {
        $this->params = self::normaliseParams($params);
        $this->fitness = new FitnessCalculator($problem);
    }

    public static function normaliseParams(array $params): array
    {
        $out = self::DEFAULTS;
        foreach ($params as $k => $v) {
            if (!array_key_exists($k, self::DEFAULTS) || $v === '' || $v === null) continue;
            $out[$k] = $v;
        }
        foreach (self::LIMITS as $k => [$min, $max]) {
            $isFloat = in_array($k, ['crossover_rate', 'mutation_rate', 'target_fitness'], true);
            $out[$k] = $isFloat ? (float)$out[$k] : (int)$out[$k];
            $out[$k] = max($min, min($max, $out[$k]));
        }
        $out['elitism'] = min($out['elitism'], $out['population_size'] - 1);
        $out['seed'] = $out['seed'] === null ? null : (int)$out['seed'];
        if (!in_array($out['crossover_method'], Crossover::METHODS, true)) $out['crossover_method'] = 'day_uniform';
        return $out;
    }

    /**
     * รัน GA และคืนผลลัพธ์
     * @param callable|null $onGeneration fn(int $gen, float $best, float $avg)
     */
    public function run(?callable $onGeneration = null): array
    {
        $P = $this->params;
        $t0 = microtime(true);
        GaRandom::seed($P['seed']);

        $selection = new TournamentSelection($P['tournament_size']);
        $crossover = new Crossover($this->problem, $P['crossover_method']);
        $mutation  = new Mutation($this->problem, $P['mutation_rate']);

        // 1) Initial population
        $pop = Population::random($this->problem, $P['population_size']);
        $pop->evaluate($this->fitness);
        $pop->sort();

        $history = [];
        $bestEver = $pop->members[0]->copy();
        $bestEver->fitness = $pop->members[0]->fitness;
        $stagnant = 0;
        $reason = 'max_generations';
        $gen = 0;
        $history[] = $this->snapshot(0, $pop);

        for ($gen = 1; $gen <= $P['generations']; $gen++) {
            // 2) Elitism: เก็บตารางที่ดีที่สุดไว้โดยไม่เปลี่ยนแปลง
            $next = new Population();
            for ($e = 0; $e < $P['elitism']; $e++) {
                $next->members[] = $pop->members[$e];
            }
            // 3) Selection → Crossover → Mutation
            while ($next->size() < $P['population_size']) {
                $pa = $selection->select($pop);
                $pb = $selection->select($pop);
                if (GaRandom::float() < $P['crossover_rate']) {
                    [$c1, $c2] = $crossover->cross($pa, $pb);
                } else {
                    $c1 = $pa->copy(); $c2 = $pb->copy();
                }
                $mutation->mutate($c1);
                $mutation->mutate($c2);
                $next->members[] = $c1;
                if ($next->size() < $P['population_size']) $next->members[] = $c2;
            }
            // 4) Evaluate new population
            $pop = $next;
            $pop->evaluate($this->fitness);
            $pop->sort();
            $history[] = $this->snapshot($gen, $pop);
            if ($onGeneration) $onGeneration($gen, $pop->members[0]->fitness, $pop->averageFitness());

            // 5) Termination
            if ($pop->members[0]->fitness > $bestEver->fitness + 1e-9) {
                $bestEver = $pop->members[0]->copy();
                $bestEver->fitness = $pop->members[0]->fitness;
                $stagnant = 0;
            } else {
                $stagnant++;
            }
            if ($bestEver->fitness >= $P['target_fitness']) { $reason = 'target_fitness'; break; }
            if ($P['stagnation_limit'] > 0 && $stagnant >= $P['stagnation_limit']) { $reason = 'stagnation'; break; }
        }

        $this->fitness->evaluate($bestEver);
        return [
            'best'               => $bestEver,
            'fitness'            => $bestEver->fitness,
            'detail'             => $bestEver->detail,
            'sessions'           => $bestEver->decode($this->problem),
            'generations'        => min($gen, $P['generations']),
            'termination'        => $reason,
            'history'            => $history,
            'evaluations'        => $this->fitness->evaluations,
            'execution_ms'       => (int)round((microtime(true) - $t0) * 1000),
            'params'             => $P,
        ];
    }

    private function snapshot(int $gen, Population $pop): array
    {
        return [
            'gen'   => $gen,
            'best'  => round($pop->members[0]->fitness, 3),
            'avg'   => round($pop->averageFitness(), 3),
            'worst' => round($pop->worstFitness(), 3),
        ];
    }
}
