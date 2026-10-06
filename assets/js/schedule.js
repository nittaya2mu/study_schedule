/* schedule.js — ปฏิทินรายสัปดาห์/รายวัน/รายการ แบบ interactive (ลากวาง, เพิ่ม, แก้ไข, ลบ, บันทึกการอ่าน) */
'use strict';

(function initSchedule() {
  const cal = document.getElementById('calendar');
  const modal = document.getElementById('itemModal');
  const form = document.getElementById('itemForm');
  const PPM = 1;          // pixel ต่อนาที (60px ต่อชั่วโมง)
  const SNAP = 15;        // ลากวางทีละ 15 นาที
  let view = 'week';
  let anchor = ymd(new Date());
  let data = null;
  let subjectsById = {};

  // ---------- Range ----------
  function range() {
    if (view === 'day') return [anchor, anchor];
    const monday = addDays(anchor, 1 - isoDow(anchor));
    return view === 'week' ? [monday, addDays(monday, 6)] : [monday, addDays(monday, 13)];
  }

  async function load() {
    const [from, to] = range();
    try {
      data = await API.get('api/schedule.php', { action: 'get', from, to });
      subjectsById = Object.fromEntries(data.subjects.map(s => [s.subject_id, s]));
      render();
    } catch (e) { cal.innerHTML = `<div class="alert alert-danger">${esc(e.message)}</div>`; }
  }

  function render() {
    const [from, to] = range();
    document.getElementById('rangeLabel').textContent = from === to ? fmtDate(from, true) : `${fmtDateShort(from)} – ${fmtDate(to)}`;
    renderMeta();
    document.getElementById('legend').innerHTML = data.subjects.map(s =>
      `<span><span class="dot" style="background:${esc(s.color)}"></span>${esc(s.subject_name)}</span>`).join('')
      + (view !== 'list' ? `<span><span class="dot" style="background:var(--good);opacity:.5"></span>ช่วงเวลาว่าง</span>
         <span><span class="dot" style="background:var(--danger);opacity:.5"></span>ไม่สะดวก</span>` : '');

    if (!data.schedule && !data.items.length) {
      cal.innerHTML = `<div class="empty">ยังไม่มีตารางอ่านหนังสือ<br>
        <a class="btn btn-primary" href="generate.php">⚙ Generate Study Schedule</a></div>`;
      return;
    }
    view === 'list' ? renderList() : renderGrid();
  }

  function renderMeta() {
    const el = document.getElementById('scheduleMeta');
    if (!data.schedule) { el.innerHTML = ''; return; }
    const s = data.schedule;
    const hours = data.items.reduce((a, it) => a + (toMin(it.end_time) - toMin(it.start_time)) / 60, 0);
    const done = data.items.filter(it => it.status === 'done').length;
    el.innerHTML = `
      <span>Fitness <b>${s.fitness.toFixed(2)}</b></span>
      <span>สร้างโดย GA <b>${s.generations}</b> generations</span>
      <span>ช่วงนี้ <b>${data.items.length}</b> sessions · <b>${fmtH(hours)}</b> ชม.</span>
      <span>อ่านแล้ว <b>${done}</b>/${data.items.length}</span>`;
  }

  // ---------- Grid (week/day) ----------
  function dayList() {
    const [from, to] = range();
    const out = [];
    for (let d = from; d <= to; d = addDays(d, 1)) out.push(d);
    return out;
  }

  function blocksFor(date) {
    const dow = isoDow(date);
    return {
      avail: data.availability.filter(a => +a.day_of_week === dow),
      blocked: data.blocked.filter(b => b.specific_date ? b.specific_date === date : +b.day_of_week === dow),
    };
  }

  function renderGrid() {
    const days = dayList();
    // ขอบเขตเวลาที่แสดง: ครอบคลุมเวลาว่างและ session ทั้งหมด
    let tMin = 8 * 60, tMax = 22 * 60;
    for (const a of data.availability) { tMin = Math.min(tMin, toMin(a.start_time)); tMax = Math.max(tMax, toMin(a.end_time)); }
    for (const it of data.items) { tMin = Math.min(tMin, toMin(it.start_time)); tMax = Math.max(tMax, toMin(it.end_time)); }
    tMin = Math.floor(tMin / 60) * 60; tMax = Math.min(24 * 60, Math.ceil(tMax / 60) * 60);
    const H = (tMax - tMin) * PPM;
    const Y = m => (m - tMin) * PPM;

    const grid = document.createElement('div');
    grid.className = 'cal' + (view === 'day' ? ' day-view' : '');
    grid.style.gridTemplateColumns = `56px repeat(${days.length}, minmax(${view === 'day' ? 200 : 100}px, 1fr))`;
    grid.dataset.tmin = tMin;

    let html = '<div class="cal-corner"></div>';
    for (const d of days) {
      const exams = data.subjects.filter(s => s.exam_date === d);
      const dd = parseDate(d);
      html += `<div class="cal-head ${d === data.today ? 'today' : ''}">${DAY_EN_SHORT[isoDow(d)]} · ${DAY_TH_SHORT[isoDow(d)]}
        <b>${dd.getDate()} ${MONTH_TH[dd.getMonth()]}</b>
        ${exams.map(s => `<span class="exam-flag" title="สอบ ${esc(s.subject_name)}">📝 สอบ ${esc(s.subject_name)}</span>`).join('')}</div>`;
    }
    html += `<div class="cal-times" style="height:${H}px">`;
    for (let m = tMin; m <= tMax; m += 60) html += `<div style="top:${Y(m)}px">${toTime(m)}</div>`;
    html += '</div>';

    for (const d of days) {
      const { avail, blocked } = blocksFor(d);
      html += `<div class="cal-col ${d === data.today ? 'today' : ''}" data-date="${d}" style="height:${H}px">`;
      for (let m = tMin; m < tMax; m += 60) html += `<div class="hour-line" style="top:${Y(m)}px"></div>`;
      for (const a of avail) html += `<div class="avail" style="top:${Y(toMin(a.start_time))}px;height:${(toMin(a.end_time) - toMin(a.start_time)) * PPM}px"></div>`;
      for (const b of blocked) html += `<div class="blocked" title="ไม่สะดวก${b.reason ? ': ' + esc(b.reason) : ''}" style="top:${Y(toMin(b.start_time))}px;height:${(toMin(b.end_time) - toMin(b.start_time)) * PPM}px"></div>`;

      const items = data.items.filter(it => it.date === d);
      const lanes = layoutLanes(items);
      for (const it of items) {
        const { lane, lanesCount, conflict } = lanes.get(it.schedule_item_id);
        const top = Y(toMin(it.start_time)), h = Math.max(22, (toMin(it.end_time) - toMin(it.start_time)) * PPM - 2);
        const left = `calc(${(lane / lanesCount) * 100}% + 3px)`, width = `calc(${100 / lanesCount}% - 6px)`;
        const afterExam = subjectsById[it.subject_id] && it.date >= subjectsById[it.subject_id].exam_date;
        html += `<div class="event ${it.status} ${conflict || afterExam ? 'conflict' : ''}" draggable="true" tabindex="0"
          data-id="${it.schedule_item_id}" style="--c:${esc(it.color)};top:${top}px;height:${h}px;left:${left};width:${width};right:auto"
          title="${esc(it.subject_name)} · ${esc(it.topic_name || 'ทบทวน')} · ${it.start_time}–${it.end_time}${conflict ? ' · ⚠️ เวลาชนกัน' : ''}${afterExam ? ' · ⚠️ หลังวันสอบ' : ''}">
          ${it.is_locked ? '<span class="lock">🔒</span>' : ''}
          <b>${esc(it.subject_name)}</b><small>${esc(it.topic_name || 'ทบทวน (Review)')}</small><small>${it.start_time}–${it.end_time}</small></div>`;
      }
      html += '</div>';
    }
    grid.innerHTML = html;
    cal.innerHTML = '';
    cal.appendChild(grid);

    // เลื่อนไปยังช่วงเวลาที่มี session แรก
    const first = data.items.length ? Math.min(...data.items.map(it => toMin(it.start_time))) : 17 * 60;
    cal.scrollTop = Math.max(0, Y(first) - 40);
  }

  /** จัด lane เมื่อ session ซ้อนเวลากัน เพื่อแสดงคู่กันและไฮไลต์ว่าชนกัน */
  function layoutLanes(items) {
    const sorted = [...items].sort((a, b) => toMin(a.start_time) - toMin(b.start_time));
    const out = new Map();
    let cluster = [], clusterEnd = -1;
    const flush = () => {
      const lanesEnd = [];
      for (const it of cluster) {
        let lane = lanesEnd.findIndex(e => e <= toMin(it.start_time));
        if (lane < 0) { lane = lanesEnd.length; lanesEnd.push(0); }
        lanesEnd[lane] = toMin(it.end_time);
        out.set(it.schedule_item_id, { lane, lanesCount: 0, conflict: cluster.length > 1 });
      }
      for (const it of cluster) out.get(it.schedule_item_id).lanesCount = lanesEnd.length;
      cluster = [];
    };
    for (const it of sorted) {
      if (toMin(it.start_time) >= clusterEnd) { flush(); clusterEnd = -1; }
      cluster.push(it);
      clusterEnd = Math.max(clusterEnd, toMin(it.end_time));
    }
    flush();
    return out;
  }

  // ---------- List view ----------
  function renderList() {
    const days = dayList();
    cal.innerHTML = `<div class="list-view">${days.map(d => {
      const items = data.items.filter(it => it.date === d);
      if (!items.length) return '';
      const exams = data.subjects.filter(s => s.exam_date === d);
      return `<div class="list-day"><h3>${fmtDate(d, true)} ${d === data.today ? '<span class="badge in_progress">วันนี้</span>' : ''}
        ${exams.map(s => `<span class="badge High">📝 สอบ ${esc(s.subject_name)}</span>`).join(' ')}</h3>
        ${items.map(it => `<div class="today-item ${it.status}" data-id="${it.schedule_item_id}" style="--c:${esc(it.color)}">
          <span class="time">${it.start_time}–${it.end_time}</span>
          <span class="what"><b>${esc(it.subject_name)}</b><small>${esc(it.topic_name || 'ทบทวน (Review)')}${it.is_locked ? ' · 🔒' : ''}</small></span>
          <span class="badge ${it.status === 'done' ? 'done' : ''}">${{ planned: 'ยังไม่ได้อ่าน', done: 'อ่านแล้ว', skipped: 'ข้าม' }[it.status]}</span>
        </div>`).join('')}</div>`;
    }).join('') || '<div class="empty">ไม่มี session ในช่วงนี้</div>'}</div>`;
  }

  // ---------- Interactions ----------
  cal.addEventListener('click', e => {
    const ev = e.target.closest('.event, .today-item[data-id]');
    if (ev) return openItem(data.items.find(it => it.schedule_item_id === +ev.dataset.id));
    const col = e.target.closest('.cal-col');
    if (col) {
      const m = minuteAt(col, e.clientY);
      openItem(null, { date: col.dataset.date, start_time: toTime(m) });
    }
  });
  cal.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.matches('.event')) openItem(data.items.find(it => it.schedule_item_id === +e.target.dataset.id));
  });

  function minuteAt(col, clientY) {
    const tMin = +col.parentElement.dataset.tmin;
    const y = clientY - col.getBoundingClientRect().top;
    return Math.max(0, Math.min(24 * 60 - SNAP, Math.round((tMin + y / PPM) / SNAP) * SNAP));
  }

  // Drag & drop: เลื่อนเวลาอ่าน
  let dragItem = null, ghost = null;
  cal.addEventListener('dragstart', e => {
    const ev = e.target.closest('.event');
    if (!ev) return;
    dragItem = data.items.find(it => it.schedule_item_id === +ev.dataset.id);
    ev.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', ev.dataset.id);
  });
  cal.addEventListener('dragend', e => {
    e.target.closest('.event')?.classList.remove('dragging');
    ghost?.remove(); ghost = null;
    cal.querySelectorAll('.drop-target').forEach(c => c.classList.remove('drop-target'));
  });
  cal.addEventListener('dragover', e => {
    const col = e.target.closest('.cal-col');
    if (!col || !dragItem) return;
    e.preventDefault();
    const dur = toMin(dragItem.end_time) - toMin(dragItem.start_time);
    const start = minuteAt(col, e.clientY - (dur * PPM) / 2);
    if (!ghost) { ghost = document.createElement('div'); ghost.className = 'drop-ghost'; }
    if (ghost.parentElement !== col) col.appendChild(ghost);
    ghost.style.top = (start - +col.parentElement.dataset.tmin) * PPM + 'px';
    ghost.style.height = dur * PPM + 'px';
    ghost.textContent = '';
    ghost.dataset.start = start;
  });
  cal.addEventListener('drop', async e => {
    const col = e.target.closest('.cal-col');
    if (!col || !dragItem || !ghost) return;
    e.preventDefault();
    const start = +ghost.dataset.start;
    const dur = toMin(dragItem.end_time) - toMin(dragItem.start_time);
    const item = dragItem;
    dragItem = null;
    if (col.dataset.date === item.date && toTime(start) === item.start_time) return;
    if (start + dur > 24 * 60) return toast('เลื่อนเกินเที่ยงคืนไม่ได้', 'warning');
    try {
      const r = await API.post('api/schedule.php', {
        action: 'update_item', schedule_item_id: item.schedule_item_id,
        date: col.dataset.date, start_time: toTime(start), end_time: toTime(start + dur),
      });
      toast(`เลื่อน ${item.subject_name} ไป ${fmtDateShort(col.dataset.date)} ${toTime(start)} แล้ว`, 'success');
      showWarnings(r.warnings);
      load();
    } catch (err) { toast(err.message, 'danger'); }
  });

  // ---------- Modal ----------
  const subjSel = form.elements.subject_id;
  const topicSel = form.elements.topic_id;
  function fillTopics(subjectId, selected) {
    const s = subjectsById[subjectId];
    topicSel.innerHTML = '<option value="">ทบทวน (Review)</option>' + (s?.topics || []).map(t =>
      `<option value="${t.topic_id}" ${+t.topic_id === +selected ? 'selected' : ''}>${esc(t.topic_name)}${t.status === 'done' ? ' ✓' : ''}</option>`).join('');
  }
  subjSel.addEventListener('change', () => { fillTopics(subjSel.value, null); checkLive(); });

  let currentItem = null;
  function openItem(item, defaults = {}) {
    if (!data.subjects.length) return toast('กรุณาเพิ่มรายวิชาก่อน', 'warning');
    currentItem = item;
    form.reset();
    subjSel.innerHTML = data.subjects.map(s => `<option value="${s.subject_id}">${esc(s.subject_name)}</option>`).join('');
    document.getElementById('itemModalTitle').textContent = item ? 'แก้ไข Session' : 'เพิ่ม Session';
    document.getElementById('deleteItemBtn').hidden = !item;
    document.getElementById('statusRow').hidden = !item;
    document.getElementById('itemWarnings').innerHTML = '';
    if (item) {
      fillForm(form, { ...item, topic_id: '' });
      fillTopics(item.subject_id, item.topic_id);
      form.querySelectorAll('[data-status]').forEach(b => b.classList.toggle('active', b.dataset.status === item.status));
    } else {
      const len = data.settings.session_minutes;
      const start = defaults.start_time || '18:00';
      fillForm(form, {
        schedule_item_id: '', subject_id: data.subjects[0].subject_id, date: defaults.date || data.today,
        start_time: start, end_time: toTime(Math.min(toMin(start) + len, 23 * 60 + 59)), is_locked: 0,
      });
      fillTopics(subjSel.value, null);
    }
    modal.showModal();
    checkLive();
  }
  document.getElementById('addItemBtn').addEventListener('click', () => openItem(null, { date: view === 'day' ? anchor : data.today }));

  // ตรวจสอบเวลาชนกัน/ข้อจำกัดแบบ real-time ระหว่างแก้ไข
  let liveTimer;
  function checkLive() {
    clearTimeout(liveTimer);
    liveTimer = setTimeout(async () => {
      const d = formData(form);
      if (!d.date || !d.start_time || !d.end_time) return;
      try {
        const r = await API.post('api/schedule.php', { action: 'check_item', ...d });
        document.getElementById('itemWarnings').innerHTML = warningsHtml(r.warnings);
      } catch (err) { document.getElementById('itemWarnings').innerHTML = warningsHtml([{ level: 'danger', message: err.message }]); }
    }, 300);
  }
  ['date', 'start_time', 'end_time'].forEach(n => form.elements[n].addEventListener('change', checkLive));

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const d = formData(form);
    const btn = form.querySelector('[type=submit]');
    await withBusy(btn, async () => {
      try {
        const r = await API.post('api/schedule.php', { ...d, action: d.schedule_item_id ? 'update_item' : 'create_item' });
        modal.close();
        toast(d.schedule_item_id ? 'บันทึกการแก้ไขแล้ว' : 'เพิ่ม session แล้ว', 'success');
        showWarnings(r.warnings);
        load();
      } catch (err) { formErrors(form, err); toast(err.message, 'danger'); }
    });
  });

  document.getElementById('deleteItemBtn').addEventListener('click', async () => {
    if (!currentItem || !confirm(`ลบ session ${currentItem.subject_name} ${currentItem.start_time}–${currentItem.end_time}?`)) return;
    try {
      const r = await API.post('api/schedule.php', { action: 'delete_item', schedule_item_id: currentItem.schedule_item_id });
      modal.close();
      toast('ลบ session แล้ว', 'success');
      showWarnings(r.warnings);
      load();
    } catch (err) { toast(err.message, 'danger'); }
  });

  document.getElementById('statusRow').addEventListener('click', async e => {
    const b = e.target.closest('[data-status]');
    if (!b || !currentItem) return;
    try {
      const r = await API.post('api/schedule.php', { action: 'set_status', schedule_item_id: currentItem.schedule_item_id, status: b.dataset.status });
      modal.close();
      toast({ done: 'บันทึกว่าอ่านแล้ว ✓', planned: 'เปลี่ยนเป็นยังไม่ได้อ่าน', skipped: 'บันทึกว่าข้าม' }[b.dataset.status], 'success');
      showWarnings(r.warnings);
      load();
    } catch (err) { toast(err.message, 'danger'); }
  });

  // ---------- Navigation ----------
  const step = () => (view === 'day' ? 1 : view === 'week' ? 7 : 14);
  document.getElementById('prevBtn').onclick = () => { anchor = addDays(anchor, -step()); load(); };
  document.getElementById('nextBtn').onclick = () => { anchor = addDays(anchor, step()); load(); };
  document.getElementById('todayBtn').onclick = () => { anchor = ymd(new Date()); load(); };
  document.querySelectorAll('[data-view]').forEach(b => b.addEventListener('click', () => {
    view = b.dataset.view;
    document.querySelectorAll('[data-view]').forEach(x => x.classList.toggle('active', x === b));
    try { localStorage.setItem('sss.view', view); } catch (e) { /* ignore */ }
    load();
  }));
  let initial = 'week';
  try { initial = localStorage.getItem('sss.view') || 'week'; } catch (e) { /* ignore */ }
  if (window.matchMedia('(max-width: 700px)').matches && initial === 'week') initial = 'list';
  (document.querySelector(`[data-view="${initial}"]`) || document.querySelector('[data-view="week"]')).click();
})();
