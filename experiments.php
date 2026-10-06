<?php
require_once __DIR__ . '/includes/bootstrap.php';
$pageTitle = 'การทดลองประเมิน Genetic Algorithm';
$activeNav = 'experiments';
$pageScripts = ['charts.js', 'experiments.js'];
require __DIR__ . '/includes/header.php';
?>
<section class="card">
  <form id="expForm" class="form form-inline" novalidate>
    <label>การทดลอง
      <select name="experiment">
        <option value="population">Experiment 1 — Population Size (20, 50, 100)</option>
        <option value="mutation">Experiment 2 — Mutation Rate (0.01, 0.05, 0.10, 0.20)</option>
        <option value="generations">Experiment 3 — Generations (50, 100, 200, 500)</option>
        <option value="baseline" selected>Experiment 4 — เปรียบเทียบ Random / Rule-based / GA</option>
      </select>
    </label>
    <label>ชุดข้อมูล
      <select name="source">
        <option value="sample">ชุดข้อมูลตัวอย่าง (5 วิชา)</option>
        <option value="user">ข้อมูลของฉัน</option>
      </select>
    </label>
    <label>รันซ้ำ (runs)
      <input type="number" name="runs" min="1" max="10" value="3">
    </label>
    <button class="btn btn-primary" type="submit" id="expRun">▶ รันการทดลอง</button>
  </form>
  <p class="muted small">แต่ละค่าพารามิเตอร์จะรันซ้ำตามจำนวน runs ด้วย seed ต่างกัน แล้วรายงานค่าเฉลี่ย ± SD ·
    พารามิเตอร์อื่นใช้ค่า default · รันจาก CLI ได้ด้วย <code>php genetic/benchmark.php all --runs=5 --csv=results</code></p>
</section>

<div id="expResults"></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
