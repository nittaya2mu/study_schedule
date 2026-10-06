<?php
/**
 * Fitness Function — ให้คะแนนความเหมาะสมของตาราง (เต็ม 100 ก่อนหักโทษ)
 *
 *   Fitness =  35 × Completion Score      (ชั่วโมงที่จัดได้ / ที่ต้องอ่าน ถ่วงน้ำหนักด้วยความสำคัญ+ความยาก)
 *            + 20 × Exam Priority Score   (อ่านในช่วงใกล้วันสอบ — ไม่เกิน proximity window)
 *            + 15 × Availability Score    (ใช้ช่วงเวลาที่ผู้ใช้มีสมาธิดีที่สุด)
 *            + 15 × Study Balance Score   (ภาระการอ่านแต่ละวันสม่ำเสมอ)
 *            + 15 × Continuity Score      (อ่านต่อเนื่องเป็นบล็อก + กระจายหลายวัน)
 *            −  Conflict Penalty          (อ่านหลัง/ในวันสอบ, อ่านวิชาเดียวติดกันเกินกำหนด)
 *            −  Overload Penalty          (เกินชั่วโมงสูงสุดต่อวัน)
 *            −  Over-allocation Penalty   (จัดเกินชั่วโมงที่ต้องอ่าน)
 *
 * ทุก Score อยู่ในช่วง 0..1 จึงอธิบายและเปรียบเทียบได้ง่าย
 */
class FitnessCalculator
{
    public const WEIGHTS = [
        'completion'   => 35,
        'exam'         => 20,
        'availability' => 15,
        'balance'      => 15,
        'continuity'   => 15,
    ];
    public const PENALTY_INVALID_SESSION = 5.0;  // ต่อ session ที่อยู่หลัง/ในวันสอบ
    public const PENALTY_EXCESS_RUN      = 2.0;  // ต่อ session ที่ติดกันเกิน max_consecutive
    public const PENALTY_OVERLOAD_HOUR   = 3.0;  // ต่อชั่วโมงที่เกิน daily max
    public const PENALTY_OVER_ALLOC      = 1.0;  // ต่อ session ที่เกินชั่วโมงที่ต้องอ่าน

    public int $evaluations = 0;

    public function __construct(private StudyProblem $p) {}

    public function evaluate(Chromosome $c): float
    {
        $this->evaluations++;
        $c->detail = $this->analyse($c->genes);
        $c->fitness = $c->detail['fitness'];
        return $c->fitness;
    }

