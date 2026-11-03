/* =========================================================
   提瓦特一言（kokuu-quote）· 前端组件 v3
   ------------------------------------------------------------
   v3 变更（配合样式 v5 的响应式重设计）：
     1. 新增元素徽标（.kq-el-badge，显示「风」「岩」等元素名），
        仅移动端可见 —— 小屏上颜色认不出是哪个元素，必须有文字
     2. 元素色不再在 JS 里算，改成写 data-el，由 teyvat.css 的
        属性选择器决定颜色。这样配色表只有一份（CSS 里），
        避免 JS 与 CSS 两处颜色表漂移
     3. 移动端（≤900px）给卡片加 .kq-float 类，配合 CSS 的底部浮层形态
   ========================================================= */
(function () {
  "use strict";

  // 移动端断点：必须与 quote.css 里的 @media (max-width: 900px) 保持一致
  var MOBILE_MAX = 900;

  // 已知的七元素。用于「台词没带 el 字段」时不显示徽标
  var KNOWN_EL = ["风", "岩", "雷", "水", "火", "草", "冰"];

  function getBase() {
    var b = window.blessing || {};
    return (b.base_url || "").replace(/\/+$/, "");
  }

  function isMobile() {
    return window.matchMedia("(max-width: " + MOBILE_MAX + "px)").matches;
  }

  function fetchQuote() {
    return fetch(getBase() + "/plugin/kokuu-quote/random", {
      credentials: "same-origin",
      headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" }
    })
      .then(function (r) { return r.json(); })
      .catch(function () {
        return { text: "风带来了故事的种子，时间使之发芽。", by: "时之风", el: "风" };
      });
  }

  // 顶栏空间有限：台词与出处各自限长，避免撑破胶囊
  // 移动端换了整条形态，可以多给一点字数
  var MAX_TEXT_DESKTOP = 22;
  var MAX_TEXT_MOBILE  = 42;
  var MAX_BY = 8;

  function clip(s, n) {
    s = String(s == null ? "" : s);
    return s.length > n ? s.slice(0, n) + "…" : s;
  }

  function fill(root, q) {
    var el = String(q.el || "").trim();
    var isKnown = KNOWN_EL.indexOf(el) >= 0;

    // 元素色交给 CSS：写 data-el，quote.css / teyvat.css 负责取色。
    // 未知元素（如用户自定义的「光」）不写 data-el，让 CSS 用默认紫。
    if (isKnown) {
      root.setAttribute("data-el", el);
    } else {
      root.removeAttribute("data-el");
    }

    var limit = isMobile() ? MAX_TEXT_MOBILE : MAX_TEXT_DESKTOP;

    var t = root.querySelector(".kq-text");
    var by = root.querySelector(".kq-by");
    if (t) { t.textContent = clip(q.text, limit); t.title = q.text || ""; }
    if (by) { by.textContent = clip(q.by, MAX_BY); by.title = q.by || ""; }

    var badge = root.querySelector(".kq-el-badge");
    if (badge) {
      // 未知元素就留空，CSS 里 .kq-el-badge:empty不占位
      badge.textContent = isKnown ? el : "";
    }
  }

  function load(root) {
    root.classList.remove("kq-swap");
    void root.offsetWidth;                      // 触发重排以重放动画
    root.classList.add("kq-swap");
    fetchQuote().then(function (q) { fill(root, q); });
  }

/* ---------------- 后台顶栏：一言 ---------------- */
  /*
   * 只挂到「侧栏版后台布局」的顶栏右侧列表（.navbar-nav.ml-auto），
   * 即用户中心 / 管理后台。
   *
   * 为何不兼容皮肤库（layout-top-nav）顶栏：
   *   皮肤库顶栏右侧是 .navbar-custom-menu > ul.nav.navbar-nav，它与
   *   「上传 / 语言 / 登录」同处一个 flex 容器，没有独立分区。实测在
   *   1440px 视口下，一言（x=1062, w=228）与语言菜单（x=1062, w=126）
   *   **完全重叠**，窄屏更挤成一团 —— 这就是「所有功能挤在一起」的根因。
   *   故皮肤库不挂载一言，保持其顶栏干净。
   *
   * [移动端策略]
   *   CSS 会把这个 <li> 改成 position:fixed 的底部浮层，
   *   所以「插入顶栏」这个动作在移动端只是为了挂载，
   *   视觉位置完全由 CSS 决定。DOM 里仍留在 .navbar-nav 里，
   *   这样不需要在 resize 时重新挂载/卸载。
   */
  function mountTopbar() {
    if (document.querySelector(".kq-top")) { return; }

    // 仅取带ml-auto 的独立右侧列表；找不到则安静退出（皮肤库 / 登录页）
    var bar = document.querySelector(".main-header .navbar-nav.ml-auto");
    if (!bar) { return; }

    // 双保险：确认它确实是靠右的独立分区，而非与其它菜单共用的容器
    if (bar.closest(".navbar-custom-menu")) { return; }

    var li = document.createElement("li");
    li.className = "nav-item kq-item";
    li.innerHTML =
      '<a class="nav-link kq-top" href="#" role="button" title="点击换一句">' +
        '<span class="kq-el-badge" aria-hidden="true"></span>' +
        '<span class="kq-body">' +
          '<span class="kq-text">…</span>' +
          '<span class="kq-by"></span>' +
        '</span>' +
        '<span class="kq-refresh" aria-hidden="true">↻</span>' +
      '</a>';
    // 置于右侧列表首位（视觉上最靠右上角）
    bar.insertBefore(li, bar.firstChild);

    li.addEventListener("click", function (ev) {
      ev.preventDefault();
      load(li);
    });

    /* =========================================================
     * 移动端：把 <li> 真正移出顶栏，挂到 <body> 末尾。
     *
     * ★ 为什么必须移，不能只靠 CSS 的 position:fixed：
     *   只要一言还在 .navbar-nav 里，它的祖先上任何
     *   transform / filter / backdrop-filter / will-change / contain
     *   都会**创建包含块**，fixed 就相对那个祖先定位而不是视口。
     *
     *   线上真实踩中的坑（2026-10-03，用户反馈「一言叠在用户头像和
     *   语言切换上面，盖住很多按钮」）：
     *   kokuu-home 的 void.css 里有
     *       body.dark-mode .main-header { backdrop-filter: blur(12px) }
     *   一开暗色模式，顶栏就成了一言的包含块，bottom:0
     *   从「贴视口底部」变成「贴顶栏底边」——
     *   实测矩形从 [0,797,390,47] 变成 [0,9,390,47]，
     *   整条压在用户菜单上。
     *
     *   那些 backdrop-filter 不能删（是 kokuu-home 的暗色毛玻璃效果），
     *   所以唯一可靠解法就是**让一言的祖先链上没有滤镜元素**。
     *
     * ⚠️ 用 body 的直接子元素这一点很关键：
     *   body 上如果也有 transform/filter，同样会失效。所以额外加一层
     *   JS 兜底：挂载后实测一言的矩形，若没落在视口底部就退回顶栏形态
     *   （见 verifyPlacement），宁可小胶囊也不许盖住整条顶栏。
     */
    function verifyPlacement() {
      if (!isMobile()) { return; }
      var r = li.getBoundingClientRect();
      var vh = window.innerHeight || document.documentElement.clientHeight;
      // 贴底判定：底边距视口底部不超过 80px（iOS 地址栏那点误差够用了）
      if (r.bottom < vh - 80) {
        // fixed 没能贴到视口底部 → 祖先里有滤镜制造了包含块。
        // 退回「顶栏内自适应小胶囊」，至少不遮住整条。
        li.classList.add("kq-inline-fallback");
      } else {
        li.classList.remove("kq-inline-fallback");
      }
    }

    function moveToBody() {
      if (!isMobile()) {
        // 回到顶栏（桌面端）
        if (li.parentNode !== bar) { bar.insertBefore(li, bar.firstChild); }
        li.classList.remove("kq-body-docked");
        return;
      }
      // 移动端：挂到 body 末尾，彻底脱离顶栏的滤镜包含块
      if (li.parentNode !== document.body) {
        document.body.appendChild(li);
        li.classList.add("kq-body-docked");
      }
      verifyPlacement();
    }

    // 移动端形态标记：CSS 里 .kq-float 负责浮层底色与悬浮阴影
    function syncMode() {
      li.querySelector(".kq-top").classList.toggle("kq-float", isMobile());
      moveToBody();
    }
    syncMode();

    // 跨断点时切换形态并重取一次（字数上限与徽标可见性都变了）
    var mq = window.matchMedia("(max-width: " + MOBILE_MAX + "px)");
    var onChange = function () {
      syncMode();
      fill(li, window.__kqLast || { text: "…", by: "", el: "" });
    };
    if (mq.addEventListener) {
      mq.addEventListener("change", onChange);
    } else if (mq.addListener) {
      mq.addListener(onChange);            /* Safari < 14 */
    }

    /*
     * 样式可能晚于脚本加载（异步 CSS），或用户开了暗色模式才加载
     * kokuu-home 的 void.css —— 包含块是「后出现」的。
     * 所以在 load 之后再验一次，并在 DOM 变化时轻量复查。
     */
    window.addEventListener("load", verifyPlacement);
    if (window.ResizeObserver) {
      try {
        new window.ResizeObserver(verifyPlacement).observe(document.body);
      } catch (_) {}
    }

    // 缓存当前台词，跨断点时无需再请求
    var origFill = fill;
    fetchQuote().then(function (q) {
      window.__kqLast = q;
      origFill(li, q);
    });
  }

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  ready(function () {
    try {
      // 首页（kokuu-home 接管的落地页）不显示一言
      if (document.body.classList.contains("kokuu-on")) { return; }
      // 其余页面（后台 / 皮肤库 / 鉴权）在顶栏右侧显示
      mountTopbar();
    } catch (e) {
      if (window.console) { console.error("[KokuuQuote]", e); }
    }
  });

  // 公共API
  window.KokuuQuote = { random: fetchQuote };
})();