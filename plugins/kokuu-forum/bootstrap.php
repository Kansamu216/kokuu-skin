<?php

use App\Models\User;
use App\Services\Hook;
use Blessing\Filter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/*
 * KokuuSkin 论坛聚合（kokuu-forum）
 *
 * 把 Flarum 论坛的最新讨论，以**仪表盘卡片容器**的形式嵌进皮肤站首页，
 * 并接管内核原有的「公告」卡片 —— 管理员想让哪几条帖子上首页，
 * 在配置页填它们的讨论 ID 即可（置顶）。
 *
 * 皮肤站与 Flarum 是两套独立系统（不同数据库、不同会话），
 * 本插件只做「入口统一 + 内容聚合」，不做代码合并。
 *
 * 设计要点：
 *   - **不注入侧边栏**。用户明确要求侧边栏保持干净，论坛入口只以
 *     仪表盘卡片 + 卡片内「前往论坛」按钮的形式存在。
 *   - **服务端代理 API**。Flarum 默认不返回 CORS 头，浏览器无法跨域读取
 *     （实测 OPTIONS 预检405、无 access-control-* 头）。因此不能在前端
 *     直接 fetch 论坛，而由本插件代为请求再吐出 JSON，彻底规避跨域。
 *   - **置顶由皮肤站侧记录 ID**。本论坛 Flarum 没装 pin/lock扩展，
 *     discussions 表连 is_pinned 列都没有，API 也不认 sort=-isPinned
 *     （实测返回 400 invalid_parameter）。所以「置顶」只能是本插件
 *     自己维护的一张 ID 名单，再单独把这些讨论抓出来顶到列表最前。
 *   - **字段按实测写死，不臆测**。本论坛 Flarum 版本返回的讨论属性为：
 *       title / slug / commentCount / participantCount / createdAt
 *       lastPostedAt / lastPostNumber
 *     **没有 viewCount，也没有 firstPost**（首帖内容不在列表接口里），
 *     用户名字段是 displayName（同时也有 username）。
 *     下面所有取值都做了 ?? 兜底，将来论坛升级加了字段也能自动显示。
 *   - **不缓存登录态接口**。讨论列表是公开数据，用 Laravel Cache 缓存
 *     缓存 TTL 秒数，兼顾性能与实时性；缓存键含论坛 URL、条数与置顶名单，
 *     多站不串味、改置顶能立即生效。
 *   - **优雅降级**。论坛不可达时不报错，卡片提示「暂时无法获取」，
 *     绝不让论坛故障拖垮皮肤站本身。置顶帖单独失败也只是跳过它，
 *     不影响其余讨论的展示。
 *
 * 视觉：复用 kokuu-home 的 Apple 扁平语言（柔和渐变、12~16px 圆角、
 * 多层柔和阴影），卡片外壳则沿用内核 AdminLTE 的 box/header 结构，
 * 与仪表盘上其它卡片对齐。
 *
 * ⚠️ 重要：辅助函数必须定义在下方「return 闭包」之**前**。
 *    PHP 的 include/require 一旦在文件顶层遇到 return，就立即停止执行该文件
 *    剩余部分，导致函数永远不被声明（Call to undefined function）。
 *    此处用 function_exists 包裹，同时防止重复加载时声明冲突。
 */

