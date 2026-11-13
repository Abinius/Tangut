<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/layout.php';

$dv = data_version();
page_start('关于 · ' . SITE_NAME, 'Tangut Dictionary（蕃汉合时掌中珠）的工具定位、数据来源、对应关系、版权与边界，以及西夏文简介。', '/about');
?>
<article class="about">
  <p class="breadcrumb"><a href="/">查询</a> <span class="sep">///</span> 关于</p>

  <section>
    <div class="seclabel"><span>01 本工具</span><span class="rule"></span></div>
    <p>「蕃汉」是一个把汉字转写成西夏文（Tangut script）的免费公益查询工具。输入姓名、姓氏或任意汉字，即可看到候选的西夏文字形、拟音与义项依据。</p>
    <p>本站不追求「一一对应」——西夏文与汉字本就不是一一对应，多候选为常态。候选项据释义反查生成，并按 tier（P0–P3）分层近似排序。</p>
  </section>

  <section>
    <div class="seclabel"><span>02 数据来源</span><span class="rule"></span></div>
    <ul>
      <li>字形：<a href="https://github.com/notofonts/tangut" target="_blank" rel="noopener">Noto Serif Tangut</a>（SIL OFL）</li>
      <li>映射：<a href="https://github.com/tangutdata" target="_blank" rel="noopener">tangutdata</a>（MIT 开放数据）</li>
      <li>拟音：龚煌城体系</li>
    </ul>
    <p>数据版本 <strong><?= h($dv) ?></strong>，全量 <?= (int) site_stats()['hanzi'] ?> 字可查。</p>
  </section>

  <section>
    <div class="seclabel"><span>03 对应关系</span><span class="rule"></span></div>
    <table class="tiers">
      <thead><tr><th>tier</th><th>含义</th><th>可信度</th></tr></thead>
      <tbody>
        <tr><td><span class="tag tag-p0">P0</span></td><td>释义即该字</td><td>最高</td></tr>
        <tr><td><span class="tag tag-p1">P1</span></td><td>首义项含该字</td><td>高</td></tr>
        <tr><td><span class="tag tag-p2">P2</span></td><td>释义含该字</td><td>中</td></tr>
        <tr><td><span class="tag tag-p3">P3</span></td><td>同音替代</td><td>低（参考）</td></tr>
      </tbody>
    </table>
  </section>

  <section>
    <div class="seclabel"><span>04 版权与边界</span><span class="rule"></span></div>
    <p>映射数据 tangutdata 以 <strong>MIT</strong> 开放；释义文字版权归原出版社，本站仅作索引引用。</p>
    <p>本工具结果为自动反查近似，<strong>不能作为学术定论</strong>。正式研究请查阅《夏汉字典》《文海》等原始资料。</p>
  </section>

  <section>
    <div class="seclabel"><span>05 西夏文简介</span><span class="rule"></span></div>
    <p class="lead">西夏文（Tangut script）是西夏王朝（1038–1227）官方创制的文字，记录西夏语（属汉藏语系藏缅语族）。</p>

    <h3 class="focus-h">创立</h3>
    <p>由西夏景宗李元昊命大臣野利仁荣于 1036 年前后创制，字形仿汉字而自成体系，约 6000 余字，通行约三百年，西夏亡后渐成死文字，19 世纪末随黑水城文献出土重见天日。</p>

    <h3 class="focus-h">结构形式</h3>
    <p>西夏文为方块表意文字，结构与汉字同源——以偏旁部首组合成字，有独体、合体之分：</p>
    <table class="structure">
      <thead><tr><th>结构</th><th>例字</th><th>说明</th></tr></thead>
      <tbody>
        <tr><td>独体</td><td><span class="glyph s2" lang="txg">𗸦</span></td><td>人</td></tr>
        <tr><td>左右</td><td><span class="glyph s2" lang="txg">𘍑</span></td><td>林</td></tr>
        <tr><td>上下</td><td><span class="glyph s2" lang="txg">𗨷</span></td><td>渐</td></tr>
        <tr><td>会意</td><td><span class="glyph s2" lang="txg">𘊇</span></td><td>昌</td></tr>
      </tbody>
    </table>

    <h3 class="focus-h">构造原理</h3>
    <p>会意字由两个或多个意符组合表达新义，例如：</p>
    <div class="formula">
      <div class="f-row"><span class="glyph s1" lang="txg">𗿿</span> <span class="eq">=</span> <span class="glyph s1" lang="txg">𗿿</span> 定 + 疑 → 孤</div>
      <div class="f-row"><span class="glyph s1" lang="txg">𗆼</span> <span class="eq">=</span> 疑 + 孤 → 愁</div>
    </div>

    <h3 class="focus-h">字数与声韵</h3>
    <p>《文海》收约 <strong>3064</strong> 字，《音同》收 <strong>6133</strong> 字，《夏汉字典》收录约 <strong>6074</strong> 字。声韵体系以龚煌城拟音最为通行。</p>
  </section>

  <section>
    <div class="seclabel"><span>字形 specimen</span><span class="rule"></span></div>
    <div class="spec">
      <div class="spec-cell"><span class="label">caption · 22px</span><span class="glyph s1" lang="txg">𘔺</span></div>
      <div class="spec-cell"><span class="label">display · 48px</span><span class="glyph s2" lang="txg">𘔺</span></div>
      <div class="spec-cell"><span class="label">hero · 96px</span><span class="glyph s3" lang="txg">𘔺</span></div>
    </div>
  </section>

  <p class="backlinks"><a href="/">回首页查询</a></p>
</article>
<?php
page_end();
