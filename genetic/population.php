<?php
/**
 * Population — กลุ่มของ Chromosome (ตารางหลายรูปแบบ) ในแต่ละ Generation
 */
class Population
{
    /** @var Chromosome[] */
    public array $members = [];

    public static function random(StudyProblem $p, int $size): self
    {
        $pop = new self();
        for ($i = 0; $i < $size; $i++) {
            $pop->members[] = Chromosome::random($p);
        }
        return $pop;
    }

    public function evaluate(FitnessCalculator $f): void
    {
        foreach ($this->members as $c) {
            if ($c->fitness === null) $f->evaluate($c);
        }
    }

    /** เรียงจาก fitness มากไปน้อย */
    public function sort(): void
    {
        usort($this->members, fn(Chromosome $a, Chromosome $b) => $b->fitness <=> $a->fitness);
    }

    public function best(): Chromosome
    {
        $best = $this->members[0];
        foreach ($this->members as $c) {
            if ($c->fitness > $best->fitness) $best = $c;
        }
        return $best;
    }

    public function averageFitness(): float
    {
        $sum = 0.0;
        foreach ($this->members as $c) $sum += $c->fitness;
        return $this->members ? $sum / count($this->members) : 0.0;
    }

    public function worstFitness(): float
    {
        $min = INF;
        foreach ($this->members as $c) $min = min($min, $c->fitness);
        return $this->members ? $min : 0.0;
    }

    public function size(): int
    {
        return count($this->members);
    }
}
