{{--
  KokuuSkin 外观设置（Blessing Skin 原生插件配置视图）

  由 PluginController@config 渲染：GET /admin/plugin/config/kokuu-appearance

  合并自原 kokuu-home（首页文案）与 kokuu-quote（台词管理）两个配置页，
  以标签页形式组织。kokuu-ui 原本没有配置页。

  此视图由 view()->file() 以 Blade 渲染，而内核后台布局（admin/base.twig）
  是 Twig，无法直接 extends。故此处自包含完整 HTML 骨架，
  并复用内核已发布的 AdminLTE 样式表，保证视觉一致。

  表单提交端点：
      POST /plugin/kokuu-appearance/settings        首页配置
      POST /plugin/kokuu-appearance/quote/manage    台词增删
--}}
@php
    $__config = function_exists('kokuu_appearance_config') ? kokuu_appearance_config() : [];
    $__quotes = function_exists('kokuu_quote_all') ? kokuu_quote_all() : [];
    $__fields = [
        ['key' => 'hero_title', 'label' => '大标题', 'type' => 'text',
         'hint' => '首页最上方的大字', 'ph' => 'KokuuSkin'],
        ['key' => 'tagline', 'label' => '一句话标语', 'type' => 'text',
         'hint' => '大标题下方的短句', 'ph' => '让每一次登录，都落在正确的像素上'],
        ['key' => 'intro', 'label' => '站点介绍', 'type' => 'textarea',
         'hint' => '首屏正文介绍', 'ph' => ''],
        ['key' => 'hero_background', 'label' => '首页主视觉背景', 'type' => 'text',
         'hint' => '填站内路径或 https 图片地址；留空使用 Minecraft 场景轮播。建议 16:9 横图，角色靠右，左侧留文案空间。',
         'ph' => '/plugins/kokuu-appearance/assets/hero-fantasy-mc.webp'],
        ['key' => 'hero_background_2', 'label' => '轮播背景 2', 'type' => 'text',
         'hint' => '留空则暂用雪原占位图；后续可填第二张生成图路径。',
         'ph' => '/plugins/kokuu-appearance/assets/hero-fantasy-mc-2.jpg'],
        ['key' => 'hero_background_3', 'label' => '轮播背景 3', 'type' => 'text',
         'hint' => '留空则暂用丛林占位图；后续可填第三张生成图路径。',
         'ph' => '/plugins/kokuu-appearance/assets/hero-fantasy-mc-3.webp'],
        ['key' => 'hero_image', 'label' => '旧版单张图片（兼容）', 'type' => 'text',
         'hint' => '旧设置迁移兼容；如配置，会按单图模式显示。',
         'ph' => '留空'],
        ['key' => 'footer_note', 'label' => '页脚一句话', 'type' => 'text',
         'hint' => '品牌名下方的那句话', 'ph' => '像素有形，月光无界'],
        ['key' => 'contact_email', 'label' => '联系邮箱', 'type' => 'text',
         'hint' => '页脚「联系」链接的地址', 'ph' => 'hi@kokuu.org'],
    ];
    $__token = csrf_token();
    $__style = glob(public_path('app/style.' . '*' . '.css'));
    $__styleUrl = $__style ? url('/app/' . basename($__style[0])) : null;
    $__tags = array_values(array_unique(array_column($__quotes, 'tag')));
    $__els = array_values(array_filter(array_unique(array_column($__quotes, 'el'))));
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $__token }}">
    <title>KokuuSkin 外观设置</title>
    @if ($__styleUrl)
        <link rel="stylesheet" href="{{ $__styleUrl }}">
    @endif
    <style>
        body { background: #F2F2F7; }
        .ka-wrap { max-width: 900px; margin: 0 auto; padding: 32px 20px 60px; }

        /* ---- 标签页 ---- */
        .ka-tabs {
            display: flex; gap: 6px; margin-bottom: 22px;
            background: rgba(120,120,128,.10); padding: 4px; border-radius: 12px;
        }
        .ka-tab {
            flex: 1; text-align: center; padding: 9px 16px; border-radius: 9px;
            font-size: 14px; font-weight: 600; color: #6E6E73; cursor: pointer;
            border: none; background: transparent; font-family: inherit;
            transition: all .18s ease;
        }
        .ka-tab:hover { color: #1C1C1E; }
        .ka-tab.active { background: #fff; color: #1C1C1E; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .ka-panel { display: none; }
        .ka-panel.active { display: block; }

        .ka-card {
            background: #fff; border: 1px solid rgba(60,60,67,.12);
            border-radius: 16px; padding: 26px 28px; margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 28px rgba(43,31,82,.06);
        }
        .ka-card h3 { margin: 0 0 4px; font-size: 19px; font-weight: 700; }
        .ka-card .ka-sub { margin: 0 0 22px; font-size: 13.5px; color: #6E6E73; }
        .ka-field { margin-bottom: 18px; }
        .ka-field:last-child { margin-bottom: 0; }
        .ka-field label { display: block; font-weight: 700; font-size: 13.5px; margin-bottom: 6px; }
        .ka-field label small { display: block; font-weight: 400; color: #6E6E73; margin-top: 2px; }
        .ka-field input, .ka-field textarea {
            width: 100%; font-size: 14px; color: #1C1C1E; background: #F2F2F7;
            border: 1.5px solid rgba(60,60,67,.14); border-radius: 10px;
            padding: 10px 12px; font-family: inherit; transition: border-color .15s;
        }
        .ka-field input:focus, .ka-field textarea:focus {
            outline: none; border-color: #0071E3; background: #fff;
        }
        .ka-btn {
            display: inline-flex; align-items: center; gap: 6px;
            background: #0071E3; color: #fff; border: none; border-radius: 10px;
            padding: 11px 22px; font-size: 14px; font-weight: 600;
            cursor: pointer; font-family: inherit; transition: background .15s;
        }
        .ka-btn:hover { background: #0062C4; }
        .ka-btn:disabled { opacity: .55; cursor: not-allowed; }
        .ka-btn-ghost { background: #E8E8ED; color: #1C1C1E; }
        .ka-btn-ghost:hover { background: #DCDCE1; }
        .ka-msg { margin-left: 12px; font-size: 13.5px; }
        .ka-msg.ok { color: #248A3D; }
        .ka-msg.err { color: #D70015; }

        .ka-warn {
            background: #FFF8E1; border: 1px solid #F5D98B; border-radius: 12px;
            padding: 14px 16px; margin-bottom: 20px; font-size: 13px; color: #7A5C00;
            line-height: 1.65;
        }
        .ka-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .ka-list { margin-top: 18px; }
        .ka-row {
            display: flex; align-items: center; gap: 12px;
            padding: 11px 14px; border-radius: 10px; background: #F9F9FB;
            margin-bottom: 7px; font-size: 13.5px;
        }
        .ka-row .ka-text { flex: 1; color: #1C1C1E; }
        .ka-row .ka-meta { font-size: 12px; color: #8E8E93; white-space: nowrap; }
        .ka-row .ka-del {
            background: transparent; border: none; color: #D70015;
            cursor: pointer; font-size: 13px; font-family: inherit; padding: 2px 6px;
        }
        .ka-row .ka-del:hover { text-decoration: underline; }
        .ka-empty { color: #8E8E93; font-size: 13.5px; padding: 12px 0; }
        @media (max-width: 640px) {
            .ka-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="ka-wrap">

    <div class="ka-tabs">
        <button type="button" class="ka-tab active" data-panel="home">首页</button>
        <button type="button" class="ka-tab" data-panel="quote">提瓦特一言</button>
    </div>

    {-- ==================== 首页配置 ==================== --}
    <div class="ka-panel active" id="panel-home">
        <div class="ka-card">
            <h3>KokuuSkin 首页设置</h3>
            <p class="ka-sub">填写表单保存后，首页立即使用你的文案。</p>

            <form id="ka-home-form" method="POST" action="{{ url('/plugin/kokuu-appearance/settings') }}">
                @foreach ($__fields as $__f)
                    <div class="ka-field">
                        <label for="ka-{{ $__f['key'] }}">
                            {{ $__f['label'] }}
                            <small>{{ $__f['hint'] }}</small>
                        </label>
                        @if ($__f['type'] === 'textarea')
                            <textarea id="ka-{{ $__f['key'] }}" name="{{ $__f['key'] }}"
                                      rows="3" placeholder="{{ $__f['ph'] }}">{{ $__config[$__f['key']] ?? '' }}</textarea>
                        @else
                            <input type="text" id="ka-{{ $__f['key'] }}" name="{{ $__f['key'] }}"
                                   value="{{ $__config[$__f['key']] ?? '' }}"
                                   placeholder="{{ $__f['ph'] }}">
                        @endif
                    </div>
                @endforeach

                <div style="margin-top:22px">
                    <button type="submit" class="ka-btn" id="ka-home-save">保存设置</button>
                    <span class="ka-msg" id="ka-home-msg"></span>
                </div>
            </form>
        </div>
    </div>

    {-- ==================== 一言管理 ==================== --}
    <div class="ka-panel" id="panel-quote">
        <div class="ka-card">
            <h3>提瓦特一言 · 台词管理</h3>
            <p class="ka-sub">共 {{ count($__quotes) }} 条台词。首页不显示，后台侧边栏顶部展示一条。</p>

            <div class="ka-warn">
                <strong>版权提示：</strong>内置台词均出自游戏《原神》，著作权归上海米哈游天命科技有限公司所有。
                本插件仅作个人学习与非商业展示。若你计划公开运营此站，请自行评估并取得授权，
                或将台词替换为你自己的文案。
            </div>

            <form id="ka-quote-add-form" method="POST" action="{{ url('/plugin/kokuu-appearance/quote/manage') }}">
                <input type="hidden" name="action" value="add">
                <div class="ka-field">
                    <label>台词正文 <small style="font-weight:400;color:#6E6E73">（必填）</small></label>
                    <input type="text" name="text" required placeholder="例：月光永远联系着我们，不论日夜。">
                </div>
                <div class="ka-grid">
                    <div class="ka-field">
                        <label>出处</label>
                        <input type="text" name="by" placeholder="例：哥伦比娅">
                    </div>
                    <div class="ka-field">
                        <label>分类</label>
                        <input type="text" name="tag" list="ka-tags" placeholder="例：日常">
                        <datalist id="ka-tags">
                            @foreach ($__tags as $__t)
                                <option value="{{ $__t }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                    <div class="ka-field">
                        <label>元素</label>
                        <input type="text" name="el" list="ka-els" placeholder="例：月">
                        <datalist id="ka-els">
                            @foreach ($__els as $__e)
                                <option value="{{ $__e }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                </div>
                <div style="margin-top:6px">
                    <button type="submit" class="ka-btn" id="ka-quote-add">添加台词</button>
                    <span class="ka-msg" id="ka-quote-msg"></span>
                </div>
            </form>

            <div class="ka-list" id="ka-quote-list">
                @forelse ($__quotes as $__i => $__q)
                    <div class="ka-row">
                        <span class="ka-text">{{ $__q['text'] }}</span>
                        <span class="ka-meta">{{ $__q['by'] }} · {{ $__q['tag'] }}</span>
                        <button type="button" class="ka-del" data-idx="{{ $__i }}">删除</button>
                    </div>
                @empty
                    <div class="ka-empty">暂无台词。</div>
                @endforelse
            </div>

            <div style="margin-top:16px">
                <button type="button" class="ka-btn ka-btn-ghost" id="ka-quote-reset">恢复内置台词库</button>
            </div>
        </div>
    </div>

</div>

<script>
(function () {
    'use strict';
    var token = document.querySelector('meta[name="csrf-token"]').content;

    // ---- 标签页切换 ----
    document.querySelectorAll('.ka-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.ka-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.ka-panel').forEach(function (p) { p.classList.remove('active'); });
            tab.classList.add('active');
            document.getElementById('panel-' + tab.dataset.panel).classList.add('active');
        });
    });

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

    function flash(el, text, ok) {
        el.textContent = text;
        el.className = 'ka-msg ' + (ok ? 'ok' : 'err');
        setTimeout(function () { el.textContent = ''; }, 4000);
    }

    // ---- 首页保存 ----
    var homeForm = document.getElementById('ka-home-form');
    homeForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('ka-home-save');
        var msg = document.getElementById('ka-home-msg');
        var data = {};
        new FormData(homeForm).forEach(function (v, k) { data[k] = v; });
        btn.disabled = true;
        post('{{ url('/plugin/kokuu-appearance/settings') }}', data)
            .then(function (r) { flash(msg, r.message || '已保存', r.code === 0); })
            .catch(function () { flash(msg, '保存失败', false); })
            .finally(function () { btn.disabled = false; });
    });

    // ---- 添加台词 ----
    var addForm = document.getElementById('ka-quote-add-form');
    addForm.addEventListener('submit', function (e) {
        e.preventDefault();
        var btn = document.getElementById('ka-quote-add');
        var msg = document.getElementById('ka-quote-msg');
        var data = {};
        new FormData(addForm).forEach(function (v, k) { data[k] = v; });
        btn.disabled = true;
        post('{{ url('/plugin/kokuu-appearance/quote/manage') }}', data)
            .then(function (r) {
                flash(msg, r.message || '', r.code === 0);
                if (r.code === 0) { setTimeout(function () { location.reload(); }, 600); }
            })
            .catch(function () { flash(msg, '添加失败', false); })
            .finally(function () { btn.disabled = false; });
    });

    // ---- 删除台词 ----
    document.querySelectorAll('.ka-del').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('确定删除这条台词？')) return;
            post('{{ url('/plugin/kokuu-appearance/quote/manage') }}', {
                action: 'delete',
                idx: btn.dataset.idx
            }).then(function (r) {
                if (r.code === 0) { btn.closest('.ka-row').remove(); }
                else { alert(r.message || '删除失败'); }
            });
        });
    });

    // ---- 恢复内置 ----
    document.getElementById('ka-quote-reset').addEventListener('click', function () {
        if (!confirm('恢复内置台词库？自定义台词将被清除。')) return;
        post('{{ url('/plugin/kokuu-appearance/quote/manage') }}', { action: 'reset' })
            .then(function (r) {
                alert(r.message || '已恢复');
                if (r.code === 0) location.reload();
            });
    });
})();
</script>
</body>
</html>
