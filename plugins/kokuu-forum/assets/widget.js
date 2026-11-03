/**
 * KokuuSkin 论坛聚合 —— 仪表盘卡片交互
 *
 * 由 bootstrap.php 的 Hook::addScriptFileToPage 注入，只在仪表盘页加载。
 *
 * 职责只有一件事：点「刷新」时把卡片里的列表重渲染，不整页跳。
 *
 * ⚠️ 为什么数据来自本站的 /plugin/kokuu-forum/api/threads 而不是直接 fetch 论坛：
 *   那是服务端代理接口，已在 PHP 侧把 Flarum 的 JSON:API 拍平成普通 JSON。
 *   Flarum 本身不开 CORS（实测 OPTIONS 预检返回 405、无 access-control-* 头），
 *   浏览器直连必然被拦。
 *
 * ⚠️ 文案注入方式：模板里放的是 <script type="application/json">，
 *   而不是可执行 script —— 因为 include 进来的片段若被 innerHTML 注入，
 *   内联 <script> 根本不会执行。所以读它的 textContent。
 */
(function () {
  'use strict';

  var root = document.getElementById('kf-widget');
  if (!root) {
    return;
  }

  var listEl = document.getElementById('kf-widget-list');
  var refreshBtn = document.getElementById('kf-widget-refresh');
  var i18nEl = document.getElementById('kf-widget-i18n');
  if (!listEl) {
    return;
  }

  var T = {};
  if (i18nEl) {
    try {
      T = JSON.parse(i18nEl.textContent || '{}');
    } catch (e) {
      T = {};
    }
  }

  var endpoint = root.getAttribute('data-endpoint') || '';

  /**
   * 转义所有插入 HTML 的用户数据。
   * 论坛标题、昵称都是外部内容，直接拼进 innerHTML 就是 XSS 入口，
   * 这段不做转义等于把论坛里的任何用户变成攻击面。
   */
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return {
        '&': '&amp;', '<': '&lt;', '>': '&gt;',
        '"': '&quot;', "'": '&#39;'
      }[c];
    });
  }

  function icon(cls) {
    return '<i class="' + cls + '"></i> ';
  }

  /**
   * 评论预览块。
   *
   * ⚠️ 两层防护，缺一不可：
   *   1. 服务端 kokuu_forum_plain_text() 已把 contentHtml 剥成纯文本；
   *   2. 这里仍然 esc() —— 纯文本里若残留 < > &（比如正文就写了「a < b」，
   *      剥标签后仍是 "<"），不转义就会破坏结构甚至注入。
   * 双保险不是冗余：第一层防的是「HTML 当结构用」，第二层防的是
   * 「纯文本里本来就有特殊字符」。
   */
  function replies(list) {
    if (!list || !list.length) {
      return '';
    }
    var out = '<div class="kf-replies">';
    list.forEach(function (r) {
      out += '<div class="kf-reply">' +
        '<span class="kf-reply-who">' + esc(r.author) + '</span>' +
        '<span class="kf-reply-text">' + esc(r.text) + '</span>' +
      '</div>';
    });
    return out + '</div>';
  }

  /** 单行讨论。pinned 为 true 时加左侧强调条与置顶标记。 */
  function row(it, pinned) {
    var last = (it.lastPoster && it.lastPoster !== it.author)
      ? '<span>' + icon('fas fa-reply') + esc(it.lastPoster) + '</span>'
      : '';

    return '<div class="kf-row' + (pinned ? ' kf-row-pinned' : '') + '">' +
      '<div class="kf-avatar" aria-hidden="true" style="background:hsl(' +
        (parseInt(it.avatarColor, 10) || 220) + ',58%,56%);">' +
        esc(it.avatarInitial || '?') +
      '</div>' +
      '<div class="kf-body">' +
        '<a class="kf-row-title" href="' + esc(it.url) + '" target="_blank" ' +
          'rel="noopener noreferrer" title="' + esc(it.title) + '">' +
          esc(it.title) +
        '</a>' +
        '<div class="kf-meta">' +
          '<span>' + icon('fas fa-user') + esc(it.author) + '</span>' +
          last +
          '<span>' + icon('far fa-clock') + esc(it.lastPostedRel || '') + '</span>' +
        '</div>' +
      '</div>' +
      '<div class="kf-stat"><b>' + (parseInt(it.comments, 10) || 0) + '</b>' +
        '<small>' + esc(T['replies'] || '回复') + '</small></div>' +
      replies(it.replies) +
    '</div>';
  }

  /** 空态 / 失败态。与 Twig 模板里的分支保持一致的语义。 */
  function state(res) {
    var ok = res && res.code === 0;
    var forum = root.getAttribute('data-forum') || '';
    var btn = ok
      ? '<a class="kf-widget-btn kf-widget-btn-primary" href="' + esc(forum) + '/new" ' +
        'target="_blank" rel="noopener noreferrer">' + icon('fas fa-pen-nib') +
        esc(T['new_post'] || '发新帖') + '</a>'
      : '<button type="button" class="kf-widget-btn kf-widget-btn-primary" ' +
        'id="kf-widget-retry">' + icon('fas fa-rotate-right') +
        esc(T['retry'] || '重试') + '</button>';

    return '<div class="kf-state">' +
      '<i class="fas ' + (ok ? 'fa-comments' : 'fa-plug') + '"></i>' +
      '<div class="kf-state-title">' +
        esc(ok ? (T['empty'] || '论坛还没有帖子') : (T['unreachable'] || '暂时无法获取论坛内容')) +
      '</div>' +
      '<div class="kf-state-msg">' +
        (ok
          ? esc(T['empty_hint'] || '')
          : esc((res && res.message) || T['unreachable_hint'] || '') +
            '<br><code class="kf-code">' + esc(forum) + '</code>') +
      '</div>' +
      btn +
    '</div>';
  }

  function render(res) {
    if (!res) {
      listEl.innerHTML = state(null);
      bindRetry();
      return;
    }

    if (!res.items || !res.items.length) {
      listEl.innerHTML = state(res);
      bindRetry();
      return;
    }

    var html = '';

    // 置顶区优先（替代内核公告卡片）
    if (res.pinned && res.pinned.length) {
      html += '<div class="kf-widget-section">' +
        '<div class="kf-widget-label">' + icon('fas fa-thumbtack') +
          esc(T['pinned'] || '置顶') + '</div>';
      res.pinned.forEach(function (it) {
        html += row(it, true);
      });
      html += '</div>';
    }

    if (res.regular && res.regular.length) {
      html += '<div class="kf-widget-section">';
      if (res.pinned && res.pinned.length) {
        html += '<div class="kf-widget-label kf-widget-label-sub">' +
          icon('fas fa-comments') + esc(T['subtitle'] || '最新讨论') + '</div>';
      }
      res.regular.forEach(function (it) {
        html += row(it, false);
      });
      html += '</div>';
    }

    // items 非空但两个分组都空 = 后端返回了意料之外的结构。
    // 直接把 innerHTML 置空会让卡片塌下去（连「空态」提示都没有），
    // 所以兜一层失败态，至少让用户知道该点重试而不是以为页面坏了。
    if (!html) {
      html = state({ code: 1, message: T['unreachable_hint'] || '' });
    }

    listEl.innerHTML = html;
  }

  /**
   * 重试按钮每次重渲染后都是新节点，事件要重新绑。
   * 用事件委托也可以，但这里只有唯一一个按钮，直接绑更直白。
   */
  function bindRetry() {
    var retry = document.getElementById('kf-widget-retry');
    if (retry) {
      retry.addEventListener('click', load);
    }
  }

  var loading = false;

  function load() {
    if (loading || !endpoint) {
      return;
    }
    loading = true;
    listEl.classList.add('kf-loading');
    if (refreshBtn) {
      refreshBtn.disabled = true;
    }

    fetch(endpoint, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(function (r) {
        if (!r.ok) {
          throw new Error('HTTP ' + r.status);
        }
        return r.json();
      })
      .then(render)
      .catch(function () {
        // 静默失败：保留旧列表继续可读，不弹错误打断阅读。
        // 但若当前本来就是空的（故障态），给个明确的失败提示，
        // 否则用户点刷新后看到一片空白，会以为是刷新坏了。
        if (!listEl.querySelector('.kf-row')) {
          listEl.innerHTML = state({ code: 1, message: T['unreachable_hint'] || '' });
          bindRetry();
        }
      })
      .then(function () {
        loading = false;
        listEl.classList.remove('kf-loading');
        if (refreshBtn) {
          refreshBtn.disabled = false;
        }
      });
  }

  if (refreshBtn) {
    refreshBtn.addEventListener('click', load);
  }
  bindRetry();

  /*
   * 标签页在后台待久了再切回来（笔记本合盖、切窗口），
   * 论坛内容可能已经变了。挂一个可见性恢复钩子自动刷一次。
   *
   * ⚠️ 必须加时间闸门：visibilitychange 在**每次**切回都会触发，
   *   而用户一天切几百次标签很常见，无脑发请求等于自己造流量。
   *   60 秒内的重复切换直接跳过——反正 PHP 侧缓存 TTL 也是 60 秒，
   *   再快也拿不到新数据。
   */
  var lastRender = Date.now();
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      return;
    }
    if (Date.now() - lastRender < 60000) {
      return;
    }
    lastRender = Date.now();
    load();
  });
})();