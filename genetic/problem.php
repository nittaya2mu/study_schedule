<?php
/**
 * StudyProblem — แปลงข้อมูลผู้ใช้ (วิชา, เวลาว่าง, ข้อจำกัด) ให้อยู่ในรูปที่ GA ใช้งานได้
 *
 *  - สร้าง "Time Slot" จริงทุกช่วงตั้งแต่วันเริ่มต้นถึงวันก่อนสอบวิชาสุดท้าย
 *  - คำนวณข้อมูลล่วงหน้า (precompute) ที่ Fitness Function ใช้บ่อย เพื่อให้ GA ทำงานเร็ว
 *
 * โมดูลนี้ไม่ติดต่อฐานข้อมูล: รับ array ธรรมดาเข้ามา จึงทดสอบ/ทดลองได้จาก CLI
 *
 * รูปแบบ input:
 * [
 *   'start_date' => 'Y-m-d',
 *   'now_minutes' => int|null,         // เวลาปัจจุบัน (นาทีนับจากเที่ยงคืน) ใช้ตัด slot ที่ผ่านไปแล้วของวันแรก
 *   'subjects' => [[ 'id','name','color','difficulty'(1-5),'priority'('Low'|'Medium'|'High'),
 *                    'exam_date','required_hours', 'topics' => [['id','name','hours'],...] ], ...],
 *   'availability' => [[ 'day'(1=Mon..7=Sun),'start'=>'HH:MM','end'=>'HH:MM','preference'(1-3) ], ...],
 *   'blocked' => [[ 'date'=>?'Y-m-d', 'day'=>?int, 'start','end' ], ...],   // ช่วงที่ไม่สะดวก / มีนัดอื่น
 *   'settings' => [ 'session_minutes','break_minutes','daily_max_hours','max_consecutive',
 *                   'proximity_window_days','max_horizon_days' ],
 * ]
 */
class StudyProblem
{
    public const PRIORITY_VALUE = ['Low' => 1, 'Medium' => 2, 'High' => 3];

    /** @var array<int,array> วิชาที่ต้องจัด (index 0..S-1) */
    public array $subjects = [];
    /** @var array<int,array> slot: date, dow, start, end, minutes, pref, day */
    public array $slots = [];
    /** @var array<int,int[]> day index => slot indexes */
    public array $daySlots = [];
    /** @var string[] day index => date */
    public array $dayDates = [];
    /** @var array<int,int[]> slot => subject indexes ที่ยังไม่สอบ (จัดลงได้) */
    public array $validSubjects = [];
    /** @var array<int,array<int,int>> [slot][subject] => จำนวนวันก่อนสอบ (<=0 = สอบไปแล้ว/วันสอบ) */
    public array $daysBefore = [];
    /** @var bool[] slot i กับ i+1 อยู่ติดกัน (วันเดียวกัน คั่นด้วยเวลาพักเท่านั้น) */
    public array $adjacentNext = [];

    public string $startDate;
    public string $endDate;
    public int $sessionMinutes;
    public int $breakMinutes;
    public int $dailyMaxMinutes;
    public int $maxConsecutive;
    public int $proximityWindow;
    public int $totalRequiredUnits = 0;
    public float $totalImportance = 0.0;

    public function __construct(array $input)
    {
        $s = $input['settings'] ?? [];
        $this->sessionMinutes  = max(15, (int)($s['session_minutes'] ?? 60));
        $this->breakMinutes    = max(0, (int)($s['break_minutes'] ?? 15));
        $this->dailyMaxMinutes = (int)round(60 * (float)($s['daily_max_hours'] ?? 4));
        $this->maxConsecutive  = max(1, (int)($s['max_consecutive'] ?? 2));
        $this->proximityWindow = max(1, (int)($s['proximity_window_days'] ?? 14));
        $maxHorizon            = max(1, (int)($s['max_horizon_days'] ?? 90));

        $this->startDate = $input['start_date'] ?? date('Y-m-d');
        $this->buildSubjects($input['subjects'] ?? []);

        // ขอบเขตของตาราง: ตั้งแต่วันเริ่ม ถึงวันก่อนสอบวิชาสุดท้าย (ไม่เกิน max_horizon_days)
        $lastExam = $this->startDate;
        foreach ($this->subjects as $sub) {
            if ($sub['exam_date'] > $lastExam) $lastExam = $sub['exam_date'];
        }
        $end = self::addDays($lastExam, -1);
        $cap = self::addDays($this->startDate, $maxHorizon - 1);
        $this->endDate = min($end, $cap);

        $this->buildSlots(
            $input['availability'] ?? [],
            $input['blocked'] ?? [],
            $input['now_minutes'] ?? null
        );
        $this->precompute();
    }

