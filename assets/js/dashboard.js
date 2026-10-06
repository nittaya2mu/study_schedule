/* dashboard.js — Today's Study, KPI, ความก้าวหน้า, แผน 7 วัน, วันสอบ */
'use strict';

(async function initDashboard() {
  let data;
  async function load() {
    try {
      data = await API.get('api/dashboard.php', { action: 'get' });
      render();
    } catch (e) { toast(e.message, 'danger'); }
  }

  function render() {
    const o = data.overall;
    document.getElementById('todayLabel').textContent = fmtDate(data.today, true);

    const next = o.next_exam;
    document.getElementById('kpis').innerHTML = `
      <div class="kpi"><div class="kpi-label">Overall Progress</div>
        <div class="kpi-value">${fmtH(o.percent)}<small>%</small></div>
        <div class="pbar" style="margin-top:6px"><span style="width:${o.percent}%;background:var(--primary)"></span></div></div>
      <div class="kpi"><div class="kpi-label">Subjects</div>
        <div class="kpi-value">${o.subjects}</div>
        <div class="kpi-sub">หัวข้อที่อ่านจบ ${o.topics_done}/${o.topics_total}</div></div>
      <div class="kpi"><div class="kpi-label">Study Hours</div>
        <div class="kpi-value">${fmtH(o.completed_hours)} <small>/ ${fmtH(o.total_hours)} ชม.</small></div>
        <div class="kpi-sub">เหลือ ${fmtH(o.remaining_hours)} ชม. · อยู่ในตาราง ${fmtH(o.planned_hours)} ชม.</div></div>
      <div class="kpi"><div class="kpi-label">Days Until Exam</div>
        <div class="kpi-value">${next ? next.days_until_exam : '–'} <small>${next ? 'วัน' : ''}</small></div>
        <div class="kpi-sub">${next ? esc(next.subject_name) + ' · ' + fmtDateShort(next.exam_date) : 'ยังไม่มีวันสอบ'}</div></div>`;

    renderToday();
    renderSubjects();
    renderWeek();
    renderExams();
  }

  function renderToday() {
    const el = document.getElementById('todayList');
    if (!data.schedule) {
      el.innerHTML = `<div class="empty">ยังไม่มีตารางอ่านหนังสือ<br>
        <a class="btn btn-primary" href="generate.php">⚙ สร้างตารางด้วย GA</a></div>`;
      return;
    }
    if (!data.today_items.length) {
      el.innerHTML = `<div class="empty">วันนี้ไม่มี session อ่านหนังสือ 🎉</div>`;
      return;
    }
    el.innerHTML = data.today_items.map(it => `
      <div class="today-item ${it.status}" style="--c:${esc(it.color)}">
        <span class="time">${it.start_time}–${it.end_time}</span>
        <span class="what"><b>${esc(it.subject_name)}</b><small>Chapter: ${esc(it.topic_name || 'ทบทวน (Review)')}</small></span>
        <button class="btn btn-sm ${it.status === 'done' ? '' : 'btn-primary'}" data-id="${it.schedule_item_id}" data-status="${it.status === 'done' ? 'planned' : 'done'}">
          ${it.status === 'done' ? '↺ ยกเลิก' : '✓ อ่านแล้ว'}</button>
      </div>`).join('');
  }

  document.getElementById('todayList').addEventListener('click', async e => {
    const btn = e.target.closest('button[data-id]');
    if (!btn) return;
    await withBusy(btn, async () => {
      try {
        const r = await API.post('api/schedule.php', { action: 'set_status', schedule_item_id: +btn.dataset.id, status: btn.dataset.status });
        toast(btn.dataset.status === 'done' ? 'บันทึกว่าอ่านแล้ว ✓' : 'ยกเลิกการบันทึกแล้ว', 'success');
        showWarnings(r.warnings.filter(w => w.level !== 'info'));
      } catch (err) { toast(err.message, 'danger'); }
    });
    load();
  });

  function renderSubjects() {
    const el = document.getElementById('subjectProgress');
    if (!data.subjects.length) {
      el.innerHTML = `<div class="empty">ยังไม่มีรายวิชา<br><a class="btn btn-primary" href="subjects.php">+ เพิ่มรายวิชา</a></div>`;
      return;
    }
    el.innerHTML = data.subjects.map(s => `
      <div class="prog-row">
        <span class="name"><span class="dot" style="background:${esc(s.color)}"></span><span>${esc(s.subject_name)}</span></span>
        <span class="pct">${fmtH(s.percent)}%</span>
        <div class="pbar" role="progressbar" aria-valuenow="${s.percent}" aria-valuemin="0" aria-valuemax="100" aria-label="${esc(s.subject_name)}">
          <span style="width:${s.percent}%;background:${esc(s.color)}"></span></div>
        <span class="meta">${fmtH(s.completed_hours)} / ${fmtH(s.total_hours)} ชม. · หัวข้อ ${s.topics_done}/${s.topics_total}</span>
      </div>`).join('');
  }

  function renderWeek() {
    const el = document.getElementById('weekChart');
    const cats = data.week.map(d => DAY_TH_SHORT[isoDow(d.date)] + ' ' + parseDate(d.date).getDate());
    Charts.bar(el, {
      categories: cats,
      stacked: true,
      label: 'ชั่วโมงอ่านตามแผน 7 วันข้างหน้า',
      tipTitle: i => fmtDate(data.week[i].date, true),
      yFmt: v => fmtH(v),
      series: [
        { name: 'อ่านแล้ว', values: data.week.map(d => d.done) },
        { name: 'ตามแผน', values: data.week.map(d => Math.max(0, d.hours - d.done)), color: getComputedStyle(document.documentElement).getPropertyValue('--series-1').trim() + '66' },
      ],
    });
  }

  function renderExams() {
    const el = document.getElementById('examList');
    const list = data.subjects.filter(s => s.days_until_exam >= 0).slice(0, 6);
    el.innerHTML = list.length ? list.map(s => `
      <li><span class="dot" style="background:${esc(s.color)}"></span>
        <span>${esc(s.subject_name)}<br><small class="muted">${fmtDate(s.exam_date, true)}</small></span>
        <span class="days ${s.days_until_exam <= 3 ? 'soon' : ''}">${daysLabel(s.days_until_exam)}</span></li>`).join('')
      : '<li class="muted">ไม่มีวันสอบที่จะถึง</li>';
  }

  let resizeTimer;
  window.addEventListener('resize', () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => data && renderWeek(), 200); });
  load();
})();
