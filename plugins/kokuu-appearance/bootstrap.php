<?php

use App\Events\ConfigureRoutes;
use App\Events\RenderingHeader;
use App\Models\User;
use App\Services\Hook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/*
 * KokuuSkin 外观（kokuu-appearance）
 *
 * 由原 kokuu-home / kokuu-ui / kokuu-quote 三个插件合并而成。
 *
 * 【为什么合并】
 *   三者原本是一条依赖链（ui → home，quote → home + ui），
 *   Blessing Skin 在依赖未满足时会**自动禁用**插件，
 *   导致安装顺序稍错就白屏。合并后一次启用、无顺序依赖。
 *
 * 【职责边界】
 *   本插件**只负责视觉**，不含任何业务逻辑。
 *   业务插件（kokuu-credit / kokuu-lottery / kokuu-signin）通过
 *   UI Kit 契约（CSS 变量 + k- 前缀类名）选用样式，
 *   **不依赖本插件**——本插件停用后业务页面仍可正常使用。
 *
 * 【UI Kit 契约】见 assets/tokens.css 与 README.md
 *
 * 【样式加载顺序】不可乱，否则配色错乱：
 *   theme.css   底层配色变量（原 kokuu-home）
 *   void.css    深色主题覆盖
 *   apple.css   Apple 扁平层（依赖上面两层的变量）
 *   motion.css  动画
 *   quote.css   一言功能样式
 *   teyvat.css  提瓦特元素装饰
 *
 * ⚠️ 辅助函数必须定义在下方「return 闭包」之**前**。
 *    PHP 的 include/require 一旦在文件顶层遇到 return 就停止执行，
 *    写在 return 之后的函数永远不会被声明。
 */

/*
 * 读取首页自定义配置（带默认值），存储于 options 表，键前缀 kokuu_home_。
 * 保留 kokuu_home_ 前缀是为了兼容已有数据，避免迁移。
 */
if (! function_exists('kokuu_appearance_config')) {
    function kokuu_appearance_config(): array
    {
        $defaults = [
            'hero_title'    => 'KokuuSkin',
            'tagline'       => '在方块世界，遇见喜欢的自己。',
            'intro'         => '挑一款喜欢的皮肤，收进你的衣柜。用 KokuuSkin 账号连接启动器，让每次冒险都有自己的模样。',
            'hero_image'    => '', // Legacy field retained for backward compatibility.
            'hero_background'   => '',
            'hero_background_2' => '',
            'hero_background_3' => '',
            'footer_note'       => '你的模样，你的冒险。',
            'contact_email' => 'hi@kokuu.org',
        ];

        $options = app('options');
        $config  = [];
        foreach ($defaults as $key => $default) {
            $value = $options->get('kokuu_home_' . $key);
            $config[$key] = ($value === null || $value === '') ? $default : $value;
        }

        return $config;
    }
}

/*
 * 保存首页配置。
 */
if (! function_exists('kokuu_appearance_save_config')) {
    function kokuu_appearance_save_config(array $payload): void
    {
        $options = app('options');
        foreach ($payload as $key => $value) {
            $options->set('kokuu_home_' . $key, $value);
        }
    }
}

/*
 * 自定义台词库路径（放 storage，不写插件目录——插件目录可能只读，
 * 且升级/重装会被覆盖）。
 */
if (! function_exists('kokuu_quote_custom_path')) {
    function kokuu_quote_custom_path(): string
    {
        return storage_path('plugins/kokuu-appearance/quotes.custom.php');
    }
}

/*
 * 读取全部台词：优先自定义库，回退内置 quotes.php。进程内静态缓存。
 */
if (! function_exists('kokuu_quote_all')) {
    function kokuu_quote_all(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }

        $custom = kokuu_quote_custom_path();
        $data   = is_file($custom) ? require $custom : null;

        if (! is_array($data) || $data === []) {
            $data = require __DIR__.'/quotes.php';
        }

        $clean = [];
        foreach ($data as $q) {
            if (! is_array($q) || trim((string) ($q['text'] ?? '')) === '') {
                continue;
            }
            $clean[] = [
                'text' => (string) $q['text'],
                'by'   => (string) ($q['by'] ?? '佚名'),
                'tag'  => (string) ($q['tag'] ?? '日常'),
                'el'   => (string) ($q['el'] ?? ''),
            ];
        }

        return $cache = ($clean ?: [[
            'text' => '风带来了故事的种子。',
            'by'   => '佚名',
            'tag'  => '日常',
            'el'   => '风',
        ]]);
    }
}

/*
 * 保存自定义台词库。
 */