    /** คำนวณแบบละเอียด คืนค่า breakdown ทั้งหมด (ใช้แสดงผลและรายงาน) */
    public function analyse(array $genes): array
    {
        $p = $this->p;
        $S = $p->subjectCount();
        $W = $p->proximityWindow;
        $maxC = $p->maxConsecutive;

        $units = array_fill(0, $S, 0);
        $subjDays = array_fill(0, $S, []);
        $dayMinutes = array_fill(0, max(1, $p->dayCount()), 0);
        $valid = 0; $invalid = 0; $proxSum = 0.0; $prefSum = 0.0;

        // ---- เดินผ่านทุก gene ครั้งเดียว ----
        $paired = 0; $excessRun = 0;
        $runSubj = -1; $runLen = 0;
        $n = count($genes);
        for ($i = 0; $i < $n; $i++) {
            $g = $genes[$i];
            if ($g >= 0) {
                $slot = $p->slots[$i];
                $dayMinutes[$slot['day']] += $slot['minutes'];
                $before = $p->daysBefore[$i][$g];
                if ($before < 1) {
                    $invalid++;
                } else {
                    $valid++;
                    $units[$g]++;
                    $proxSum += $before <= $W ? 1.0 : $W / $before;
                    $prefSum += $slot['pref'] / 3;
                    $subjDays[$g][$slot['day']] = true;
                }
            }
            // ความต่อเนื่อง: นับความยาว run ของวิชาเดียวกันใน slot ที่ติดกัน
            if ($g >= 0 && $g === $runSubj) {
                $runLen++;
            } else {
                $this->closeRun($runLen, $maxC, $paired, $excessRun);
                $runSubj = $g; $runLen = $g >= 0 ? 1 : 0;
            }
            if (!$p->adjacentNext[$i]) {
                $this->closeRun($runLen, $maxC, $paired, $excessRun);
                $runSubj = -1; $runLen = 0;
            }
        }
        $this->closeRun($runLen, $maxC, $paired, $excessRun);

        // ---- 1) Completion ----
        $completion = 0.0; $overAlloc = 0; $subjectStats = [];
        $spacing = 0.0;
        foreach ($p->subjects as $si => $sub) {
            $req = $sub['required_units'];
            $got = $units[$si];
            $completion += $sub['importance'] * min($got, $req);
            $overAlloc += max(0, $got - $req);
            $idealDays = max(1, (int)ceil(min($got, $req) / $maxC));
            $spacing += $got > 0 ? min(1.0, count($subjDays[$si]) / $idealDays) : 0.0;
            $subjectStats[] = [
                'subject_id'      => $sub['id'],
                'name'            => $sub['name'],
                'color'           => $sub['color'],
                'required_hours'  => round($req * $p->sessionMinutes / 60, 2),
                'scheduled_hours' => round($got * $p->sessionMinutes / 60, 2),
                'days'            => count($subjDays[$si]),
            ];
        }
        $completion = $p->totalImportance > 0 ? $completion / $p->totalImportance : 0;

        // ---- 2) Exam priority & 3) Availability ----
        $exam  = $valid ? $proxSum / $valid : 0.0;
        $avail = $valid ? $prefSum / $valid : 0.0;

        // ---- 4) Balance : 1 − coefficient of variation ของภาระรายวัน (เฉพาะวันที่มี slot) ----
        $loads = [];
        foreach ($p->daySlots as $d => $slots) {
            if ($slots) $loads[] = $dayMinutes[$d];
        }
        $balance = 0.0;
        if ($loads && array_sum($loads) > 0) {
            $mean = array_sum($loads) / count($loads);
            $var = 0.0;
            foreach ($loads as $l) $var += ($l - $mean) ** 2;
            $cv = sqrt($var / count($loads)) / $mean;
            $balance = max(0.0, 1 - min(1.0, $cv));
        }

        // ---- 5) Continuity : อ่านเป็นบล็อก (≤ max_consecutive) + กระจายหลายวัน ----
        $blockScore = $maxC <= 1 ? 1.0 : ($valid ? min(1.0, $paired / $valid) : 0.0);
        $spaceScore = $S ? $spacing / $S : 0.0;
        $continuity = 0.5 * $blockScore + 0.5 * $spaceScore;

        // ---- Penalties ----
        $overloadHours = 0.0; $overloadDays = 0;
        foreach ($dayMinutes as $m) {
            if ($m > $p->dailyMaxMinutes) {
                $overloadHours += ($m - $p->dailyMaxMinutes) / 60;
                $overloadDays++;
            }
        }
        $conflictPenalty = $invalid * self::PENALTY_INVALID_SESSION + $excessRun * self::PENALTY_EXCESS_RUN;
        $overloadPenalty = $overloadHours * self::PENALTY_OVERLOAD_HOUR;
        $overAllocPenalty = $overAlloc * self::PENALTY_OVER_ALLOC;

        $scores = [
            'completion'   => $completion,
            'exam'         => $exam,
            'availability' => $avail,
            'balance'      => $balance,
            'continuity'   => $continuity,
        ];
        $positive = 0.0;
        foreach (self::WEIGHTS as $k => $w) $positive += $w * $scores[$k];
        $fitness = $positive - $conflictPenalty - $overloadPenalty - $overAllocPenalty;

        return [
            'fitness'   => round($fitness, 4),
            'scores'    => array_map(fn($v) => round($v, 4), $scores),
            'weighted'  => array_map(fn($k) => round(self::WEIGHTS[$k] * $scores[$k], 3), array_combine(array_keys($scores), array_keys($scores))),
            'penalties' => [
                'conflict'        => round($conflictPenalty, 3),
                'overload'        => round($overloadPenalty, 3),
                'over_allocation' => round($overAllocPenalty, 3),
            ],
            'stats' => [
                'sessions'              => $valid + $invalid,
                'valid_sessions'        => $valid,
                'scheduled_hours'       => round($valid * $p->sessionMinutes / 60, 2),
                'required_hours'        => round($p->requiredHours(), 2),
                'capacity_hours'        => round($p->capacityHours(), 2),
                'sessions_after_exam'   => $invalid,
                'excess_consecutive'    => $excessRun,
                'overload_days'         => $overloadDays,
                'overload_hours'        => round($overloadHours, 2),
                'over_allocated'        => $overAlloc,
                'constraint_violations' => $invalid + $excessRun + $overloadDays,
                'subjects_complete'     => count(array_filter($subjectStats, fn($s) => $s['scheduled_hours'] >= $s['required_hours'])),
                'subjects_total'        => $S,
            ],
            'subjects' => $subjectStats,
        ];
    }

    private function closeRun(int $len, int $maxC, int &$paired, int &$excess): void
    {
        if ($len >= 2) $paired += min($len, $maxC);
        if ($len > $maxC) $excess += $len - $maxC;
    }
}
