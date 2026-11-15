// TangutPoster 全链路测试：mock DOM/canvas，注入 share.js，调用 open() 覆盖绘制路径
'use strict';
const fs = require('fs');
const path = require('path');

const SRC = path.join(__dirname, 'assets', 'share.js');
let failures = 0;

function makeCtx() {
  const t = {};
  return new Proxy(t, {
    get(o, p) {
      if (p in o) return o[p];
      if (p === 'createLinearGradient' || p === 'createRadialGradient') return () => ({ addColorStop() {} });
      if (p === 'getImageData') return () => ({ data: new Uint8ClampedArray(368 * 2 * 400 * 2 * 4) });
      if (p === 'measureText') return () => ({ width: 10 });
      return function () {};
    },
    set(o, p, v) { o[p] = v; return true; }
  });
}

function mockEl(extra) {
  return Object.assign({
    style: {}, classList: { add() {}, remove() {}, toggle() {} },
    addEventListener() {}, querySelectorAll() { return []; },
    dataset: {}, setAttribute() {}, src: '', href: '', hidden: true,
    showModal() {}, close() {}, open: true
  }, extra || {});
}

const dlg = mockEl({ querySelectorAll: () => [{ dataset: { style: 'acid' }, classList: { toggle() {}, add() {}, remove() {} }, addEventListener() {} }] });
const els = { posterDlg: dlg, posterImg: mockEl(), posterDl: mockEl(), posterHint: mockEl(), posterClose: mockEl(), posterPrev: mockEl(), posterNext: mockEl() };

global.window = global;
global.Image = function () { const self = this; this.width = 1280; this.height = 1920; setTimeout(() => { if (self.onload) self.onload(); }, 0); };
global.document = {
  fonts: { load: () => Promise.resolve(), ready: Promise.resolve() },
  createElement: () => ({ width: 0, height: 0, getContext: makeCtx, toDataURL: () => 'data:image/png;base64,x', toBlob: cb => cb(null) }),
  getElementById: id => els[id] || null,
  querySelectorAll: () => [],
  addEventListener() {}
};
global.localStorage = { getItem: () => null, setItem() {} };
global.URL = { createObjectURL: () => 'blob:x', revokeObjectURL() {} };

// 注入 share.js
eval(fs.readFileSync(SRC, 'utf8'));

function payload(mode, items) {
  if (mode === 'combo') return { mode: 'combo', items, dv: '2026.11.0' };
  return Object.assign({ dv: '2026.11.0' }, items[0]);
}

const cases = [
  ['单字·有对应', payload('single', [{ hanzi: '青', tangut: '𗘍', phon: 'ŋwər¹', gloss: '青', tier: 'P0', lfw: '0257' }])],
  ['单字·无对应', payload('single', [{ hanzi: '斌', tangut: '○', phon: '', gloss: '', tier: 'none', lfw: '' }])],
  ['单字·多字形', payload('single', [{ hanzi: '张', tangut: '𗳋𗳌', phon: 'gjij¹', gloss: '张', tier: 'P0', lfw: '0471' }])],
  ['组合2·含无对应', payload('combo', [
    { hanzi: '张', tangut: '𗳋', phon: 'gjij¹', gloss: '张', tier: 'P0', lfw: '0471' },
    { hanzi: '斌', tangut: '○', phon: '—', gloss: '西夏文无此义项', tier: 'none', lfw: '—' }
  ])],
  ['组合3', payload('combo', ['张', '斌', '青'].map(c => ({ hanzi: c, tangut: c === '斌' ? '○' : '𗘍', phon: c === '斌' ? '—' : 'ŋwər¹', gloss: c, tier: c === '斌' ? 'none' : 'P0', lfw: c === '斌' ? '—' : '0257' })))],
  ['组合4', payload('combo', ['张', '斌', '瑞', '峰'].map(c => ({ hanzi: c, tangut: '𗘍', phon: 'ŋwər¹', gloss: c, tier: 'P0', lfw: '0257' })))],
  ['组合8', payload('combo', Array.from({ length: 8 }, (_, i) => ({ hanzi: String.fromCharCode(0x4e00 + i), tangut: '𗘍', phon: 'ŋwər¹', gloss: 'x', tier: 'P0', lfw: '0257' })))],
  ['组合16', payload('combo', Array.from({ length: 16 }, (_, i) => ({ hanzi: String.fromCharCode(0x4e00 + i), tangut: '𗘍', phon: 'ŋwər¹', gloss: 'x', tier: 'P0', lfw: '0257' })))],
  ['组合·含借字', payload('combo', [
    { hanzi: '张', tangut: '𗳋', phon: 'gjij¹', gloss: '张', tier: 'P0', lfw: '0471', homophone: '' },
    { hanzi: '斌', tangut: '𗴂', phon: 'pə¹', gloss: '斌', tier: 'P3', lfw: '0258', homophone: '奔' }
  ])],
  ['单字·借字', payload('single', [{ hanzi: '斌', tangut: '𗴂', phon: 'pə¹', gloss: '斌', tier: 'P3', lfw: '0258', homophone: '奔' }])],
  ['空字段兜底', payload('single', [{ hanzi: '', tangut: '', phon: '', gloss: '', tier: '', lfw: '' }])]
];

(async () => {
  for (const style of ['acid', 'chrome', 'holo']) {
    for (const [name, data] of cases) {
      try {
        global.localStorage.getItem = () => style;
        await global.TangutPoster.open(data);
        // 等待异步 render 完成
        await new Promise(r => setTimeout(r, 30));
        console.log(`OK   [${style}] ${name}`);
      } catch (e) {
        failures++;
        console.log(`FAIL [${style}] ${name}: ${e.message}`);
      }
    }
  }
  console.log(failures ? `\n${failures} FAILURES` : '\nALL PASS');
  process.exit(failures ? 1 : 0);
})();
