<?php
/**
 * Baseline Schedulers — ใช้เปรียบเทียบกับ GA (Experiment 4)
 *
 *  RandomScheduler    : สุ่มวิชาลง slot (เหมือน 1 chromosome ใน initial population)
 *  RuleBasedScheduler : Greedy แบบ Earliest-Deadline-First
 *                       วนทีละ slot ตามเวลา เลือกวิชาที่สอบเร็วที่สุด (เสมอกัน → สำคัญกว่า)
 *                       โดยเคารพชั่วโมงสูงสุดต่อวัน และจำนวน session ติดกัน
 *
 * ทั้งคู่ถูกประเมินด้วย Fitness Function เดียวกับ GA จึงเปรียบเทียบได้อย่างยุติธรรม
 */
class RandomScheduler
{
    public function __construct(private StudyProblem $p) {}

    public function run(?int $seed = null): array
    {
        $t0 = microtime(true);
        GaRandom::seed($seed);
        $c = Chromosome::random($this->p);
        (new FitnessCalculator($this->p))->evaluate($c);
        return self::result($c, $this->p, $t0);
    }

    public static function result(Chromosome $c, StudyProblem $p, float $t0): array
    {
        return [
            'best'         => $c,
            'fitness'      => $c->fitness,
            'detail'       => $c->detail,
            'sessions'     => $c->decode($p),
            'execution_ms' => (int)round((microtime(true) - $t0) * 1000),
        ];
    }
}

class RuleBasedScheduler
{
    public function __construct(private StudyProblem $p) {}

    public function run(): array
    {
        $t0 = microtime(true);
        $p = $this->p;
        $remaining = array_map(fn($s) => $s['required_units'], $p->subjects);

        // ลำดับความสำคัญของวิชา: วันสอบเร็วก่อน → importance สูงก่อน
        $order = array_keys($p->subjects);
        usort($order, function ($a, $b) use ($p) {
            $sa = $p->subjects[$a]; $sb = $p->subjects[$b];
            return [$sa['exam_date'], -$sa['importance']] <=> [$sb['exam_date'], -$sb['importance']];
        });

        $genes = array_fill(0, $p->slotCount(), -1);
        $dayMinutes = [];
        $runSubj = -1; $runLen = 0;
        foreach ($p->slots as $i => $slot) {
            $used = $dayMinutes[$slot['day']] ?? 0;
            $prevAdjacent = $i > 0 && $p->adjacentNext[$i - 1];
            if (!$prevAdjacent) { $runSubj = -1; $runLen = 0; }
            if ($used + $slot['minutes'] > $p->dailyMaxMinutes) { $runSubj = -1; $runLen = 0; continue; }

            $pick = -1;
            foreach ($order as $si) {
                if ($remaining[$si] <= 0 || $p->daysBefore[$i][$si] < 1) continue;
                if ($si === $runSubj && $runLen >= $p->maxConsecutive) continue;
                $pick = $si;
                break;
            }
            if ($pick < 0) { $runSubj = -1; $runLen = 0; continue; }
            $genes[$i] = $pick;
            $remaining[$pick]--;
            $dayMinutes[$slot['day']] = $used + $slot['minutes'];
            $runLen = $pick === $runSubj ? $runLen + 1 : 1;
            $runSubj = $pick;
        }

        $c = new Chromosome($genes);
        (new FitnessCalculator($p))->evaluate($c);
        return RandomScheduler::result($c, $p, $t0);
    }
}
