{{--
  KokuuSkin 首页配置（Blessing Skin 原生插件配置视图）

  由 PluginController@config 渲染：GET /admin/plugin/config/kokuu-home
  插件管理页会自动显示「设置」按钮（依赖 package.json 的 config 字段）。

  说明：此视图由 view()->file() 以 Blade 渲染，而内核后台布局
  （admin/base.twig）是 Twig，无法直接 extends。故此处自包含完整
  HTML 骨架，并复用内核已发布的 AdminLTE 样式表，保证视觉一致。

  表单通过 fetch 提交到插件自身的保存端点：
      POST /plugin/kokuu-home/settings
--}}
@php
    $__config = function_exists('kokuu_home_config') ? kokuu_home_config() : [];
    $__fields = [
        ['key' => 'hero_title', 'label' => '大标题', 'type' => 'text',
         'hint' => '首页最上方的大字', 'ph' => 'KokuuSkin'],
        ['key' => 'tagline', 'label' => '一句话标语', 'type' => 'text',
         'hint' => '大标题下方的短句', 'ph' => '让每一次登录，都落在正确的像素上'],
        ['key' => 'intro', 'label' => '站点介绍', 'type' => 'textarea',
         'hint' => '首屏正文介绍', 'ph' => ''],
        ['key' => 'hero_image', 'label' => '右侧图片', 'type' => 'text',
         'hint' => '留空则使用内置的 6 张 MC 场景轮播；可填站内路径或 http(s) 外链',
         'ph' => '/plugins/kokuu-home/assets/scenes/01-mineshaft.png'],
        ['key' => 'footer_note', 'label' => '页脚一句话', 'type' => 'text',
         'hint' => '品牌名下方的那句话', 'ph' => '像素有形，月光无界'],
        ['key' => 'contact_email', 'label' => '联系邮箱', 'type' => 'text',
         'hint' => '页脚「联系」链接的地址', 'ph' => 'hi@kokuu.org'],
    ];
    $__token = csrf_token();
    // 动态解析内核样式表实际文件名（带内容哈希，随构建变化，不可硬编码）
    $__style = glob(public_path('app/style.' . '*' . '.css'));
    $__styleUrl = $__style ? url('/app/' . basename($__style[0])) : null;
