<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$qRaw = trim((string) ($_GET['q'] ?? ''));
$truncated = mb_strlen($qRaw, 'UTF-8') > 100;
$q = mb_substr($qRaw, 0, 100, 'UTF-8');

$title = $q !== '' ? '翻译 · ' . mb_substr($q, 0, 20, 'UTF-8') : '翻译';
$desc = $q !== '' ? '西夏文逐字翻译「' . mb_substr($q, 0, 20, 'UTF-8') . '」——点击译文中任一西夏文可查看候选并替换' : '把汉字整句逐字译为西夏文，逐字可点选候选替换。';
$canonical = $q !== '' ? '/translate?q=' . rawurlencode($q) : '/translate';
page_start($title, $desc, $canonical);
?>
<section class="translate">
  <div class="seclabel"><span>翻译</span><span class="rule"></span></div>
  <?php if ($q === ''): ?>
  <p class="empty">请输入要翻译的汉字（超过 10 个汉字自动进入本页）。</p>
  <?php else: ?>
  <?php if ($truncated): ?><p class="tr-trunc">已截断至 100 字</p><?php endif; ?>
  <div class="tr-result" id="trResult">
    <?php foreach (lookup_text($q) as $i => $it):
        $ch = (string) ($it['char'] ?? '');
        // 非汉字字位（标点/数字/空格）：透传原文（占一格，顶部对齐），不可点击
        if (!preg_match('/\p{Han}/u', $ch)): ?>
    <span class="tr-glyph tr-pass" data-tr-i="<?= $i ?>"><span class="tr-glyph-src"><?= h($ch) ?></span></span>
    <?php continue;
        endif;
        $cands = $it['candidates'];
        $fb = null;
        if (!$cands) {
            $fb = pinyin_fallback($ch, 4);
        }
        $top = $cands ? $cands[0] : ($fb[0] ?? null);
        $payload = $cands ?: ($fb ?? []);
        $badge = ($fb && ($fb[0]['homophone'] ?? '') !== '') ? '借「' . $fb[0]['homophone'] . '」' : '';
        $none = !$top;
    ?>
    <button type="button" class="tr-glyph<?= $none ? ' is-none' : '' ?><?= $badge !== '' ? ' is-borrow' : '' ?>" data-tr-i="<?= $i ?>" data-tr-cands='<?= h(json_encode($payload, JSON_UNESCAPED_UNICODE)) ?>'>
      <span class="tr-glyph-src"><?= h($ch) ?></span>
      <span class="tr-glyph-ch" lang="txg"><?= h((string) ($top['tangut'] ?? '○')) ?></span>
      <?php if ($badge !== ''): ?><span class="tr-badge"><?= h($badge) ?></span><?php endif; ?>
    </button>
    <?php endforeach; ?>
  </div>
  <div class="tr-copy-row"><button type="button" class="tr-copy" id="trCopy">复制译文</button></div>
  <?php endif; ?>
</section>
<dialog id="trDlg" class="tr-dlg">
  <button type="button" class="ps-close" id="trClose" aria-label="关闭候选" title="关闭">×</button>
  <div class="tr-wheel" id="trWheel" role="radiogroup" aria-label="候选西夏字"></div>
