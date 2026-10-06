/* =====================================================================
   charts.js — กราฟ SVG ขนาดเล็ก (ไม่ใช้ไลบรารีภายนอก)
     Charts.line(el, opts)  : เส้น + crosshair tooltip  (เช่น convergence ของ GA)
     Charts.bar(el, opts)   : แท่ง (grouped/stacked) + tooltip
   ===================================================================== */
'use strict';

const Charts = (() => {
  const NS = 'http://www.w3.org/2000/svg';
  const css = v => getComputedStyle(document.documentElement).getPropertyValue(v).trim();
  const SERIES = () => [css('--series-1'), css('--series-2'), css('--series-3')];

  function svgEl(tag, attrs = {}, parent) {
    const el = document.createElementNS(NS, tag);
    for (const [k, v] of Object.entries(attrs)) el.setAttribute(k, v);
    if (parent) parent.appendChild(el);
    return el;
  }

  /** ขั้นของแกนที่อ่านง่าย (1, 2, 2.5, 5 × 10^n) */
  function niceStep(range, count = 4) {
    const raw = range / count;
    const p = Math.pow(10, Math.floor(Math.log10(raw)));
    const n = raw / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
  }

  function ticks(min, max, step) {
    const out = [];
    for (let v = min; v <= max + step / 1000; v += step) out.push(Math.round(v * 1e6) / 1e6);
    return out;
  }

  const fmt = v => (Math.round(v * 100) / 100).toLocaleString('th-TH');

  function frame(el, height) {
    el.innerHTML = '';
    el.style.position = 'relative';
    const width = Math.max(280, el.clientWidth || 600);
    const svg = svgEl('svg', { viewBox: `0 0 ${width} ${height}`, role: 'img' }, el);
    const tip = document.createElement('div');
    tip.className = 'chart-tip';
    tip.hidden = true;
    el.appendChild(tip);
    return { svg, tip, width };
  }

  function legend(el, items, box = false) {
    if (items.length < 2) return;
    const lg = document.createElement('div');
    lg.className = 'chart-legend';
    lg.innerHTML = items.map(s => `<span><i class="${box ? 'box' : ''}" style="background:${s.color}"></i>${esc(s.name)}</span>`).join('');
    el.appendChild(lg);
  }

  function yAxis(svg, m, w, h, yMin, yMax, yFmt, step) {
    const g = svgEl('g', { class: 'grid' }, svg);
    const ax = svgEl('g', { class: 'axis' }, svg);
    for (const t of ticks(yMin, yMax, step || niceStep(yMax - yMin))) {
      const y = m.t + h - ((t - yMin) / (yMax - yMin)) * h;
      svgEl('line', { x1: m.l, x2: m.l + w, y1: y, y2: y }, g);
      svgEl('text', { x: m.l - 6, y: y + 4, 'text-anchor': 'end' }, ax).textContent = yFmt(t);
    }
  }

  /**
   * opts: { series:[{name, values:number[], color?}], x?: number[], height, yMin?, yMax?, xLabel, yFmt, label }
   */
  function line(el, opts) {
    const height = opts.height || 240;
    const { svg, tip, width } = frame(el, height);
    svg.setAttribute('aria-label', opts.label || 'line chart');
    const m = { t: 12, r: 16, b: 30, l: 44 };
    const w = width - m.l - m.r, h = height - m.t - m.b;
    const colors = SERIES();
    const series = opts.series.map((s, i) => ({ ...s, color: s.color || colors[i % colors.length] }));
    const n = Math.max(...series.map(s => s.values.length));
    const xs = opts.x || Array.from({ length: n }, (_, i) => i);
    const all = series.flatMap(s => s.values);
    const lo = Math.min(...all), hi = Math.max(...all);
    const step = niceStep((hi - lo) || 1);
    const yMin = opts.yMin ?? Math.floor(lo / step) * step;
    let yMax = opts.yMax ?? Math.ceil(hi / step) * step;
    if (yMax <= yMin) yMax = yMin + step;
    const yFmt = opts.yFmt || fmt;
    yAxis(svg, m, w, h, yMin, yMax, yFmt, step);

    const xMin = xs[0], xMax = xs[xs.length - 1] || 1;
    const X = v => m.l + ((v - xMin) / ((xMax - xMin) || 1)) * w;
    const Y = v => m.t + h - ((v - yMin) / (yMax - yMin)) * h;

    const ax = svgEl('g', { class: 'axis' }, svg);
    svgEl('line', { x1: m.l, x2: m.l + w, y1: m.t + h, y2: m.t + h, class: 'baseline' }, svg);
    const xt = 5;
    for (let i = 0; i <= xt; i++) {
      const v = Math.round(xMin + (xMax - xMin) * i / xt);
      svgEl('text', { x: X(v), y: height - 10, 'text-anchor': 'middle' }, ax).textContent = v;
    }
    if (opts.xLabel) svgEl('text', { x: m.l + w, y: height - 10, 'text-anchor': 'end', dy: -14 }, ax).textContent = opts.xLabel;

    for (const s of series) {
      const d = s.values.map((v, i) => `${i ? 'L' : 'M'}${X(xs[i]).toFixed(1)},${Y(v).toFixed(1)}`).join('');
      svgEl('path', { d, fill: 'none', stroke: s.color, 'stroke-width': 2, 'stroke-linejoin': 'round', 'stroke-dasharray': s.dashed ? '5 4' : '' }, svg);
    }

    // Crosshair + tooltip
    const cross = svgEl('line', { y1: m.t, y2: m.t + h, stroke: css('--text-3'), 'stroke-dasharray': '3 3', visibility: 'hidden' }, svg);
    const dots = series.map(s => svgEl('circle', { r: 4, fill: s.color, stroke: css('--surface'), 'stroke-width': 2, visibility: 'hidden' }, svg));
    const hit = svgEl('rect', { x: m.l, y: m.t, width: w, height: h, fill: 'transparent' }, svg);
    hit.addEventListener('pointermove', e => {
      const r = svg.getBoundingClientRect();
      const px = (e.clientX - r.left) * (width / r.width);
      let i = Math.round(((px - m.l) / w) * (n - 1));
      i = Math.max(0, Math.min(n - 1, i));
      const x = X(xs[i]);
      cross.setAttribute('x1', x); cross.setAttribute('x2', x); cross.setAttribute('visibility', 'visible');
      series.forEach((s, k) => {
        const v = s.values[Math.min(i, s.values.length - 1)];
        dots[k].setAttribute('cx', x); dots[k].setAttribute('cy', Y(v)); dots[k].setAttribute('visibility', 'visible');
      });
      tip.hidden = false;
      tip.innerHTML = `${esc(opts.xName || 'x')} ${xs[i]}<br>` + series.map(s =>
        `<span class="dot" style="background:${s.color}"></span> ${esc(s.name)}: <b>${yFmt(s.values[Math.min(i, s.values.length - 1)])}</b>`).join('<br>');
      const left = (x / width) * r.width;
      tip.style.left = Math.min(left + 12, r.width - tip.offsetWidth - 4) + 'px';
      tip.style.top = '8px';
    });
    hit.addEventListener('pointerleave', () => {
      tip.hidden = true; cross.setAttribute('visibility', 'hidden');
      dots.forEach(d => d.setAttribute('visibility', 'hidden'));
    });
    legend(el, series);
  }

  /**
   * opts: { categories:string[], series:[{name, values, color?}], stacked?, height, yFmt, label, yMax? }
   */
  function bar(el, opts) {
    const height = opts.height || 220;
    const { svg, tip, width } = frame(el, height);
    svg.setAttribute('aria-label', opts.label || 'bar chart');
    const m = { t: 14, r: 10, b: 30, l: 44 };
    const w = width - m.l - m.r, h = height - m.t - m.b;
    const colors = SERIES();
    const series = opts.series.map((s, i) => ({ ...s, color: s.color || colors[i % colors.length] }));
    const cats = opts.categories;
    const totals = cats.map((_, i) => opts.stacked
      ? series.reduce((a, s) => a + (s.values[i] || 0), 0)
      : Math.max(...series.map(s => s.values[i] || 0)));
    const top = Math.max(...totals, 0.0001);
    const step = niceStep(opts.yMax ?? top);
    const yMax = opts.yMax ?? Math.ceil(top / step) * step;
    const yFmt = opts.yFmt || fmt;
    yAxis(svg, m, w, h, 0, yMax, yFmt, step);

    const band = w / cats.length;
    const groupW = Math.min(band * 0.7, opts.stacked ? 44 : 30 * series.length);
    const barW = opts.stacked ? groupW : (groupW - 2 * (series.length - 1)) / series.length;
    const Y = v => m.t + h - (v / yMax) * h;
    const ax = svgEl('g', { class: 'axis' }, svg);

    cats.forEach((c, i) => {
      const gx = m.l + band * i + (band - groupW) / 2;
      let acc = 0;
      series.forEach((s, k) => {
        const v = s.values[i] || 0;
        if (v <= 0) return;
        const x = opts.stacked ? gx : gx + k * (barW + 2);
        const y0 = opts.stacked ? acc : 0;
        const y1 = y0 + v;
        acc = opts.stacked ? y1 : acc;
        const top = Y(y1), bottom = Y(y0);
        const hh = Math.max(1, bottom - top - (opts.stacked && y0 > 0 ? 2 : 0));
        const isTop = !opts.stacked || k === series.length - 1 || series.slice(k + 1).every(o => !(o.values[i] > 0));
        const r = Math.min(4, barW / 2, hh);
        const path = isTop
          ? `M${x},${top + hh} V${top + r} Q${x},${top} ${x + r},${top} H${x + barW - r} Q${x + barW},${top} ${x + barW},${top + r} V${top + hh} Z`
          : `M${x},${top} H${x + barW} V${top + hh} H${x} Z`;
        svgEl('path', { d: path, fill: s.color }, svg);
      });
      svgEl('text', { x: m.l + band * i + band / 2, y: height - 10, 'text-anchor': 'middle' }, ax).textContent = c;
      if (opts.valueLabels) {
        svgEl('text', { x: m.l + band * i + band / 2, y: Y(totals[i]) - 5, 'text-anchor': 'middle', 'font-weight': 600 }, ax).textContent = yFmt(totals[i]);
      }
      const hit = svgEl('rect', { x: m.l + band * i, y: m.t, width: band, height: h, fill: 'transparent' }, svg);
      hit.addEventListener('pointermove', e => {
        const r = svg.getBoundingClientRect();
        tip.hidden = false;
        tip.innerHTML = `<b>${esc(opts.tipTitle ? opts.tipTitle(i) : c)}</b><br>` + series.map(s =>
          `<span class="dot" style="background:${s.color}"></span> ${esc(s.name)}: <b>${yFmt(s.values[i] || 0)}</b>`).join('<br>');
        const left = ((m.l + band * (i + 0.5)) / width) * r.width;
        tip.style.left = Math.max(4, Math.min(left - tip.offsetWidth / 2, r.width - tip.offsetWidth - 4)) + 'px';
        tip.style.top = Math.max(0, (Y(totals[i]) / height) * r.height - tip.offsetHeight - 8) + 'px';
      });
      hit.addEventListener('pointerleave', () => { tip.hidden = true; });
    });
    svgEl('line', { x1: m.l, x2: m.l + w, y1: m.t + h, y2: m.t + h, class: 'baseline' }, svg);
    legend(el, series, true);
  }

  return { line, bar };
})();
