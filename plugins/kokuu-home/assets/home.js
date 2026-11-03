/* =========================================================
   KokuuSkin 皮肤站首页（前端接管）
   由 KokuuHome 插件注入到首页 `</body>` 之前（DOM 已就绪）。
   不改内核 home.twig：这里直接用 KokuuSkin 落地页整体替换 body 内容。

   风格：Apple 扁平（白底/大留白/细腻阴影/大圆角）
        + Minecraft 像素点缀（像素方块/硬边/网格）
        + 挪德卡莱月神意象（月亮/星尘/哥白鸽）

   特性：
   - 文案与右侧图片由 window.KOKUU_CONFIG 驱动（后台表单可改）
   - 支持浅色 / 虚空两态，两态都是 Apple 扁平语言
   ========================================================= */
(function () {
  "use strict";

  var THEME_KEY = "kokuu_theme"; // "void" | "paper"

  // 读取后台配置，带默认值
  function readConfig() {
    var c = window.KOKUU_CONFIG || {};
    return {
      hero_title: c.hero_title || "KokuuSkin",
      tagline: c.tagline || "让每一次登录，都落在正确的像素上",
      intro: c.intro
        || "皮肤站用来存放角色、分享外观。把喜欢的样子收进衣柜，随时调用。",
      footer_note: c.footer_note || "像素有形，月光无界",
      contact_email: c.contact_email || "hi@kokuu.org",
      // 右侧图片：可自定义；空则用内置像素插画
      hero_image: c.hero_image || ""
    };
  }

  function currentTheme() {
    try {
      var saved = localStorage.getItem(THEME_KEY);
      if (saved === "void" || saved === "paper") return saved;
    } catch (e) { /* 忽略 */ }
    return "paper";
  }

  function applyTheme(theme) {
    var root = document.querySelector(".kokuu-root");
    if (root) { root.classList.toggle("kokuu-void", theme === "void"); }
    try { localStorage.setItem(THEME_KEY, theme); } catch (e) { /* 忽略 */ }
    updateToggleLabel(theme);
  }

  function updateToggleLabel(theme) {
    var btn = document.getElementById("kokuu-theme-toggle");
    if (!btn) return;
    var isVoid = theme === "void";
    btn.innerHTML = isVoid
      ? '<span class="px-sun"></span>日间'
      : '<span class="px-moon"></span>夜间';
    btn.setAttribute("title", isVoid ? "切换到浅色模式" : "切换到夜间模式");
  }

  function esc(s) {
    return String(s == null ? "" : s)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  // 内置 MC 像素场景（可被设置页 hero_image 覆盖为单张自定义图）
  var SCENES = [
    { file: "scenes/01-mineshaft.png", name: "矿洞深处",   tag: "CAVE" },
    { file: "scenes/02-nether.png",    name: "下界熔岩",   tag: "NETHER" },
    { file: "scenes/03-snow.png",      name: "雪原清晨",   tag: "SNOW" },
    { file: "scenes/04-jungle.png",    name: "丛林神庙",   tag: "JUNGLE" },
    { file: "scenes/05-end.png",       name: "末地黑曜石", tag: "THE END" },
    { file: "scenes/06-village.png",   name: "村庄日暮",   tag: "VILLAGE" }
  ];
  var SLIDE_MS = 5200;
  var slideIdx = 0;
  var slideTimer = null;

  function mount() {
    var b = window.blessing || {};
    var base = (b.base_url || "").replace(/\/+$/, "");
    var asset = base + "/plugins/kokuu-home/assets/";
    var cfg = readConfig();
    var theme = currentTheme();
    // 右侧图片：仅当后台确实填了自定义图时才用单图模式；
    // 未填（空字符串）→ useCustom=false → 走内置 MC 场景轮播。
    // 切勿在此给 heroImg 兜底成内置插画，否则下方 heroImg ? ... : SCENES
    // 永远为真，轮播的 6 张图/圆点/进度条都不会被渲染。
    var useCustom = !!cfg.hero_image;
    var heroImg = useCustom
      ? (/^https?:\/\//i.test(cfg.hero_image) ? cfg.hero_image : base + "/" + cfg.hero_image.replace(/^\/+/, ""))
      : "";

    try {
      var fi = document.querySelector(
        'link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]'
      );
      if (fi) { fi.href = asset + "favicon.png"; }

      var html = [
        '<div class="kokuu-root' + (theme === "void" ? " kokuu-void" : "") + '">',
        '  <div class="kokuu-grid" aria-hidden="true"></div>',
        '  <div class="kokuu-dust" id="kokuu-dust"></div>',
        '  <div class="kokuu-navbar" aria-hidden="true"></div>',

        /* ---------- 顶栏 ---------- */
        '  <header class="kokuu-topbar">',
        '    <a class="kokuu-brand" href="' + base + '/">',
        '      <span class="kokuu-brand-name">Kokuu<span class="kokuu-accent">Skin</span></span>',
        '    </a>',
        '    <div class="kokuu-topbar-right">',
        '      <button type="button" class="kokuu-theme-toggle" id="kokuu-theme-toggle"></button>',
        '      <nav class="kokuu-nav" id="kokuu-nav">',
        '        <a href="' + base + '/skinlib">皮肤库</a>',
        '        <a href="' + base + '/auth/login">登录</a>',
        '        <a href="' + base + '/auth/register">注册</a>',
        '      </nav>',
        '    </div>',
        '  </header>',

        /* ---------- HERO ---------- */
        '  <section class="kokuu-hero">',
        '    <div class="kokuu-hero-text">',
        '      <p class="kokuu-eyebrow"><span class="px-dot"></span>SKIN STATION</p>',
        '      <h1 class="kokuu-title" id="kokuu-hero-title">' + esc(cfg.hero_title) + '</h1>',
        '      <p class="kokuu-tagline" id="kokuu-hero-tagline">' + esc(cfg.tagline) + '</p>',
        '      <p class="kokuu-sub" id="kokuu-hero-intro">' + esc(cfg.intro) + '</p>',
        '      <div class="kokuu-cta" id="kokuu-cta">',
        '        <a class="kbtn primary" href="' + base + '/skinlib">浏览皮肤库</a>',
        '        <a class="kbtn" href="' + base + '/auth/register">创建账户</a>',
        '      </div>',
        '      <ul class="kokuu-badges">',
        '        <li><span class="px-grass"></span>皮肤库</li>',
        '        <li><span class="px-water"></span>登录验证</li>',
        '        <li><span class="px-moon"></span>像素工坊</li>',
        '      </ul>',
        '    </div>',
        /* 右侧：可自定义图片位 / 内置 MC 场景轮播 */
        '    <div class="kokuu-hero-art">',
        '      <figure class="kokuu-shot" id="kokuu-shot">',
        useCustom
          ? '        <img src="' + esc(heroImg) + '" alt="首页配图" class="kokuu-shot-img kokuu-shot-single">'
          : SCENES.map(function (s, i) {
              return '        <img src="' + esc(asset + s.file) + '" alt="' + esc(s.name) +
                     '" class="kokuu-shot-img' + (i === 0 ? " is-on" : "") + '" data-i="' + i + '">';
            }).join("\n"),
        '        <div class="kokuu-shot-cap" id="kokuu-shot-cap">' +
          (useCustom
            ? '<span class="px-moon"></span>自定义图片'
            : '<span class="px-grass"></span><span id="kokuu-cap-name">' + esc(SCENES[0].name) +
              '</span><em id="kokuu-cap-tag">' + esc(SCENES[0].tag) + '</em>') +
        '        </div>',
        useCustom ? '' :
        '        <div class="kokuu-dots" id="kokuu-dots">' +
          SCENES.map(function (s, i) {
            return '<button type="button" class="kokuu-dot' + (i === 0 ? " is-on" : "") +
                   '" data-i="' + i + '" aria-label="' + esc(s.name) + '"></button>';
          }).join("") +
        '        </div>',
        '        <div class="kokuu-progress" id="kokuu-progress"></div>',
        '      </figure>',
        '    </div>',
        '  </section>',

        /* ---------- 主体 ---------- */
        '  <main class="kokuu-main">',

        '    <section class="kokuu-block reveal">',
        '      <p class="kokuu-kicker">01 / 功能</p>',
        '      <h2 class="kokuu-h2">我们提供什么</h2>',
        '      <div class="kokuu-cards">',
        '        <article class="kcard">',
        '          <div class="kcard-ico"><span class="px-grass"></span></div>',
        '          <h3>皮肤库</h3>',
        '          <p>收录角色与外观，支持预览、下载与一键换肤。把喜欢的样子存进衣柜。</p>',
        '          <a class="kmore" href="' + base + '/skinlib">前往 →</a>',
        '        </article>',
        '        <article class="kcard">',
        '          <div class="kcard-ico"><span class="px-water"></span></div>',
        '          <h3>登录验证</h3>',
        '          <p>基于 Yggdrasil 的账号验证，让启动器直接认得这台服务器。一套账号，畅玩全模组。</p>',
        '          <a class="kmore" href="' + base + '/auth/login">前往 →</a>',
        '        </article>',
        '        <article class="kcard">',
        '          <div class="kcard-ico"><span class="px-mine"></span></div>',
        '          <h3>上传换肤</h3>',
        '          <p>自己做的材质也能上传分享。工作室成员与朋友都能把作品挂上来。</p>',
        '          <a class="kmore" href="' + base + '/skinlib/upload">前往 →</a>',
        '        </article>',
        '      </div>',
        '    </section>',

        '    <section class="kokuu-block reveal">',
        '      <p class="kokuu-kicker">02 / 理念</p>',
        '      <h2 class="kokuu-h2">我们怎么做</h2>',
        '      <div class="kokuu-creed">',
        '        <div class="kokuu-creed-col">',
        '          <p>先把事做对，再把事做好。</p>',
        '          <p class="small">偏好稳定可维护的方案：先评估、再落地，不为一时方便埋坑。',
        '          皮肤站、启动器与服务端，都按能长期跑下去的方式搭建。</p>',
        '        </div>',
        '        <div class="kokuu-creed-col">',
        '          <p>慢一点，但更稳一点。</p>',
        '          <p class="small">把基础打牢，剩下的让它自然生长。',
        '          更在乎你进来时它还在、还好用。</p>',
        '        </div>',
        '      </div>',
        '    </section>',

        '  </main>',

        /* ---------- 页脚 ---------- */
        '  <footer class="kokuu-foot">',
        '    <div class="foot-left">',
        '      <span class="kokuu-foot-brand">Kokuu<span class="kokuu-accent">Skin</span></span>',
        '      <p>' + esc(cfg.footer_note) + '</p>',
        '    </div>',
        '    <div class="foot-right">',
        '      <a href="' + base + '/skinlib">皮肤库</a>',
        '      <a href="' + base + '/skinlib/upload">上传</a>',
        '      <a href="' + base + '/auth/login">登录</a>',
        '      <a href="mailto:' + esc(cfg.contact_email) + '">联系</a>',
        '    </div>',
        '    <p class="foot-fine">© 2026 KokuuSkin · Powered by Blessing Skin</p>',
        '  </footer>',

        '</div>'
      ].join("\n");

      document.body.innerHTML = html;
      document.body.classList.add("kokuu-on");
      document.title = cfg.hero_title + " · 皮肤站";

      initDust();
      initReveal();
      initCarousel();

      var toggle = document.getElementById("kokuu-theme-toggle");
      if (toggle) {
        updateToggleLabel(theme);
        toggle.addEventListener("click", function () {
          applyTheme(currentTheme() === "void" ? "paper" : "void");
        });
      }

      detectLogin(base);
    } catch (e) {
      document.body.style.visibility = "visible";
      if (window.console) { console.error("[KokuuHome]", e); }
    }
  }

  /* 像素星尘（浅色淡墨 / 夜间星点） */
  function initDust() {
    var c = document.getElementById("kokuu-dust");
    if (!c || !c.getContext) { return; }
    var ctx = c.getContext("2d");
    var dpr = window.devicePixelRatio || 1;
    var W = 0, H = 0, dots = [];

    function resize() {
      W = c.width = Math.floor(window.innerWidth * dpr);
      H = c.height = Math.floor(window.innerHeight * dpr);
      c.style.width = window.innerWidth + "px";
      c.style.height = window.innerHeight + "px";
      dots = [];
      // 像素方块（呼应 MC），不用圆点
      var n = Math.floor(window.innerWidth / (window.innerWidth < 800 ? 18 : 26));
      for (var i = 0; i < n; i++) {
        dots.push({
          x: Math.floor(Math.random() * W),
          y: Math.floor(Math.random() * H),
          s: (Math.random() < 0.8 ? 2 : 3) * dpr,
          v: (Math.random() * 0.22 + 0.05) * dpr,
          a: Math.random() * 0.5 + 0.2
        });
      }
    }
    resize();
    window.addEventListener("resize", resize);

    function frame() {
      if (!c.isConnected) { return; }
      var isVoid = !!document.querySelector(".kokuu-root.kokuu-void");
      ctx.clearRect(0, 0, W, H);
      for (var i = 0; i < dots.length; i++) {
        var p = dots[i];
        p.y -= p.v;
        if (p.y < -4 * dpr) { p.y = H; p.x = Math.floor(Math.random() * W); }
        ctx.fillStyle = isVoid
          ? "rgba(200,210,255," + (p.a * 0.5) + ")"
          : "rgba(43,31,82," + (p.a * 0.10) + ")";
        ctx.fillRect(p.x, p.y, p.s, p.s); // 硬边方块 = 像素感
      }
      window.requestAnimationFrame(frame);
    }
    window.requestAnimationFrame(frame);
  }

  /* 右侧图片轮播：自动切换 + 圆点指示 + 悬停暂停 */
  function initCarousel() {
    var dots = document.getElementById("kokuu-dots");
    if (!dots) { return; }                          // 自定义单图模式无轮播
    var imgs = document.querySelectorAll("#kokuu-shot .kokuu-shot-img");
    if (imgs.length < 2) { return; }
    var capName = document.getElementById("kokuu-cap-name");
    var capTag = document.getElementById("kokuu-cap-tag");
    var shot = document.getElementById("kokuu-shot");
    var bar = document.getElementById("kokuu-progress");

    function show(i) {
      slideIdx = (i + imgs.length) % imgs.length;
      for (var k = 0; k < imgs.length; k++) {
        imgs[k].classList.toggle("is-on", k === slideIdx);
      }
      for (var d = 0; d < dots.children.length; d++) {
        dots.children[d].classList.toggle("is-on", d === slideIdx);
      }
      if (capName) { capName.textContent = SCENES[slideIdx].name; }
      if (capTag) { capTag.textContent = SCENES[slideIdx].tag; }
      // 重启底部进度条动画，让自动切换可见
      if (bar) {
        bar.classList.remove("is-run");
        void bar.offsetWidth;
        bar.classList.add("is-run");
      }
    }

    function next() { show(slideIdx + 1); }
    function start() {
      stop();
      slideTimer = window.setInterval(function () {
        if (!document.body.contains(shot)) { stop(); return; }
        next();
      }, SLIDE_MS);
    }
    function stop() {
      if (slideTimer) { window.clearInterval(slideTimer); slideTimer = null; }
    }

    // 圆点点击
    for (var d = 0; d < dots.children.length; d++) {
      (function (idx) {
        dots.children[idx].addEventListener("click", function (ev) {
          ev.stopPropagation();
          show(idx);
          start();                                  // 重置计时
        });
      })(d);
    }
    // 点击图片切换下一张
    shot.addEventListener("click", function (ev) {
      if (ev.target.closest(".kokuu-dots")) { return; }
      next();
      start();
    });
    // 页面隐藏时暂停，省电（不因悬停暂停，避免"看起来不轮播"）
    document.addEventListener("visibilitychange", function () {
      if (document.hidden) { stop(); } else { start(); }
    });

    show(0);
    start();
  }

  function initReveal() {
    var els = document.querySelectorAll(".reveal");
    if (!("IntersectionObserver" in window)) {
      els.forEach(function (e) { e.classList.add("in"); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add("in"); io.unobserve(en.target); }
      });
    }, { threshold: 0.12 });
    els.forEach(function (e) { io.observe(e); });
  }

  function detectLogin(base) {
    try {
      fetch(base + "/user", {
        credentials: "same-origin",
        redirect: "manual",
        headers: { "X-Requested-With": "XMLHttpRequest" }
      }).then(function (r) { applyAuthState(r.status === 200, base); })
        .catch(function () {});
    } catch (e) {}
  }

  function applyAuthState(loggedIn, base) {
    var nav = document.getElementById("kokuu-nav");
    if (nav) {
      nav.innerHTML = loggedIn
        ? '<a href="' + base + '/skinlib">皮肤库</a>' +
          '<a href="' + base + '/user">用户中心</a>' +
          '<a href="' + base + '/user/closet">我的衣柜</a>'
        : '<a href="' + base + '/skinlib">皮肤库</a>' +
          '<a href="' + base + '/auth/login">登录</a>' +
          '<a href="' + base + '/auth/register">注册</a>';
    }
    var cta = document.getElementById("kokuu-cta");
    if (cta) {
      cta.innerHTML = loggedIn
        ? '<a class="kbtn primary" href="' + base + '/user">进入用户中心</a>' +
          '<a class="kbtn" href="' + base + '/user/closet">我的衣柜</a>'
        : '<a class="kbtn primary" href="' + base + '/skinlib">浏览皮肤库</a>' +
          '<a class="kbtn" href="' + base + '/auth/register">创建账户</a>';
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mount);
  } else {
    mount();
  }
})();
