<?php
/**
 * Mutation — สุ่มเปลี่ยน Gene บางตัว เพื่อรักษาความหลากหลายและหนี Local Optimum
 *
 * แต่ละ gene มีโอกาส mutationRate ที่จะเกิด 1 ใน 2 แบบ:
 *   1) Reassign : เปลี่ยนวิชาของ slot (หรือทำให้ว่าง)
 *        Monday 18:00 → Database   ⇒   Monday 18:00 → AI
 *   2) Move/Swap: สลับ slot กับ slot อื่น = เลื่อนเวลาอ่าน
 *        Monday 18:00 → Database   ⇒   Monday 20:00 → Database
 */
class Mutation
{
    public function __construct(private StudyProblem $p, private float $rate = 0.05) {}

    public function mutate(Chromosome $c): void
    {
        $n = count($c->genes);
        if ($n === 0 || $this->rate <= 0) return;
        $changed = false;
        for ($i = 0; $i < $n; $i++) {
            if (GaRandom::float() >= $this->rate) continue;
            $changed = true;
            if (GaRandom::float() < 0.5) {
                // Reassign: เลือกวิชาที่ยังไม่สอบ หรือว่าง
                $valid = $this->p->validSubjects[$i];
                $k = GaRandom::int(-1, count($valid) - 1);
                $c->genes[$i] = $k < 0 ? -1 : $valid[$k];
            } else {
                // Move: สลับกับ slot อื่นแบบสุ่ม (มักอยู่ใกล้กัน เพื่อเลื่อนเวลาเล็กน้อย)
                $j = GaRandom::float() < 0.7
                    ? max(0, min($n - 1, $i + GaRandom::int(-4, 4)))
                    : GaRandom::int(0, $n - 1);
                [$c->genes[$i], $c->genes[$j]] = [$c->genes[$j], $c->genes[$i]];
            }
        }
        if ($changed) { $c->fitness = null; $c->detail = null; }
    }
}
