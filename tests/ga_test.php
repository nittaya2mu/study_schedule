<?php
/**
 * Unit tests ของ Genetic Algorithm module (ไม่ต้องใช้ฐานข้อมูล / framework)
 *   php tests/ga_test.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../genetic/genetic_algorithm.php';
require_once __DIR__ . '/../genetic/sample_problem.php';

$passed = 0; $failed = 0;
function check(string $name, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) { $passed++; echo "  ✓ $name\n"; }
    else { $failed++; echo "  ✗ $name" . ($detail ? " — $detail" : '') . "\n"; }
}

$input = sample_problem_input('2026-10-05');   // จันทร์
$p = new StudyProblem($input);

echo "StudyProblem\n";
check('สร้าง slot ได้', $p->slotCount() > 0);
check('ทุก slot ยาวเท่า session_minutes', array_reduce($p->slots, fn($ok, $s) => $ok && $s['end'] - $s['start'] === 60, true));
check('ไม่มี slot ในวันพุธ (ไม่ว่าง)', !array_filter($p->slots, fn($s) => $s['dow'] === 3));
check('ไม่มี slot ทับช่วงไม่สะดวก (อาทิตย์ 15:00–16:00)',
    !array_filter($p->slots, fn($s) => $s['dow'] === 7 && $s['start'] < 960 && $s['end'] > 900));
$gapOk = true;
foreach ($p->slots as $i => $s) {
    $n = $p->slots[$i + 1] ?? null;
    if ($n && $n['date'] === $s['date'] && $n['start'] < $s['end'] + 15) $gapOk = false;
}
check('มีเวลาพัก ≥ 15 นาทีระหว่าง session', $gapOk);
check('slot สุดท้ายอยู่ก่อนวันสอบวิชาสุดท้าย', end($p->slots)['date'] < '2026-11-02');
check('required units = ชั่วโมง/ความยาว session', $p->totalRequiredUnits === 51);

$p2 = new StudyProblem(['now_minutes' => 18 * 60 + 30] + $input);
check('ตัด slot ที่ผ่านไปแล้วของวันแรก', $p2->slots[0]['date'] !== '2026-10-05' || $p2->slots[0]['start'] >= 18 * 60 + 30);

echo "Chromosome / Operators\n";
GaRandom::seed(1);
$c = Chromosome::random($p);
check('ความยาว chromosome = จำนวน slot', count($c->genes) === $p->slotCount());
check('gene เป็นวิชาที่ยังไม่สอบ หรือ -1',
    array_reduce(array_keys($c->genes), fn($ok, $i) => $ok && ($c->genes[$i] === -1 || in_array($c->genes[$i], $p->validSubjects[$i], true)), true));

$x = new Crossover($p, 'day_uniform');
[$k1, $k2] = $x->cross(Chromosome::random($p), Chromosome::random($p));
check('crossover ได้ลูกความยาวถูกต้อง', count($k1->genes) === $p->slotCount() && count($k2->genes) === $p->slotCount());

$m = new Mutation($p, 1.0);
$before = $c->genes;
$m->mutate($c);
check('mutation เปลี่ยน gene', $before !== $c->genes);
check('mutation reset fitness', $c->fitness === null);

echo "Fitness\n";
$f = new FitnessCalculator($p);
$empty = new Chromosome(array_fill(0, $p->slotCount(), -1));
$f->evaluate($empty);
check('ตารางว่าง: completion = 0', $empty->detail['scores']['completion'] == 0);
$bad = new Chromosome(array_fill(0, $p->slotCount(), 1));   // Database ทุก slot (รวมหลังสอบ)
$f->evaluate($bad);
check('session หลังวันสอบถูกนับเป็น violation', $bad->detail['stats']['sessions_after_exam'] > 0);
check('ตารางที่ผิดข้อจำกัดได้ fitness ต่ำกว่า rule-based', $bad->fitness < (new RuleBasedScheduler($p))->run()['fitness']);

echo "Genetic Algorithm\n";
$r1 = (new GeneticAlgorithm($p, ['seed' => 42, 'generations' => 60]))->run();
$r2 = (new GeneticAlgorithm($p, ['seed' => 42, 'generations' => 60]))->run();
check('seed เดียวกันได้ผลเหมือนกัน (reproducible)', $r1['fitness'] === $r2['fitness']);
$hist = array_column($r1['history'], 'best');
$mono = true;
for ($i = 1; $i < count($hist); $i++) if ($hist[$i] < $hist[$i - 1] - 1e-9) $mono = false;
check('best fitness ไม่ลดลง (elitism)', $mono);
check('GA ดีกว่า random', $r1['fitness'] > (new RandomScheduler($p))->run(42)['fitness']);
check('ไม่มี constraint violation', $r1['detail']['stats']['constraint_violations'] === 0);

$slotsUsed = array_column($r1['sessions'], 'slot');
check('ไม่มี session เวลาชนกัน', count($slotsUsed) === count(array_unique($slotsUsed)));
$examOf = array_column($input['subjects'], 'exam_date', 'id');
check('ไม่มี session ในวัน/หลังวันสอบ', !array_filter($r1['sessions'], fn($s) => $s['date'] >= $examOf[$s['subject_id']]));
$topicOrder = true; $seen = [];
foreach ($r1['sessions'] as $s) {
    if ($s['topic_id'] === null) continue;
    $last = $seen[$s['subject_id']] ?? 0;
    if ($s['topic_id'] < $last) $topicOrder = false;
    $seen[$s['subject_id']] = $s['topic_id'];
}
check('หัวข้อถูกจัดตามลำดับบทเรียน', $topicOrder);

$stop = (new GeneticAlgorithm($p, ['seed' => 1, 'generations' => 1000, 'stagnation_limit' => 15]))->run();
check('หยุดเมื่อ fitness ไม่ดีขึ้น (stagnation)', $stop['termination'] === 'stagnation' && $stop['generations'] < 1000);
$target = (new GeneticAlgorithm($p, ['seed' => 1, 'target_fitness' => 50]))->run();
check('หยุดเมื่อถึง target fitness', $target['termination'] === 'target_fitness');

$norm = GeneticAlgorithm::normaliseParams(['population_size' => 99999, 'mutation_rate' => '0.5', 'elitism' => 500]);
check('จำกัดค่าพารามิเตอร์ที่เกินขอบเขต', $norm['population_size'] === 300 && $norm['mutation_rate'] === 0.5 && $norm['elitism'] === 20);

echo "\n$passed passed, $failed failed\n";
exit($failed ? 1 : 0);
