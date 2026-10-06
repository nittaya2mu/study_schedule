<?php
/**
 * Chromosome = ตารางอ่านหนังสือ 1 รูปแบบ
 *
 * การเข้ารหัส (Encoding): Slot-based integer encoding
 *   genes[i] = index ของวิชาที่อ่านใน time slot ที่ i   (-1 = ว่าง / ไม่อ่าน)
 *
 * ข้อดีของการเข้ารหัสแบบนี้:
 *   - 1 slot มีได้แค่ 1 วิชา  →  ไม่มีทางเกิดเวลาชนกัน (overlap) โดยโครงสร้าง
 *   - Crossover / Mutation ทำได้ง่ายและได้ลูกที่ถูกรูปแบบเสมอ
 *
 * เมื่อ decode แล้ว แต่ละ Gene ที่ไม่ว่างจะกลายเป็น Study Session:
 *   [Monday, 18:00, Data Structures, Chapter 3]
 */
class Chromosome
{
    /** @var int[] */
    public array $genes;
    public ?float $fitness = null;
    public ?array $detail = null;

    public function __construct(array $genes)
    {
        $this->genes = $genes;
    }

    /**
     * สร้าง Chromosome แบบสุ่ม — ใช้สร้าง Initial Population และเป็น Random baseline
     * ความน่าจะเป็นที่ slot จะถูกใช้ = ชั่วโมงที่ต้องอ่าน / ชั่วโมงว่างทั้งหมด
     */
    public static function random(StudyProblem $p): self
    {
        $n = $p->slotCount();
        $fill = $n > 0 ? min(1.0, $p->totalRequiredUnits / $n) : 0;
        $genes = [];
        for ($i = 0; $i < $n; $i++) {
            $valid = $p->validSubjects[$i];
            if ($valid && GaRandom::float() < $fill) {
                $genes[] = $valid[GaRandom::int(0, count($valid) - 1)];
            } else {
                $genes[] = -1;
            }
        }
        return new self($genes);
    }

    public function copy(): self
    {
        return new self($this->genes);   // PHP array = copy-on-write
    }

    /**
     * Decode: แปลง Chromosome เป็นรายการ Study Session ที่มีวัน เวลา วิชา และหัวข้อ
     * หัวข้อถูกกระจายตามลำดับบทเรียน (บทแรกอ่านก่อน) ส่วนที่เกินจะเป็น "ทบทวน"
     */
    public function decode(StudyProblem $p): array
    {
        $topicPtr = [];
        $topicUsed = [];
        $sessions = [];
        foreach ($this->genes as $i => $si) {
            if ($si < 0 || $p->daysBefore[$i][$si] < 1) continue;   // ตัด session ที่หลังวันสอบทิ้ง
            $sub = $p->subjects[$si];
            $topicPtr[$si] ??= 0;
            $topicUsed[$si] ??= 0;

            $topic = null;
            while (isset($sub['topics'][$topicPtr[$si]])) {
                $t = $sub['topics'][$topicPtr[$si]];
                if ($topicUsed[$si] < $t['units']) { $topic = $t; $topicUsed[$si]++; break; }
                $topicPtr[$si]++;
                $topicUsed[$si] = 0;
            }

            $slot = $p->slots[$i];
            $sessions[] = [
                'slot'         => $i,
                'date'         => $slot['date'],
                'day_of_week'  => $slot['dow'],
                'start_time'   => StudyProblem::toTime($slot['start']),
                'end_time'     => StudyProblem::toTime($slot['end']),
                'subject_id'   => $sub['id'],
                'subject_name' => $sub['name'],
                'color'        => $sub['color'],
                'topic_id'     => $topic['id'] ?? null,
                'topic_name'   => $topic['name'] ?? 'ทบทวน (Review)',
            ];
        }
        return $sessions;
    }
}

/**
 * ตัวสุ่มที่กำหนด seed ได้ — ทำให้ผลการทดลองทำซ้ำได้ (reproducible)
 */
final class GaRandom
{
    public static function seed(?int $seed): void
    {
        if ($seed !== null) mt_srand($seed);
        else mt_srand();
    }

    public static function float(): float
    {
        return mt_rand() / (mt_getrandmax() + 1);
    }

    public static function int(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }
}
