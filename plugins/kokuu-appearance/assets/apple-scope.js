/* =========================================================
 * KokuuSkin · apple 作用域启用（kokuu-ui）
 *
 * 做什么：
 *   只把 `kokuu-apple` 加到 body 上，让 apple.css 的作用域规则生效。
 *
 * 为什么需要它：
 *   kokuu-ui 的 rebuild.js 也做同一件事，但它的注入范围被限定为
 *   user / admin / skinlib 三个前缀 —— 因为它还负责侧栏折叠、
 *   进场 stagger 动画、深色按钮补齐这些**有状态**的行为，
 *   在插件页上跑那些是不合适的。
 *
 *   结果：plugin/* 页面（含 /plugin/kokuu-exchange、/plugin/kokuu-credit/ledger）
 *   body 上没有 kokuu-apple，apple.css 里所有 `body.kokuu-apple xxx`
 *   一行都不生效，页面退回内核 AdminLTE 的紫渐变顶栏 + 紫渐变侧栏，
 *   和隔壁 user/admin 页的 Apple 扁平化完全不是一套东西。
 *   ⚠️ 症状极其隐蔽：能正常渲染、零报错、零 500，只是配色不对。
 *
 * ⚠️ 刻意**不**做的事：
 *   不复制 rebuild.js 的任何行为（侧栏折叠 localStorage、stagger 动画）。
 *   那些会往同一个 localStorage key 写状态，两边都跑会互相覆盖，
 *   表现为折叠状态在 user 页与插件页之间来回跳。
 *   这里只加类名，零状态、零事件监听、幂等。
 * ========================================================= */
(function () {
  "use strict";

  var BODY_CLASS = "kokuu-apple";

  function mark() {
    if (document.body && !document.body.classList.contains(BODY_CLASS)) {
      document.body.classList.add(BODY_CLASS);
    }
  }

  /*
   * 时机必须与 rebuild.js 一致（DOMContentLoaded 或立即执行）。
   * 晚于首次样式重算会闪一下紫底再跳成浅灰。
   */
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", mark);
  } else {
    mark();
  }
})();
