<?php

use App\Models\User;
use App\Services\Hook;
use Illuminate\Http\Request;

/*
 * 提瓦特一言（kokuu-quote）
 *
 * 依赖 kokuu-home 与 kokuu-ui 两个主题插件，以与其视觉语言保持一致
 * （依赖关系在 package.json 的 require 中声明；若依赖未满足，
 *   Blessing Skin 会自动禁用本插件，因此请先启用前两个插件）。
 *
 * 功能：
 *   - 台词库：内置 quotes.php（挪德卡莱「空月之歌」40 条），
 *             用户增删的内容保存在 storage/plugins/kokuu-quote/quotes.custom.php
 *   - API：GET /plugin/kokuu-quote/random[?tag=&el=] 返回一条随机台词（JSON）
 *   - 前端：quote.js + quote.css 在后台顶栏右侧展示一条短句，点击换一句
 *   - 管理：原生配置页 /admin/plugins/config/kokuu-quote 增删台词、恢复内置库
 *
 * 【数据存储】用户数据一律写入 storage/ 目录，不写插件目录——
 *   插件目录在生产环境常为只读，且升级 / 重装插件时会被覆盖。
 *
 * 【版权】台词著作权归上海米哈游天命科技有限公司所有，
 *        仅作个人学习与非商业展示；公开运营请自行评估授权。
 *
 * ⚠️ 重要：辅助函数必须定义在下方「return 闭包」之**前**。
 *    PHP 的 include/require 一旦在文件顶层遇到 return，就立即停止执行该文件
 *    剩余部分——若把函数写在 return 之后，它们永远不会被声明，调用时将报
 *    "Call to undefined function"。此处用 function_exists 包裹，
 *    同时防止本文件被重复加载时声明冲突。
 */


/*
 * 自定义词库文件路径（放在 storage 而非插件目录，见文件头说明）。
 */
if (! function_exists('kokuu_quote_custom_path')) {
    function kokuu_quote_custom_path(): string
    {
        return storage_path('plugins/kokuu-quote/quotes.custom.php');
    }
}

/*
 * 读取全部台词：优先用户自定义库，回退内置 quotes.php。
 * 进程内静态缓存，避免同一请求内重复读盘。
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

        if (!is_array($data) || $data === []) {
            $data = require __DIR__ . '/quotes.php';
        }

        // 规范化：补齐缺失字段、过滤空正文，防止前端拿到异常结构
        $clean = [];
        foreach ($data as $q) {
            if (!is_array($q) || trim((string) ($q['text'] ?? '')) === '') {
                continue;
            }
            $clean[] = [
                'text' => (string) $q['text'],
                'by'   => (string) ($q['by'] ?? '佚名'),
                'tag'  => (string) ($q['tag'] ?? '日常'),
                'el'   => (string) ($q['el'] ?? ''),
            ];
        }

        // 词库完全为空时的兜底，保证前端不会拿到空数组
        return $cache = ($clean ?: [[
            'text' => '风带来了故事的种子。',
            'by'   => '佚名',
            'tag'  => '日常',
            'el'   => '风',
        ]]);
    }
}

/*
 * 保存自定义台词库到 storage（自动创建目录）。
 */
if (! function_exists('kokuu_quote_save')) {
    function kokuu_quote_save(array $quotes): void
    {
        $path = kokuu_quote_custom_path();
        $dir  = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $php = "<?php\n\n"
            . "/* 自定义台词库（由插件配置页生成）。可自由编辑，删除本文件即回退内置库。 */\n\n"
            . 'return ' . var_export($quotes, true) . ";\n";

        file_put_contents($path, $php);
    }
}

/*
 * 恢复内置台词库（删除自定义文件）。
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
 * 按字符数裁剪文本（多字节安全）。
 */
if (! function_exists('kokuu_quote_clip')) {
    function kokuu_quote_clip(string $text, int $limit): string
    {
        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit) : $text;
    }
}

return function ($plugin) {
    Hook::addStyleFileToPage($plugin->assets('quote.css'), ['*']);

    /*
     * 提瓦特元素样式（v1.2 从 kokuu-ui 迁入本插件）。
     *
     * 迁移原因：元素装饰原本由 kokuu-ui 用 ['*'] 全站注入，
     * 导致**首页也被铺上提瓦特元素**——首页是 MC 像素插画风格，
     * 和提瓦特水印/页脚署名混在一起既不统一也干扰内容。
     * 现在元素只服务于一言这一个功能，作用域收窄到 .kq-item。
     *
     * 注入范围排除首页（'/' 与 ''），首页本来就不显示一言，
     * 也没必要加载这份CSS。
     */
    Hook::addStyleFileToPage(
        $plugin->assets('teyvat.css'),
        ['user', 'user/*', 'admin', 'admin/*', 'skinlib', 'skinlib/*']
    );

    Hook::addScriptFileToPage($plugin->assets('quote.js'), ['*']);

    Hook::addRoute(function ($router) {
        // ---- 随机一言 API（公开，供前端顶栏调用）----
        $router->get('plugin/kokuu-quote/random', function (Request $request) {
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
        // 配置界面由 Blessing Skin 原生机制提供（GET /admin/plugins/config/kokuu-quote），
        // 此处仅提供操作 API 供其 fetch 调用。
        $router->post('plugin/kokuu-quote/manage', function (Request $request) {
            $user = $request->user();
            abort_if(!$user || $user->permission < User::ADMIN, 403);

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
                    if (!isset($all[$idx])) {
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
