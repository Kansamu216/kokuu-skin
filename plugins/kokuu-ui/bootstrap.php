<?php

use App\Services\Hook;

/*
 * KokuuSkin 后台界面重塑（kokuu-ui）
 *
 * 本插件是 kokuu-home 主题插件的**补充**，在 package.json 中通过
 * `"require": { "kokuu-home": "^1.0.0" }` 声明依赖，复用其品牌与虚空主题。
 * 注意：Blessing Skin 在扫描插件时若发现依赖未启用，会**自动禁用本插件**，
 *       因此安装顺序必须为 kokuu-home → 本插件。
 *
 * 定位：对 Blessing Skin 后台（用户中心 / 皮肤库 / 管理后台）做
 * "大刀阔斧"的界面重塑，而**不重写 React 业务逻辑**：
 *   - rebuild.js：用 JS 在 DOM 层面重建 AdminLTE 外壳
 *     （侧边栏 → 悬浮分离式布局、内容区重排、顶栏玻璃化、背景层注入）
 *   - apple.css：Apple 扁平设计系统（大留白 / 纯净面 / 细腻阴影 / 大圆角）
 *   - motion.css：页面过渡、卡片入场 stagger、悬停浮起等流畅动画
 *
 *   提瓦特元素装饰曾在本插件里（teyvat.css），v1.1 起迁至 kokuu-quote ——
 *   元素应与所属功能同在，且不应污染首页。
 *
 * React 继续渲染内容区并处理数据与交互，本插件只替换"舞台"。
 * 配色变量（--a-*）由 apple.css 自身定义，因此即使 kokuu-home 的
 * theme.css 缺失，样式依然完整可用。
 */