if (! function_exists('kokuu_quote_save')) {
    function kokuu_quote_save(array $quotes): void
    {
        $path = kokuu_quote_custom_path();
        $dir  = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $php = "<?php\n\n"
            ."/* 自定义台词库（由插件配置页生成）。可自由编辑，删除本文件即回退内置库。 */\n\n"
            .'return '.var_export($quotes, true).";\n";

        file_put_contents($path, $php);
    }
}

/*
 * 恢复内置台词库。
 */
if (! function_exists('kokuu_quote_reset')) {
    function kokuu_quote_reset(): void
    {
        $path = kokuu_quote_custom_path();
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/*
 * 按字符数裁剪（多字节安全）。
 */
if (! function_exists('kokuu_quote_clip')) {
    function kokuu_quote_clip(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) : $text;
    }
}

return function ($plugin) {
    /* =========================================================
     * 样式注入 —— 顺序不可调整（四层架构）
     *
     *   Layer 1  tokens.css   设计令牌（唯一真相源）
     *   Layer 2  ui-kit.css   标准组件库（k-* 类名）
     *   Layer 3  theme.css    内核外壳覆盖（AdminLTE/React 内容区）
     *            void.css     深色主题
     *            apple.css    遗留 Apple 覆写（逐步被 ui-kit 取代）
     *   Layer 4  motion.css   动效
     * ========================================================= */

    // Layer 1：设计令牌 —— 必须最先加载，其他层全部依赖
    Hook::addStyleFileToPage($plugin->assets('tokens.css'), ['*']);

    // Layer 2：标准组件库 —— 业务插件可直接使用 k-* 类名
    Hook::addStyleFileToPage($plugin->assets('ui-kit.css'), ['*']);

    // Layer 3：内核外壳覆盖
    Hook::addStyleFileToPage($plugin->assets('theme.css'), ['*']);
    Hook::addStyleFileToPage($plugin->assets('void.css'), ['*']);
    Hook::addStyleFileToPage($plugin->assets('apple.css'), ['*']);

    // Layer 4：动效
    Hook::addStyleFileToPage($plugin->assets('motion.css'), ['*']);

    // 5. 一言功能样式
    Hook::addStyleFileToPage($plugin->assets('quote.css'), ['*']);
    // ⚠️ 一言的脚本必须单独注入：合并三个插件时漏掉了这一行，
    //    导致顶栏一言完全不显示（用户反馈「提瓦特一言不见了」）。
    Hook::addScriptFileToPage($plugin->assets('quote.js'), ['*']);

    // 6. 提瓦特元素装饰（仅内容页，排除首页——首页是 MC 像素风格）
    Hook::addStyleFileToPage(
        $plugin->assets('teyvat.css'),
        ['user', 'user/*', 'admin', 'admin/*', 'skinlib', 'skinlib/*']
    );

    /* =========================================================
     * 脚本注入
     * ========================================================= */

    // 首页：JS 接管整页 DOM，渲染 KokuuSkin 落地页
    Hook::addStyleFileToPage($plugin->assets('home.css'), ['/', '']);
    Hook::addScriptFileToPage($plugin->assets('home.js'), ['/', '']);

    // 鉴权页：重皮肤，保留内核表单与提交逻辑
    Hook::addStyleFileToPage($plugin->assets('auth.css'), ['auth/*']);
    Hook::addScriptFileToPage($plugin->assets('auth.js'), ['auth/*']);

    // 全站外壳统一
    Hook::addScriptFileToPage($plugin->assets('shell.js'), ['*']);

    // 后台外壳重建（侧栏悬浮、内容区重排、顶栏玻璃化）
    // auth/* 也纳入：登录页没有顶栏，ensureDarkToggle 会补一个浮动切换按钮，
    // 否则登录/注册页完全没有进入深色的入口（2026-11-04 用户反馈）。
    Hook::addScriptFileToPage(
        $plugin->assets('rebuild.js'),
        ['user', 'user/*', 'admin', 'admin/*', 'skinlib', 'skinlib/*', 'auth/*']
    );

    /*
     * plugin/* 页面的 apple 作用域启用。
     *
     * rebuild.js 只在 user/admin/skinlib 三个前缀注入，
     * 而插件自有页面（/plugin/kokuu-credit/ledger 等）不在其中，
     * 会导致 body 上没有 kokuu-apple，apple.css 作用域规则全部失效。
     *
     * apple-scope.js 只加类名，零状态、零事件监听、幂等，
     * 不与 rebuild.js 打架（后者本来就不在这些页面上跑）。
     *
     * ⚠️ 本文件将来会随「废弃作用域前缀」一并移除（阶段 2.7）。
     */
    Hook::addScriptFileToPage($plugin->assets('apple-scope.js'), ['plugin', 'plugin/*']);

    /*
     * 页脚修复：{year} 年份替换 + 自定义版权文本。
     * 全站注入，脚本幂等，找不到 .main-footer 直接返回。
     */
    Hook::addScriptFileToPage($plugin->assets('footer.js'), ['*']);

    /* =========================================================
     * 首页配置注入
     * ========================================================= */

    /*
     * 把保存的配置注入 window.KOKUU_CONFIG。
     * 未配置时字段为默认值，home.js 读取后渲染。
     */
    Event::listen(RenderingHeader::class, function ($event) {
        $config = kokuu_appearance_config();
        if (request()->path() === '/') {
            /*
             * 每次访问随机挑选 3 款公开皮肤，避免首页长期只展示同样三张。
             * 先取主键再回表，比 orderByRaw('RAND()') 便宜；不足 3 条时
             * 退化为全部，页面在 0 条时会自行隐藏精选区。
             */
            $pool = \App\Models\Texture::query()
                ->where('public', true)->whereIn('type', ['steve', 'alex'])
                ->pluck('tid');
            $picked = $pool->count() > 3 ? $pool->random(3)->all() : $pool->all();
            $config['featured_skins'] = \App\Models\Texture::query()
                ->whereIn('tid', $picked)->get(['tid', 'name', 'type'])
                ->map(fn ($texture) => ['id' => $texture->tid, 'name' => $texture->name,
                    'model' => $texture->type === 'alex' ? 'Alex' : 'Steve'])->all();
        }
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $event->addContent('<script>window.KOKUU_CONFIG = '.$json.';</script>');
    });

    /* =========================================================
     * Yggdrasil API 视图接管
     * ========================================================= */

    /*
     * 接管 yggdrasil-api 的「启动器配置」卡片。
     *
     * 为什么用 replaceNamespace 而不是直接改插件文件：
     *   - 插件文件属于上游，升级/重装会被覆盖
     *   - PluginManager::loadViewsAndTranslations() 用的是 addNamespace()，
     *     那是 **merge** 语义（新目录追加到末尾），原目录仍然优先
     *   - replaceNamespace() 整体替换 hints 数组，能真正顶掉单个视图名
     *
     * ⚠️ 时序：loadViewsAndTranslations() 在 bootPlugin() 之前对所有插件跑完，
     *    所以本插件执行时 Yggdrasil 命名空间必定已注册。
     *
     * ⚠️ 必须把原目录保留在数组里：该插件还有 config.twig / log.twig
     *    由同一命名空间提供，丢了会 500。原目录放**后面**，
     *    于是 Yggdrasil::dnd 命中我们的覆盖版，其余回落到插件自带版本。
     */
    $finder = app('view')->getFinder();
    $origHints = (array) ($finder->getHints()['Yggdrasil'] ?? []);
    if ($origHints) {
        $finder->replaceNamespace(
            'Yggdrasil',
            array_merge([$plugin->getPath().'/views/ygg'], $origHints)
        );
        Hook::addStyleFileToPage($plugin->assets('ygg.css'), ['user', 'user/*']);
    }

    /* =========================================================
     * 侧边栏精简
     * ========================================================= */

    /*
     * 用 side_menu 过滤器而非改 config/menu.php：
     *   过滤器拿到的是**最终**菜单，core 一个字不用动，
     *   插件卸载后自动恢复原样。
     *
     * ⚠️ 注册要用 Filter::add()（不是 apply()，后者是读取）
     * ⚠️ 必须用 app(\Blessing\Filter::class) 取实例，
     *    Filter::add() 是实例方法，静态调用会抛异常，
     *    且没有 app('filter') 绑定，也没有 Filter facade。
     *
     * 移除项：
     *   general.my-reports  站点不开放举报，点了是空列表
     *   general.developer   面向站长的 OAuth2 应用管理，普通用户不需要
     *
     * 保留：仪表盘 / 我的衣柜 / 角色管理 / 个人资料 —— 普通用户真在用。
     * ⚠️ 删的是**菜单项**不是路由，直接输 URL 仍可访问。
     */
    app(\Blessing\Filter::class)->add('side_menu', function ($menu, $type) {
        if ($type !== 'user' || ! is_array($menu)) {
            return $menu;
        }

        $isAdmin = false;
        try {
            $u = auth()->user();
            $isAdmin = $u && $u->permission >= \App\Models\User::ADMIN;
        } catch (\Throwable $e) {
            // 鉴权容器不可用（CLI / 未登录）→ 按非管理员处理，保守隐藏
            $isAdmin = false;
        }

        $hide = [
            'general.my-reports',
            'general.developer',
        ];

        $out = [];
        foreach ($menu as $item) {
            $title = (string) \Illuminate\Support\Arr::get($item, 'title', '');

            if (in_array($title, $hide, true)) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    });

    /* =========================================================
     * 路由：配置保存 + 一言 API
     * ========================================================= */

    Hook::addRoute(function ($router) {
        /*
         * 首页配置保存。
         *
         * ⚠️ 必须删掉 storage/options.php，否则配置永远不生效。
         *    Blessing Skin 的 App\Services\Option 构造函数**优先读这个文件**，
         *    存在即 return，根本不查数据库。
         *    用 delete 而非重建：重建需要 Artisan::call('options:cache')，
         *    在 HTTP 请求里跑 Artisan 既慢又有副作用；
         *    直接删掉即可，下次任意请求会走 DB 分支自动重建。
         */
        $router->post('plugin/kokuu-appearance/settings', function (Request $request) {
            $user = $request->user();
            abort_if(! $user || $user->permission < User::ADMIN, 403);

            kokuu_appearance_save_config([
                'hero_title'    => (string) $request->input('hero_title', ''),
                'tagline'       => (string) $request->input('tagline', ''),
                'intro'         => (string) $request->input('intro', ''),
                'hero_image'    => (string) $request->input('hero_image', ''),
                'hero_background' => (string) $request->input('hero_background', ''),
                'hero_background_2' => (string) $request->input('hero_background_2', ''),
                'hero_background_3' => (string) $request->input('hero_background_3', ''),
                'footer_note'   => (string) $request->input('footer_note', ''),
                'contact_email' => (string) $request->input('contact_email', ''),
            ]);

            @unlink(storage_path('options.php'));

            return response()->json(['code' => 0, 'message' => 'Saved.']);
        })->middleware(['web', 'auth', 'role:admin']);

        // ---- 随机一言 API（公开，供前端顶栏调用）----
        $router->get('plugin/kokuu-appearance/quote', function (Request $request) {
            $all = kokuu_quote_all();
            if (empty($all)) {
                return response()->json(['code' => 1, 'message' => '台词库为空。'], 404);
            }

            $pool = $all;
            $tag  = trim((string) $request->query('tag', ''));
            $el   = trim((string) $request->query('el', ''));

            if ($tag !== '') {
                $pool = array_values(array_filter($pool, fn ($q) => ($q['tag'] ?? '') === $tag));
            }
            if ($el !== '') {
                $pool = array_values(array_filter($pool, fn ($q) => ($q['el'] ?? '') === $el));
            }
            // 筛选无结果时回退全库，避免前端拿到空响应
            if (empty($pool)) {
                $pool = $all;
            }

            $q = $pool[random_int(0, count($pool) - 1)];

            return response()->json([
                'text' => (string) ($q['text'] ?? ''),
                'by'   => (string) ($q['by'] ?? '佚名'),
                'tag'  => (string) ($q['tag'] ?? ''),
                'el'   => (string) ($q['el'] ?? ''),
            ]);
        });

        // ---- 台词增删 / 恢复（仅管理员）----
        $router->post('plugin/kokuu-appearance/quote/manage', function (Request $request) {
            $user = $request->user();
            abort_if(! $user || $user->permission < User::ADMIN, 403);

            switch ($request->input('action')) {
                case 'add':
                    $text = trim((string) $request->input('text', ''));
                    if ($text === '') {
                        return response()->json(['code' => 1, 'message' => '请填写台词正文。']);
                    }

                    $all = kokuu_quote_all();
                    array_unshift($all, [
                        'text' => kokuu_quote_clip($text, 60),
                        'by'   => kokuu_quote_clip(trim((string) $request->input('by', '')), 20) ?: '佚名',
                        'tag'  => kokuu_quote_clip(trim((string) $request->input('tag', '')), 10) ?: '日常',
                        'el'   => kokuu_quote_clip(trim((string) $request->input('el', '')), 4),
                    ]);
                    kokuu_quote_save($all);

                    return response()->json(['code' => 0, 'message' => '已添加新台词。']);

                case 'delete':
                    $idx = (int) $request->input('idx', -1);
                    $all = kokuu_quote_all();
                    if (! isset($all[$idx])) {
                        return response()->json(['code' => 1, 'message' => '删除失败：序号不存在。']);
                    }
                    array_splice($all, $idx, 1);
                    kokuu_quote_save($all);

                    return response()->json(['code' => 0, 'message' => '已删除该台词。']);

                case 'reset':
                    kokuu_quote_reset();

                    return response()->json(['code' => 0, 'message' => '已恢复内置台词库。']);

                default:
                    return response()->json(['code' => 1, 'message' => '未知操作。']);
            }
        })->middleware(['web', 'auth', 'role:admin']);
    });
};

