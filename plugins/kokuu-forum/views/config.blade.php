{{--
  KokuuSkin 论坛聚合 · 配置页

  由 PluginController@config 渲染：GET /admin/plugins/config/kokuu-forum
  插件管理页会自动显示「设置」按钮（依赖 package.json 的 config 字段）。

  说明：此视图由 view()->file() 以 Blade 渲染，内核后台布局
  （admin/base.twig）为 Twig，无法直接 extends，故自包含 HTML 骨架，
  并复用内核已发布的 AdminLTE 样式表以保持视觉一致。

  表单通过 fetch 提交到插件自身的操作端点：
      POST /plugin/kokuu-forum/settings
--}}
@php
    $__cfg = function_exists('kokuu_forum_config') ? kokuu_forum_config() : [
        'forum_url' => 'https://chat.kokuu.org', 'menu_title' => '社区',
        'widget_limit' => 6, 'cache_ttl' => 60, 'pinned' => [],
        'preview_replies' => 2,
    ];
    // 置顶项存的是数组，表单需要逗号串
    $__pinned = is_array($__cfg['pinned'])
        ? implode(',', $__cfg['pinned'])
        : (string) $__cfg['pinned'];
    $__token = csrf_token();
    $__style = glob(public_path('app/style.' . '*' . '.css'));
    $__styleUrl = $__style ? url('/app/' . basename($__style[0])) : null;
    $__theme = glob(public_path('plugins/kokuu-home/assets/theme.*' . '.css'));
    $__themeUrl = $__theme ? url('/plugins/kokuu-home/assets/' . basename($__theme[0])) : null;
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $__token }}">
    <title>论坛聚合设置</title>
    @if ($__styleUrl)
        <link rel="stylesheet" href="{{ $__styleUrl }}">
    @endif
    @if ($__themeUrl)
        {{-- 复用 kokuu-home 的设计 token，保证两页视觉一致 --}}
        <link rel="stylesheet" href="{{ $__themeUrl }}">
    @endif
    <link rel="stylesheet" href="{{ url('/plugins/kokuu-forum/assets/thread.css') }}?v=1.2.0">
    <style>
        /* 仅补齐配置页自身的排版；配色一律走 thread.css / theme.css 的 token */
        body { background: var(--paper, #F5F3EE); }
        .kf-cfg-code { font-size: 11.5px; opacity: .75; word-break: break-all; }
        .kf-inline { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-top: 22px; }
        #kf-test-out { margin-top: 14px; font-size: 13px; line-height: 1.8; }
    </style>
</head>
<body>

<div class="kf-cfg">

    <div class="kf-cfg-card">
        <h2>论坛聚合</h2>
        <p class="kf-hint">
            把Flarum 论坛的最新讨论以卡片形式嵌进皮肤站<b>仪表盘首页</b>，
            并接管内核原来的「公告」卡片。侧边栏不做任何改动，
            「前往论坛」按钮就在卡片右上角。
            讨论列表由本站服务端代理拉取（Flarum 未开放 CORS，浏览器无法直接跨域读取），
            因此论坛暂时不可用时，皮肤站本身不会受影响。
        </p>

        <form id="kf-cfg-form">
            <div class="kf-field">
                <label for="kf-forum-url">
                    论坛地址<span class="kf-tip">Flarum 的站点根地址，不带 /api</span>
                </label>
                <input type="text" id="kf-forum-url" name="forum_url"
                       value="{{ $__cfg['forum_url'] }}" placeholder="https://chat.kokuu.org" required>
            </div>

            <div class="kf-field-row">
                <div class="kf-field">
                    <label for="kf-menu-title">卡片标题<span class="kf-tip">最多 12 字</span></label>
                    <input type="text" id="kf-menu-title" name="menu_title"
                           value="{{ $__cfg['menu_title'] }}" maxlength="12" placeholder="社区">
                </div>
                <div class="kf-field">
                    <label for="kf-widget-limit">
                        展示条数<span class="kf-tip">1 ~ 30，置顶帖不占名额</span>
                    </label>
                    <input type="number" id="kf-widget-limit" name="widget_limit"
                           value="{{ $__cfg['widget_limit'] }}" min="1" max="30" step="1">
                </div>
            </div>

            <div class="kf-field">
                <label for="kf-pinned">
                    置顶帖公告<span class="kf-tip">讨论 ID，逗号分隔，最多 10 条</span>
                </label>
                <input type="text" id="kf-pinned" name="pinned"
                       value="{{ $__pinned }}" placeholder="留空 = 不显示公告区，例如：2,5">
                <div class="kf-note">
                    填入想置顶的<b>讨论 ID</b>（不是帖子标题），这些帖会显示在卡片最上方，
                    带橙色标记与常亮竖条，等同于原来的公告位。<br>
                    为什么不让论坛自己置顶：本论坛 Flarum 未安装 pin 扩展，
                    <code>discussions</code> 表没有 <code>is_pinned</code> 列，
                    API 也拒绝 <code>sort=-isPinned</code>（实测返回 400），所以只能由皮肤站侧记录 ID。<br>
                    怎么找 ID：打开帖子，网址形如
                    <code>{{ rtrim($__cfg['forum_url'], '/') }}/d/2-kokuuskin</code>，
                    其中 <code>2</code> 就是 ID。ID 一经创建不会变，改标题也不影响。<br>
                    留空则不显示置顶区，只展示最新讨论。
                </div>
            </div>

            <div class="kf-field-row">
                <div class="kf-field">
                    <label for="kf-preview-replies">
                        评论预览条数<span class="kf-tip">0 = 只显示回复数，不展开正文</span>
                    </label>
                    <input type="number" id="kf-preview-replies" name="preview_replies"
                           value="{{ $__cfg['preview_replies'] }}" min="0" max="5" step="1">
                    <div class="kf-note" style="background:rgba(15,163,163,.08);border-left-color:#0FA3A3;">
                        每条帖子下方显示最近几条回复的正文摘要，让人一眼看出大家在聊什么，
                        而不是只有一个孤零零的回复数。只取<strong>回复</strong>，不含楼主首帖
                        （首帖内容通常和标题重复）。<br>
                        这只会多出 <strong>1 次</strong>接口请求（不是每帖一次），不会拖慢仪表盘。
                    </div>
                </div>
                <div class="kf-field">
                    <label for="kf-cache-ttl">
                        缓存秒数<span class="kf-tip">0 = 不缓存，每次都实时拉取</span>
                    </label>
                    <input type="number" id="kf-cache-ttl" name="cache_ttl"
                           value="{{ $__cfg['cache_ttl'] }}" min="0" max="3600" step="10">
                </div>
            </div>

            <div class="kf-inline">
                <button type="submit" class="kf-btn" id="kf-save">
                    <i class="fas fa-check"></i> 保存设置
                </button>
                <a class="kf-btn kf-btn-ghost" href="{{ url('/user') }}" target="_blank"
                   rel="noopener noreferrer">
                    <i class="fas fa-eye"></i> 预览仪表盘
                </a>
                <span id="kf-msg" style="font-size:13px;"></span>
            </div>
        </form>
    </div>

    <div class="kf-cfg-card">
        <h2>连通性自检</h2>
        <p class="kf-hint">
            直接请求论坛的 JSON:API，检查当前配置能否拉到讨论列表，
            并顺带验证已填写的置顶帖 ID 是否真实存在。
            这里的请求同样由服务端发起，仅用于验证，不写入任何数据。
        </p>
        <button type="button" class="kf-btn kf-btn-ghost" id="kf-test">
            <i class="fas fa-plug"></i> 测试连接
        </button>
        <div id="kf-test-out"></div>
    </div>

    <div class="kf-cfg-card">
        <h2>常见问题</h2>
        <p class="kf-hint" style="margin-bottom:10px;">
            <strong style="color:var(--ink);">仪表盘上看不到卡片？</strong><br>
            卡片是可拖拽的 widget 网格，若之前手动拖动过，它可能不在默认位置——
            拖回「右侧栏」即可。另外确认插件已启用，并清理视图缓存
            （<code class="kf-cfg-code">php artisan view:clear</code>）。
        </p>
        <p class="kf-hint" style="margin-bottom:10px;">
            <strong style="color:var(--ink);">提示「暂时无法获取论坛内容」？</strong><br>
            依次确认：① 论坛地址填对（不要带 <code class="kf-cfg-code">/api</code>）；
            ② 论坛自身可访问；③ 服务器出网正常（低配机常见 DNS 故障）。
        </p>
        <p class="kf-hint" style="margin-bottom:10px;">
            <strong style="color:var(--ink);">填了置顶 ID 但没显示置顶？</strong><br>
            多半是 ID 填错了或帖子已被删除。点上方「测试连接」会逐条核对置顶帖，
            缺失的会明确列出。
        </p>
        <p class="kf-hint" style="margin-bottom:0;">
            <strong style="color:var(--ink);">改了论坛地址还是显示旧数据？</strong><br>
            不会——缓存键包含论坛地址与置顶名单，改动后旧缓存自然失效。
            若确实没变，把缓存秒数临时设为 0 保存一次即可强制实时拉取。
        </p>
    </div>

</div>

<script>
(function () {
    var token    = document.querySelector('meta[name="csrf-token"]');
    var saveUrl   = @json(url('/plugin/kokuu-forum/settings'));
    var threadUrl = @json(url('/plugin/kokuu-forum/api/threads'));

    function say(el, text, ok) {
        el.textContent = text;
        el.style.color = ok ? '#0B7C7C' : '#B3261E';
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token ? token.content : ''
            },
            body: new URLSearchParams(data).toString()
        }).then(function (r) { return r.json(); });
    }

    // ---- 保存 ----
    var form = document.getElementById('kf-cfg-form');
    var btn  = document.getElementById('kf-save');
    var msg  = document.getElementById('kf-msg');
    form.addEventListener('submit', function (ev) {
        ev.preventDefault();

        var url = document.getElementById('kf-forum-url').value.trim();
        // 前端先拦一道：后端只接受 http/https，此处提前给出更友好的提示
        if (!/^https?:\/\//i.test(url)) {
            say(msg, '论坛地址必须以 http:// 或 https:// 开头', false);
            return;
        }

        btn.disabled = true;
        say(msg, '保存中…', true);

        post(saveUrl, {
            forum_url:    url,
            menu_title:   document.getElementById('kf-menu-title').value.trim(),
            widget_limit: document.getElementById('kf-widget-limit').value,
            cache_ttl:    document.getElementById('kf-cache-ttl').value,
            preview_replies: document.getElementById('kf-preview-replies').value,
            pinned:       document.getElementById('kf-pinned').value.trim()
        })
            .then(function (d) {
                if (d && d.code === 0) { say(msg, '已保存', true); }
                else { say(msg, '保存失败：' + ((d && d.message) || '未知错误'), false); }
            })
            .catch(function (e) { say(msg, '保存失败：' + e.message, false); })
            .finally(function () { btn.disabled = false; });
    });

    // ---- 连通性自检 ----
    var testBtn = document.getElementById('kf-test');
    var testOut = document.getElementById('kf-test-out');

    // ⚠️ 必须转义：测试输出里会拼进论坛返回的标题、昵称、论坛地址，
    //   这些全是外部内容，直接 innerHTML 就是 XSS 入口。
    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;',
                     '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    testBtn.addEventListener('click', function () {
        // limit 传 1 即可，此处只关心「能不能通」与置顶帖对不对
        testBtn.disabled = true;
        testOut.innerHTML = '<span style="color:var(--ink-45)">正在连接论坛…</span>';

        // 把配置页里填的（可能还没保存的）置顶名单一并带上做校验
        var pinned = document.getElementById('kf-pinned').value.trim();

        fetch(threadUrl + '?limit=1', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.code !== 0) {
                    testOut.innerHTML =
                        '<span style="color:#B3261E">✕ 连接失败</span>' +
                        '<div style="color:var(--ink-45); margin-top:6px">' +
                        esc(d.message || '未知错误') +
                        '<br>目标：<code>' + esc(d.forum) + '</code></div>';
                    return;
                }

                var html = '<span style="color:#0B7C7C">✓ 连接正常</span>' +
                    '<div style="color:var(--ink-45); margin-top:6px">' +
                    '目标：<code>' + esc(d.forum) + '</code><br>' +
                    '最近一条：' + (d.items[0]
                        ? esc(d.items[0].title) + '（' + d.items[0].comments + ' 回复）'
                        : '论坛暂无帖子');

                // 置顶帖核对：配置页填的 vs 接口实际能抓到的
                var want = pinned.split(/[,，、\s]+/).filter(Boolean).map(Number).filter(function (n) {
                    return n > 0;
                });
                if (want.length) {
                    var got = (d.pinned || []).map(function (it) { return parseInt(it.id, 10); });
                    var missing = want.filter(function (id) { return got.indexOf(id) < 0; });

                    html += '<br>置顶帖：';
                    if (!missing.length) {
                        html += '<span style="color:#0B7C7C">全部 ' + got.length + ' 条已生效</span>';
                    } else {
                        html += '<span style="color:#B3261E">' +
                            esc(missing.join(', ')) +
                            ' 未生效（ID 不存在，或该帖已被删除）</span>';
                    }
                }

                html += '</div>';
                testOut.innerHTML = html;
            })
            .catch(function (e) {
                testOut.innerHTML =
                    '<span style="color:#B3261E">✕ 请求失败</span>' +
                    '<div style="color:var(--ink-45); margin-top:6px">' + esc(e.message) + '</div>';
            })
            .finally(function () { testBtn.disabled = false; });
    });
})();
</script>
</body>
</html>