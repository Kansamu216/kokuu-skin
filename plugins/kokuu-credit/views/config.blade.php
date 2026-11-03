{{--
  KokuuSkin 积分 · 配置页

  由 PluginController@config 渲染：GET /admin/plugin/config/kokuu-credit
  插件管理页会自动显示「设置」按钮（依赖 package.json 的 config 字段）。

  说明：此视图由 view()->file() 以 Blade 渲染，内核后台布局（admin/base.twig）
  为 Twig，无法直接 extends，故自包含完整 HTML 骨架。

  【重构说明】
  论坛与 SSO 功能已下线，本页只保留：
    · 管理平台密钥（/api/admin/* 服务端接口鉴权用）
    · 签名有效期
    · 积分流水查看
    · 调分面板
--}}
@php
    $__cfg   = function_exists('kokuu_credit_config') ? kokuu_credit_config() : [];
    $__stats = function_exists('kokuu_credit_stats') ? kokuu_credit_stats() : [];
    $__token = csrf_token();
    $__style = glob(public_path('app/style.' . '*' . '.css'));
    $__styleUrl = $__style ? url('/app/' . basename($__style[0])) : null;
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $__token }}">
    <title>KokuuSkin 积分 · 配置</title>
    @if ($__styleUrl)
        <link rel="stylesheet" href="{{ $__styleUrl }}">
    @endif
    <style>
        body { background: #F7F5F0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif; }
        .kc-wrap { max-width: 880px; margin: 0 auto; padding: 32px 20px 60px; }
        .kc-head { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 24px; }
        .kc-head h1 { margin: 0 0 6px; font-size: 22px; font-weight: 700; color: #241A45; }
        .kc-head p { margin: 0; font-size: 13.5px; color: #6B6483; }
        .kc-back { font-size: 13px; color: #0FA3A3; text-decoration: none; white-space: nowrap; }
        .kc-back:hover { color: #241A45; }
        .kc-card {
            background: #fff; border: 1px solid rgba(43,31,82,.10);
            border-radius: 16px; padding: 24px 26px; margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 28px rgba(43,31,82,.06);
        }
        .kc-card h2 { margin: 0 0 6px; font-size: 17px; font-weight: 700; color: #241A45; display: flex; align-items: center; gap: 8px; }
        .kc-card h2 i { color: #0FA3A3; }
        .kc-sub { margin: 0 0 20px; font-size: 13px; color: #6B6483; line-height: 1.6; }
        .kc-field { margin-bottom: 18px; }
        .kc-field:last-child { margin-bottom: 0; }
        .kc-field label { display: block; font-weight: 600; font-size: 13.5px; margin-bottom: 6px; color: #241A45; }
        .kc-field input {
            width: 100%; font-size: 14px; color: #241A45; background: #FAF9F6;
            border: 1.5px solid rgba(43,31,82,.10); border-radius: 10px;
            padding: 10px 12px; font-family: inherit; transition: border-color .15s;
        }
        .kc-field input:focus { outline: none; border-color: #0FA3A3; background: #fff; }
        .kc-help { margin: 6px 0 0; font-size: 12.5px; color: #9A94AC; line-height: 1.55; }
        .kc-help code { background: #F3F1EC; padding: 1px 5px; border-radius: 4px; font-size: 12px; }
        .kc-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #0FA3A3; color: #fff; border: none; border-radius: 10px;
            padding: 11px 22px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: inherit; transition: background .15s;
        }
        .kc-btn:hover { background: #0B7C7C; }
        .kc-btn:disabled { opacity: .55; cursor: not-allowed; }
        .kc-msg { margin-left: 12px; font-size: 13.5px; }
        .kc-msg.ok { color: #2FA36B; }
        .kc-msg.err { color: #C4344E; }

        .kc-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
        .kc-stat { background: #FAF9F6; border-radius: 12px; padding: 16px; }
        .kc-stat-v { font-size: 24px; font-weight: 800; color: #241A45; line-height: 1.1; }
        .kc-stat-l { font-size: 12px; color: #9A94AC; margin-top: 4px; }

        .kc-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
        .kc-table th {
            text-align: left; padding: 10px 12px; font-size: 12px; font-weight: 700;
            color: #6B6483; background: #FAF9F6; border-bottom: 1px solid rgba(43,31,82,.10);
        }
        .kc-table td { padding: 10px 12px; border-bottom: 1px solid rgba(43,31,82,.06); color: #241A45; }
        .kc-table tr:last-child td { border-bottom: none; }
        .kc-table tr:hover td { background: #FAF9F6; }
        .kc-num { text-align: right; font-variant-numeric: tabular-nums; }
        .kc-delta-pos { color: #2FA36B; font-weight: 600; }
        .kc-delta-neg { color: #C4344E; font-weight: 600; }
        .kc-empty { text-align: center; color: #9A94AC; padding: 24px; font-size: 13px; }
        @media (max-width: 560px) { .kc-stats { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="kc-wrap">

    <div class="kc-head">
        <div>
            <h1>KokuuSkin 积分</h1>
            <p>唯一账本是皮肤站的 <code>users.score</code>，所有加减都记入 credit_ledger。</p>
        </div>
        <a class="kc-back" href="{{ url('/admin/plugins') }}">← 返回插件列表</a>
    </div>

    <div id="kc-root"
         data-config='@json($__cfg, JSON_UNESCAPED_UNICODE)'
         data-stats='@json($__stats)'
         data-url-settings="{{ url('/plugin/kokuu-credit/settings') }}"
         data-url-ledger="{{ url('/plugin/kokuu-credit/api/ledger') }}"></div>

    {{-- 统计 --}}
    <div class="kc-card">
        <h2><i class="fa fa-chart-simple"></i> 概览</h2>
        <div class="kc-stats">
            <div class="kc-stat">
                <div class="kc-stat-v" id="kc-stat-total">{{ $__stats['total'] ?? 0 }}</div>
                <div class="kc-stat-l">累计流水笔数</div>
            </div>
            <div class="kc-stat">
                <div class="kc-stat-v" id="kc-stat-today">{{ $__stats['today_events'] ?? 0 }}</div>
                <div class="kc-stat-l">今日笔数</div>
            </div>
            <div class="kc-stat">
                <div class="kc-stat-v" id="kc-stat-earned">{{ $__stats['today'] ?? 0 }}</div>
                <div class="kc-stat-l">今日发放积分</div>
            </div>
        </div>
    </div>

    {{-- 配置 --}}
    <div class="kc-card">
        <h2><i class="fa fa-gear"></i> 接口配置</h2>
        <p class="kc-sub">
            管理平台（KokuuPanel 等）通过 <code>/plugin/kokuu-credit/api/admin/*</code>
            调用调分接口时，需要与此处一致的 HMAC 密钥。
        </p>
        <form id="kc-form">
            <div class="kc-field">
                <label>管理平台密钥</label>
                <input type="password" name="admin_secret" id="kc-admin-secret"
                       placeholder="{{ !empty($__cfg['admin_secret']) ? '已设置（留空不修改）' : '未设置' }}">
                <p class="kc-help">
                    留空保存不会覆盖已设置的密钥。与调用方（如 KokuuPanel）配置的密钥必须完全一致。
                </p>
            </div>
            <div class="kc-field">
                <label>签名有效期（秒）</label>
                <input type="number" name="ttl" id="kc-ttl"
                       value="{{ $__cfg['ttl'] ?? 300 }}" min="30" max="3600">
                <p class="kc-help">
                    请求中时间戳超出该范围即拒绝，防止重放攻击。建议 300 秒。
                </p>
            </div>
            <div style="margin-top: 20px;">
                <button type="submit" class="kc-btn" id="kc-save">保存配置</button>
                <span class="kc-msg" id="kc-save-msg"></span>
            </div>
        </form>
    </div>

    {{-- 流水 --}}
    <div class="kc-card">
        <h2><i class="fa fa-list-ul"></i> 积分流水</h2>
        <p class="kc-sub">最近 50 条。每笔都有唯一 event_id，重复请求不会重复入账。</p>
        <div id="kc-ledger">
            <div class="kc-empty">加载中…</div>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';
    var root = document.getElementById('kc-root');
    var token = document.querySelector('meta[name="csrf-token"]').content;
    var urlSettings = root.dataset.urlSettings;
    var urlLedger = root.dataset.urlLedger;

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        }).then(function (r) { return r.json(); });
    }

    // ---- 保存 ----
    document.getElementById('kc-form').addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('kc-save');
        var msg = document.getElementById('kc-save-msg');
        btn.disabled = true;
        post(urlSettings, {
            admin_secret: document.getElementById('kc-admin-secret').value,
            ttl: document.getElementById('kc-ttl').value
        }).then(function (r) {
            msg.textContent = r.message || '已保存';
            msg.className = 'kc-msg ' + (r.code === 0 ? 'ok' : 'err');
            document.getElementById('kc-admin-secret').value = '';
        }).catch(function () {
            msg.textContent = '保存失败';
            msg.className = 'kc-msg err';
        }).finally(function () {
            btn.disabled = false;
            setTimeout(function () { msg.textContent = ''; }, 4000);
        });
    });

    // ---- 流水加载 ----
    function loadLedger() {
        var el = document.getElementById('kc-ledger');
        fetch(urlLedger + '?limit=50', {
            headers: { 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (res) {
            if (res.code !== 0 || !res.items || !res.items.length) {
                el.innerHTML = '<div class="kc-empty">暂无流水记录</div>';
                return;
            }
            var rows = res.items.map(function (it) {
                var cls = it.delta > 0 ? 'kc-delta-pos' : (it.delta < 0 ? 'kc-delta-neg' : '');
                var sign = it.delta > 0 ? '+' : '';
                return '<tr>' +
                    '<td>' + (it.nickname || '—') + '</td>' +
                    '<td class="' + cls + ' kc-num">' + sign + it.delta + '</td>' +
                    '<td class="kc-num">' + it.balance_after + '</td>' +
                    '<td>' + (it.source_label || it.source) + '</td>' +
                    '<td>' + (it.note || '') + '</td>' +
                    '<td>' + (it.created_at || '') + '</td>' +
                    '</tr>';
            }).join('');
            el.innerHTML = '<table class="kc-table"><thead><tr>' +
                '<th>用户</th><th class="kc-num">变化</th><th class="kc-num">余额</th>' +
                '<th>来源</th><th>备注</th><th>时间</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table>';
        }).catch(function () {
            el.innerHTML = '<div class="kc-empty">加载失败</div>';
        });
    }
    loadLedger();
})();
</script>
</body>
</html>

