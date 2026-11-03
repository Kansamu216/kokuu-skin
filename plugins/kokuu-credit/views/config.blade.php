{{--
  KokuuSkin 论坛积分 · 配置页（Blessing Skin 原生插件配置视图）

  由 PluginController@config 渲染：
      GET /admin/plugins/config/kokuu-credit
  路径见 app/Http/Controllers/PluginController.php:22
      return $plugin->getConfigView();   // 内部是 view()->file($path)
  插件管理页会自动显示「设置」按钮（依赖 package.json 的 config 字段）。

  ⚠️⚠️ 为什么是**自包含 HTML 骨架**，而不是 @extends('admin._base')
  ------------------------------------------------------------------
  这里踩过一个真实事故，务必别再改回去：

  本视图由 `view()->file()` 直接渲染，是**独立视图**，不在内核后台
  布局的继承链里。原先写的是 `@extends('admin._base')`，而
  **`admin._base` 这个视图根本不存在**（全站唯一引用就在本文件）。
  结果：管理员点插件列表里的「设置」→
      View [admin._base] not found → 500

  之所以长期没人发现，是因为 laravel.log 里**一条错误都没有** ——
  从没有人点过这个按钮。同类问题的排查成本极高，所以：

  ① 不要用 @extends / @section，内核后台布局是 Twig，继承不过来；
  ② 骨架自包含，复用内核已发布的 AdminLTE 样式表保持视觉一致；
  ③ 表单交互靠自己这段内联 <script>，不依赖外部 credit.js。

  数据取值方式：**直接调函数取实时值**，不用 View::share 的快照。
  因为 getConfigView() 不传参，share 是插件启动那一刻的快照，
  管理员改完配置再刷新会看到旧值。函数调用每次走 options 实时读
  （options 有文件缓存，保存时已 @unlink 掉 storage/options.php）。

  ⚠️ 本页**不使用**任何 Twig 专属语法，纯 Blade。
--}}
@php
  $cfg   = function_exists('kokuu_credit_config') ? kokuu_credit_config() : [];
  $stats = function_exists('kokuu_credit_stats')  ? kokuu_credit_stats()  : [];
  $sso   = function_exists('kokuu_sso_config')    ? kokuu_sso_config()    : [];

  $__token = csrf_token();
  // 复用内核已发布的 AdminLTE 样式表，避免自己重造表单控件样式
  $__style = glob(public_path('app/style.' . '*' . '.css'));
  $__styleUrl = $__style ? url('/app/' . basename($__style[0])) : null;
  $__apple = url('/plugins/kokuu-ui/assets/apple.css');
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ $__token }}">
  <title>KokuuSkin 论坛积分 · 配置</title>
  @if ($__styleUrl)
    <link rel="stylesheet" href="{{ $__styleUrl }}">
  @endif
  {{-- 复用 kokuu-ui 的 apple 扁平化变量/字体，和站内其他页面同一套观感 --}}
  <link rel="stylesheet" href="{{ $__apple }}">
  <style>
    :root {
      --kc-bg:        #F2F2F7;
      --kc-surface:   #FFFFFF;
      --kc-ink:       #1C1C1E;
      --kc-ink-2:     #6E6E73;
      --kc-ink-3:     #A1A1A6;
      --kc-sep:       rgba(60, 60, 67, .12);
      --kc-accent:    #0FA3A3;
      --kc-accent-2:  #8B7FD4;
      --kc-ok-bg:     rgba(52, 199, 89, .12);
      --kc-ok-ink:    #1B7A38;
      --kc-warn-bg:   #FFF4E5;
      --kc-warn-ink:  #8A5A00;
      --kc-warn-bd:   #FFD8A8;
      --kc-sh:        0 1px 2px rgba(0, 0, 0, .04), 0 8px 28px rgba(43, 31, 82, .06);
    }
    body.dark-mode {
      --kc-bg:       #12131A;
      --kc-surface:  #1C1E27;
      --kc-ink:      #F2F2F7;
      --kc-ink-2:    #A1A1A6;
      --kc-ink-3:    #6E6E73;
      --kc-sep:      rgba(255, 255, 255, .12);
      --kc-ok-bg:    rgba(52, 199, 89, .16);
      --kc-ok-ink:   #4CD964;
      --kc-warn-bg:  rgba(255, 159, 10, .14);
      --kc-warn-ink: #FFB340;
      --kc-warn-bd:  rgba(255, 159, 10, .3);
      --kc-sh:       0 1px 2px rgba(0, 0, 0, .3), 0 8px 28px rgba(0, 0, 0, .35);
    }

    /* 内核 AdminLTE 会给 body 上紫渐变，这里统一盖成 Apple 浅灰底 */
    body {
      background: var(--kc-bg) !important;
      color: var(--kc-ink);
      font-family: -apple-system, BlinkMacSystemFont, "SF Pro SC", "SF Pro Text",
                   "PingFang SC", "Helvetica Neue", "Microsoft YaHei", sans-serif;
      -webkit-font-smoothing: antialiased;
    }

    .kc-wrap { max-width: 940px; margin: 0 auto; padding: 32px 20px 64px; }

    .kc-head {
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; flex-wrap: wrap; margin-bottom: 22px;
    }
    .kc-head h1 {
      margin: 0; font-size: 25px; font-weight: 700; letter-spacing: -.02em;
      color: var(--kc-ink);
    }
    .kc-head p { margin: 4px 0 0; font-size: 13.5px; color: var(--kc-ink-2); }
    .kc-back {
      font-size: 13.5px; font-weight: 600; color: var(--kc-ink-2);
      text-decoration: none; padding: 8px 14px; border-radius: 999px;
      background: rgba(60, 60, 67, .07); white-space: nowrap;
      transition: background .2s, color .2s;
    }
    .kc-back:hover { background: rgba(60, 60, 67, .14); color: var(--kc-ink); text-decoration: none; }

    .kc-card {
      background: var(--kc-surface); border: 1px solid var(--kc-sep);
      border-radius: 20px; padding: 24px 26px; margin-bottom: 20px;
      box-shadow: var(--kc-sh);
    }
    .kc-card h2 {
      margin: 0 0 4px; font-size: 18px; font-weight: 700; color: var(--kc-ink);
      display: flex; align-items: center; gap: 9px;
    }
    .kc-card h2 i { color: var(--kc-accent); font-size: 16px; }
    .kc-card .kc-sub { margin: 0 0 20px; font-size: 13px; color: var(--kc-ink-2); line-height: 1.7; }

    /* ---- 表单栅格（不依赖 AdminLTE 的 .row/.form-group，避免被覆盖） ---- */
    .kc-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 14px 18px; }
    .kc-field  { margin-bottom: 16px; }
    .kc-field > label {
      display: block; font-size: 13px; font-weight: 700;
      color: var(--kc-ink); margin-bottom: 7px;
    }
    .kc-field input[type="text"],
    .kc-field input[type="password"],
    .kc-field input[type="number"],
    .kc-field input[type="email"] {
      width: 100%; font-size: 14px; color: var(--kc-ink);
      background: var(--kc-bg); border: 1.5px solid var(--kc-sep);
      border-radius: 10px; padding: 10px 12px; font-family: inherit;
      transition: border-color .18s, box-shadow .18s, background .18s;
    }
    .kc-field input:focus {
      outline: none; border-color: var(--kc-accent);
      background: var(--kc-surface);
      box-shadow: 0 0 0 4px rgba(15, 163, 163, .14);
    }
    .kc-help { margin: 6px 0 0; font-size: 12.5px; color: var(--kc-ink-2); line-height: 1.7; }
    .kc-help code {
      background: rgba(60, 60, 67, .08); border-radius: 5px;
      padding: 1px 5px; font-size: 12px; color: var(--kc-ink);
    }

    .kc-check {
      display: flex; align-items: flex-start; gap: 10px;
      font-size: 13.5px; font-weight: 600; color: var(--kc-ink);
      margin-bottom: 6px; cursor: pointer;
    }
    .kc-check input[type="checkbox"] { margin-top: 2px; flex: 0 0 auto; }

    /* ---- 按钮 ---- */
    .kc-actions { display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin-top: 6px; }
    .kc-btn {
      font-size: 14.5px; font-weight: 700; color: #fff;
      background: linear-gradient(135deg, var(--kc-accent), var(--kc-accent-2));
      border: none; border-radius: 999px; padding: 11px 26px;
      cursor: pointer; font-family: inherit;
      box-shadow: 0 8px 20px -10px rgba(15, 163, 163, .8);
      transition: filter .2s, transform .16s;
    }
    .kc-btn:hover   { filter: brightness(1.06); transform: translateY(-1px); }
    .kc-btn:active  { transform: translateY(0); }
    .kc-btn:disabled { opacity: .55; cursor: default; transform: none; filter: none; }
    .kc-btn.ghost {
      background: rgba(60, 60, 67, .08); color: var(--kc-ink); box-shadow: none;
    }
    .kc-btn.ghost:hover { background: rgba(60, 60, 67, .15); }

    .kc-msg { font-size: 13px; font-weight: 600; margin-left: 4px; }
    .kc-msg.ok  { color: var(--kc-ok-ink); }
    .kc-msg.bad { color: #B3261E; }

    /* ---- 自检输入组 ---- */
    .kc-inline { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .kc-inline input[type="email"] {
      flex: 1 1 260px; min-width: 200px; font-size: 14px; color: var(--kc-ink);
      background: var(--kc-bg); border: 1.5px solid var(--kc-sep);
      border-radius: 10px; padding: 10px 12px; font-family: inherit;
    }
    .kc-inline input[type="email"]:focus {
      outline: none; border-color: var(--kc-accent);
      background: var(--kc-surface); box-shadow: 0 0 0 4px rgba(15, 163, 163, .14);
    }

    /* ---- 状态徽章 / 结果块 ---- */
    .kc-status {
      display: inline-flex; align-items: center; gap: 7px;
      font-size: 12.5px; font-weight: 700; padding: 4px 11px;
      border-radius: 999px; background: rgba(60, 60, 67, .08);
      color: var(--kc-ink-2);
    }
    .kc-status.ok  { background: var(--kc-ok-bg);  color: var(--kc-ok-ink); }
    .kc-status.bad { background: rgba(255, 59, 48, .13); color: #B3261E; }

    .kc-panel {
      margin-top: 14px; font-size: 13px; line-height: 1.8;
      border-radius: 12px; padding: 13px 16px;
      background: rgba(60, 60, 67, .05); color: var(--kc-ink);
      border: 1px solid var(--kc-sep);
      white-space: pre-wrap; word-break: break-word;
      display: none;
    }
    .kc-panel.show { display: block; }
    .kc-panel.ok  { background: var(--kc-ok-bg);  color: var(--kc-ok-ink);
                    border-color: rgba(52, 199, 89, .3); }
    .kc-panel.bad { background: rgba(255, 59, 48, .1); color: #B3261E;
                    border-color: rgba(255, 59, 48, .25); }

    .kc-warn {
      background: var(--kc-warn-bg); border: 1px solid var(--kc-warn-bd);
      color: var(--kc-warn-ink); border-radius: 12px;
      padding: 12px 15px; font-size: 13px; line-height: 1.8; margin-bottom: 18px;
    }

    /* ---- 最近发放列表 ---- */
    #kc-ledger { font-size: 13.5px; color: var(--kc-ink-2); line-height: 1.9; }

    /* ---- 表格 ----
       ⚠️ 这里刻意用 `.kcCfg-table` 而不是 `.kc-table`。
          credit.css 里那份 `.kc-table` 是给**流水页**（虚空深色）用的，
          写死了 #EEEDFE 文字 / #534AB7 边框。将来若有人给本页也加上
          credit.css，那些规则会让表格变成「白字白底」完全看不见。
          另起类名等于提前避坑。

       （至于 credit.js 运行时往 #kc-ledger 等容器里塞的 `.kc-table`，
        下面已在本页作用域内单独覆盖成浅色，见后。） */
    .kcCfg-table { width: 100%; border-collapse: collapse; }
    .kcCfg-table th {
      text-align: left; font-size: 12.5px; font-weight: 600;
      color: var(--kc-ink-2); padding: 8px 10px;
      border-bottom: 1px solid var(--kc-sep); white-space: nowrap;
    }
    .kcCfg-table td {
      padding: 10px; font-size: 13.5px; color: var(--kc-ink);
      border-bottom: 1px solid var(--kc-sep); vertical-align: top;
    }
    .kcCfg-table tr:last-child td { border-bottom: none; }
    .kcCfg-empty { color: var(--kc-ink-2); font-size: 13.5px; }

    /* credit.js 往 #kc-ledger / #kc-sso-links / #kc-sso-detail 里塞的是
       `.kc-table`，而 credit.css 里那份是**流水页的虚空深色**配色
       （#EEEDFE 文字 + #534AB7 边框）。在本页浅色底上会白字白底看不见。
       credit.js 是外部文件、不该为这一页改它，所以在**本页作用域内**
       把这几个容器里的 .kc-table 覆盖回浅色 Apple 观感。
       ⚠️ 只在 #kc-ledger / #kc-sso-links / #kc-sso-detail 之内覆盖，
          不要写全局 .kc-table，否则会污染流水页。 */
    #kc-ledger .kc-table,
    #kc-sso-links .kc-table,
    #kc-sso-detail .kc-table { width: 100%; border-collapse: collapse; }
    #kc-ledger .kc-table th,
    #kc-sso-links .kc-table th,
    #kc-sso-detail .kc-table th {
      text-align: left; font-size: 12.5px; font-weight: 600;
      color: var(--kc-ink-2); padding: 8px 10px;
      border-bottom: 1px solid var(--kc-sep); white-space: nowrap;
    }
    #kc-ledger .kc-table td,
    #kc-sso-links .kc-table td,
    #kc-sso-detail .kc-table td {
      padding: 10px; font-size: 13.5px; color: var(--kc-ink);
      border-bottom: 1px solid var(--kc-sep); vertical-align: top;
    }
    #kc-ledger .kc-table tr:hover td,
    #kc-sso-links .kc-table tr:hover td,
    #kc-sso-detail .kc-table tr:hover td { background: rgba(60, 60, 67, .04); }
    #kc-ledger .kc-empty,
    #kc-sso-links .kc-empty { color: var(--kc-ink-2); font-size: 13.5px; }
    #kc-sso-detail .kc-table td:first-child { color: var(--kc-ink-2); }

    /* credit.js 造的操作提示气泡：credit.css 里没有 .kc-toast 规则
       （只建了元素没写样式），不补就是一行裸文字。这里补上。 */
    .kc-toast {
      position: fixed; left: 50%; bottom: 32px; transform: translateX(-50%);
      z-index: 9999; max-width: 86vw;
      background: #1C1C1E; color: #fff; font-size: 13.5px; font-weight: 600;
      padding: 11px 20px; border-radius: 999px;
      box-shadow: 0 10px 30px -8px rgba(0, 0, 0, .5);
      animation: kcToastIn .22s cubic-bezier(.4, 0, .2, 1);
    }
    .kc-toast.ok  { background: #1B7A38; }
    .kc-toast.err { background: #B3261E; }
    @keyframes kcToastIn {
      from { opacity: 0; transform: translate(-50%, 10px); }
      to   { opacity: 1; transform: translate(-50%, 0); }
    }

    @media (max-width: 680px) {
      .kc-wrap { padding: 20px 14px 48px; }
      .kc-card { padding: 20px 18px; border-radius: 16px; }
      .kc-grid2 { grid-template-columns: 1fr; }
      .kc-head h1 { font-size: 21px; }
    }
  </style>
</head>
<body>
<div class="kc-wrap">

  <div class="kc-head">
    <div>
      <h1>KokuuSkin 论坛积分</h1>
      <p>论坛侧只加分不扣分，唯一账本是皮肤站的 <code>users.score</code>。</p>
    </div>
    <a class="kc-back" href="{{ url('/admin/plugins') }}">
      <i class="fa fa-arrow-left"></i> 返回插件列表
    </a>
  </div>

  {{-- 这个隐藏根节点承载 credit.js 需要的全部 data-* 与表单容器。
       注意：credit.js 通过 Hook 注入在 admin/* 页面上，本页 URL
       admin/plugins/config/kokuu-credit 正好命中 admin/*，所以脚本在。 --}}
  <div id="kc-root"
       data-config='@json($cfg, JSON_UNESCAPED_UNICODE)'
       data-stats='@json($stats)'
       data-sso='@json($sso, JSON_UNESCAPED_UNICODE)'
       data-url-settings="{{ url('/plugin/kokuu-credit/settings') }}"
       data-url-ledger="{{ url('/plugin/kokuu-credit/api/ledger') }}"
       data-url-selftest="{{ url('/plugin/kokuu-credit/api/selftest') }}"
       data-url-sso="{{ url('/plugin/kokuu-credit/sso') }}"
       data-url-sso-status="{{ url('/plugin/kokuu-credit/api/sso-status') }}"
       data-url-sso-links="{{ url('/plugin/kokuu-credit/api/sso-links') }}"></div>

  {{-- ============ 加分规则 ============ --}}
  <div class="kc-card">
    <h2><i class="fa fa-coins"></i> 加分规则</h2>
    <p class="kc-sub">
      论坛里发主题 / 回复 / 被赞时，由论坛扩展带 HMAC 签名调用皮肤站接口加分。
      所有分值只进不出。
    </p>

    <form id="kc-form">
      <div class="kc-field">
        <label>论坛地址</label>
        <input type="text" name="forum_url" value="{{ $cfg['forum_url'] ?? '' }}">
        <p class="kc-help">Flarum 的 <code>url</code>（config.php 里的值），用于自检时反查论坛接口。</p>
      </div>

      <div class="kc-field">
        <label>HMAC 密钥</label>
        <input type="password" name="secret" value="" autocomplete="off"
               placeholder="{{ ($cfg['secret'] ?? '') === '' ? '未设置' : '已设置（留空则不修改）' }}">
        <p class="kc-help">与论坛端扩展配置的密钥必须完全一致。留空保存不会覆盖已设置的密钥。</p>
      </div>

      <div class="kc-grid2">
        <div class="kc-field">
          <label>发起主题得分</label>
          <input type="number" name="score_thread" min="0" value="{{ $cfg['score_thread'] ?? 0 }}">
        </div>
        <div class="kc-field">
          <label>回复帖子得分</label>
          <input type="number" name="score_reply" min="0" value="{{ $cfg['score_reply'] ?? 0 }}">
        </div>
        <div class="kc-field">
          <label>帖子被赞得分</label>
          <input type="number" name="score_liked" min="0" value="{{ $cfg['score_liked'] ?? 0 }}">
        </div>
        <div class="kc-field">
          <label>单次加分上限</label>
          <input type="number" name="max_per_event" min="1" value="{{ $cfg['max_per_event'] ?? 1 }}">
        </div>
        <div class="kc-field">
          <label>每日加分上限</label>
          <input type="number" name="max_per_day" min="0" value="{{ $cfg['max_per_day'] ?? 0 }}">
        </div>
        <div class="kc-field">
          <label>签名有效期（秒）</label>
          <input type="number" name="ttl" min="30" max="3600" value="{{ $cfg['ttl'] ?? 300 }}">
        </div>
      </div>
    </form>

    <div class="kc-actions">
      <button class="kc-btn" id="kc-save"><i class="fa fa-save"></i> 保存配置</button>
      <span class="kc-msg" id="kc-save-msg"></span>
    </div>

    <div class="kc-inline" style="margin-top: 18px;">
      <input type="email" id="kc-email" placeholder="用皮肤站账号邮箱做全链路自检">
      <button class="kc-btn ghost" id="kc-selftest"><i class="fa fa-plug"></i> 自检</button>
      <span class="kc-msg" id="kc-selftest-msg"></span>
    </div>
  </div>

  {{-- ============ 最近发放 ============ --}}
  <div class="kc-card">
    <h2><i class="fa fa-list-ul"></i> 最近发放</h2>
    <p class="kc-sub">最近通过接口加分的流水，按时间倒序。</p>
    <div id="kc-ledger">加载中…</div>
  </div>

  {{-- ============ 账号互通（OAuth2） ============ --}}
  <div class="kc-card">
    <h2><i class="fa fa-link"></i> 账号互通（OAuth2）</h2>
    <p class="kc-sub">
      皮肤站作为授权服务器，Flarum 作为客户端。用户点论坛登录页的
      「使用皮肤站账号登录」后会被引导到这里，授权通过即自动登录论坛 ——
      密码始终只由皮肤站掌握，论坛侧不保存任何密码。
    </p>

    <form id="kc-sso-form">
      <label class="kc-check">
        <input type="checkbox" name="enabled" id="kc-sso-enabled"
               {{ !empty($sso['enabled']) ? 'checked' : '' }}>
        <span>启用账号互通</span>
      </label>

      <label class="kc-check" style="margin-top: 4px;">
        <input type="checkbox" name="auto_register" id="kc-sso-autoreg"
               {{ !empty($sso['auto_register']) ? 'checked' : '' }}>
        <span>首次登录自动在论坛建号</span>
      </label>
      <p class="kc-help" style="margin: 0 0 18px 26px;">
        皮肤站用户在论坛还没有同邮箱账号时，是否自动创建一个。
        关闭后只有「论坛已存在同邮箱账号」的人能登录。
        自动建的账号密码为空，无法用论坛原生登录表单独立登录。
      </p>

      <div class="kc-field">
        <label>论坛回调地址</label>
        <input type="text" name="forum_callback"
               value="{{ $sso['forum_callback'] ?? '' }}"
               placeholder="https://chat.kokuu.org/auth/kokuu/callback">
        <p class="kc-help">
          必须与 Flarum 扩展里的回调地址<b>逐字一致</b>（差一个斜杠都会报
          <code>redirect_uri mismatch</code>）。改这个地址后，OAuth 客户端的
          redirect 也会同步更新。
        </p>
      </div>
    </form>

    <div class="kc-actions">
      <button class="kc-btn" id="kc-sso-save"><i class="fa fa-save"></i> 保存互通配置</button>
      <span class="kc-status" id="kc-sso-state">客户端状态加载中…</span>
    </div>

    <div class="kc-panel" id="kc-sso-detail"></div>

    <h3 style="margin: 22px 0 10px; font-size: 15px; font-weight: 700; color: var(--kc-ink);">
      最近绑定
    </h3>
    <div id="kc-sso-links" style="font-size: 13.5px; color: var(--kc-ink-2);">加载中…</div>
  </div>

  <div class="kc-warn">
    <b>提示：</b>本页配置保存在 <code>options</code> 表（键前缀
    <code>kokuu_credit_</code> / <code>kokuu_sso_</code>）。
    保存后会自动清掉 <code>storage/options.php</code> 文件缓存，刷新即生效。
  </div>

</div>

{{--
  ⚠️⚠️ 本页的交互**必须**靠自己这段内联 <script>，不能指望外部 credit.js。

  为什么 —— 这是本文件最关键的一条经验，实测过，别再走弯路：

    插件的 credit.css / credit.js 是用 Hook::addStyleFileToPage 与
    Hook::addScriptFileToPage 注入的，而这两个钩子最终由
    app/Http/View/Composers/HeadComposer.php:83 消费，
    写进的是**内核 Twig 布局**的 <head> / 页脚。

    可是配置页走的是另一条路：
        PluginController@config
          -> $plugin->getConfigView()
             -> view()->file($path)      ← 独立 Blade 渲染，不进布局
    独立渲染 **不经过** HeadComposer，所以那两个钩子在本页**完全不生效**。

    实测证据：拿真实管理员 session 抓 /admin/plugins/config/kokuu-credit，
    HTML 里只有本文件自己写的 <link>，**一个 credit.js <script> 标签都没有**。
    （这个坑很反直觉：URL 确实命中 `admin/*`，request()->is('admin/*')
      也就真的是 true，注入钩子确实「匹配成功」了 —— 只是没人把它渲染出来。）

  所以本页的样式与脚本都自包含：
    · <style>  —— 见 <head>，配色取 kokuu-ui 的 apple 变量，与站内一致
    · <script> —— 见下方，用原生 fetch 调插件自己的 JSON 端点

  端点与字段名一览（改后端时这里要同步改）：
    POST /plugin/kokuu-credit/settings        { forum_url, secret?, score_* ..., ttl }
    POST /plugin/kokuu-credit/api/selftest    { email }  -> { code, message, forum_status }
    POST /plugin/kokuu-credit/sso             { enabled, auto_register, forum_callback }
    GET  /plugin/kokuu-credit/api/ledger      -> { code, items[{created_at,nickname,
                                                   source_label,delta,balance_after}] }
    GET  /plugin/kokuu-credit/api/sso-status  -> { code, client_ready, client_id, callback,
                                                   authorize_url, token_url, userinfo_url }
    GET  /plugin/kokuu-credit/api/sso-links   -> { code, items[{uid,nickname,forum_user_id,
                                                   last_email,last_ip,last_seen}] }

  ⚠️ 字段名以**后端实际返回**为准，别照抄旧版页面的写法：
     · sso-status 里是 client_ready，不是 ready
     · sso-links 里是 nickname/last_email/last_seen，不是 email/created_at
     · ledger 里是 created_at/nickname，不是 at/email
--}}
<script>
(function () {
  'use strict';

  var root = document.getElementById('kc-root');
  if (!root) { return; }

  var d = root.dataset;
  var token = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

  /* 统一请求封装：JSON 进 JSON 出，带 CSRF 与 AJAX 标记 */
  function req(method, url, body) {
    var opt = {
      method: method,
      credentials: 'same-origin',
      headers: {
        'Accept':           'application/json',
        'X-CSRF-TOKEN':     token,
        'X-Requested-With': 'XMLHttpRequest'
      }
    };
    if (body !== undefined && body !== null) {
      opt.headers['Content-Type'] = 'application/json';
      opt.body = JSON.stringify(body);
    }
    return fetch(url, opt).then(function (r) {
      return r.json().catch(function () {
        return { code: 1, message: 'HTTP ' + r.status };
      });
    });
  }

  function esc(s) {
    return String(s === null || s === undefined ? '' : s)
      .replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
      });
  }

  /* 顶部浮出提示（.kc-toast 样式在本页 <style> 里） */
  var toastTimer = null;
  function toast(msg, ok) {
    var el = document.querySelector('.kc-toast');
    if (!el) {
      el = document.createElement('div');
      document.body.appendChild(el);
    }
    el.className = 'kc-toast ' + (ok ? 'ok' : 'err');
    el.textContent = msg;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.remove(); }, 3200);
  }

  function table(headers, rows, emptyText) {
    if (!rows.length) { return '<div class="kc-empty">' + esc(emptyText) + '</div>'; }
    return '<table class="kc-table"><thead><tr>'
      + headers.map(function (h) { return '<th>' + esc(h) + '</th>'; }).join('')
      + '</tr></thead><tbody>'
      + rows.map(function (cells) {
          return '<tr>' + cells.map(function (c) {
            return '<td>' + (c === null || c === undefined ? '' : c) + '</td>';
          }).join('') + '</tr>';
        }).join('')
      + '</tbody></table>';
  }

  /* ============ 1. 保存加分配置 ============ */
  var saveBtn = document.getElementById('kc-save');
  if (saveBtn) {
    saveBtn.addEventListener('click', function () {
      var form = document.getElementById('kc-form');
      var data = {};
      Array.prototype.forEach.call(form.elements, function (el) {
        if (!el.name) { return; }
        data[el.name] = el.value;
      });
      // 密钥留空 = 不修改（后端也按此语义处理），不要发一个空串把它清掉
      if (data.secret === '') { delete data.secret; }

      saveBtn.disabled = true;
      req('POST', d.urlSettings, data).then(function (r) {
        toast(r.message || (r.code === 0 ? '已保存' : '保存失败'), r.code === 0);
        if (r.code === 0) { loadLedger(); }
      }).catch(function () {
        toast('请求失败', false);
      }).then(function () { saveBtn.disabled = false; });
    });
  }

  /* ============ 2. 全链路自检 ============ */
  var stBtn = document.getElementById('kc-selftest');
  if (stBtn) {
    stBtn.addEventListener('click', function () {
      var email = (document.getElementById('kc-email') || {}).value || '';
      if (!email.trim()) { toast('请填写皮肤站账号邮箱', false); return; }

      stBtn.disabled = true;
      req('POST', d.urlSelftest, { email: email.trim() }).then(function (r) {
        toast(r.message || (r.code === 0 ? '自检通过' : '自检失败'), r.code === 0);
        if (r.code === 0) { loadLedger(); }
      }).catch(function () {
        toast('请求失败', false);
      }).then(function () { stBtn.disabled = false; });
    });
  }

  /* ============ 3. 最近发放 ============ */
  var ledgerEl = document.getElementById('kc-ledger');
  function loadLedger() {
    if (!ledgerEl) { return; }
    req('GET', d.urlLedger + '?limit=10').then(function (r) {
      if (!r || r.code !== 0) { ledgerEl.innerHTML = '<div class="kc-empty">加载失败</div>'; return; }
      var items = r.items || [];
      ledgerEl.innerHTML = table(
        ['时间', '用户', '来源', '变动', '余额'],
        items.map(function (it) {
          var delta = (it.delta > 0 ? '+' : '') + it.delta;
          var cls = it.delta > 0 ? 'kc-delta-plus' : (it.delta < 0 ? 'kc-delta-minus' : 'kc-delta-zero');
          return [
            esc(it.created_at),
            esc(it.nickname),
            esc(it.source_label || it.source),
            '<span class="' + cls + '">' + esc(delta) + '</span>',
            esc(it.balance_after)
          ];
        }),
        '暂无发放记录'
      );
    }).catch(function () {
      ledgerEl.innerHTML = '<div class="kc-empty">加载失败</div>';
    });
  }
  loadLedger();

  /* ============ 4. 保存互通配置 ============ */
  var ssoSaveBtn = document.getElementById('kc-sso-save');
  if (ssoSaveBtn) {
    ssoSaveBtn.addEventListener('click', function () {
      var payload = {
        enabled:        document.getElementById('kc-sso-enabled').checked ? 1 : 0,
        auto_register:  document.getElementById('kc-sso-autoreg').checked ? 1 : 0,
        forum_callback: (document.querySelector('#kc-sso-form input[name="forum_callback"]') || {}).value || ''
      };
      ssoSaveBtn.disabled = true;
      req('POST', d.urlSso, payload).then(function (r) {
        toast(r.message || (r.code === 0 ? '已保存' : '保存失败'), r.code === 0);
        if (r.code === 0) { loadSsoStatus(); }
      }).catch(function () {
        toast('请求失败', false);
      }).then(function () { ssoSaveBtn.disabled = false; });
    });
  }

  /* ============ 5. OAuth 客户端状态 ============ */
  var stateEl = document.getElementById('kc-sso-state');
  var detailEl = document.getElementById('kc-sso-detail');
  function loadSsoStatus() {
    if (!stateEl) { return; }
    req('GET', d.urlSsoStatus).then(function (r) {
      if (!r || r.code !== 0) {
        stateEl.className = 'kc-status bad';
        stateEl.textContent = '状态查询失败';
        return;
      }
      if (!r.enabled) {
        stateEl.className = 'kc-status';
        stateEl.textContent = '互通未启用';
      } else if (!r.client_ready) {          // ⚠️ 字段名是 client_ready
        stateEl.className = 'kc-status bad';
        stateEl.textContent = 'OAuth 客户端未就绪';
      } else {
        stateEl.className = 'kc-status ok';
        stateEl.textContent = '已启用 · 客户端 ' + r.client_id;
      }

      if (!detailEl) { return; }
      var rows = [
        ['论坛回调',     r.callback || '（未设置）'],
        ['自动建号',     r.auto_register ? '是' : '否'],
        ['授权端点',     r.authorize_url || ''],
        ['令牌端点',     r.token_url || ''],
        ['用户信息端点', r.userinfo_url || ''],
        ['论坛登录入口', (r.callback || '').replace(/\/auth\/kokuu\/callback$/, '/auth/kokuu')]
      ];
      detailEl.innerHTML = table(
        ['项目', '值'],
        rows.map(function (p) { return [esc(p[0]), esc(p[1])]; }),
        ''
      );
    }).catch(function () {
      stateEl.className = 'kc-status bad';
      stateEl.textContent = '状态查询失败';
    });
  }

  /* ============ 6. 最近绑定 ============ */
  var linksEl = document.getElementById('kc-sso-links');
  function loadSsoLinks() {
    if (!linksEl) { return; }
    req('GET', d.urlSsoLinks).then(function (r) {
      if (!r || r.code !== 0) { linksEl.innerHTML = '<div class="kc-empty">加载失败</div>'; return; }
      var items = r.items || [];
      // ⚠️ 字段名：nickname / last_email / last_seen（不是 email / created_at）
      linksEl.innerHTML = table(
        ['皮肤站用户', '邮箱', '论坛用户', 'IP', '最近活跃'],
        items.map(function (it) {
          return [
            esc(it.nickname) + ' <span class="kc-event-id">uid ' + esc(it.uid) + '</span>',
            esc(it.last_email),
            it.forum_user_id === '' || it.forum_user_id === null
              ? '<span style="color:var(--kc-ink-3)">—</span>'
              : '#' + esc(it.forum_user_id),
            esc(it.last_ip),
            esc(it.last_seen)
          ];
        }),
        '还没有任何账号通过互通登录过'
      );
    }).catch(function () {
      linksEl.innerHTML = '<div class="kc-empty">加载失败</div>';
    });
  }

  loadSsoStatus();
  loadSsoLinks();
})();
</script>
</body>
</html>