if (! function_exists('kokuu_forum_config')) {
    /**
     * 读取插件配置（存options 表，键前缀 kokuu_forum_）。
     */
    function kokuu_forum_config(): array
    {
        $defaults = [
            // 论坛根地址（Flarum 的 url，见 config.php）
            'forum_url'    => 'https://chat.kokuu.org',
            // 仪表盘卡片的标题
            'menu_title'   => '社区',
            // 卡片里显示几条**普通**讨论（置顶帖不占这个名额）
            'widget_limit' => 6,
            // 缓存秒数，0 = 不缓存
            'cache_ttl'    => 60,
            /*
             * 每个帖子里显示几条最新评论（0 = 不显示）。
             * 只取「回复」不含首帖（首帖内容通常和标题重复）。
             */
            'preview_replies' => 2,
            /*
             * 置顶帖 ID 列表（逗号分隔，如 "2,5"）。
             *
             * 用途：替代内核的「公告」卡片 —— 管理员想让哪几条帖子上仪表盘，
             * 填它们的讨论 ID 即可，显示在列表最上方并带「置顶」标记。
             *
             * ⚠️ 为什么不让论坛自己置顶：本论坛 Flarum **没有装 pin/lock 扩展**，
             *   discussions 表连 is_pinned 列都没有，API 也不认 sort=-isPinned
             *   （实测返回 400 invalid_parameter）。所以置顶必须由皮肤站侧记录ID。
             *
             * 存 ID 而非标题：标题会改，ID 不会，指向稳定。
             */
            'pinned'       => '',
        ];

        $options = app('options');
        $config  = [];
        foreach ($defaults as $key => $default) {
            $value = $options->get('kokuu_forum_' . $key);
            $config[$key] = ($value === null || $value === '') ? $default : $value;
        }

        // limit 与 ttl 做范围钳制，避免后台误填导致异常
        $config['widget_limit'] = max(1, min(30, (int) $config['widget_limit']));
        $config['cache_ttl']    = max(0, min(3600, (int) $config['cache_ttl']));
        $config['preview_replies'] = max(0, min(5, (int) $config['preview_replies']));

        // 去掉末尾斜杠，避免拼接出双斜杠
        $config['forum_url'] = rtrim((string) $config['forum_url'], '/');

        // 置顶 ID 归一化：去空白、去重、只留数字、限 10 条
        $pinned = array_slice(array_values(array_unique(array_filter(
            array_map('intval', explode(',', (string) $config['pinned'])),
            fn ($id) => $id > 0
        ))), 0, 10);
        $config['pinned'] = $pinned;

        return $config;
    }
}

if (! function_exists('kokuu_forum_save_config')) {
    /**
     * 保存插件配置到 options 表。
     */
    function kokuu_forum_save_config(array $payload): void
    {
        $options = app('options');
        foreach ($payload as $key => $value) {
            $options->set('kokuu_forum_' . $key, $value);
        }
    }
}

if (! function_exists('kokuu_forum_normalize')) {
    /**
     * 把 Flarum 的一条 discussion 拍平成插件内部结构。
     *
     * v1.2 起本函数只是 FlarumClient::normalizeDiscussion() 的薄包装，
     * 归一化规则（slug 不能重复拼 id、字段全部 ?? 兜底）已下沉到
     * kokuu-core，那是「对论坛的既有认知」该待的地方。
     *
     * 保留这个函数名是为了不改动其余调用点 —— 插件内部仍按原来的
     * 方式传 ($disc, $users, $url, $pinned)。
     */
    function kokuu_forum_normalize(array $disc, array $users, string $forumUrl, bool $pinned = false): array
    {
        $item = \KokuuStudio\KokuuCore\FlarumClient::normalizeDiscussion($disc, $users, $forumUrl);
        $item['isPinned'] = $pinned;

        return $item;
    }
}

if (! function_exists('kokuu_forum_plain_text')) {
    /**
     * 把 Flarum 的 contentHtml 洗成一段安全的纯文本。
     *
     * v1.2 起实现已下沉到 kokuu-core 的 PlainText。
     *
     * 为什么要下沉而不留在本插件：这是整条 Flarum 数据链上**唯一的
     * XSS 入口**，安全代码不该藏在某个业务插件里 —— 万一以后接论坛
     * 数据的是另一个插件，漏掉这段防护就是直接漏一个 XSS。
     * 放在共享层，任何接入方都自动继承这条防线。
     */
    function kokuu_forum_plain_text(string $html, int $limit = 90): string
    {
        return \KokuuStudio\KokuuCore\PlainText::from($html, $limit);
    }
}

