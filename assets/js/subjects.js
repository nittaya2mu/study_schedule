/* subjects.js — จัดการรายวิชาและหัวข้อ */
'use strict';

(function initSubjects() {
  const listEl = document.getElementById('subjectList');
  const modal = document.getElementById('subjectModal');
  const form = document.getElementById('subjectForm');
  const panel = document.getElementById('detailPanel');
  const PRI = { High: 3, Medium: 2, Low: 1 };
  let subjects = [];
  let selectedId = null;

  // ---------- Color swatches ----------
  const sw = document.getElementById('swatches');
  sw.innerHTML = SUBJECT_COLORS.map(c => `<button type="button" class="swatch" style="background:${c}" data-color="${c}" aria-label="สี ${c}"></button>`).join('');
  sw.addEventListener('click', e => {
    const b = e.target.closest('.swatch');
    if (b) { form.elements.color.value = b.dataset.color; markSwatch(); }
  });
  form.elements.color.addEventListener('input', markSwatch);
  function markSwatch() {
    sw.querySelectorAll('.swatch').forEach(s => s.classList.toggle('active', s.dataset.color === form.elements.color.value.toLowerCase()));
  }
  form.elements.difficulty.addEventListener('input', () => { document.getElementById('diffOut').textContent = form.elements.difficulty.value; });

  // ---------- List ----------
  async function load() {
    try {
      subjects = await API.get('api/subjects.php', { action: 'list' });
      renderList();
      if (selectedId) openDetail(selectedId);
    } catch (e) { toast(e.message, 'danger'); }
  }

  function renderList() {
    const q = document.getElementById('subjectSearch').value.trim().toLowerCase();
    const sort = document.getElementById('subjectSort').value;
    let rows = subjects.filter(s => s.subject_name.toLowerCase().includes(q));
    rows.sort({
      exam: (a, b) => a.exam_date.localeCompare(b.exam_date),
      priority: (a, b) => PRI[b.priority] - PRI[a.priority] || a.exam_date.localeCompare(b.exam_date),
      difficulty: (a, b) => b.difficulty - a.difficulty,
      name: (a, b) => a.subject_name.localeCompare(b.subject_name),
    }[sort]);

    if (!subjects.length) {
      listEl.innerHTML = `<div class="card empty" style="grid-column:1/-1">ยังไม่มีรายวิชา — เริ่มเพิ่มวิชาที่ต้องอ่านสอบ<br>
        <button class="btn btn-primary" onclick="document.getElementById('addSubjectBtn').click()">+ เพิ่มรายวิชา</button></div>`;
      return;
    }
    listEl.innerHTML = rows.map(s => `
      <article class="subject-card ${s.subject_id === selectedId ? 'selected' : ''}" style="--c:${esc(s.color)}" data-id="${s.subject_id}" tabindex="0">
        <div class="row" style="justify-content:space-between">
          <span class="badge ${s.priority}">${s.priority}</span>
          <span class="stars" title="ความยาก ${s.difficulty}/5" aria-label="ความยาก ${s.difficulty} จาก 5">${'★'.repeat(s.difficulty)}${'☆'.repeat(5 - s.difficulty)}</span>
        </div>
        <h3>${esc(s.subject_name)}</h3>
        <div class="row">📅 ${fmtDate(s.exam_date)} · <b>${daysLabel(s.days_until_exam)}</b></div>
        <div class="pbar"><span style="width:${s.percent}%;background:${esc(s.color)}"></span></div>
        <div class="row" style="justify-content:space-between">
          <span>${fmtH(s.completed_hours)} / ${fmtH(s.total_hours)} ชม.</span>
          <span>หัวข้อ ${s.topics_done}/${s.topics_total}</span>
        </div>
      </article>`).join('') || '<p class="muted">ไม่พบรายวิชาที่ค้นหา</p>';
  }
  document.getElementById('subjectSearch').addEventListener('input', renderList);
  document.getElementById('subjectSort').addEventListener('change', renderList);
  listEl.addEventListener('click', e => {
    const card = e.target.closest('.subject-card');
    if (card) openDetail(+card.dataset.id);
  });
  listEl.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.target.matches('.subject-card')) openDetail(+e.target.dataset.id);
  });

  // ---------- Add / Edit modal ----------
  function openModal(subject = null) {
    form.reset();
    formErrors(form, null);
    document.getElementById('subjectFormError').hidden = true;
    document.getElementById('subjectModalTitle').textContent = subject ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชา';
    document.getElementById('topicsInit').hidden = !!subject;
    form.elements.subject_id.value = '';
    if (subject) fillForm(form, subject);
    else {
      form.elements.exam_date.value = addDays(ymd(new Date()), 14);
      form.elements.color.value = SUBJECT_COLORS[subjects.length % SUBJECT_COLORS.length];
    }
    document.getElementById('diffOut').textContent = form.elements.difficulty.value;
    markSwatch();
    modal.showModal();
    form.elements.subject_name.focus();
  }
  document.getElementById('addSubjectBtn').addEventListener('click', () => openModal());

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const data = formData(form);
    const isEdit = !!data.subject_id;
    if (!isEdit && data.topics_text) {
      data.topics = data.topics_text.split('\n').map(l => {
        const [name, h] = l.split('|').map(x => x.trim());
        return { topic_name: name, estimated_hours: h || 1 };
      }).filter(t => t.topic_name);
    }
    delete data.topics_text;
    const btn = form.querySelector('[type=submit]');
    await withBusy(btn, async () => {
      try {
        const r = await API.post('api/subjects.php', { ...data, action: isEdit ? 'update' : 'create' });
        modal.close();
        toast(isEdit ? 'บันทึกการแก้ไขแล้ว' : 'เพิ่มรายวิชาแล้ว', 'success');
        selectedId = r.subject_id;
        load();
      } catch (err) {
        formErrors(form, err);
        const fe = document.getElementById('subjectFormError');
        fe.textContent = err.message; fe.hidden = false;
      }
    });
  });

  // ---------- Detail panel + Topics ----------
  async function openDetail(id) {
    selectedId = id;
    renderList();
    panel.hidden = false;
    const body = document.getElementById('detailBody');
    try {
      const s = await API.get('api/subjects.php', { action: 'get', subject_id: id });
      const sum = subjects.find(x => x.subject_id === id) || {};
      document.getElementById('detailTitle').innerHTML = `<span class="dot" style="background:${esc(s.color)}"></span> ${esc(s.subject_name)}`;
      const topicHours = s.topics.reduce((a, t) => a + Number(t.estimated_hours), 0);
      body.innerHTML = `
        <dl class="dl">
          <dt>Difficulty</dt><dd>${s.difficulty}/5</dd>
          <dt>Priority</dt><dd><span class="badge ${s.priority}">${s.priority}</span></dd>
          <dt>Exam Date</dt><dd>${fmtDate(s.exam_date, true)} (${daysLabel(sum.days_until_exam)})</dd>
          <dt>Study Hours</dt><dd>${fmtH(Number(s.total_hours))} ชม. (อ่านแล้ว ${fmtH(sum.completed_hours || 0)} · ในตาราง ${fmtH(sum.planned_hours || 0)})</dd>
          ${s.description ? `<dt>คำอธิบาย</dt><dd>${esc(s.description)}</dd>` : ''}
        </dl>
        <div class="card-head" style="margin:18px 0 6px"><h2>Topics (${s.topics.length})</h2>
          <span class="muted small">รวม ${fmtH(topicHours)} ชม.</span></div>
        ${topicHours > Number(s.total_hours) ? `<div class="alert alert-warning small">⚠️ ชั่วโมงของหัวข้อรวม (${fmtH(topicHours)}) มากกว่า Study Hours ของวิชา</div>` : ''}
        <ul class="topic-list">${s.topics.map(t => `
          <li class="${t.status}" data-topic="${t.topic_id}">
            <input type="checkbox" ${t.status === 'done' ? 'checked' : ''} aria-label="อ่านจบแล้ว">
            <span class="t-name">${esc(t.topic_name)}<br><small class="muted">${fmtH(Number(t.studied_hours))} / ${fmtH(Number(t.estimated_hours))} ชม.
              ${t.status === 'in_progress' ? '<span class="badge in_progress">กำลังอ่าน</span>' : ''}</small></span>
            <button class="icon-btn" data-act="edit" aria-label="แก้ไข">✎</button>
            <button class="icon-btn" data-act="del" aria-label="ลบ">🗑</button>
          </li>`).join('') || '<li class="muted">ยังไม่มีหัวข้อ</li>'}</ul>
        <form class="topic-add" id="topicAdd">
          <input name="topic_name" placeholder="เพิ่มหัวข้อ เช่น Graph" required maxlength="150">
          <input name="estimated_hours" type="number" min="0.5" max="100" step="0.5" value="2" aria-label="ชั่วโมง">
          <button class="btn" type="submit">+</button>
        </form>
        <div class="modal-actions" style="margin-top:18px">
          <button class="btn btn-danger-ghost" id="delSubject">ลบวิชา</button>
          <span class="spacer"></span>
          <button class="btn" id="editSubject">✎ แก้ไขวิชา</button>
        </div>`;

      body.querySelector('#editSubject').onclick = () => openModal(s);
      body.querySelector('#delSubject').onclick = async () => {
        if (!confirm(`ลบวิชา "${s.subject_name}" พร้อมหัวข้อ, session ในตาราง และประวัติการอ่านทั้งหมด?`)) return;
        try {
          await API.post('api/subjects.php', { action: 'delete', subject_id: id });
          toast('ลบรายวิชาแล้ว', 'success');
          selectedId = null; panel.hidden = true; load();
        } catch (err) { toast(err.message, 'danger'); }
      };
      body.querySelector('#topicAdd').onsubmit = async e => {
        e.preventDefault();
        try {
          await API.post('api/topics.php', { action: 'create', subject_id: id, ...formData(e.target) });
          load();
        } catch (err) { toast(err.message, 'danger'); }
      };
      body.querySelector('.topic-list').onclick = async e => {
        const li = e.target.closest('li[data-topic]');
        if (!li) return;
        const tid = +li.dataset.topic;
        const t = s.topics.find(x => +x.topic_id === tid);
        try {
          if (e.target.matches('input[type=checkbox]')) {
            await API.post('api/topics.php', { action: 'set_status', topic_id: tid, status: e.target.checked ? 'done' : 'not_started' });
          } else if (e.target.dataset.act === 'del') {
            if (!confirm(`ลบหัวข้อ "${t.topic_name}"?`)) return;
            await API.post('api/topics.php', { action: 'delete', topic_id: tid });
          } else if (e.target.dataset.act === 'edit') {
            const name = prompt('ชื่อหัวข้อ', t.topic_name);
            if (name === null) return;
            const hours = prompt('ชั่วโมงโดยประมาณ', t.estimated_hours);
            if (hours === null) return;
            await API.post('api/topics.php', { action: 'update', topic_id: tid, topic_name: name, estimated_hours: hours });
          } else return;
          load();
        } catch (err) { toast(err.message, 'danger'); }
      };
    } catch (e) { body.innerHTML = `<p class="form-error">${esc(e.message)}</p>`; }
  }
  document.getElementById('closeDetail').addEventListener('click', () => { panel.hidden = true; selectedId = null; renderList(); });

  load();
})();
