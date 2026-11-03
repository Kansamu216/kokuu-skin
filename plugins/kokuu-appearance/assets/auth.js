/* =========================================================
   KOKUU 鉴权页增强（前端）
   注入到 /auth/* 页面 `</body>` 之前（DOM 已就绪）。
   不替换表单：仅加雨幕 / 纸纹背景，并把品牌行换成 KOKUU 标识，
   内核的登录/注册表单字段与提交逻辑原样保留。
   ========================================================= */
(function () {
  "use strict";

  function paint() {
    var b = window.blessing || {};
    var base = (b.base_url || "").replace(/\/+$/, "");
    var asset = base + "/plugins/kokuu-home/assets/";
    var favicon = asset + "favicon.png";

    try {
      // 站点图标换成 KOKUU
      var fi = document.querySelector(
        'link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]'
      );
      if (fi) { fi.href = favicon; }

      // 雨幕 canvas
      var rain = document.createElement("canvas");
      rain.className = "kokuu-auth-rain";
      document.body.appendChild(rain);

      // 纸纹
      var noise = document.createElement("div");
      noise.className = "kokuu-auth-noise";
      document.body.appendChild(noise);

      // 品牌行换成 KokuuSkin 纯文字标识（去掉 logo 图片，避免图标过小）
      var brand = document.querySelector(".login-logo a");
      if (brand && !brand.querySelector(".kokuu-accent")) {
        brand.innerHTML = 'Kokuu<span class="kokuu-accent">Skin</span>';
      }

      // 浏览器标签标题：把内核的 "Blessing Skin" 换成 KokuuSkin
      document.title = document.title
        .replace(/Blessing\s*Skin/gi, "KokuuSkin")
        .replace(/^\s*[-\u2013\u2014]\s*/, "")
        .replace(/\s*[-\u2013\u2014]\s*$/, "");

      initRain(rain);
    } catch (e) {
      if (window.console) { console.error("[KokuuAuth]", e); }
    }
  }

  /* 轻量雨幕（鉴权页专用，密度更低） */
  function initRain(canvas) {
    var ctx = canvas.getContext("2d");
    var dpr = window.devicePixelRatio || 1;
    var W = 0, H = 0, drops = [];

    function resize() {
      W = canvas.width = Math.floor(window.innerWidth * dpr);
      H = canvas.height = Math.floor(window.innerHeight * dpr);
      canvas.style.width = window.innerWidth + "px";
      canvas.style.height = window.innerHeight + "px";
      drops = [];
      var n = Math.floor(window.innerWidth / 9);
      for (var i = 0; i < n; i++) {
        drops.push({
          x: Math.random() * W,
          y: Math.random() * H,
          l: (Math.random() * 0.5 + 0.5) * H * 0.22,
          v: (Math.random() * 2 + 3) * dpr
        });
      }
    }
    resize();
    window.addEventListener("resize", resize);

    function frame() {
      if (!canvas.isConnected) { return; }
      ctx.clearRect(0, 0, W, H);
      ctx.strokeStyle = "rgba(15,163,163,0.5)";
      ctx.lineWidth = 1.1 * dpr;
      ctx.lineCap = "round";
      for (var i = 0; i < drops.length; i++) {
        var d = drops[i];
        ctx.beginPath();
        ctx.moveTo(d.x, d.y);
        ctx.lineTo(d.x - d.l * 0.18, d.y + d.l);
        ctx.stroke();
        d.y += d.v;
        d.x -= d.v * 0.18;
        if (d.y > H) { d.y = -d.l; d.x = Math.random() * W + d.l; }
      }
      window.requestAnimationFrame(frame);
    }
    frame();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", paint);
  } else {
    paint();
  }
})();