</dialog>
<script>
(function () {
  var dlg = document.getElementById('trDlg');
  var wheel = document.getElementById('trWheel');
  var result = document.getElementById('trResult');
  var closeBtn = document.getElementById('trClose');
  if (!dlg || !wheel || !result) return;
  var cur = -1;

  function setSel(w, card) {
    var cs = w.querySelectorAll('.nb-card');
    for (var i = 0; i < cs.length; i++) {
      cs[i].classList.remove('selected');
      cs[i].setAttribute('aria-checked', 'false');
    }
    card.classList.add('selected');
    card.setAttribute('aria-checked', 'true');
  }
  function centerSelect(w) {
    var cards = w.querySelectorAll('.nb-card');
    if (!cards.length) return;
    var r = w.getBoundingClientRect(), c = r.top + r.height / 2, best = cards[0], bd = 1e9;
    for (var i = 0; i < cards.length; i++) {
      var o = cards[i].getBoundingClientRect(), d = Math.abs((o.top + o.height / 2) - c);
      if (d < bd) { bd = d; best = cards[i]; }
    }
    setSel(w, best);
  }
  function stepCard(w, dir) {
    var cs = Array.prototype.slice.call(w.querySelectorAll('.nb-card'));
    if (!cs.length) return;
    var sel = w.querySelector('.nb-card.selected');
    var i = sel ? cs.indexOf(sel) : 0, n = i + dir;
    if (n < 0 || n >= cs.length) return;
    cs[n].scrollIntoView({ block: 'center', behavior: 'smooth' });
    setSel(w, cs[n]);
  }
  function closeDlg() {
    document.documentElement.classList.remove('poster-open');
    if (dlg.open) dlg.close();
    dlg.classList.remove('poster-fallback');
  }
  function pick(c) {
    var g = result.querySelector('.tr-glyph[data-tr-i="' + cur + '"]');
    if (g) {
      var chEl = g.querySelector('.tr-glyph-ch');
      if (chEl) { chEl.textContent = c.tangut || '○'; g.classList.remove('is-none'); }
      var badge = g.querySelector('.tr-badge');
      if (c.homophone) {
        g.classList.add('is-borrow');
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'tr-badge';
          g.appendChild(badge);
        }
        badge.textContent = '借「' + c.homophone + '」';
      } else if (badge) {
        badge.remove();
        g.classList.remove('is-borrow');
      }
    }
    closeDlg();
  }
  function openDlg(i, cands) {
    cur = i;
    wheel.innerHTML = '';
    for (var k = 0; k < cands.length; k++) {
      (function (c) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'nb-card' + (k === 0 ? ' selected' : '');
        b.setAttribute('role', 'radio');
        b.setAttribute('aria-checked', k === 0 ? 'true' : 'false');
        var g = document.createElement('span');
        g.className = 'nb-glyph-lg'; g.lang = 'txg'; g.textContent = c.tangut || '○';
        b.appendChild(g);
        var p = document.createElement('span');
        p.className = 'nb-phon-lg'; p.textContent = c.gong || '—';
        b.appendChild(p);
        if (c.homophone) {
          var bo = document.createElement('span');
          bo.className = 'nb-borrow'; bo.textContent = '借「' + c.homophone + '」';
          b.appendChild(bo);
        }
        if (c.gloss_zh) {
          var gl = document.createElement('span');
          gl.className = 'nb-gloss-lg'; gl.textContent = c.gloss_zh;
          b.appendChild(gl);
        }
        b.addEventListener('click', function () { pick(c); });
        wheel.appendChild(b);
      })(cands[k]);
    }
    wheel.scrollTop = 0;
    if (typeof dlg.showModal === 'function') dlg.showModal();
    else dlg.classList.add('poster-fallback');
    document.documentElement.classList.add('poster-open');
  }
  result.addEventListener('click', function (e) {
    var g = e.target.closest('.tr-glyph');
    if (!g || !g.dataset.trCands) return;
    var cands = JSON.parse(g.dataset.trCands);
    if (!cands || !cands.length) return;
    openDlg(g.dataset.trI, cands);
  });
  if (closeBtn) closeBtn.addEventListener('click', closeDlg);
  dlg.addEventListener('cancel', function (e) { e.preventDefault(); closeDlg(); });
  dlg.addEventListener('click', function (e) { if (e.target === dlg) closeDlg(); });
  var tm;
  wheel.addEventListener('scroll', function () {
    clearTimeout(tm);
    tm = setTimeout(function () { centerSelect(wheel); }, 50);
  }, { passive: true });
  wheel.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown') { e.preventDefault(); stepCard(wheel, 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); stepCard(wheel, -1); }
  });
  var copyBtn = document.getElementById('trCopy');
  if (copyBtn) {
    function fallbackCopy(t) {
      var ta = document.createElement('textarea');
      ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
    }
    copyBtn.addEventListener('click', function () {
      var s = '', gs = result.querySelectorAll('.tr-glyph');
      for (var i = 0; i < gs.length; i++) {
        var ch = gs[i].querySelector('.tr-glyph-ch');
        if (ch) s += ch.textContent;
      }
      if (!s) return;
      var done = function () {
        copyBtn.textContent = '已复制';
        setTimeout(function () { copyBtn.textContent = '复制译文'; }, 1600);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(s).then(done, function () { fallbackCopy(s); done(); });
      } else { fallbackCopy(s); done(); }
    });
  }
})();
</script>
<?php
page_end();
