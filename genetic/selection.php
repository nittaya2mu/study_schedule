<?php
/**
 * Selection — Tournament Selection
 * สุ่มหยิบ k ตารางจาก Population แล้วเลือกตัวที่ fitness สูงที่สุดเป็นพ่อแม่
 * k มาก = selection pressure สูง (ลู่เข้าเร็ว แต่เสี่ยงติด local optimum)
 */
class TournamentSelection
{
    public function __construct(private int $tournamentSize = 3) {}

    public function select(Population $pop): Chromosome
    {
        $n = $pop->size();
        $best = null;
        for ($i = 0; $i < $this->tournamentSize; $i++) {
            $cand = $pop->members[GaRandom::int(0, $n - 1)];
            if ($best === null || $cand->fitness > $best->fitness) $best = $cand;
        }
        return $best;
    }
}
