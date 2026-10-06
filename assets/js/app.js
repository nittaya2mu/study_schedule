/* =====================================================================
   app.js — ฟังก์ชันกลางที่ทุกหน้าใช้ร่วมกัน
   ===================================================================== */
'use strict';

const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

/** เรียก PHP API ด้วย Fetch แล้วคืน data (โยน Error ถ้า ok=false) */
const API = {
  async request(url, { method = 'GET', params = null, body = null } = {}) {
    if (params) url += (url.includes('?') ? '&' : '?') + new URLSearchParams(params);
    const opts = { method, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' };
    if (body) {
      opts.headers['Content-Type'] = 'application/json';
      opts.headers['X-CSRF-Token'] = CSRF;
      opts.body = JSON.stringify(body);
    }
    let res, json;
    try {
      res = await fetch(url, opts);
      json = await res.json();
    } catch (e) {
      throw new Error('ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้');
    }
    if (res.status === 401) { location.href = 'login.php'; throw new Error(json.error); }
    if (!json.ok) {
      const err = new Error(json.error || 'เกิดข้อผิดพลาด');
      err.fields = json.fields || {};
      throw err;
    }
    return json.data;
  },
  get(url, params) { return this.request(url, { params }); },
  post(url, body) { return this.request(url, { method: 'POST', body }); },
};

/* ---------- Toast ---------- */
function toast(message, type = 'info', ms = 4200) {
  const stack = document.getElementById('toastStack');
  if (!stack) return alert(message);
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.textContent = message;
  stack.appendChild(el);
  setTimeout(() => el.remove(), ms);
}

/** แสดงคำเตือนที่ API ส่งกลับมา (เช่น เวลาชนกัน / เวลาอ่านที่เหลือ) */
function showWarnings(warnings = []) {
  for (const w of warnings) {
    const icon = w.level === 'info' ? 'ℹ️ ' : '⚠️ ';
    toast(icon + w.message, w.level === 'danger' ? 'danger' : w.level === 'warning' ? 'warning' : 'info', 7000);
  }
}

function warningsHtml(warnings = []) {
  if (!warnings.length) return '';
  return `<ul class="warn-list">${warnings.map(w =>
    `<li class="${esc(w.level)}">${w.level === 'info' ? 'ℹ️' : '⚠️'} ${esc(w.message)}</li>`).join('')}</ul>`;
}

/* ---------- Helpers ---------- */
function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function formData(form) {
  const out = {};
  for (const el of form.elements) {
    if (!el.name || el.disabled) continue;
    if (el.type === 'checkbox') out[el.name] = el.checked ? 1 : 0;
    else if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; }
    else out[el.name] = el.value;
  }
  return out;
}

function fillForm(form, data) {
  for (const [k, v] of Object.entries(data)) {
    const el = form.elements[k];
    if (!el) continue;
    if (el.type === 'checkbox') el.checked = !!Number(v);
    else el.value = v ?? '';
  }
}

/** แสดง error ใต้ฟอร์ม + ไฮไลต์ช่องที่ผิด */
function formErrors(form, err) {
  form.querySelectorAll('.field-error').forEach(el => el.classList.remove('field-error'));
  for (const name of Object.keys(err?.fields || {})) form.elements[name]?.classList.add('field-error');
}

async function withBusy(btn, fn) {
  const label = btn?.innerHTML;
  if (btn) { btn.disabled = true; btn.innerHTML = '⏳ ' + btn.textContent.trim(); }
  try { return await fn(); }
  finally { if (btn) { btn.disabled = false; btn.innerHTML = label; } }
}

const DAY_TH = ['', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
const DAY_TH_SHORT = ['', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
const DAY_EN_SHORT = ['', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
const MONTH_TH = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

/** 'YYYY-MM-DD' → Date (เวลาท้องถิ่น ไม่เลื่อนวันเพราะ timezone) */
function parseDate(s) { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); }
function ymd(d) { return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; }
function addDays(s, n) { const d = parseDate(s); d.setDate(d.getDate() + n); return ymd(d); }
function isoDow(s) { return parseDate(s).getDay() || 7; }
function fmtDate(s, withDow = false) {
  const d = parseDate(s);
  return (withDow ? DAY_TH[isoDow(s)] + ' ' : '') + `${d.getDate()} ${MONTH_TH[d.getMonth()]} ${d.getFullYear() + 543}`;
}
function fmtDateShort(s) { const d = parseDate(s); return `${d.getDate()} ${MONTH_TH[d.getMonth()]}`; }
function toMin(t) { const [h, m] = t.split(':').map(Number); return h * 60 + m; }
function toTime(m) { return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`; }
function fmtH(h) { return (Math.round(h * 10) / 10).toLocaleString('th-TH'); }
function daysLabel(n) { return n === 0 ? 'วันนี้' : n === 1 ? 'พรุ่งนี้' : n < 0 ? 'สอบแล้ว' : `อีก ${n} วัน`; }

/** สีประจำวิชา (categorical palette, เรียงตามลำดับที่ตรวจสอบ CVD แล้ว) */
const SUBJECT_COLORS = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

/* ---------- Modal (<dialog>) ---------- */
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-close]');
  if (btn) btn.closest('dialog')?.close();
});

/* ---------- Sidebar (mobile) ---------- */
(() => {
  const sb = document.getElementById('sidebar');
  const bd = document.getElementById('sidebarBackdrop');
  const toggle = open => { sb?.classList.toggle('open', open); bd?.classList.toggle('open', open); };
  document.getElementById('menuBtn')?.addEventListener('click', () => toggle(!sb.classList.contains('open')));
  bd?.addEventListener('click', () => toggle(false));
})();