    // ------------------------------------------------------------------
    private function buildSubjects(array $subjects): void
    {
        foreach ($subjects as $sub) {
            $required = (float)($sub['required_hours'] ?? 0);
            if ($required <= 0 || ($sub['exam_date'] ?? '') <= $this->startDate) {
                continue; // สอบไปแล้ว หรือไม่มีชั่วโมงเหลือให้อ่าน
            }
            $priority = self::PRIORITY_VALUE[$sub['priority'] ?? 'Medium'] ?? 2;
            $difficulty = min(5, max(1, (int)($sub['difficulty'] ?? 3)));
            // Importance 0..1 : ผสมความสำคัญ (60%) กับความยาก (40%)
            $importance = 0.6 * ($priority / 3) + 0.4 * ($difficulty / 5);
            $units = (int)ceil($required * 60 / $this->sessionMinutes - 1e-9);

            $topics = [];
            foreach ($sub['topics'] ?? [] as $t) {
                $hours = (float)($t['hours'] ?? 1);
                if ($hours <= 0) continue;
                $topics[] = [
                    'id'    => $t['id'] ?? null,
                    'name'  => $t['name'],
                    'units' => max(1, (int)ceil($hours * 60 / $this->sessionMinutes - 1e-9)),
                ];
            }

            $this->subjects[] = [
                'id'             => $sub['id'] ?? count($this->subjects) + 1,
                'name'           => $sub['name'],
                'color'          => $sub['color'] ?? '#2a78d6',
                'difficulty'     => $difficulty,
                'priority'       => $priority,
                'exam_date'      => $sub['exam_date'],
                'required_hours' => $required,
                'required_units' => $units,
                'importance'     => $importance,
                'topics'         => $topics,
            ];
            $this->totalRequiredUnits += $units;
            $this->totalImportance += $importance * $units;   // ใช้ normalise Completion Score
        }
    }

    private function buildSlots(array $availability, array $blocked, ?int $nowMinutes): void
    {
        if (!$this->subjects || $this->endDate < $this->startDate) return;

        $windowsByDay = [];
        foreach ($availability as $a) {
            $windowsByDay[(int)$a['day']][] = [
                'start' => self::toMinutes($a['start']),
                'end'   => self::toMinutes($a['end']),
                'pref'  => min(3, max(1, (int)($a['preference'] ?? 2))),
            ];
        }
        foreach ($windowsByDay as &$w) {
            usort($w, fn($x, $y) => $x['start'] <=> $y['start']);
        }
        unset($w);

        $date = $this->startDate;
        $dayIdx = 0;
        while ($date <= $this->endDate) {
            $dow = (int)date('N', strtotime($date));
            $blocks = [];
            foreach ($blocked as $b) {
                $match = (!empty($b['date']) && $b['date'] === $date)
                      || (empty($b['date']) && isset($b['day']) && (int)$b['day'] === $dow);
                if ($match) $blocks[] = [self::toMinutes($b['start']), self::toMinutes($b['end'])];
            }

            $this->dayDates[$dayIdx] = $date;
            $this->daySlots[$dayIdx] = [];
            foreach ($windowsByDay[$dow] ?? [] as $win) {
                $t = $win['start'];
                while ($t + $this->sessionMinutes <= $win['end']) {
                    $end = $t + $this->sessionMinutes;
                    $blockEnd = null;
                    foreach ($blocks as [$bs, $be]) {
                        if ($t < $be && $end > $bs) { $blockEnd = max($blockEnd ?? 0, $be); }
                    }
                    if ($blockEnd !== null) { $t = $blockEnd; continue; }   // ข้ามช่วงที่ไม่สะดวก
                    if ($dayIdx === 0 && $nowMinutes !== null && $t < $nowMinutes) {
                        $t = $end + $this->breakMinutes;
                        continue;                                           // ช่วงเวลาที่ผ่านไปแล้ววันนี้
                    }
                    $this->daySlots[$dayIdx][] = count($this->slots);
                    $this->slots[] = [
                        'date'    => $date,
                        'dow'     => $dow,
                        'start'   => $t,
                        'end'     => $end,
                        'minutes' => $this->sessionMinutes,
                        'pref'    => $win['pref'],
                        'day'     => $dayIdx,
                    ];
                    $t = $end + $this->breakMinutes;
                }
            }
            $date = self::addDays($date, 1);
            $dayIdx++;
        }
    }

    private function precompute(): void
    {
        $examDay = [];
        foreach ($this->subjects as $si => $sub) {
            $examDay[$si] = self::dayNumber($sub['exam_date']);
        }
        $n = count($this->slots);
        for ($i = 0; $i < $n; $i++) {
            $slot = $this->slots[$i];
            $d = self::dayNumber($slot['date']);
            $this->validSubjects[$i] = [];
            foreach ($this->subjects as $si => $_) {
                $before = $examDay[$si] - $d;
                $this->daysBefore[$i][$si] = $before;
                if ($before >= 1) $this->validSubjects[$i][] = $si;
            }
            $next = $this->slots[$i + 1] ?? null;
            $this->adjacentNext[$i] = $next !== null
                && $next['day'] === $slot['day']
                && ($next['start'] - $slot['end']) <= $this->breakMinutes + 5;
        }
    }

    // ------------------------------------------------------------------
    public function slotCount(): int { return count($this->slots); }
    public function subjectCount(): int { return count($this->subjects); }
    public function dayCount(): int { return count($this->dayDates); }

    public function isSolvable(): bool
    {
        return $this->subjectCount() > 0 && $this->slotCount() > 0;
    }

    /** เวลาว่างทั้งหมด (ชั่วโมง) */
    public function capacityHours(): float
    {
        return count($this->slots) * $this->sessionMinutes / 60;
    }

    public function requiredHours(): float
    {
        return $this->totalRequiredUnits * $this->sessionMinutes / 60;
    }

    // ------------------------------------------------------------------
    public static function toMinutes(string $hhmm): int
    {
        $p = explode(':', $hhmm);
        return (int)$p[0] * 60 + (int)($p[1] ?? 0);
    }

    public static function toTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public static function addDays(string $date, int $days): string
    {
        return date('Y-m-d', strtotime("$date " . ($days >= 0 ? '+' : '') . "$days day"));
    }

    public static function dayNumber(string $date): int
    {
        return intdiv(strtotime($date . ' 12:00:00 UTC'), 86400);
    }
}