if (! function_exists('kokuu_forum_fetch_replies')) {
    /**
     * 抓取若干讨论的最新回复，按讨论 ID 分组返回。
     *
     * @return array<string, array> 讨论 ID（字符串） => 回复数组，每项
     *         ['author' => 显示名, 'text' => 纯文本摘要, 'number' => 楼层号]
     *
     * v1.2 起实现已下沉到 kokuu-core 的 FlarumClient::fetchReplies()。
     *
     * 「一次请求拿全部、绝不逐帖请求」这个核心决定连同它背后的
     * 一整套实测结论（filter[discussion] 多值返回 422、
     * filter[type] 不生效、/api/discussions/{id}/posts 是 404…）
     * 都已经写进共享层的文档注释，这里不再重复 ——
     * 想知道为什么这么写，去看 FlarumClient 的注释。
     */
    function kokuu_forum_fetch_replies(array $wantedIds, int $perDiscussion): array
    {
        $cfg = kokuu_forum_config();

        return \KokuuStudio\KokuuCore\FlarumClient::fetchReplies(
            $cfg['forum_url'] ?? '',
            $wantedIds,
            $perDiscussion
        );
    }
}

if (! function_exists('kokuu_forum_user_index')) {
    /**
     * 从 JSON:API 响应的 included 里抽出「id => 用户名」索引。
     *
     * v1.2 起下沉到 kokuu-core：displayName 优先、再退 username 的规则
     * 是本论坛的实测事实，属于跨插件共享的知识。
     */
    function kokuu_forum_user_index(array $body): array
    {
        return \KokuuStudio\KokuuCore\FlarumClient::userIndex($body);
    }
}

