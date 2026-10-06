<?php
/**
 * Crossover — นำตาราง 2 แบบมาผสมกันเป็นตารางลูก
 *
 *  day_uniform (ค่าเริ่มต้น): เลือกทั้ง "วัน" จากพ่อหรือแม่
 *      Parent A: Mon → DS,   Tue → DB
 *      Parent B: Mon → AI,   Tue → Math
 *      Child   : Mon → DS,   Tue → Math
 *    รักษาโครงสร้างภายในวัน (บล็อกการอ่านต่อเนื่อง) ได้ดี
 *
 *  one_point / two_point / uniform: แบบมาตรฐาน ใช้เปรียบเทียบในการทดลอง
 */
class Crossover
{
    public const METHODS = ['day_uniform', 'one_point', 'two_point', 'uniform'];

    public function __construct(private StudyProblem $p, private string $method = 'day_uniform')
    {
        if (!in_array($method, self::METHODS, true)) $this->method = 'day_uniform';
    }

    /** @return Chromosome[] ลูก 2 ตัว */
    public function cross(Chromosome $a, Chromosome $b): array
    {
        $ga = $a->genes; $gb = $b->genes;
        $n = count($ga);
        if ($n < 2) return [$a->copy(), $b->copy()];
        $c1 = $ga; $c2 = $gb;

        switch ($this->method) {
            case 'one_point':
                $cut = GaRandom::int(1, $n - 1);
                for ($i = $cut; $i < $n; $i++) { $c1[$i] = $gb[$i]; $c2[$i] = $ga[$i]; }
                break;

            case 'two_point':
                $x = GaRandom::int(0, $n - 1); $y = GaRandom::int(0, $n - 1);
                if ($x > $y) [$x, $y] = [$y, $x];
                for ($i = $x; $i <= $y; $i++) { $c1[$i] = $gb[$i]; $c2[$i] = $ga[$i]; }
                break;

            case 'uniform':
                for ($i = 0; $i < $n; $i++) {
                    if (GaRandom::float() < 0.5) { $c1[$i] = $gb[$i]; $c2[$i] = $ga[$i]; }
                }
                break;

            default: // day_uniform
                foreach ($this->p->daySlots as $slots) {
                    if (!$slots || GaRandom::float() < 0.5) continue;
                    foreach ($slots as $i) { $c1[$i] = $gb[$i]; $c2[$i] = $ga[$i]; }
                }
        }
        return [new Chromosome($c1), new Chromosome($c2)];
    }
}