@endphp
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $__token }}">
    <title>KokuuSkin 首页设置</title>
    {{-- 复用内核已编译的 AdminLTE / Bootstrap 样式，保证与后台一致 --}}
    @if ($__styleUrl)
        <link rel="stylesheet" href="{{ $__styleUrl }}">
    @endif
    <style>
        body { background: #F2F2F7; }
        .kk-wrap { max-width: 820px; margin: 0 auto; padding: 32px 20px 60px; }
        .kk-card {
            background: #fff; border: 1px solid rgba(60,60,67,.12);
            border-radius: 16px; padding: 26px 28px; margin-bottom: 20px;
            box-shadow: 0 1px 2px rgba(0,0,0,.04), 0 8px 28px rgba(43,31,82,.06);
        }
        .kk-card h3 { margin: 0 0 4px; font-size: 19px; font-weight: 700; }
        .kk-card .kk-sub { margin: 0 0 22px; font-size: 13.5px; color: #6E6E73; }
        .kk-field { margin-bottom: 18px; }
        .kk-field:last-child { margin-bottom: 0; }
        .kk-field label { display: block; font-weight: 700; font-size: 13.5px; margin-bottom: 6px; }
        .kk-field label small { display: block; font-weight: 400; color: #6E6E73; margin-top: 2px; }
        .kk-field input, .kk-field textarea {
            width: 100%; font-size: 14.5px; color: #1C1C1E;
            background: #F2F2F7; border: 1.5px solid rgba(60,60,67,.14);
            border-radius: 10px; padding: 10px 13px;
            font-family: inherit; transition: border-color .15s, box-shadow .15s;
        }
        .kk-field textarea { resize: vertical; min-height: 80px; line-height: 1.7; }
        .kk-field input:focus, .kk-field textarea:focus {
            outline: none; border-color: #0FA3A3; background: #fff;
            box-shadow: 0 0 0 4px rgba(15,163,163,.14);
        }
        .kk-btn {
            font-size: 15px; font-weight: 700; color: #fff; background: #2B1F52;
            border: none; border-radius: 999px; padding: 12px 30px; cursor: pointer;
            transition: background .2s, transform .16s;
        }
        .kk-btn:hover { background: #0FA3A3; transform: translateY(-1px); }
        .kk-btn:active { transform: scale(.97); }
        .kk-btn:disabled { opacity: .6; cursor: default; transform: none; }
        .kk-msg { margin-left: 14px; font-size: 13.5px; font-weight: 600; }
        .kk-note { margin: 0; font-size: 13.5px; color: #6E6E73; line-height: 1.9; }
        .kk-note code {
            background: rgba(43,31,82,.07); padding: 1px 6px;
            border-radius: 5px; font-size: 12.5px;
        }
        .kk-back { display: inline-block; margin-top: 8px; font-size: 13.5px; color: #6E6E73; text-decoration: none; }
        .kk-back:hover { color: #0FA3A3; }
    </style>
</head>
<body>
<div class="kk-wrap">

    <div class="kk-card">
        <h3>KokuuSkin 首页设置</h3>
        <p class="kk-sub">填写表单保存后，首页立即使用你的文案。</p>

        <form id="kk-form" method="POST" action="{{ url('/plugin/kokuu-home/settings') }}">
            @foreach ($__fields as $__f)
                <div class="kk-field">
                    <label for="kk-{{ $__f['key'] }}">
                        {{ $__f['label'] }}
                        <small>{{ $__f['hint'] }}</small>
                    </label>
                    @if ($__f['type'] === 'textarea')
                        <textarea id="kk-{{ $__f['key'] }}" name="{{ $__f['key'] }}"
                                  rows="3" placeholder="{{ $__f['ph'] }}">{{ $__config[$__f['key']] ?? '' }}</textarea>
                    @else
                        <input type="text" id="kk-{{ $__f['key'] }}" name="{{ $__f['key'] }}"
                               value="{{ $__config[$__f['key']] ?? '' }}"
                               placeholder="{{ $__f['ph'] }}">
                    @endif
                </div>
            @endforeach

            <div style="margin-top:22px;display:flex;align-items:center;">
                <button type="submit" class="kk-btn" id="kk-save">保存设置</button>
                <span class="kk-msg" id="kk-msg"></span>
            </div>
        </form>
    </div>

    <div class="kk-card">
        <h3>说明</h3>
        <p class="kk-note">
            · 保存后刷新首页（<code>/</code>）即可看到效果，<strong>无需重新构建前端</strong>。<br>
            · 右侧图片留空时使用内置 6 张 MC 场景轮播（矿洞 / 下界 / 雪原 / 丛林 / 末地 / 村庄）。<br>
            · 场景图由 <code>gen_mc_scenes.py</code> 生成，改构图可编辑脚本后重跑。<br>
            · 备用插画 <code>moon-columbina.png</code>（挪德卡莱月神）同在 <code>assets/</code> 下。
        </p>
        <a class="kk-back" href="{{ url('/admin/plugins/manage') }}">← 返回插件管理</a>
    </div>

</div>

<script>
    (function () {
        var form = document.getElementById('kk-form');
        var msg = document.getElementById('kk-msg');
        var btn = document.getElementById('kk-save');
        var token = document.querySelector('meta[name="csrf-token"]');

        function say(text, ok) {
            msg.textContent = text;
            msg.style.color = ok ? '#0B7C7C' : '#B3261E';
        }

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            btn.disabled = true;
            say('保存中…', true);

            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token ? token.content : ''
                },
                body: new URLSearchParams(new FormData(form)).toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.code === 0) {
                        say('✓ 已保存，刷新首页即可看到效果。', true);
                    } else {
                        say('保存失败：' + ((data && data.message) || '未知错误'), false);
                    }
                })
                .catch(function (e) { say('保存失败：' + e.message, false); })
                .finally(function () { btn.disabled = false; });
        });
    })();
</script>
</body>
</html>