if (! function_exists('kokuu_forum_fetch')) {
    /**
     * 代理请求 Flarum，返回归一化后的数组（置顶帖在前）。
     *
     * v1.2 起本函数只负责**编排**：置顶、列表、评论三段怎么组合、
     * 置顶与列表如何去重、评论附加在哪一步 —— 这些是业务决策，留在本插件。
     * 而「怎么访问论坛」（超时、Accept 头、pool 的异常占位坑、
     * 字段归一化、纯文本清洗）已全部下沉到 kokuu-core。
     *
     * @return array{ok:bool,items:array,message:?string}
     */
    function kokuu_forum_fetch(int $limit): array
    {
        $cfg  = kokuu_forum_config();
        $base = $cfg['forum_url'] ?? '';

        // 缓存键必须包含置顶名单与评论条数：管理员改了任一项后若沿用旧键，
        // 在 TTL 内看到的仍是旧内容，会以为「保存没生效」。
        $ttl = (int) $cfg['cache_ttl'];
        $key = \KokuuStudio\KokuuCore\FlarumClient::cacheKey('forum_threads', $base, [
            'limit'    => $limit,
            'pinned'   => $cfg['pinned'],
            'replies'  => (int) $cfg['preview_replies'],
        ]);

        // 有缓存则直接返回；注意论坛为空时也要缓存，避免反复穿透
        if ($ttl > 0) {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $fail = function (string $msg) use ($ttl, $key) {
            $fallback = ['ok' => false, 'items' => [], 'message' => $msg];
            if ($ttl > 0) {
                Cache::put($key, $fallback, $ttl);
            }

            return $fallback;
        };

        if ($base === '') {
            return $fail('未配置论坛地址');
        }

        // ---- 1. 置顶帖 ----
        //
        // 必须单独请求而不能靠列表接口顺带拿到：置顶公告往往是「老帖」，
        // 早就不在「最近 N 条」里了，只取列表是永远抓不到的。
        // 并发由 FlarumClient 负责，pool 的Throwable占位坑也在那里封掉了。
        $pinnedItems = [];
        $pinnedSeen   = [];
        foreach (\KokuuStudio\KokuuCore\FlarumClient::fetchByIds($base, $cfg['pinned']) as $item) {
            if ($item['id'] === '') {
                continue;
            }
            $item['isPinned'] = true;
            $pinnedItems[]    = $item;
            $pinnedSeen[$item['id']] = true;
        }

        // ---- 2. 普通讨论列表 ----
        $list = \KokuuStudio\KokuuCore\FlarumClient::fetchDiscussions($base, $limit);
        if (! $list['ok']) {
            return $fail($list['message'] ?? '论坛数据格式异常');
        }

        $items = $pinnedItems;
        foreach ($list['items'] as $item) {
            // 置顶帖可能同时出现在最新列表里，去重（保留置顶那份）
            if ($item['id'] === '' || isset($pinnedSeen[$item['id']])) {
                continue;
            }
            $item['isPinned'] = false;
            $items[] = $item;
        }

        /*
         * ---- 3. 每帖的最新回复预览 ----
         *
         * 放在最后做，且**失败不影响前面的结果**：
         * 列表已经拿到了，评论只是附加信息。
         * 评论接口挂了最多是少显示几行字，不该让整个卡片变成「论坛不可达」。
         */
        $previewCount = (int) $cfg['preview_replies'];
        $replies = ($previewCount > 0 && ! empty($items))
            ? kokuu_forum_fetch_replies(array_map(fn ($it) => $it['id'], $items), $previewCount)
            : [];

        foreach ($items as &$it) {
            $it['replies'] = $replies[$it['id']] ?? [];
        }
        unset($it);

        $result = ['ok' => true, 'items' => $items, 'message' => null];

        if ($ttl > 0) {
            Cache::put($key, $result, $ttl);
        }

        return $result;
    }
}

if (! function_exists('kokuu_forum_relative_time')) {
    /**
     * 把 ISO8601 时间转成「3 天前」这类相对表述。
     * 解析失败时返回空串——前端会退化为显示绝对日期。
     */
    function kokuu_forum_relative_time(string $iso): string
    {
        if ($iso === '') {
            return '';
        }
        $ts = strtotime($iso);
        if ($ts === false) {
            return '';
        }

        $diff = time() - $ts;
        if ($diff < 60) {
            return '刚刚';
        }
        if ($diff < 3600) {
            return intdiv($diff, 60) . ' 分钟前';
        }
        if ($diff < 86400) {
            return intdiv($diff, 3600) . ' 小时前';
        }
        if ($diff < 2592000) {
            return intdiv($diff, 86400) . ' 天前';
        }

        return date('Y-m-d', $ts);
    }
}

if (! function_exists('kokuu_forum_avatar_color')) {
    /**
     * 由昵称派生的稳定色相，让头像底色有辨识度。
     * 同一昵称永远得到同一颜色（crc32 稳定），不会每次刷新都变。
     */
    function kokuu_forum_avatar_color(string $name): int
    {
        if ($name === '') {
            return 220;
        }

        return (int) (crc32($name) % 360);
    }
}

if (! function_exists('kokuu_forum_initial')) {
    /**
     * 取昵称首字作为头像文字。
     * 用 mb_substr 而非 substr，保证中文不被截成半个字（乱码方块）。
     */
    function kokuu_forum_initial(string $name): string
    {
        if ($name === '') {
            return '?';
        }
        $ch = mb_substr($name, 0, 1, 'UTF-8');

        return mb_strtoupper($ch, 'UTF-8');
    }
}

if (! function_exists('kokuu_forum_decorate')) {
    /**
     * 给 kokuu_forum_fetch() 的结果补上展示用字段。
     *
     * 为什么要单独一步：Twig 环境（rcrowe/twigbridge）**只暴露了**
     * trans / url / auth_user 等桥接函数，插件自定义的 kokuu_forum_*
     * 函数在模板里一律不可调用。所以所有派生计算必须在这里（PHP 侧）
     * 做完，模板只负责显示。
     */
    function kokuu_forum_decorate(array $result): array
    {
        $result['items'] = array_map(function ($it) {
            $it['lastPostedRel'] = kokuu_forum_relative_time((string) $it['lastPosted']);
            $it['avatarColor']   = kokuu_forum_avatar_color((string) $it['author']);
            $it['avatarInitial'] = kokuu_forum_initial((string) $it['author']);

            return $it;
        }, $result['items']);

        // 置顶与普通讨论分开放，模板里不必再做过滤判断，
        // 前端 JS 拿到的也是同一个分好组的结构，刷新后能原样重建。
        $result['pinned']   = array_values(array_filter(
            $result['items'],
            fn ($it) => ! empty($it['isPinned'])
        ));
        $result['regular']  = array_values(array_filter(
            $result['items'],
            fn ($it) => empty($it['isPinned'])
        ));
        $result['hasPinned'] = count($result['pinned']) > 0;

        return $result;
    }
}

if (! function_exists('kokuu_forum_i18n')) {
    /**
     * 模板与前端 JS 共用的文案表。
     *
     * 走 PHP 数组而非 lang 文件：Twig 侧要用{{ i18n|json_encode }} 注入
     * 给 JS，写成 lang 文件反而多一层间接；站点是单语言（中文），没必要。
     */
    function kokuu_forum_i18n(): array
    {
        return [
            'subtitle'         => '来自社区的最新讨论',
            'unit_threads'     => '条',
            'replies'          => '回复',
            'refresh'          => '刷新',
            'forum'            => '前往论坛',
            'pinned'           => '置顶',
            'empty'            => '论坛还没有帖子',
            'empty_hint'       => '成为第一个发帖的人吧。',
            'new_post'         => '发新帖',
            'unreachable'      => '暂时无法获取论坛内容',
            'unreachable_hint' => '论坛暂时联系不上，请稍后再试。',
            'retry'            => '重试',
            'just_now'         => '刚刚',
            'minutes_ago'      => ' 分钟前',
            'hours_ago'        => ' 小时前',
            'days_ago'         => ' 天前',
            'no_reply'         => '还没有人回复',
        ];
    }
}

return function ($plugin) {
    /*
     * ---- 注册插件视图目录 ----
     *
     * 内核仪表盘的 grid.twig 用 `{{ include('user.widgets.dashboard.forum') }}`
     * 渲染 widget，而这个 Twig loader（TwigBridge\Twig\Loader）最终是调
     * Laravel 的 view finder 去 **search path 里找同名文件**。
     * 默认 search path 只有 resources/views/overrides 与 resources/views，
     * **不含插件目录**，所以不注册的话会直接抛
     * 「Unable to find template user.widgets.dashboard.forum.twig」。
     *
     * addLocation 是**追加**到末尾（FileViewFinder::addLocation），所以
     * 插件模板优先级低于内核 —— 万一将来内核自己出了同名视图，
     * 内核的会赢，符合「内核优先」的直觉。
     *
     * 视图文件按 Laravel 命名约定放在 views/user/widgets/dashboard/forum.twig，
     * 点号即目录分隔。
     */
    View::addLocation($plugin->getPath() . '/views');

    /*
     * ---- 仪表盘容器（核心）----
     *
     * 用户要的是「在仪表盘内做一个容器显示一部分帖子」，而不是侧栏新开页面。
     *
     * 内核仪表盘（/user）是**可拖拽的 widget 网格**，由 UserController@index 组装：
     *   $grid = ['layout' => [['md-7','md-5']], 'widgets' => [[[...左列...], [...右列...]]]];
     *   $grid = $filter->apply('grid:user.index', $grid);
     * 然后 shared/grid.twig 逐个 {{ include(widget) }}。
     *
     * 所以挂 widget 只需往 widgets 里塞一个视图名（本例追加到右列，
     * 不新增行，故 layout 无需改动；若新增行则必须同时给 layout 补列宽数组，
     * 否则 shared/grid.twig 取 layout[loop.index0] 会越界）。
     *
     * 视图名用 `user.widgets.dashboard.forum` —— 落在
     * resources/views/user/widgets/dashboard/ 下，与内核自带的
     * announcement / usage 同级，视觉风格天然一致（AdminLTE card）。
     *
     * ⚠️ 这里必须用 `Filter::add()` 而**不是** `Event::listen()`：
     * `grid:user.index` 是 Blessing\Filter 的钩子（`$filter->apply()`），
     * 不是 Laravel 事件 —— 用 Event::listen 挂不上去，且**静默无效**（不报错）。
     * 且 Filter 回调**只接收 $grid 一个参数**（apply 的 $args 为空），
     * 返回值即替换后的 $grid —— 必须 return，漏了等于没改。
     *
     * ⚠️ Filter::add() 是**实例方法，不能静态调用**。
     *    写 Filter::add(...) 会抛
     *    "Non-static method Blessing\Filter::add() cannot be called statically"，
     *    而 PluginManager::loadBootstrapper 用 catch(\Throwable){ report($th); }
     *    **静默吞掉**这个异常 —— 表现就是插件「装了但什么都不生效」，
     *    没有报错、没有 500、日志里也只有一行 report 记录。极难排查。
     *    正确写法是 resolve(Filter::class)->add(...)，内核 Hook 类也是这么写的。
     */
    resolve(Filter::class)->add('grid:user.index', function ($grid) {
        $our = 'user.widgets.dashboard.forum';

        // 内核默认布局：第 1 行 = [左列(md-7), 右列(md-5)]
        // 论坛卡片放到右列**开头**：右列原本只有公告卡，公告被我们接管后
        // 右列只剩这一张卡，放开头视觉上与左列顶部的邮箱验证卡平齐。
        if (! isset($grid['widgets'][0][1]) || ! is_array($grid['widgets'][0][1])) {
            $grid['widgets'][0][1] = [];
        }
        if (! in_array($our, $grid['widgets'][0][1], true)) {
            array_unshift($grid['widgets'][0][1], $our);
        }

        // 接管内核「公告」卡片：公告能力并入本插件，改为置顶指定帖子实现。
        //
        // 为什么必须显式移除：内核 announcement widget 无条件出现在
        // grid 里，只要它在，右列就会一直挂着一个跟论坛内容重复的卡片，
        // 且它自己那份公告后台（site.announcement 选项）就没人用了。
        //
        // 用 array_filter 而不是 unset：widget 数组是**按下标 foreach** 的
        // （shared/grid.twig 用 loop.index0 取 layout），中间挖掉元素会
        // 造成下标不连续，必须重新 array_values 压平。
        foreach ($grid['widgets'] as $row => $columns) {
            foreach ($columns as $col => $widgets) {
                $grid['widgets'][$row][$col] = array_values(array_filter(
                    (array) $widgets,
                    fn ($w) => $w !== 'user.widgets.dashboard.announcement'
                ));
            }
        }

        return $grid;
    });

    /*
     * ---- widget 数据注入 ----
     *
     * ⚠️ 关键：widget 走的是 `{{ include('user.widgets.dashboard.forum') }}`，
     * **不是**控制器 `view('user.index')->with([...])`。
     * include 的模板**共享当前视图的变量，但拿不到控制器传的数据**，
     * 所以必须用 View::composer 往这个 widget 模板上挂数据。
     *
     * 只挂在 widget 模板上（精确匹配），不要用 '*'——
     * 否则每个页面渲染都会白跑一次论坛 HTTP 请求。
     *
     * 数据取不到时（论坛挂了）也要把变量挂上，只是 items 为空，
     * 让模板走「暂时无法获取」分支而不是抛未定义变量。
     */
    View::composer('user.widgets.dashboard.forum', function ($view) {
        $cfg = kokuu_forum_config();

        $view->with([
            'kokuu_forum_cfg'  => $cfg,
            'kokuu_forum_data' => kokuu_forum_decorate(kokuu_forum_fetch((int) $cfg['widget_limit'])),
            'kokuu_forum_i18n' => kokuu_forum_i18n(),
        ]);
    });

    /*
     * ---- 卡片刷新用的 JSON 接口 ----
     *
     * 数据来自本站的 /plugin/kokuu-forum/api/threads —— 那是服务端代理接口，
     * 已在 PHP 侧把 Flarum 的 JSON:API 拍平成普通 JSON，因此前端可以直接
     * fetch，不存在跨域问题（Flarum 本身不开 CORS，实测预检返回 405）。
     */
    Hook::addRoute(function ($router) {
        $router->get('plugin/kokuu-forum/api/threads', function (Request $request) {
            $cfg = kokuu_forum_config();

            // 允许 ?limit= 覆写，但钳制在 1~30，防止被刷
            $limit = (int) $request->query('limit', $cfg['widget_limit']);
            $limit = max(1, min(30, $limit));

            $data = kokuu_forum_decorate(kokuu_forum_fetch($limit));

            return response()->json([
                'code'       => $data['ok'] ? 0 : 1,
                'message'    => $data['message'],
                'forum'      => $cfg['forum_url'],
                'items'      => $data['items'],
                'pinned'     => $data['pinned'],
                'regular'    => $data['regular'],
                'hasPinned'  => $data['hasPinned'],
            ]);
        })->middleware(['web']);

        // 配置保存（仅管理员）
        //
        // 配置界面由 Blessing Skin 原生机制提供
        // （GET /admin/plugins/config/kokuu-forum，依赖 package.json 的 config 字段），
        // 此处仅提供保存 API 供其 fetch 调用。
        $router->post('plugin/kokuu-forum/settings', function (Request $request) {
            $user = $request->user();
            abort_if (! $user || $user->permission < User::ADMIN, 403);

            $url = trim((string) $request->input('forum_url', ''));
            // 只允许 http/https，防止 javascript: 伪协议注入
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            abort_if (! in_array($scheme, ['http', 'https'], true), 400, '论坛地址无效');

            $title = trim((string) $request->input('menu_title', ''));

            // 置顶名单：逗号分隔字符串 → 存成规范化后的逗号串。
            // 这里先 trim 每个元素再剔掉非数字项，比直接 intval 整个数组稳：
            // 用户在后台很容易粘成 "2, 5,，7" 这种带空格/中文逗号的形态。
            $raw = str_replace(['，', '、', ' '], ',', (string) $request->input('pinned', ''));
            $ids = array_slice(array_values(array_unique(array_filter(
                array_map('intval', explode(',', $raw)),
                fn ($id) => $id > 0
            ))), 0, 10);

            kokuu_forum_save_config([
                'forum_url'    => rtrim($url, '/'),
                'menu_title'   => $title !== '' ? mb_substr($title, 0, 12) : '社区',
                'widget_limit' => max(1, min(30, (int) $request->input('widget_limit', 6))),
                'cache_ttl'    => max(0, min(3600, (int) $request->input('cache_ttl', 60))),
                'pinned'       => implode(',', $ids),
                'preview_replies' => max(0, min(5, (int) $request->input('preview_replies', 2))),
            ]);

            // ⚠️ 必须删掉 storage/options.php，否则配置永远不生效。
            //
            // Blessing Skin 的 App\Services\Option 构造函数**优先读这个文件**，
            // 存在即 return，根本不查数据库。所以只要它在，
            // 刚才 set() 写进DB 的值永远读不出来（表现为「保存成功但配置没变」）。
            // 内核自己也是这么处理的（UpdateController 升级时删同一文件）。
            //
            // 用 delete 而不是重新生成：重建需要 Artisan::call('options:cache')，
            // 在HTTP 请求里跑 Artisan 既慢又有副作用；直接删掉即可，
            // 下次任意请求会走DB 分支自动重建，功能不受影响。
            @unlink(storage_path('options.php'));

            // 只清本插件自己的缓存键，不动全站缓存。
            //
            // ⚠️ 不要用 Cache::flush()：那是把**整个缓存后端**清空，
            // 在 file 驱动下等于把 storage/framework/cache 全删，
            // 若站点把 session 也放缓存里，用户会被集体踢下线。
            // 本插件的缓存键带 forum_url|limit|pinned 前缀的 md5，
            // 无法反查，所以最稳的做法是逐个前缀删——
            // Cache::forget 支持通配吗？不支持，因此这里退而求其次：
            // 换配置后新键自然不同（键里含 limit 与 pinned），
            // 旧键只是占点空间，等 TTL 到期自然淘汰，无需主动清理。
            // forum_url 变更时同理：新旧键不同，旧键自动过期。

            return response()->json(['code' => 0, 'message' => 'Saved.']);
        })->middleware(['web']);
    });

    // ---- 页面样式 ----
    // 仪表盘（含仪表盘内所有子路由）与插件配置页都要有样式：
    // 'user' 只匹配 /user 本身，仪表盘的子页面（如 /user/notice）是别的路径，
    // 这里用 'user/*' 一并覆盖，免得拖拽排序后跳过去样式丢了。
    Hook::addStyleFileToPage($plugin->assets('thread.css'), [
        'user', 'user/*',
        'admin', 'admin/*',
        'plugin/kokuu-forum/*',
    ]);

    // 刷新脚本：仪表盘卡片点「刷新」时局部重渲染，不必整页跳。
    Hook::addScriptFileToPage($plugin->assets('widget.js'), ['user', 'user/*']);

    // ---- 配置页 ----
    // package.json 的 "config": "config.blade.php" 会被 PluginController@config
    // 用 view()->file() 渲染，内核会自动在插件管理页显示「设置」按钮。
    // 这里不用再注册路由。
};