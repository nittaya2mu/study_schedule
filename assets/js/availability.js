/* availability.js — เวลาว่างประจำสัปดาห์, ช่วงไม่สะดวก, การตั้งค่าการอ่าน */
'use strict';

(function initAvailability() {
  const editor = document.getElementById('weekEditor');
  const settingsForm = document.getElementById('settingsForm');
  const blockedForm = document.getElementById('blockedForm');
  const T0 = 6 * 60, T1 = 24 * 60;  // ช่วงที่แสดงบน timeline 06:00–24:00
  let windows = {};                  // day => [{start_time,end_time,preference}]
  let blocked = [];
  let settings = {};

  async function load() {
    try {
      const d = await API.get('api/availability.php', { action: 'list' });
      windows = {};
      for (let i = 1; i <= 7; i++) windows[i] = [];
      d.windows.forEach(w => windows[w.day_of_week].push({ start_time: w.start_time, end_time: w.end_time, preference: w.preference }));
      blocked = d.blocked;
      settings = d.settings;
      fillForm(settingsForm, settings);
      renderEditor();
      renderBlocked();
    } catch (e) { toast(e.message, 'danger'); }
  }

  function renderEditor() {
    editor.innerHTML = '';
    for (let day = 1; day <= 7; day++) {
      const row = document.createElement('div');
      row.className = 'day-row';
      row.innerHTML = `
        <div class="day-name">${DAY_TH[day]}<small>${['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][day]}</small></div>
        <div>
          <div class="windows">${windows[day].map((w, i) => `
            <div class="win" data-i="${i}">
              <input type="time" data-f="start_time" value="${w.start_time}" aria-label="เริ่ม">
              <span class="sep">–</span>
              <input type="time" data-f="end_time" value="${w.end_time}" aria-label="สิ้นสุด">
              <select data-f="preference" aria-label="ระดับความสะดวก">
                <option value="3" ${w.preference == 3 ? 'selected' : ''}>★★★ ดีที่สุด</option>
                <option value="2" ${w.preference == 2 ? 'selected' : ''}>★★ ดี</option>
                <option value="1" ${w.preference == 1 ? 'selected' : ''}>★ พอได้</option>
              </select>
              <button class="icon-btn" data-act="remove" aria-label="ลบช่วงเวลา">✕</button>
            </div>`).join('') || '<span class="muted small" style="padding-top:6px">ไม่ว่าง</span>'}
          </div>
          <button class="btn btn-sm" data-act="add" style="margin-top:6px">+ เพิ่มช่วงเวลา</button>
          ${timeline(day)}
        </div>`;
      row.dataset.day = day;
      editor.appendChild(row);
    }
    renderSummary();
  }

  function timeline(day) {
    const pos = (s, e) => `left:${((toMin(s) - T0) / (T1 - T0)) * 100}%;width:${((toMin(e) - toMin(s)) / (T1 - T0)) * 100}%`;
    const wins = windows[day].filter(w => w.start_time && w.end_time && w.end_time > w.start_time)
      .map(w => `<span class="p${w.preference}" style="${pos(w.start_time, w.end_time)}" title="${w.start_time}–${w.end_time}"></span>`).join('');
    const bl = blocked.filter(b => !b.specific_date && +b.day_of_week === day)
      .map(b => `<span class="blocked" style="${pos(b.start_time, b.end_time)}" title="ไม่สะดวก ${b.start_time}–${b.end_time}"></span>`).join('');
    return `<div class="timeline" aria-hidden="true">${wins}${bl}</div>
      <div class="timeline-axis"><span>06:00</span><span>12:00</span><span>18:00</span><span>24:00</span></div>`;
  }

  /** ประมาณจำนวน session ต่อสัปดาห์ด้วยกติกาเดียวกับ GA (session + break) */
  function renderSummary() {
    const len = +settingsForm.elements.session_minutes.value || 60;
    const brk = +settingsForm.elements.break_minutes.value || 0;
    let minutes = 0, sessions = 0;
    for (let d = 1; d <= 7; d++) for (const w of windows[d]) {
      if (!w.start_time || !w.end_time || w.end_time <= w.start_time) continue;
      minutes += toMin(w.end_time) - toMin(w.start_time);
      for (let t = toMin(w.start_time); t + len <= toMin(w.end_time); t += len + brk) sessions++;
    }
    document.getElementById('availSummary').innerHTML =
      `เวลาว่างรวม <b>${fmtH(minutes / 60)}</b> ชม./สัปดาห์ → จัดได้ประมาณ <b>${sessions}</b> session (session ละ ${len} นาที, พัก ${brk} นาที)`
      + ` = <b>${fmtH(sessions * len / 60)}</b> ชม. อ่านจริงต่อสัปดาห์`;
  }

  editor.addEventListener('input', e => {
    const win = e.target.closest('.win');
    if (!win) return;
    const day = +e.target.closest('.day-row').dataset.day;
    windows[day][+win.dataset.i][e.target.dataset.f] = e.target.value;
    const tl = e.target.closest('.day-row').querySelector('.timeline');
    tl.outerHTML = timeline(day).split('<div class="timeline-axis">')[0];
    renderSummary();
  });
  editor.addEventListener('click', e => {
    const act = e.target.dataset.act;
    if (!act) return;
    const day = +e.target.closest('.day-row').dataset.day;
    if (act === 'add') {
      const last = windows[day][windows[day].length - 1];
      const start = last ? last.end_time : '18:00';
      windows[day].push({ start_time: start, end_time: toTime(Math.min(toMin(start) + 120, 23 * 60 + 59)), preference: 2 });
    } else if (act === 'remove') {
      windows[day].splice(+e.target.closest('.win').dataset.i, 1);
    }
    renderEditor();
  });

  document.getElementById('saveWeekly').addEventListener('click', async e => {
    const list = [];
    for (let d = 1; d <= 7; d++) windows[d].forEach(w => list.push({ day_of_week: d, ...w }));
    await withBusy(e.currentTarget, async () => {
      try {
        const r = await API.post('api/availability.php', { action: 'save_weekly', windows: list });
        toast(`บันทึกเวลาว่างแล้ว (${r.saved} ช่วง)`, 'success');
        load();
      } catch (err) { toast(err.message, 'danger'); }
    });
  });

  settingsForm.addEventListener('input', renderSummary);
  settingsForm.addEventListener('submit', async e => {
    e.preventDefault();
    try {
      await API.post('api/availability.php', { action: 'save_settings', ...formData(settingsForm) });
      formErrors(settingsForm, null);
      toast('บันทึกการตั้งค่าแล้ว', 'success');
    } catch (err) { formErrors(settingsForm, err); toast(err.message, 'danger'); }
  });

  // ---------- Blocked times ----------
  blockedForm.elements.kind.addEventListener('change', () => {
    const k = blockedForm.elements.kind.value;
    blockedForm.querySelectorAll('[data-kind]').forEach(l => { l.hidden = l.dataset.kind !== k; });
  });
  blockedForm.addEventListener('submit', async e => {
    e.preventDefault();
    const d = formData(blockedForm);
    if (d.kind === 'weekly') delete d.specific_date; else delete d.day_of_week;
    try {
      await API.post('api/availability.php', { action: 'add_blocked', ...d });
      toast('เพิ่มช่วงเวลาที่ไม่สะดวกแล้ว', 'success');
      blockedForm.elements.reason.value = '';
      load();
    } catch (err) { formErrors(blockedForm, err); toast(err.message, 'danger'); }
  });

  function renderBlocked() {
    const el = document.getElementById('blockedList');
    el.innerHTML = blocked.map(b => `
      <li><span class="badge">${b.specific_date ? fmtDate(b.specific_date) : 'ทุก' + DAY_TH[b.day_of_week]}</span>
        <span>${b.start_time}–${b.end_time}</span>
        <span class="muted spacer">${esc(b.reason || '')}</span>
        <button class="icon-btn" data-id="${b.unavailable_id}" aria-label="ลบ">✕</button></li>`).join('')
      || '<li class="muted">ยังไม่มี</li>';
  }
  document.getElementById('blockedList').addEventListener('click', async e => {
    const id = e.target.dataset.id;
    if (!id) return;
    try {
      await API.post('api/availability.php', { action: 'delete_blocked', unavailable_id: +id });
      load();
    } catch (err) { toast(err.message, 'danger'); }
  });

  load();
})();
