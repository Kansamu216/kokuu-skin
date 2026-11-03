/* =========================================================
   KokuuSkin 后台外壳重建（kokuu-ui）
   注入到 user/admin/skinlib 页面 `</body>` 之前（DOM 已就绪）。

   与 kokuu-home 的关系：
     - kokuu-home 提供品牌、配色、虚空深色主题
     - 本脚本为 apple.css / motion.css 提供"启用类"与 DOM 编排，
       并对 AdminLTE 外壳做无侵入的增强（折叠、进场 stagger），
       React 继续渲染内容区并处理数据交互。

   设计原则：不破坏 AdminLTE / React 的既有行为，只做加法。
   ========================================================= */
(function () {
  "use strict";

  var BODY_CLASS = "kokuu-apple";
  var IN_CLASS = "kokuu-in";

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  /* 标记 body，启用 apple.css 的作用域样式 */
  function markBody() {
    document.body.classList.add(BODY_CLASS);
  }

  /* 侧边栏折叠：接管 pushmenu 按钮，状态写 localStorage，跨页保持 */
  function initSidebar() {
    var toggle = document.querySelector('[data-widget="pushmenu"]');
    var sidebar = document.querySelector(".main-sidebar");
    if (!toggle || !sidebar) { return; }

    var KEY = "kokuu_sidebar_collapsed";
    var stored = null;
    try { stored = localStorage.getItem(KEY); } catch (e) { /* 忽略 */ }

    function isCollapsed() {
      return document.body.classList.contains("sidebar-collapse");
    }
    function apply(collapsed) {
      document.body.classList.toggle("sidebar-collapse", collapsed);
      try {
        localStorage.setItem(KEY, collapsed ? "1" : "0");
      } catch (e) { /* 忽略 */ }
    }

    if (stored === "1") { apply(true); }
    else if (stored === "0") { apply(false); }

    // AdminLTE 的 pushmenu 自带 toggle；但要接管持久化，
    // 监听其点击（事件委托，避免被 AdminLTE 停止冒泡）
    toggle.addEventListener("click", function () {
      // 等待 AdminLTE 完成自身切换后同步状态
      setTimeout(function () { apply(isCollapsed()); }, 60);
    });
  }

  /* =========================================================
     内容区卡片 stagger 进场：给顶层卡片加递增延迟的 .kokuu-in

     🔴🔴 动画结束后必须回收「层叠上下文」，否则会砸掉所有弹窗！
     ---------------------------------------------------------
     原因：只要元素的 computed transform 不是字面量 none（哪怕值等价于
     单位矩阵 matrix(1,0,0,1,0,0)），浏览器就会为它创建层叠上下文。
     一旦 `.row` / `.content-wrapper` 成了层叠上下文，
     它们内部元素（含 `#modal-apply` 这种 z-index:1050 的弹窗）的
     z-index 就只能在**该上下文内部**比较；而 Bootstrap 的
     `.modal-backdrop`(1040) 挂在 <body> 上属于外层上下文
     → 遮罩反过来盖住弹窗，页面发黑、全部按钮点不动。
     （2026-10-03 线上复现：点「立即使用」后弹窗不可交互，
       elementFromPoint 命中的是 .modal-backdrop。
       z-index 数字看上去 1050 > 1040 完全正常，极难定位。）

     修法两层：
       1. motion.css 的 keyframes 改成只写 `from`、不带 fill-mode
       2. 这里在 animationend 时打 .kokuu-in-done，
          由 CSS 把 transform 钉成 none（双保险，防回归）
     ========================================================= */
  var DONE_CLASS = "kokuu-in-done";

  function markInDone(el) {
    if (el.classList.contains(DONE_CLASS)) { return; }
    el.classList.add(DONE_CLASS);
    // 顺手把自定义延迟变量也清掉，避免后续复用时串味
    el.style.removeProperty("--kokuu-in-delay");
  }

  function bindInEnd(el) {
    if (el.__kokuuInBound) { return; }
    el.__kokuuInBound = true;
    el.addEventListener("animationend", function (ev) {
      // 只认自己那条入场动画，忽略子元素冒泡上来的
      if (ev.target !== el || ev.animationName !== "kokuuAppleCardIn") { return; }
      markInDone(el);
    });
    // 兜底：万一 animationend 没触发（元素被隐藏/动画被中断），
    // 动画时长 0.6s + 最大延迟 40*0.055≈2.2s，3.5s 后无条件收尾
    setTimeout(function () { markInDone(el); }, 3500);
  }

  function staggerCards() {
    var host =
      document.querySelector(".content-wrapper") || document.body;
    var cards = host.querySelectorAll(
      ".content > .container-fluid > .card, .content > .container-fluid > .row, .d-flex.flex-wrap > .card, .d-flex.flex-wrap > a > .card"
    );
    var i = 0;
    for (var k = 0; k < cards.length; k++) {
      var el = cards[k];
      if (el.classList.contains(IN_CLASS)) { continue; }
      el.classList.add(IN_CLASS);
      // 容器级（前几个 block 卡片）给较大间隔，网格内卡片快速递增
      el.style.setProperty("--kokuu-in-delay", (i * 0.055).toFixed(3) + "s");
      bindInEnd(el);
      i++;
      if (i > 40) { i = 40; } // 防止过多累积延迟
    }
  }

  /* React 异步渲染完成后，把新出现的卡片也补上进场动画 */
  function watchForNewCards() {
    if (!("MutationObserver" in window)) {
      setTimeout(staggerCards, 400);
      return;
    }
    var host = document.querySelector(".content-wrapper") || document.body;
    var mo = new MutationObserver(function () {
      staggerCards();
    });
    mo.observe(host, { childList: true, subtree: true });
    // 观察一段时间后停止，节省开销
    setTimeout(function () { mo.disconnect(); }, 8000);
  }

  /* 自适应：大屏恢复正常边距，小屏默认折叠侧栏（Apple 移动端观感） */
  function responsive() {
    var mq = window.matchMedia("(max-width: 992px)");
    function apply() {
      // 无侧栏布局（如皮肤库 layout-top-nav）不存在折叠态，
      // 强行加 sidebar-collapse 会让 AdminLTE 布局规则错乱、挤压内容
      if (!document.querySelector(".main-sidebar")) { return; }
      if (mq.matches) {
        document.body.classList.add("sidebar-collapse");
      }
    }
    apply();
    if (mq.addEventListener) { mq.addEventListener("change", apply); }
    else if (mq.addListener) { mq.addListener(apply); }
  }

  /* ---- 深色模式切换按钮（补齐 layout-top-nav 布局的缺失）----
     内核把切换按钮放在 shared/header.twig 的 #toggle-dark-mode 占位里，
     由 darkMode.tsx 在启动时渲染 React 组件。但皮肤库（skinlib/base.twig）
     自己写了顶栏、没有 include shared.header，于是该占位不存在 → 无按钮。
     这里对无侧边栏布局补一个等价的按钮，并自行处理切换与持久化。 */
  function ensureDarkToggle() {
    // 已有（内核渲染的）则不干预
    if (document.querySelector("#toggle-dark-mode")) { return; }
    // 只对无侧边栏布局补齐；侧边栏布局由内核处理
    if (document.querySelector(".main-sidebar")) { return; }

    var bar =
      document.querySelector(".main-header .navbar-custom-menu ul.nav") ||
      document.querySelector(".main-header .navbar-nav");
    if (!bar) { return; }

    var base = (window.blessing && window.blessing.base_url) || "";
    var li = document.createElement("li");
    li.className = "nav-item kokuu-dark-toggle";
    li.id = "toggle-dark-mode";
    li.innerHTML =
      '<a class="nav-link" href="#" role="button" title="切换深色模式" aria-label="切换深色模式">' +
        '<i class="fas fa-sun" aria-hidden="true"></i>' +
      "</a>";
    // 插到用户菜单之前，避免与用户头像挤在一起
    bar.insertBefore(li, bar.firstChild);

    li.addEventListener("click", function (ev) {
      ev.preventDefault();
      var isDark = document.body.classList.toggle("dark-mode");
      var icon = li.querySelector("i");
      if (icon) {
        icon.className = "fas fa-" + (isDark ? "moon" : "sun");
      }
      // 持久化：端点为 toggle 语义，未登录时静默失败即可
      var tokenEl = document.querySelector('meta[name="csrf-token"]');
      try {
        fetch(base + "/user/dark-mode", {
          method: "PUT",
          credentials: "same-origin",
          headers: {
            "X-Requested-With": "XMLHttpRequest",
            "X-CSRF-TOKEN": tokenEl ? tokenEl.content : ""
          }
        }).catch(function () {});
      } catch (e) { /* 忽略 */ }
    });
  }

  /* 内容区整体进场动画（motion.css 的 kokuuApplePageIn）同样会残留
     transform → 创建层叠上下文。这里同样收尾。
     ⚠️ `.content-wrapper` 是**所有**弹窗的祖先，它残留 transform 的
        危害最大，必须一起处理。 */
  function settlePageIn() {
    ["content-wrapper", "content-header"].forEach(function (cls) {
      var el = document.querySelector("." + cls);
      if (!el) { return; }
      var finish = function () {
        if (!el.classList.contains(DONE_CLASS)) {
          el.classList.add(DONE_CLASS);
        }
      };
      el.addEventListener("animationend", function (ev) {
        if (ev.target !== el) { return; }
        finish();
      });
      setTimeout(finish, 1200);
    });
  }

  function mount() {
    try {
      markBody();
      initSidebar();
      settlePageIn();
      staggerCards();
      responsive();
      watchForNewCards();
      ensureDarkToggle();
    } catch (e) {
      if (window.console) { console.error("[KokuuUI]", e); }
    }
  }

  ready(mount);
})();