return function ($plugin) {
    // Apple 扁平视觉系统（基础层）。依赖 kokuu-home 先加载（其 theme.css/void.css
    // 提供底层配色与虚空深色），本插件在其之上做层次 / 圆角 / 间距的 Apple 化重塑。
    Hook::addStyleFileToPage($plugin->assets('apple.css'), ['*']);

    // 动画系统
    Hook::addStyleFileToPage($plugin->assets('motion.css'), ['*']);

    /*
     *⚠️ 提瓦特元素装饰（teyvat.css）已于 v1.1 从本插件移出，迁到 kokuu-quote。
     *
     * 原因：元素装饰（水印、页脚「提瓦特大陆」署名、同心环）原本用 ['*']
     * 全站注入，结果首页也被铺上这些元素 —— 首页是 MC 像素插画风格，
     * 和提瓦特水印混在一起风格不统一，还干扰首屏内容。
     * 元素应该和它所属的功能待在一起，所以归位到「提瓦特一言」。
     */

    // 首页由 kokuu-home 接管，本插件不介入（保持首页设计）
    // 后台：注入外壳重建脚本
    Hook::addScriptFileToPage(
        $plugin->assets('rebuild.js'),
        ['user', 'user/*', 'admin', 'admin/*', 'skinlib', 'skinlib/*']
    );

    /*
     * plugin/* 页面的 apple 作用域启用。
     *
     * 背景：rebuild.js 顺带会打上 `kokuu-apple` 类，但它的注入范围
     * 被限定在 user / admin / skinlib 三个前缀（因为它还管侧栏折叠、
     * 进场动画这些**有状态**的行为）。而插件自有页面
     * （/plugin/kokuu-exchange、/plugin/kokuu-credit/ledger 等）
     * 不在这个范围里 → body 上没有 kokuu-apple，
     * apple.css 里所有 `body.kokuu-apple xxx` 作用域规则全部失效，
     * 顶栏与侧栏退回内核 AdminLTE 的紫渐变
     * （实测 header rgb(43,31,82)、sidebar linear-gradient(#2b1f52→#1c1638)），
     * 和隔壁 user 页的 Apple 扁平化完全不是一套东西。
     *
     * ⚠️ 这个 bug 极其隐蔽：页面能正常渲染、零报错、零 500，
     *    只是配色不对，很容易被当成「本来就是这个风格」。
     *
     * apple-scope.js 只加类名，零状态、零事件监听、幂等，
     * 不会与 rebuild.js 打架（rebuild.js 本来就不在这些页面上跑）。
     * 放在本插件而不是各业务插件里，是因为本插件才是 apple.css 的所有者 ——
     * 业务插件不必知道主题的存在。
     *
     * ⚠️ 若某个插件的页面用 `view()->file()` 渲染（不继承布局），
     *    apple-scope 同样不生效 —— 那类页面必须整页自包含样式。
     */
    Hook::addScriptFileToPage($plugin->assets('apple-scope.js'), ['plugin', 'plugin/*']);

    /*
     * 页脚修复：{year} 年份替换 + 自定义版权文本包元素。
     * 全站注入（含首页与鉴权页），因为任何页面的页脚都有这个问题。
     * 脚本本身是幂等的，且找不到 .main-footer 会直接返回。
     */
    Hook::addScriptFileToPage($plugin->assets('footer.js'), ['*']);

    /*
     * Yggdrasil API「启动器配置」卡片：接管第三方插件的视图。
     *
     * 背景：yggdrasil-api 自带的 views/dnd.twig 是 AdminLTE 原生外观
     *（card-success + 渐变表头 + 描边），文案也偏含糊，
     * 与本站的 Apple 扁平体系不是一套语言。
     *
     * 为什么用 replaceNamespace 而不是直接改插件文件：
     *   - 插件文件属于上游，升级/重装就会被覆盖，改了留不住；
     *   - Blessing Skin 的 PluginManager::loadViewsAndTranslations() 用的是
     *     addNamespace()，它是 **merge** 语义（新目录追加到末尾），
     *     直接 addNamespace 的话原目录仍然优先，覆盖不生效；
     *   - replaceNamespace() 是整体替换 hints 数组，能真正顶掉单个视图名。
     *
     * ⚠️ 时序：loadViewsAndTranslations() 在 bootPlugin() **之前**
     *    对**所有**插件跑完一遍（PluginManager::enable() 里先 all()->each，
     *    再 enabled->each(bootPlugin)），所以本插件的 bootstrapper 执行时
     *    `Yggdrasil` 命名空间必定已注册，可以安全读取并前置我们的目录。
     *
     * ⚠️ 必须把原目录保留在数组里：该插件还有 config.twig / log.twig
     *    两个视图由同一个命名空间提供，丢了会 500。原目录放在**后面**，
     *    于是 `Yggdrasil::dnd` 命中我们的覆盖版，其余回落到插件自带版本。
     */
    $finder = app('view')->getFinder();
    $origHints = (array) ($finder->getHints()['Yggdrasil'] ?? []);
    if ($origHints) {
        // 前插我们的目录 → Yggdrasil::dnd 优先，其余视图仍能找到插件自带版本
        $finder->replaceNamespace(
            'Yggdrasil',
            array_merge([$plugin->getPath().'/views/ygg'], $origHints)
        );
        Hook::addStyleFileToPage($plugin->assets('ygg.css'), ['user', 'user/*']);
    }

    /*
     * =========================================================
     * 侧边栏精简：移除对普通用户无意义的菜单项
     * =========================================================
     *
     * 为什么用 `side_menu` 过滤器而不是改 config/menu.php：
     *   SideMenuComposer::compose() 的流程是
     *     event(new ConfigureUserMenu($menu))   ← 各插件往里追加
     *     $menu = $filter->apply('side_menu', $menu, [$type]);  ← 我们在这里
     *   过滤器拿到的是**最终**菜单，且 core 一个字都不用动，
     *   插件卸载后自动恢复原样（同接管 dashboard 的思路）。
     *   ⚠️ 注册要用 Filter::add()（**不是** apply()，后者是读取）。
     *   ⚠️ 必须用 app(\Blessing\Filter::class) 取实例：
     *      `Filter::add()` 是**实例方法**，静态调用会抛
     *      "Non-static method Blessing\Filter::add() cannot be called statically"；
     *      且**没有 app('filter') 绑定**，也没有 Filter facade。
     *
     * 移除的三项，以及为什么：
     *
     *  1. general.my-reports（我的举报 / fa-flag）
     *     站点不开放举报功能，用户没有任何东西可举报，
     *     点了是空列表，纯干扰。
     *
     *  2. general.developer（高级功能 → OAuth2 应用 / fa-code-branch）
     *     面向站长的第三方应用授权功能。普通用户既不需要也看不懂，
     *     暴露它只会让人以为点进去能干什么。管理员如需管理，
     *     从 后台 → 用户管理 里进。
     *
     *  3. forum 的「论坛积分流水」（fa-coins，kokuu-credit 追加的）
     *     ⚠️ 这个**同时是 bug**：那条路由挂着
     *       abort_if(! $user || $user->permission < User::ADMIN, 403)
     *     也就是**本来就只给管理员**，菜单却挂在所有用户都可见的
     *     user 区 —— 普通用户点进去必然 403。
     *     菜单与权限不匹配，比「多余」更严重。
     *     → 这里按权限过滤：管理员仍能看到（后台要看全站流水），
     *       普通用户直接看不见，不用改 kokuu-credit 那边。
     *
     * 保留：仪表盘 / 我的衣柜 / 角色管理 / 个人资料 —— 普通用户真在用。
     * ⚠️ 删的是**菜单项**不是路由：直接输 URL 仍可访问（该 403 的仍 403），
     *    没有任何功能被真正关闭。
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
            'general.my-reports',   // 我的举报：不开放举报功能
            'general.developer',    // 高级功能（OAuth2 应用）：站长专用
        ];

        $out = [];
        foreach ($menu as $item) {
            $title = (string) \Illuminate\Support\Arr::get($item, 'title', '');

            if (in_array($title, $hide, true)) {
                continue;
            }

            // 积分流水：仅管理员可见（路由本身也要求 ADMIN）
            if ($title === '论坛积分流水' && ! $isAdmin) {
                continue;
            }

            $out[] = $item;
        }

        return $out;
    });
};
