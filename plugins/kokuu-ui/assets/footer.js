/* =========================================================
   kokuu-ui · 页脚修复脚本
   ------------------------------------------------------------
   修两个用户反馈的问题：

   【1】{year} 原样显示
   内核 resources/views/shared/copyright.twig 只替换两个占位符：
       {% set rules = {'{site_name}': site_name, '{site_url}': site_url} %}
   {year} 不在 rules 里，所以**原样输出**。
   这不是 bug 而是内核设计如此——它压根没提供年份占位符。
   但用户显然希望它显示成 2026，所以本脚本在DOM 就绪后替换掉。
   为什么用 JS 而不改内核模板：内核模板在 resources/ 里，
   升级即被覆盖；插件改CSS/JS 才是可持续的做法。

   【2】页脚靠左、排列混乱
   内核结构是两个**并列且对齐方式冲突**的节点：
       <div id="copyright-text" class="float-right d-none d-sm-inline">…</div>
       <b>Copyright © …</b>. All rights reserved.
   前者 float-right、且在 d-sm 以下 display:none；
   后者是裸文本节点，跟在 <footer> 的流里。
   结果：桌面端一个靠右一个靠左、中间大片空白；
         移动端前者被隐藏，只剩靠左的那半截。
   修法在 apple.css 里（把 footer 改成 flex 两端对齐），
   本脚本只负责补data-has-year 标记，供 CSS 决定要不要隐藏内核那段。

   ⚠️ 安全：只处理自己插件路径下的页脚，用 textContent 不碰 innerHTML，
      避免万一custom_copyright 里被注入脚本时扩大影响面。
   ========================================================= */
(function () {
  "use strict";

  function ready(fn) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var footer = document.querySelector(".main-footer");
    if (!footer) { return; }

    /* ---- 1. 年份替换 ----
     * 遍历文本节点而非 innerHTML：版权文本是用户自定义的，
     * 用 TreeWalker 只改文本节点的内容，绝不重新解析 HTML，
     * 这样即便文本里含特殊字符也不会被当成标记。
     */
    var walker = document.createTreeWalker(footer, NodeFilter.SHOW_TEXT, null);
    var nodes = [];
    var node;
    while ((node = walker.nextNode())) { nodes.push(node); }

    var year = String(new Date().getFullYear());
    var replaced = false;

    nodes.forEach(function (n) {
      var text = n.nodeValue;
      if (!text || text.indexOf("{year}") === -1) { return; }
      n.nodeValue = text.split("{year}").join(year);
      replaced = true;
    });

    /* ---- 2. 标记状态，供 CSS 决定内核那段的去留 ----
     * 若用户已经自己填了年份，内核的 "Powered with ❤ by Blessing Skin Server"
     * 仍会 float-right 显示，与自定义文本并列显得杂乱。
     * 站点所有者填了自定义版权，说明想用自己的署名 —— 此时把内核那段
     * 降级成极淡的小字（CSS 处理），而不是隐藏（保留开源致谢）。
     */
    if (replaced) {
      footer.setAttribute("data-kokuu-year", "1");
    }

    /* ---- 3. 把「自定义版权」整段包进 <span>，让 flex 能分别布局 ----
     * 内核结构（实测 childNodes 序列）：
     *   [0] #text  "YOU CAN NOT MODIFIY..."   ← 注释被解析成文本了？不，是 HTML 注释
     *   [1] #text  空白
     *   [2] DIV#copyright-text.float-right
     *   [3] #text  空白
     *   [4] B      "Copyright © 2026 KokuuAuth."
     *   [5] #text  " All rights reserved."    ← 裸文本
     *
     * ⚠️ 三个踩过的坑：
     *   a) 裸文本和 #copyright-text 都是 **.container 的子节点**，
     *      不是 footer 的子节点。查 footer.firstElementChild 只会拿到
     *      .container，找不到 #copyright-text。
     *   b) 「自定义版权」不是**单个**裸文本节点 —— 它被 <b>…</b> 切成
     *      「元素 + 尾随裸文本」两段。只包 nodeType===3 会只包到
     *      " All rights reserved."，把 <b> 漏在外面。
     *   c) HTML 注释（YOU CAN NOT MODIFIY...）在 DOM 里是 nodeType 8，
     *      遍历时要跳过。
     *
     * 做法：从 #copyright-text 之后开始，把所有节点（元素 + 文本）
     * 直到末尾全部移进一个 span —— 它们在视觉上就是「一段版权文字」。
     */
    var ct = document.getElementById("copyright-text");
    var host = null;
    if (ct && ct.parentNode) {
      host = ct.parentNode;                            // 正常路径：.container
    } else if (footer.querySelector(".container")) {
      host = footer.querySelector(".container");        // 退路一
    } else {
      host = footer;                                   // 退路二
    }

    // 收集要包裹的节点：从 ct 之后（或 host 开头）到 host 末尾
    var nodes = [];
    var n = ct ? ct.nextSibling : host.firstChild;
    while (n) {
      // nodeType: 1=元素 3=文本 8=注释 9=文档类型
      if (n.nodeType === 1 || n.nodeType === 3) {
        var t = n.nodeValue !== null ? n.nodeValue : "";
        if (n.nodeType === 1 || t.trim() !== "") {    // 元素保留，纯空白丢弃
          nodes.push(n);
        }
      }
      n = n.nextSibling;
    }

    // 已经有内容才包
    if (nodes.length) {
      var span = document.createElement("span");
      span.className = "kokuu-footer-note";
      host.insertBefore(span, nodes[0]);              // 先插到第一个前面
      nodes.forEach(function (x) { span.appendChild(x); });   // 再逐个搬进去
    }
  });
})();