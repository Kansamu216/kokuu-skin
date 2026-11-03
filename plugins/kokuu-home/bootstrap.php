<?php

use App\Events\RenderingHeader;
use App\Events\ConfigureRoutes;
use App\Models\User;
use App\Services\Hook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/*
 * KokuuSkin 全站主题（kokuu-home）
 *
 * Blessing Skin 会以 `app->call(getRequire($path), ['plugin' => $plugin])`
 * 的方式加载本文件，因此必须 `return` 一个闭包，且参数名固定为 `$plugin`
 * （框架按参数名注入 Plugin 实例）。
 *
 * 设计原则：不改内核任何模板。所有视觉统一都通过 Hook 注入实现：
 *   - 首页（/）：home.js 整体接管 body，渲染 KokuuSkin 落地页
 *     （Apple 扁平 + MC 像素点缀 + 6 张场景轮播）
 *   - 鉴权页（/auth/*）：auth.css/js 重皮肤，**保留内核表单与提交逻辑**
 *   - 全站（*）：theme.css + shell.js 把 AdminLTE 外壳统一为 KokuuSkin 配色，
 *     并把站点图标与品牌文字改为「KokuuSkin」（纯文字，不使用 logo 图片）
 *
 * 双主题：
 *   - 浅色（纸雨）：Apple 扁平，纯净白面 + 大留白 + 细腻阴影
 *   - 深色（虚空）：挂 body.dark-mode，为 Apple Dark 质感（纯黑底 + 分层灰面），
 *     由 void.css 定义；首页另有独立切换按钮（.kokuu-void，不依赖服务端）
 *
 * 扩展能力：
 *   - 表单式自定义首页：配置存 options 表，通过 RenderingHeader 事件注入为
 *     window.KOKUU_CONFIG，home.js 读取后渲染（实现不改代码即可换文案）
 *   - 配置页：走原生插件配置机制（package.json 的 config 字段 +
 *     views/config.blade.php），入口 /admin/plugins/config/kokuu-home
 *
 * ⚠️ 重要：辅助函数必须定义在下方「return 闭包」之**前**。
 *    PHP 的 include/require 一旦在文件顶层遇到 return，就立即停止执行该文件
 *    剩余部分——若把函数写在 return 之后 they'll 永远不会被声明，
 *    导致调用时 "Call to undefined function"。同理用 function_exists 包裹，
 *    防止本文件被重复加载时声明冲突。
 */

/*
 * 读取首页自定义配置（带默认值）。
 * 存储在 options 表，键前缀 kokuu_home_。
 */
if (! function_exists('kokuu_home_config')) {
    function kokuu_home_config(): array
    {
        $defaults = [
            'hero_title'   => 'KokuuSkin',
            'tagline'      => '让每一次登录，都落在正确的像素上',
            'intro'        => '皮肤站用来存放角色、分享外观。把喜欢的样子收进衣柜，随时调用。',
            // 右侧图片：留空则使用内置像素插画 moon-columbina.png
            'hero_image'   => '',
            'footer_note'  => '像素有形，月光无界',
            'contact_email'=> 'hi@kokuu.org',
        ];

        $options = app('options');
        $config  = [];
        foreach ($defaults as $key => $default) {
            $value = $options->get('kokuu_home_' . $key);
            // 空字符串视为未配置，回退默认值
            $config[$key] = ($value === null || $value === '') ? $default : $value;
        }

        return $config;
    }
}

/*
 * 保存首页自定义配置到 options 表。
 */
if (! function_exists('kokuu_home_save_config')) {
    function kokuu_home_save_config(array $payload): void
    {
        $options = app('options');
        foreach ($payload as $key => $value) {
            $options->set('kokuu_home_' . $key, $value);
        }
    }
}

return function ($plugin) {
    // 首页：仅根路径注入 KokuuSkin 落地页（JS 接管整页 DOM）
    Hook::addStyleFileToPage($plugin->assets('home.css'), ['/', '']);
    Hook::addScriptFileToPage($plugin->assets('home.js'), ['/', '']);

    // 鉴权页：登录 / 注册 / 找回密码 / 重置 —— 重皮肤为 KOKUU 卡片，保留原表单
    Hook::addStyleFileToPage($plugin->assets('auth.css'), ['auth/*']);
    Hook::addScriptFileToPage($plugin->assets('auth.js'), ['auth/*']);

    // 全站：用户中心 / 皮肤库 / 后台的 AdminLTE 外壳统一为 KOKUU 配色
    Hook::addStyleFileToPage($plugin->assets('theme.css'), ['*']);
    Hook::addScriptFileToPage($plugin->assets('shell.js'), ['*']);

    // 虚空深色主题：全站可切换（后端 body.dark-mode + 首页 .kokuu-void）
    Hook::addStyleFileToPage($plugin->assets('void.css'), ['*']);

    /*
     * 表单式自定义首页：把保存的配置注入到 window.KOKUU_CONFIG。
     * RenderingHeader 会在 <head> 渲染 extra_head 时输出，这里在所有页面
     * 注入一份（未配置时字段为 null，home.js 会回退到内置默认文案）。
     */
    Event::listen(RenderingHeader::class, function ($event) {
        $config = kokuu_home_config();
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $event->addContent('<script>window.KOKUU_CONFIG = ' . $json . ';</script>');
    });

    // 配置界面入口：走 Blessing Skin 原生插件配置机制
    // （package.json 的 config 字段 + views/config.blade.php）
    // 插件管理页会据此显示「设置」按钮：/admin/plugins/config/kokuu-home
    // 此处不再自建侧栏菜单项，避免与原生入口重复。
    Hook::addRoute(function ($router) {
        // 配置界面由 Blessing Skin 原生机制提供（GET /admin/plugins/config/kokuu-home），
        // 此处仅提供保存端点供其 fetch 提交。
        $router
            ->post('plugin/kokuu-home/settings', function (Request $request) {
                $user = $request->user();
                abort_if(!$user || $user->permission < User::ADMIN, 403);

                kokuu_home_save_config([
                    'hero_title'    => (string) $request->input('hero_title', ''),
                    'tagline'       => (string) $request->input('tagline', ''),
                    'intro'         => (string) $request->input('intro', ''),
                    'hero_image'    => (string) $request->input('hero_image', ''),
                    'footer_note'   => (string) $request->input('footer_note', ''),
                    'contact_email' => (string) $request->input('contact_email', ''),
                ]);

                // ⚠️ 必须删掉 storage/options.php，否则配置永远不生效。
                //
                // Blessing Skin 的 App\Services\Option 构造函数**优先读这个文件**，
                // 存在即 return，根本不查数据库。所以只要它在，
                // 上面set() 写进 DB 的值永远读不出来
                // （表现为「保存成功但首页不变」）。
                // 内核自己也是这么处理的（UpdateController 升级时删同一文件）。
                //
                // 用 delete 而不是重新生成：重建需要 Artisan::call('options:cache')，
                // 在 HTTP 请求里跑 Artisan 既慢又有副作用；直接删掉即可，
                // 下次任意请求会走 DB 分支自动重建，功能不受影响。
                @unlink(storage_path('options.php'));

                return response()->json([
                    'code'    => 0,
                    'message' => 'Saved.',
                ]);
            })
            ->middleware(['web', 'auth', 'role:admin']);
    });
};
