/* =========================================================
   KOKUU 全站外壳增强（前端）
   注入到所有页面（request()->is('*')）。
   作用：
   1) 把站点图标统一换成 KOKUU 图标，强化品牌一致性；
   2) 把后台 / 皮肤库已有的品牌位（顶栏 .navbar-brand、侧栏 .brand-link）
      换成 KOKUU 标识（这些是服务端 Twig 渲染、在 React 根之外，安全可改）。
   首页与鉴权页由各自的脚本处理，这里对不存在的选择器自动跳过。
   ========================================================= */
(function () {
  "use strict";

  function fixFavicon(base, favicon) {
    var fi = document.querySelector(
      'link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]'
    );
    if (fi) { fi.href = favicon; }
  }

  function rebrand() {
    // 皮肤库顶栏品牌（.navbar-brand）→ 纯文字 KokuuSkin
    var nav = document.querySelector(".navbar-brand");
    if (nav && !nav.querySelector(".kokuu-accent")) {
      nav.innerHTML =
        '<span style="font-weight:800;font-size:18px;letter-spacing:.3px">' +
        'Kokuu<span class="kokuu-accent">Skin</span></span>';
    }

    // 用户中心 / 后台侧栏品牌（.brand-link）→ 纯文字 KokuuSkin
    var side = document.querySelector(".brand-link");
    if (side && !side.querySelector(".kokuu-accent")) {
      var img = side.querySelector("img.brand-image");
      side.innerHTML =
        '<span style="font-weight:800;font-size:17px;letter-spacing:.3px">' +
        'Kokuu<span class="kokuu-accent">Skin</span></span>';
      if (img) { img.style.display = "none"; }
    }
  }

  function run() {
    var b = window.blessing || {};
    var base = (b.base_url || "").replace(/\/+$/, "");
    var asset = base + "/plugins/kokuu-home/assets/";
    fixFavicon(base, asset + "favicon.png");
    rebrand();

    // 浏览器标签标题：把内核的 "Blessing Skin" 换成 KokuuSkin，并保留子页面名
    document.title = document.title
      .replace(/Blessing\s*Skin/gi, "KokuuSkin")
      .replace(/^\s*[-\u2013\u2014]\s*/, "")
      .replace(/\s*[-\u2013\u2014]\s*$/, "");
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run);
  } else {
    run();
  }
})();
