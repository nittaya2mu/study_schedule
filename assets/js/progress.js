/* progress.js — Progress Tracking: ชั่วโมงที่อ่าน, หัวข้อที่อ่านจบ, ประวัติ */
'use strict';

(function initProgress() {
  const logForm = document.getElementById('logForm');
  let summary = null;
  let topicsCache = {};
  const open = new Set();

  async function load() {
    try {
      summary = await API.get('api/progress.php', { action: 'summary' });
      renderKpis();
      renderBars();
      renderDaily();
      fillSubjectSelect();
      loadLogs();
    } catch (e) { toast(e.message, 'danger'); }
  }

  function renderKpis() {
    const o = summary.overall;
    document.getElementById('kpis').innerHTML = `
      <div class="kpi"><div class="kpi-label">Overall Progress</div><div class="kpi-value">${fmtH(o.percent)}<small>%</small></div>
        <div class="pbar" style="margin-top:6px"><span style="width:${o.percent}%;background:var(--primary)"></span></div></div>
      <div class="kpi"><div class="kpi-label">Study Hours Completed</div><div class="kpi-value">${fmtH(o.completed_hours)} <small>ชม.</small></div>
        <div class="kpi-sub">จากเป้าหมาย ${fmtH(o.total_hours)} ชม.</div></div>
      <div class="kpi"><div class="kpi-label">Study Hours Remaining</div><div class="kpi-value">${fmtH(o.remaining_hours)} <small>ชม.</small></div>
        <div class="kpi-sub">อยู่ในตารางแล้ว ${fmtH(o.planned_hours)} ชม.</div></div>
      <div class="kpi"><div class="kpi-label">Topic Completion</div><div class="kpi-value">${o.topics_done}<small> / ${o.topics_total}</small></div>
        <div class="kpi-sub">${fmtH(o.topic_percent)}% ของหัวข้อทั้งหมด</div></div>`;
  }

  function renderBars() {
    const el = document.getElementById('subjectBars');
    if (!summary.subjects.length) {
      el.innerHTML = '<div class="empty">ยังไม่มีรายวิชา <br><a class="btn btn-primary" href="subjects.php">+ เพิ่มรายวิชา</a></div>';
      return;
    }
    el.innerHTML = summary.subjects.map(s => `
      <div class="prog-row" data-id="${s.subject_id}" style="cursor:pointer">
        <span class="name"><span class="dot" style="background:${esc(s.color)}"></span><span>${esc(s.subject_name)}</span></span>
        <span class="pct">${fmtH(s.percent)}%</span>
        <div class="pbar" role="progressbar" aria-valuenow="${s.percent}" aria-valuemin="0" aria-valuemax="100" aria-label="${esc(s.subject_name)}">
          <span style="width:${s.percent}%;background:${esc(s.color)}"></span></div>
        <span class="meta">อ่านแล้ว ${fmtH(s.completed_hours)} / ${fmtH(s.total_hours)} ชม. · เหลือ ${fmtH(s.remaining_hours)} ชม.
          · หัวข้อ ${s.topics_done}/${s.topics_total} · สอบ${daysLabel(s.days_until_exam)}
          ${s.unplanned_hours > 0 && s.days_until_exam > 0 ? `<br><span style="color:var(--warn)">⚠️ ยังไม่ได้จัดลงตาราง ${fmtH(s.unplanned_hours)} ชม. — ลอง Regenerate</span>` : ''}</span>
        <div class="topics" style="grid-column:1/-1" ${open.has(s.subject_id) ? '' : 'hidden'}></div>
      </div>`).join('');
    open.forEach(id => renderTopics(id));
  }

  async function renderTopics(id) {
    const box = document.querySelector(`.prog-row[data-id="${id}"] .topics`);
    if (!box) return;
    box.hidden = false;
    try {
      const s = await API.get('api/subjects.php', { action: 'get', subject_id: id });
      topicsCache[id] = s.topics;
      box.innerHTML = `<ul class="topic-list">${s.topics.map(t => `
        <li class="${t.status}"><label class="check" style="display:flex;gap:8px;align-items:center;flex:1">
          <input type="checkbox" data-topic="${t.topic_id}" ${t.status === 'done' ? 'checked' : ''}>
          <span class="t-name">${esc(t.topic_name)} <small class="muted">${fmtH(+t.studied_hours)}/${fmtH(+t.estimated_hours)} ชม.</small></span></label>
          ${t.status === 'in_progress' ? '<span class="badge in_progress">กำลังอ่าน</span>' : t.status === 'done' ? '<span class="badge done">อ่านจบ</span>' : ''}
        </li>`).join('') || '<li class="muted">ไม่มีหัวข้อ</li>'}</ul>`;
    } catch (e) { box.textContent = e.message; }
  }

  document.getElementById('subjectBars').addEventListener('click', async e => {
    const cb = e.target.closest('input[data-topic]');
    if (cb) {
      e.stopPropagation();
      try {
        await API.post('api/topics.php', { action: 'set_status', topic_id: +cb.dataset.topic, status: cb.checked ? 'done' : 'not_started' });
        load();
      } catch (err) { toast(err.message, 'danger'); }
      return;
    }
    if (e.target.closest('.topics')) return;
    const row = e.target.closest('.prog-row');
    if (!row) return;
    const id = +row.dataset.id;
    if (open.has(id)) { open.delete(id); row.querySelector('.topics').hidden = true; }
    else { open.add(id); renderTopics(id); }
  });

  function renderDaily() {
    Charts.bar(document.getElementById('dailyChart'), {
      label: 'ชั่วโมงที่อ่านจริงต่อวัน',
      categories: summary.daily.map((d, i) => (i % 2 === 0 || summary.daily.length < 8) ? String(parseDate(d.date).getDate()) : ''),
      tipTitle: i => fmtDate(summary.daily[i].date, true),
      series: [{ name: 'ชั่วโมงที่อ่าน', values: summary.daily.map(d => d.hours) }],
      yFmt: v => fmtH(v),
    });
  }

  function fillSubjectSelect() {
    const sel = logForm.elements.subject_id;
    const cur = sel.value;
    sel.innerHTML = summary.subjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_name)}</option>`).join('');
    if (cur) sel.value = cur;
    loadTopicOptions();
  }
  async function loadTopicOptions() {
    const id = +logForm.elements.subject_id.value;
    const sel = logForm.elements.topic_id;
    if (!id) { sel.innerHTML = '<option value="">—</option>'; return; }
    if (!topicsCache[id]) {
      try { topicsCache[id] = (await API.get('api/subjects.php', { action: 'get', subject_id: id })).topics; }
      catch (e) { topicsCache[id] = []; }
    }
    sel.innerHTML = '<option value="">—</option>' + topicsCache[id].map(t => `<option value="${t.topic_id}">${esc(t.topic_name)}</option>`).join('');
  }
  logForm.elements.subject_id.addEventListener('change', loadTopicOptions);

  logForm.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      const r = await API.post('api/progress.php', { action: 'log', ...formData(logForm) });
      formErrors(logForm, null);
      toast('บันทึกการอ่านแล้ว', 'success');
      showWarnings(r.warnings);
      topicsCache = {};
      load();
    } catch (err) { formErrors(logForm, err); toast(err.message, 'danger'); }
  });

  async function loadLogs() {
    try {
      const rows = await API.get('api/progress.php', { action: 'logs' });
      document.querySelector('#logTable tbody').innerHTML = rows.map(r => `
        <tr><td>${fmtDate(r.study_date)}</td>
          <td><span class="dot" style="background:${esc(r.color)}"></span> ${esc(r.subject_name)}</td>
          <td>${esc(r.topic_name || '—')}</td>
          <td class="num">${r.minutes}</td>
          <td>${r.schedule_item_id ? '<span class="badge">ตาราง</span>' : '<span class="badge in_progress">บันทึกเอง</span>'}</td>
          <td class="muted">${esc(r.note || '')}</td>
          <td><button class="icon-btn" data-del="${r.progress_id}" aria-label="ลบ">🗑</button></td></tr>`).join('')
        || '<tr><td colspan="7" class="muted">ยังไม่มีประวัติ — กด "อ่านแล้ว" ในตารางหรือบันทึกด้านบน</td></tr>';
    } catch (e) { toast(e.message, 'danger'); }
  }
  document.getElementById('logTable').addEventListener('click', async e => {
    const id = e.target.dataset.del;
    if (!id || !confirm('ลบบันทึกการอ่านนี้? (ถ้ามาจากตาราง session จะกลับเป็น "ยังไม่ได้อ่าน")')) return;
    try { await API.post('api/progress.php', { action: 'delete_log', progress_id: +id }); load(); }
    catch (err) { toast(err.message, 'danger'); }
  });

  let t;
  window.addEventListener('resize', () => { clearTimeout(t); t = setTimeout(() => summary && renderDaily(), 200); });
  load();
})();
