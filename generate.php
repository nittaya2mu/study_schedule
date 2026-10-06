<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/genetic/genetic_algorithm.php';
$pageTitle = 'สร้างตารางอ่านหนังสือด้วย Genetic Algorithm';
$activeNav = 'generate';
$pageScripts = ['charts.js', 'generate.js'];
require __DIR__ . '/includes/header.php';
$D = GeneticAlgorithm::DEFAULTS;
?>
<div class="grid-generate">
  <section class="card">
    <div class="card-head"><h2>พารามิเตอร์ของ GA</h2></div>
    <form id="gaForm" class="form" novalidate>
      <label>เริ่มจัดตารางตั้งแต่วันที่
        <input type="date" name="start_date" value="<?= e(today()) ?>" min="<?= e(today()) ?>">
      </label>
      <div class="preset-row" role="group" aria-label="Preset">
        <button type="button" class="chip" data-preset="fast">เร็ว</button>
        <button type="button" class="chip active" data-preset="balanced">สมดุล</button>
        <button type="button" class="chip" data-preset="quality">คุณภาพสูง</button>
      </div>
      <div class="grid-2">
        <label>Population size
          <input type="number" name="population_size" min="4" max="300" value="<?= $D['population_size'] ?>">
        </label>
        <label>Generations (สูงสุด)
          <input type="number" name="generations" min="1" max="1000" value="<?= $D['generations'] ?>">
        </label>
        <label>Crossover rate
          <input type="number" name="crossover_rate" min="0" max="1" step="0.05" value="<?= $D['crossover_rate'] ?>">
        </label>
        <label>Mutation rate
          <input type="number" name="mutation_rate" min="0" max="1" step="0.01" value="<?= $D['mutation_rate'] ?>">
        </label>
        <label>Tournament size
          <input type="number" name="tournament_size" min="2" max="10" value="<?= $D['tournament_size'] ?>">
        </label>
        <label>Elitism
          <input type="number" name="elitism" min="0" max="20" value="<?= $D['elitism'] ?>">
        </label>
      </div>
      <details>
        <summary>ตัวเลือกขั้นสูง</summary>
        <div class="grid-2">
          <label>Crossover method
            <select name="crossover_method">
              <option value="day_uniform">Day-uniform (สลับทั้งวัน)</option>
              <option value="one_point">One-point</option>
              <option value="two_point">Two-point</option>
              <option value="uniform">Uniform</option>
            </select>
          </label>
          <label>หยุดเมื่อ fitness ≥
            <input type="number" name="target_fitness" min="0" max="100" step="0.5" value="<?= $D['target_fitness'] ?>">
          </label>
          <label>หยุดเมื่อไม่ดีขึ้นติดต่อกัน (gen, 0 = ปิด)
            <input type="number" name="stagnation_limit" min="0" max="1000" value="<?= $D['stagnation_limit'] ?>">
          </label>
          <label>Random seed (เว้นว่าง = สุ่ม)
            <input type="number" name="seed" placeholder="เช่น 42">
          </label>
        </div>
      </details>
      <div class="callout small">
        session ที่ <b>อ่านแล้ว</b>, <b>ข้าม</b>, <b>ล็อกไว้</b> หรืออยู่ <b>ก่อนวันเริ่ม</b> จะถูกเก็บไว้ —
        GA จะจัดเฉพาะชั่วโมงที่ยังเหลือ
      </div>
      <button class="btn btn-primary btn-block btn-lg" type="submit" id="runBtn">⚙ Generate Study Schedule</button>
    </form>
  </section>

  <section class="card" id="resultCard">
    <div class="card-head">
      <h2>ผลลัพธ์</h2>
      <a class="btn" href="schedule.php" id="viewScheduleBtn" hidden>ดูตารางในปฏิทิน →</a>
    </div>
    <div id="pipeline" class="pipeline">
      <span>Load Data</span><span>Initial Population</span><span>Fitness</span><span>Selection</span>
      <span>Crossover</span><span>Mutation</span><span>New Population</span><span>Best Chromosome</span>
    </div>
    <div id="resultBody"><p class="muted">กด <b>Generate Study Schedule</b> เพื่อให้ GA ค้นหาตารางที่เหมาะสมที่สุด ผลลัพธ์ครั้งล่าสุดจะแสดงที่นี่</p></div>
  </section>
</div>

<section class="card">
  <div class="card-head"><h2>ประวัติการสร้างตาราง</h2><span class="muted small">เลือกเวอร์ชันที่ต้องการใช้งานได้</span></div>
  <div class="table-wrap"><table class="table" id="versionsTable">
    <thead><tr><th>#</th><th>สร้างเมื่อ</th><th>ช่วงวันที่</th><th class="num">Fitness</th><th class="num">Generations</th><th class="num">เวลา (ms)</th><th class="num">Sessions</th><th></th></tr></thead>
    <tbody></tbody>
  </table></div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
