/* generate.js — ตั้งค่าพารามิเตอร์ GA, รัน, แสดงผล Fitness/Convergence/Baseline */
'use strict';

(function initGenerate() {
  const form = document.getElementById('gaForm');
  const body = document.getElementById('resultBody');
  const pipeline = document.getElementById('pipeline');
  const PRESETS = {
    fast:     { population_size: 30,  generations: 80,  mutation_rate: 0.02, stagnation_limit: 30 },
    balanced: { population_size: 50,  generations: 200, mutation_rate: 0.02, stagnation_limit: 0 },
    quality:  { population_size: 100, generations: 500, mutation_rate: 0.01, stagnation_limit: 0 },
  };
  const SCORE_LABEL = {
    completion: 'Completion', exam: 'Exam Priority', availability: 'Availability',
    balance: 'Study Balance', continuity: 'Continuity',
  };
  const WEIGHTS = { completion: 35, exam: 20, availability: 15, balance: 15, continuity: 15 };
  const TERM = { max_generations: 'ครบจำนวน generation', target_fitness: 'fitness ถึงเป้าหมาย', stagnation: 'fitness ไม่ดีขึ้นต่อเนื่อง' };
  let lastResult = null;

  form.querySelector('.preset-row').addEventListener('click', e => {
    const b = e.target.closest('[data-preset]');
    if (!b) return;
    form.querySelectorAll('[data-preset]').forEach(x => x.classList.toggle('active', x === b));
    fillForm(form, PRESETS[b.dataset.preset]);
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('runBtn');
    pipeline.classList.add('running');
    pipeline.querySelectorAll('span').forEach(s => s.classList.remove('on'));
    body.innerHTML = '<p class="muted">กำลังวิวัฒนาการประชากรของตาราง… (Selection → Crossover → Mutation)</p>';
    await withBusy(btn, async () => {
      try {
        const r = await API.post('api/schedule.php', { action: 'generate', ...formData(form) });
        lastResult = r;
        renderResult(r, true);
        toast(`สร้างตารางสำเร็จ — Fitness ${r.fitness.toFixed(2)}`, 'success');
        loadVersions();
      } catch (err) {
        body.innerHTML = `<div class="alert alert-danger">${esc(err.message)}</div>`;
        if (/เวลาว่าง/.test(err.message)) body.innerHTML += '<p><a class="btn" href="availability.php">ไปหน้าเวลาว่าง →</a></p>';
        if (/วิชา/.test(err.message)) body.innerHTML += '<p><a class="btn" href="subjects.php">ไปหน้ารายวิชา →</a></p>';
      } finally {
        pipeline.classList.remove('running');
      }
    });
  });

  function renderResult(r, fresh) {
    pipeline.querySelectorAll('span').forEach(s => s.classList.add('on'));
    document.getElementById('viewScheduleBtn').hidden = false;
    const st = r.detail.stats;
    const p = r.problem;
    body.innerHTML = `
      ${fresh ? '' : `<p class="muted small">ผลการรันล่าสุด · ${esc(r.created_at || '')}</p>`}
      <div class="mini-kpis">
        <div><b>${r.fitness.toFixed(2)}</b><span>Best Fitness (เต็ม 100)</span></div>
        <div><b>${r.generations}</b><span>Generations</span></div>
        <div><b>${(r.execution_ms / 1000).toFixed(2)}s</b><span>Execution Time</span></div>
        <div><b>${st.constraint_violations}</b><span>Constraint Violations</span></div>
      </div>
      ${p ? `<p class="small muted">${p.subjects} วิชา · ${p.slots} time slots ใน ${p.days} วัน (${fmtDate(p.start_date)} – ${fmtDate(p.end_date)})
        · ต้องอ่าน ${fmtH(p.required_hours)} ชม. / เวลาว่าง ${fmtH(p.capacity_hours)} ชม.
        ${r.kept ? ` · เก็บ session เดิมไว้ ${r.kept} รายการ` : ''}
        ${r.termination ? ` · หยุดเพราะ${TERM[r.termination] || r.termination}` : ''}</p>` : ''}
      ${p && p.required_hours > p.capacity_hours ? `<div class="alert alert-warning small">⚠️ ชั่วโมงที่ต้องอ่านมากกว่าเวลาว่างก่อนสอบ — GA จะจัดให้วิชาที่สำคัญ/ยากได้เวลาก่อน ลองเพิ่มเวลาว่างเพื่อให้อ่านครบ</div>` : ''}
      <div class="result-grid">
        <div>
          <h3>Convergence — Fitness ต่อ Generation</h3>
          <div class="chart-box" id="convChart"></div>
        </div>
        <div>
          <h3>Fitness Breakdown</h3>
          ${Object.entries(r.detail.scores).map(([k, v]) => `
            <div class="score-row"><span>${SCORE_LABEL[k]} <small class="muted">×${WEIGHTS[k]}</small></span>
              <div class="pbar"><span style="width:${v * 100}%"></span></div>
              <span class="val">${(v * WEIGHTS[k]).toFixed(1)}</span></div>`).join('')}
          ${Object.entries(r.detail.penalties).map(([k, v]) => `
            <div class="score-row penalty"><span>− ${{ conflict: 'Conflict', overload: 'Overload', over_allocation: 'Over-allocation' }[k]}</span>
              <span></span><span class="val ${v > 0 ? 'penalty' : 'muted'}">${v > 0 ? '−' + v.toFixed(1) : '0'}</span></div>`).join('')}
        </div>
        ${r.baselines ? `<div>
          <h3>เปรียบเทียบกับ Baseline</h3>
          <div class="chart-box" id="baseChart" style="min-height:180px"></div>
        </div>` : ''}
        <div>
          <h3>ชั่วโมงที่จัดได้ต่อวิชา</h3>
          <div class="table-wrap"><table class="table">
            <thead><tr><th>วิชา</th><th class="num">ต้องอ่าน</th><th class="num">จัดได้</th><th class="num">วัน</th></tr></thead>
            <tbody>${r.detail.subjects.map(s => `<tr>
              <td><span class="dot" style="background:${esc(s.color)}"></span> ${esc(s.name)}</td>
              <td class="num">${fmtH(s.required_hours)}</td>
              <td class="num">${s.scheduled_hours >= s.required_hours ? '✓ ' : '⚠️ '}${fmtH(s.scheduled_hours)}</td>
              <td class="num">${s.days}</td></tr>`).join('')}</tbody>
          </table></div>
        </div>
      </div>`;

    Charts.line(document.getElementById('convChart'), {
      label: 'Fitness ต่อ generation', xName: 'Generation',
      x: r.history.map(h => h.gen),
      series: [
        { name: 'Best', values: r.history.map(h => h.best) },
        { name: 'Average', values: r.history.map(h => h.avg), dashed: true },
      ],
      yFmt: v => v.toFixed(1),
    });
    if (r.baselines) {
      Charts.bar(document.getElementById('baseChart'), {
        label: 'Fitness ของแต่ละวิธี', height: 190, valueLabels: true,
        categories: r.baselines.map(b => b.method === 'Genetic Algorithm' ? 'GA' : b.method),
        series: [{ name: 'Fitness', values: r.baselines.map(b => Math.max(0, b.fitness)) }],
        yFmt: v => v.toFixed(1),
      });
    }
  }

  async function loadLatest() {
    try {
      const r = await API.get('api/schedule.php', { action: 'latest_run' });
      if (r && !lastResult) renderResult({ ...r, problem: null, termination: null }, false);
    } catch (e) { /* ยังไม่มีผล */ }
  }

  async function loadVersions() {
    try {
      const rows = await API.get('api/schedule.php', { action: 'versions' });
      const tb = document.querySelector('#versionsTable tbody');
      tb.innerHTML = rows.map(v => `
        <tr class="${+v.is_active ? 'active-row' : ''}">
          <td>${v.schedule_id}</td>
          <td>${esc(v.created_at)}</td>
          <td>${fmtDateShort(v.start_date)} – ${fmtDateShort(v.end_date)}</td>
          <td class="num">${Number(v.fitness).toFixed(2)}</td>
          <td class="num">${v.generations}</td>
          <td class="num">${v.execution_ms}</td>
          <td class="num">${v.items}</td>
          <td>${+v.is_active ? '<span class="badge done">ใช้งานอยู่</span>'
            : `<button class="btn btn-sm" data-activate="${v.schedule_id}">ใช้เวอร์ชันนี้</button>`}</td>
        </tr>`).join('') || '<tr><td colspan="8" class="muted">ยังไม่เคยสร้างตาราง</td></tr>';
    } catch (e) { toast(e.message, 'danger'); }
  }
  document.querySelector('#versionsTable').addEventListener('click', async e => {
    const id = e.target.dataset.activate;
    if (!id) return;
    try {
      await API.post('api/schedule.php', { action: 'activate', schedule_id: +id });
      toast('เปลี่ยนเวอร์ชันตารางแล้ว', 'success');
      loadVersions();
    } catch (err) { toast(err.message, 'danger'); }
  });

  let t;
  window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => lastResult && renderResult(lastResult, true), 250); });
  loadLatest();
  loadVersions();
})();
