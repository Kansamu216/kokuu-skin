{{--
  提瓦特一言 · 台词管理（Blessing Skin 原生插件配置视图）

  由 PluginController@config 渲染：GET /admin/plugin/config/kokuu-quote
  插件管理页会自动显示「设置」按钮（依赖 package.json 的 config 字段）。

  说明：此视图由 view()->file() 以 Blade 渲染，内核后台布局
  （admin/base.twig）为 Twig，无法直接 extends，故自包含 HTML 骨架，
  并复用内核已发布的 AdminLTE 样式表以保持视觉一致。

  表单通过 fetch 提交到插件自身的操作端点：
      POST /plugin/kokuu-quote/manage
--}}
@php
    $__quotes = function_exists('kokuu_quote_all') ? kokuu_quote_all() : [];
    $__token = csrf_token();
    $__style = glob(public_path('app/style.' . '*' . '.css'));
    $__styleUrl = $__style ? url('/app/' . basename($__style[0])) : null;
    // 标签与元素从现有词库动态提取，避免与 quotes.php 脱节
    $__tags = array_values(array_unique(array_column($__quotes, 'tag')));
    $__els = array_values(array_filter(array_unique(array_column($__quotes, 'el'))));
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $__token }}">
    <title>提瓦特一言管理</title>
    @if ($__styleUrl)
        <link rel="stylesheet" href="{{ $__styleUrl }}">
    @endif
    <style>
        body { background: #F2F2F7; }
        .kq-wrap { max-width: 900px; margin: 0 auto; padding: 32px 20px 60px; }
        .kq-card {
            background: #fff; border: 1px solid rgba(60,60,67,.12);
            border-radius: 16px; padding: 24px 26px; margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 28px rgba(43,31,82,.06);
        }
        .kq-card h3 { margin: 0 0 4px; font-size: 19px; font-weight: 700; }
        .kq-card .kq-sub { margin: 0 0 20px; font-size: 13.5px; color: #6E6E73; }
        .kq-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .kq-field { margin-bottom: 16px; }
        .kq-field label { display: block; font-weight: 700; font-size: 13px; margin-bottom: 6px; }
        .kq-field input, .kq-field select, .kq-field textarea {
            width: 100%; font-size: 14px; color: #1C1C1E; background: #F2F2F7;
            border: 1.5px solid rgba(60,60,67,.14); border-radius: 10px;
            padding: 10px 12px; font-family: inherit;
        }
        .kq-field input:focus, .kq-field select:focus {
            outline: none; border-color: #0FA3A3; background: #fff;
            box-shadow: 0 0 0 4px rgba(15,163,163,.14);
        }
        .kq-btn {
            font-size: 14.5px; font-weight: 700; color: #fff; background: #2B1F52;
            border: none; border-radius: 999px; padding: 11px 26px; cursor: pointer;
            transition: background .2s, transform .16s;
        }
        .kq-btn:hover { background: #0FA3A3; transform: translateY(-1px); }
        .kq-btn:disabled { opacity: .6; cursor: default; transform: none; }
        .kq-btn.ghost { background: rgba(60,60,67,.08); color: #1C1C1E; }
        .kq-btn.ghost:hover { background: rgba(60,60,67,.14); }
        .kq-btn.danger { background: #FFE9E9; color: #B3261E; padding: 6px 13px; font-size: 12.5px; }
        .kq-btn.danger:hover { background: #FFD6D6; }
        .kq-table { width: 100%; border-collapse: collapse; }
        .kq-table th {
            text-align: left; font-size: 12.5px; color: #6E6E73; font-weight: 600;
            padding: 8px 10px; border-bottom: 1px solid rgba(60,60,67,.12);
        }
        .kq-table td {
            padding: 11px 10px; border-bottom: 1px solid rgba(60,60,67,.08);
            font-size: 14px; vertical-align: top;
        }
        .kq-table tr:hover td { background: rgba(60,60,67,.02); }
        .kq-pill {
            display: inline-block; font-size: 11.5px; font-weight: 700;
            padding: 3px 8px; border-radius: 7px; white-space: nowrap;
        }
        .kq-pill.tag { background: rgba(139,127,212,.16); color: #5B4BB0; }
        .kq-pill.el { background: rgba(15,163,163,.14); color: #0B7C7C; }
        .kq-pill.el.none { background: rgba(60,60,67,.08); color: #6E6E73; }
        .kq-msg { margin-left: 12px; font-size: 13px; font-weight: 600; }
        .kq-warn {
            background: #FFF4E5; border: 1px solid #FFD8A8; color: #8A5A00;
            border-radius: 12px; padding: 12px 15px; font-size: 13px;
            line-height: 1.75; margin-bottom: 20px;
        }
        .kq-back { display: inline-block; margin-top: 6px; font-size: 13.5px; color: #6E6E73; text-decoration: none; }
        .kq-back:hover { color: #0FA3A3; }
        @media (max-width: 640px) { .kq-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="kq-wrap">

    <div class="kq-card">
        <h3>提瓦特一言 · 台词管理</h3>
        <p class="kq-sub">共 {{ count($__quotes) }} 条台词。首页不显示，后台侧边栏顶部展示一条。</p>

        <div class="kq-warn">
            <strong>版权提示：</strong>内置台词均出自游戏《原神》，著作权归上海米哈游天命科技有限公司所有。
            本插件仅作个人学习与非商业展示。若你计划公开运营此站，请自行评估并取得授权，
            或将台词替换为你自己的文案。
        </div>

        <form id="kq-add-form" method="POST" action="{{ url('/plugin/kokuu-quote/manage') }}">
            <input type="hidden" name="action" value="add">
            <div class="kq-field">
                <label>台词正文 <small style="font-weight:400;color:#6E6E73">（必填）</small></label>
                <input type="text" name="text" required placeholder="例：月光永远联系着我们，不论日夜。">
            </div>
            <div class="kq-grid">
                <div class="kq-field">
                    <label>出处 / 角色</label>
                    <input type="text" name="by" placeholder="例：哥伦比娅">
                </div>
                <div class="kq-field">
                    <label>主题标签</label>
                    <select name="tag">
                        @foreach ($__tags as $__t)
                            <option value="{{ $__t }}">{{ $__t }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="kq-field">
                    <label>关联元素</label>
                    <select name="el">
                        <option value="">无</option>
                        @foreach ($__els as $__e)
                            <option value="{{ $__e }}">{{ $__e }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:flex;align-items:center;">
                <button type="submit" class="kq-btn" id="kq-add-btn">添加台词</button>
                <span class="kq-msg" id="kq-add-msg"></span>
            </div>
        </form>
    </div>

    <div class="kq-card">
        <h3>台词列表</h3>
        <p class="kq-sub">点击「删除」移除单条台词。</p>
        @if (count($__quotes) === 0)
            <p class="kq-sub">暂无台词，请在上方添加，或恢复内置库。</p>
        @else
        <table class="kq-table">
            <thead>
                <tr><th style="width:46%">台词</th><th>出处</th><th>主题</th><th>元素</th><th></th></tr>
            </thead>
            <tbody>
                @foreach ($__quotes as $__i => $__q)
                <tr>
                    <td style="font-weight:600">「{{ $__q['text'] }}」</td>
                    <td style="color:#6E6E73;white-space:nowrap">{{ $__q['by'] }}</td>
                    <td><span class="kq-pill tag">{{ $__q['tag'] }}</span></td>
                    <td><span class="kq-pill el {{ $__q['el'] === '' ? 'none' : '' }}">{{ $__q['el'] === '' ? '无' : $__q['el'] }}</span></td>
                    <td>
                        <button type="button" class="kq-btn danger kq-del"
                                data-idx="{{ $__i }}">删除</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
        <span class="kq-msg" id="kq-list-msg"></span>
    </div>

    <div class="kq-card">
        <h3>恢复内置台词库</h3>
        <p class="kq-sub">删除自定义台词文件，回到插件自带的台词（你添加的会全部丢失）。</p>
        <button type="button" class="kq-btn ghost" id="kq-reset">恢复内置台词库</button>
        <span class="kq-msg" id="kq-reset-msg"></span>
        <br>
        <a class="kq-back" href="{{ url('/admin/plugins/manage') }}">← 返回插件管理</a>
    </div>

</div>

<script>
    (function () {
        var token = document.querySelector('meta[name="csrf-token"]');
        var endpoint = @json(url('/plugin/kokuu-quote/manage'));

        function say(el, text, ok) {
            el.textContent = text;
            el.style.color = ok ? '#0B7C7C' : '#B3261E';
        }

        function post(data) {
            return fetch(endpoint, {
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

        // 添加
        var addForm = document.getElementById('kq-add-form');
        var addBtn = document.getElementById('kq-add-btn');
        var addMsg = document.getElementById('kq-add-msg');
        addForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            addBtn.disabled = true;
            say(addMsg, '提交中…', true);
            post(new FormData(addForm))
                .then(function (d) {
                    if (d && d.code === 0) { location.reload(); }
                    else { say(addMsg, '添加失败：' + ((d && d.message) || '未知错误'), false); }
                })
                .catch(function (e) { say(addMsg, '添加失败：' + e.message, false); })
                .finally(function () { addBtn.disabled = false; });
        });

        // 删除
        var listMsg = document.getElementById('kq-list-msg');
        [].forEach.call(document.querySelectorAll('.kq-del'), function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('确定删除这条台词？')) { return; }
                btn.disabled = true;
                post({ action: 'delete', idx: btn.getAttribute('data-idx') })
                    .then(function (d) {
                        if (d && d.code === 0) { location.reload(); }
                        else { say(listMsg, '删除失败', false); btn.disabled = false; }
                    })
                    .catch(function (e) { say(listMsg, '删除失败：' + e.message, false); btn.disabled = false; });
            });
        });

        // 恢复内置
        var resetBtn = document.getElementById('kq-reset');
        var resetMsg = document.getElementById('kq-reset-msg');
        resetBtn.addEventListener('click', function () {
            if (!confirm('确定恢复内置台词库？你添加的台词将全部丢失。')) { return; }
            resetBtn.disabled = true;
            post({ action: 'reset' })
                .then(function (d) {
                    if (d && d.code === 0) { location.reload(); }
                    else { say(resetMsg, '恢复失败', false); resetBtn.disabled = false; }
                })
                .catch(function (e) { say(resetMsg, '恢复失败：' + e.message, false); resetBtn.disabled = false; });
        });
    })();
</script>
</body>
</html>
