/* experiments.js — รัน Experiment 1–4 และแสดงตาราง/กราฟผลลัพธ์ */
'use strict';

(function initExperiments() {
  const form = document.getElementById('expForm');
  const out = document.getElementById('expResults');
  const TITLES = {
    population: 'Experiment 1 — Population Size',
    mutation: 'Experiment 2 — Mutation Rate',
    generations: 'Experiment 3 — Number of Generations',
    baseline: 'Experiment 4 — เปรียบเทียบกับ Baseline',
  };
  const SCORE_LABEL = { completion: 'Completion', exam: 'Exam Priority', availability: 'Availability', balance: 'Balance', continuity: 'Continuity' };

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('expRun');
    const d = formData(form);
    await withBusy(btn, async () => {
      try {
        const r = await API.post('api/experiments.php', { action: 'run', ...d });
        const block = document.createElement('div');
        block.className = 'exp-block';
        out.prepend(block);
        r.experiment === 'baseline' ? renderBaseline(block, r) : renderSweep(block, r);
      } catch (err) { toast(err.message, 'danger'); }
    });
  });

  function problemLine(r) {
    const p = r.problem;
    return `${p.source === 'user' ? 'ข้อมูลของฉัน' : 'ชุดข้อมูลตัวอย่าง'} · ${p.subjects} วิชา · ${p.slots} slots / ${p.days} วัน
      · ต้องอ่าน ${fmtH(p.required_hours)} ชม. / ว่าง ${fmtH(p.capacity_hours)} ชม. · ${r.runs} runs ต่อค่า`;
  }

  function renderSweep(block, r) {
    const rows = r.rows;
    const best = rows.reduce((a, b) => (b.fitness_mean > a.fitness_mean ? b : a));
    const fastest = rows.reduce((a, b) => (b.time_ms_mean < a.time_ms_mean ? b : a));
    const id = 'c' + Math.random().toString(36).slice(2);
    block.innerHTML = `
      <section class="card">
        <div class="card-head"><h2>${TITLES[r.experiment]}</h2><span class="muted small">${problemLine(r)}</span></div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>${esc(r.param)}</th><th class="num">Fitness (mean)</th><th class="num">SD</th><th class="num">Max</th>
            <th class="num">Execution Time (ms)</th><th class="num">Generations</th><th class="num">Violations</th></tr></thead>
          <tbody>${rows.map(x => `<tr class="${x === best ? 'active-row' : ''}">
            <td>${x.value}</td><td class="num"><b>${x.fitness_mean.toFixed(2)}</b></td><td class="num">${x.fitness_sd.toFixed(2)}</td>
            <td class="num">${x.fitness_max.toFixed(2)}</td><td class="num">${x.time_ms_mean.toFixed(0)}</td>
            <td class="num">${x.generations}</td><td class="num">${x.violations}</td></tr>`).join('')}</tbody>
        </table></div>
      </section>
      <div class="grid-2 align-start">
        <section class="card"><h3>Fitness เฉลี่ย (Best) ต่อ Generation</h3><div class="chart-box" id="${id}a"></div></section>
        <section class="card"><h3>Execution Time (ms)</h3><div class="chart-box" id="${id}b"></div></section>
      </div>
      <p class="insight">📌 ค่า <b>${esc(r.param)} = ${best.value}</b> ให้ fitness เฉลี่ยสูงสุด (${best.fitness_mean.toFixed(2)})
        ${best !== fastest ? ` ขณะที่ ${fastest.value} ใช้เวลาน้อยที่สุด (${fastest.time_ms_mean.toFixed(0)} ms) — พิจารณา trade-off ระหว่างคุณภาพกับเวลา` : ' และใช้เวลาน้อยที่สุดด้วย'}.</p>`;

    const maxLen = Math.max(...rows.map(x => x.curve.length));
    Charts.line(document.getElementById(id + 'a'), {
      label: 'Convergence', xName: 'Generation',
      series: rows.slice(0, 3).concat(rows.length > 3 ? [rows[rows.length - 1]] : []).filter((v, i, a) => a.indexOf(v) === i)
        .map(x => ({ name: `${r.param} = ${x.value}`, values: pad(x.curve, maxLen) })),
      yFmt: v => v.toFixed(1),
    });
    Charts.bar(document.getElementById(id + 'b'), {
      label: 'Execution time', valueLabels: true,
      categories: rows.map(x => String(x.value)),
      series: [{ name: 'ms', values: rows.map(x => x.time_ms_mean) }],
      yFmt: v => Math.round(v).toLocaleString(),
    });
  }

  /** ต่อเส้นของ run ที่สั้นกว่าด้วยค่าสุดท้าย เพื่อให้แกน x เท่ากัน */
  function pad(arr, n) { return arr.concat(Array(Math.max(0, n - arr.length)).fill(arr[arr.length - 1])); }

  function renderBaseline(block, r) {
    const rows = r.rows;
    const ga = rows.find(x => x.method === 'Genetic Algorithm');
    const others = rows.filter(x => x !== ga);
    const id = 'c' + Math.random().toString(36).slice(2);
    block.innerHTML = `
      <section class="card">
        <div class="card-head"><h2>${TITLES.baseline}</h2><span class="muted small">${problemLine(r)}</span></div>
        <div class="table-wrap"><table class="table">
          <thead><tr><th>Method</th><th class="num">Fitness</th><th class="num">SD</th><th class="num">Time (ms)</th>
            <th class="num">Violations</th><th class="num">ชั่วโมงที่จัด</th><th class="num">วิชาที่จัดครบ</th>
            ${Object.values(SCORE_LABEL).map(l => `<th class="num">${l}</th>`).join('')}</tr></thead>
          <tbody>${rows.map(x => `<tr class="${x === ga ? 'active-row' : ''}">
            <td><b>${esc(x.method)}</b></td><td class="num"><b>${x.fitness_mean.toFixed(2)}</b></td><td class="num">${x.fitness_sd.toFixed(2)}</td>
            <td class="num">${x.time_ms_mean.toFixed(0)}</td><td class="num">${x.violations}</td>
            <td class="num">${fmtH(x.scheduled_hours)} / ${fmtH(x.required_hours)}</td>
            <td class="num">${fmtH(x.subjects_complete)} / ${x.subjects_total}</td>
            ${Object.keys(SCORE_LABEL).map(k => `<td class="num">${x.scores[k].toFixed(2)}</td>`).join('')}</tr>`).join('')}</tbody>
        </table></div>
      </section>
      <div class="grid-2 align-start">
        <section class="card"><h3>Fitness Score</h3><div class="chart-box" id="${id}a"></div></section>
        <section class="card"><h3>คะแนนแต่ละองค์ประกอบ (0–1)</h3><div class="chart-box" id="${id}b"></div></section>
      </div>
      <p class="insight">📌 GA ได้ fitness เฉลี่ย <b>${ga.fitness_mean.toFixed(2)}</b> เทียบกับ
        ${others.map(o => `${esc(o.method)} <b>${o.fitness_mean.toFixed(2)}</b> (${ga.fitness_mean >= o.fitness_mean ? '+' : ''}${(ga.fitness_mean - o.fitness_mean).toFixed(2)})`).join(' และ ')}
        — แลกกับเวลาประมวลผล ${ga.time_ms_mean.toFixed(0)} ms</p>`;

    Charts.bar(document.getElementById(id + 'a'), {
      label: 'Fitness by method', valueLabels: true,
      categories: rows.map(x => x.method === 'Genetic Algorithm' ? 'GA' : x.method),
      series: [{ name: 'Fitness', values: rows.map(x => Math.max(0, x.fitness_mean)) }],
      yFmt: v => v.toFixed(1),
    });
    Charts.bar(document.getElementById(id + 'b'), {
      label: 'Score components by method', yMax: 1,
      categories: Object.values(SCORE_LABEL),
      series: rows.map(x => ({ name: x.method === 'Genetic Algorithm' ? 'GA' : x.method, values: Object.keys(SCORE_LABEL).map(k => x.scores[k]) })),
      yFmt: v => v.toFixed(2),
    });
  }
})();
