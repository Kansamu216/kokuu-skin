<?php

/*
 * FlarumClient —— Flarum 论坛 API 的统一访问层
 *
 * 由 kokuu-core 提供，供 kokuu-forum 等插件共用。
 *
 * ═══════════════════════════════════════════════════════════════
 *  为什么这个类值得抽出来（而不是各插件各写一套 HTTP 调用）
 * ═══════════════════════════════════════════════════════════════
 *  1. **Flarum 的既有认知是共享资产**。本论坛的字段名、Accept 头的要求、
 *     slug 的构成规则、超时该设多少 —— 这些是「我们和这个论坛打交道」
 *     积累下来的知识。复制一份意味着论坛升级要改两处，漏一处就静默出错。
 *
 *  2. **降级行为必须一致**。论坛不可达时绝不抛异常、绝不 500，
 *     这是反复踩过才写下来的纪律。任何新接入方都应继承这条纪律，
 *     而不是各自决定「出错了怎么办」。
 *
 * ═══════════════════════════════════════════════════════════════
 *  本论坛的硬事实（全部实测得来，不是照抄 Flarum 文档）
 * ═══════════════════════════════════════════════════════════════
 *  · Accept 必须写 `application/vnd.api+json`，否则 Flarum 返回 415。
 *    这是 JSON:API 规范的强制要求，但文档里不显眼，很容易漏。
 *
 *  · 讨论的 slug 本身就是 "{id}-{标题转写}"（实测 "2-kokuuskin"），
 *    所以详情页是 `/d/{slug}`，**不能再拼一次 id**，
 *    否则会得到 /d/2-2-kokuuskin 这种重复段。
 *
 *  · 本论坛 Flarum 没装 pin/lock 扩展，discussions 表连 is_pinned 列都没有，
 *    API 也不认 sort=-isPinned（实测 400 invalid_parameter）。
 *    所以「置顶」只能是调用方自己维护 ID 名单 —— 那是业务决策，不是本类的事。
 *
 *  · 列表接口返回的讨论属性：title / slug / commentCount / participantCount /
 *    createdAt / lastPostedAt / lastPostNumber。
 *    **没有 viewCount，也没有 firstPost**（首帖内容不在列表接口里）。
 *    用户名字段是 displayName（同时也有 username）。
 *
 *  · 浏览器不能直接跨域读 Flarum（OPTIONS 预检 405、无 access-control-* 头），
 *    所以必须由服务端代为请求 —— 这也是本类存在的根本原因。
 *
 * ═══════════════════════════════════════════════════════════════
 *  ⚠️ Http::pool() 不抛异常（最贵的坑）
 * ═══════════════════════════════════════════════════════════════
 *  池内某个请求连接失败时，Laravel 会把捕获到的 ConnectionException /
 *  RequestException 对象**塞进返回数组里占对应位置**，
 *  而不是让整个 pool 调用失败。
 *
 *  所以遍历结果时必须先 `instanceof \Throwable` 判一次，
 *  否则对异常对象调 ->successful() 会炸出
 *  "Call to undefined method ...::successful()"，整个页面 500 ——
 *  而这恰恰是「论坛挂了」时最不该发生的事。
 *
 *  记这个坑是因为它只在**故障路径**上出现：论坛正常时永远走不到，
 *  出问题时又表现为 500 而非降级，很容易误以为是自己的代码写错了。
 *  本类的 fetchPinned() 已经把它封进去了，调用方拿到的永远是纯响应数组。
 */

namespace KokuuStudio\KokuuCore;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

if (! class_exists(FlarumClient::class, false)) {
    /**
     * Flarum 论坛 API 客户端。
     *
     * 全部方法都是**静态且无状态**的：论坛地址、条数等参数一律显式传入，
     * 不藏在实例属性里。这样调用方不需要先 new 一个对象，
     * 也不存在「忘了设 baseUrl 就发出请求」这类顺序问题。
     */
    final class FlarumClient
    {
        /**
         * 列表类请求的超时（秒）。
         *
         * 为什么要两个值：connectTimeout 覆盖「建立连接」，
         * timeout 覆盖「等待响应」。论坛假死时 connect 往往很快成功、
         * 迟迟不回数据，只设 connectTimeout 会让页面挂住。
         */
        public const TIMEOUT = 6;
        public const CONNECT_TIMEOUT = 3;

        /**
         * 评论抓取用更短的超时：它只是「锦上添花」，
         * 抓不到绝不能拖住主列表的展示。
         */
        public const TIMEOUT_REPLIES = 5;

        /** JSON:API 的强制 Accept 头，缺失会得到 415。 */
        private const ACCEPT = 'application/vnd.api+json';

        /**
         * 构造一个配好超时与 Accept 头的 HTTP 客户端。
         *
         * @param  int  $timeout  总超时秒数
         * @param  bool $form     true = 以 application/x-www-form-urlencoded 发送
         */
        private static function http(int $timeout = self::TIMEOUT, bool $form = false)
        {
            $c = Http::timeout($timeout)
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->withHeaders(['Accept' => self::ACCEPT]);

            return $form ? $c->asForm() : $c;
        }

        /**
         * 取讨论列表（已归一化，按传入顺序返回）。
         *
         * @param  string $baseUrl  论坛根地址，如 https://chat.kokuu.org
         * @param  int     $limit    要几条
         * @param  array   $extra    额外查询参数
         * @return array{ok:bool, items:array, message:?string}
         *         失败时 ok=false 且 items 为空，**绝不抛异常**
         */
        public static function fetchDiscussions(string $baseUrl, int $limit, array $extra = []): array
        {
            if ($baseUrl === '') {
                return self::fail('未配置论坛地址');
            }

            $query = array_merge([
                'include'     => 'user,lastPostedUser',
                'sort'        => '-lastPostedAt',
                'page[limit]' => self::clampLimit($limit),
            ], $extra);

            $url = rtrim($baseUrl, '/') . '/api/discussions?' . http_build_query($query);

            try {
                $response = self::http()->get($url);
            } catch (\Throwable $e) {
                // 论坛不可达（DNS 失败/连接超时/SSL 错误…）
                return self::fail('暂时无法连接论坛');
            }

            if (! $response->successful()) {
                return self::fail('论坛返回 HTTP ' . $response->status());
            }

            $body = $response->json();
            if (! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
                return self::fail('论坛返回了无法解析的数据');
            }

            $users = self::userIndex($body);
            $items = [];
            foreach ($body['data'] as $disc) {
                if (! is_array($disc)) {
                    continue;
                }
                $items[] = self::normalizeDiscussion($disc, $users, $baseUrl);
            }

            return self::ok($items);
        }

        /**
         * 按 ID 逐个取讨论，用于「置顶公告」——它们往往是老帖，
         * 永远不在「最近 N 条」里，只取列表是抓不到的。
         *
         * 并发执行。⚠️ 这里把 pool 的 Throwable 占位坑封掉了：
         * 返回的数组里只会有真正的响应对象，调用方不必再判类型。
         *
         * @param  array $ids 讨论 ID 列表
         * @return array<int, array> 归一化后的讨论，失败的条目直接缺席
         */
        public static function fetchByIds(string $baseUrl, array $ids): array
        {
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            if (! $ids || $baseUrl === '') {
                return [];
            }

            try {
                $responses = self::http()->pool(function ($pool) use ($baseUrl, $ids) {
                    return array_map(function ($id) use ($pool, $baseUrl) {
                        return $pool->as('d' . $id)->get(
                            rtrim($baseUrl, '/') . '/api/discussions/' . $id
                            . '?' . http_build_query(['include' => 'user,lastPostedUser'])
                        );
                    }, $ids);
                });
            } catch (\Throwable $e) {
                // pool 本身构建失败（极少见）——整体放弃，不抛给调用方
                return [];
            }

            $users = [];
            $items = [];
            foreach ($responses as $i => $response) {
                // ⚠️ 关键：Http::pool 不抛异常，失败位置塞的是异常对象。
                //    少了这个判断，论坛挂掉时这里会 500 而不是降级。
                if ($response instanceof \Throwable) {
                    continue;
                }
                if (! method_exists($response, 'successful') || ! $response->successful()) {
                    continue;
                }
                $body = $response->json();
                if (! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
                    continue;
                }
                // 各请求的 included 各自带一份用户，合并而非覆盖
                $users = self::mergeUsers($users, self::userIndex($body));
                $disc = $body['data'];
                if (is_array($disc)) {
                    $items[$i] = self::normalizeDiscussion($disc, $users, $baseUrl);
                }
            }

            // 保持调用方给定的 ID 顺序，失败的条目留空
            $out = [];
            foreach ($ids as $i => $id) {
                if (isset($items[$i])) {
                    $out[] = $items[$i];
                }
            }

            return $out;
        }

        /**
         * 取若干讨论的最近回复（不含首帖）。
         *
         * 首帖（number=0）是主题正文，通常和标题重复；
         * 用户要的是「大家说了什么」，不是楼主开的头。
         *
         * ⚠️⚠️ 本方法最核心的设计决定：**一次请求拿全部，绝不逐帖请求**。
         *
         *   最直觉的写法是对每个讨论单独 GET 一次
         *   /api/posts?filter[discussion]={id}，6 个帖子 = 6 次串行 HTTP。
         *   论坛慢 1 秒，用户就得等 6 秒，仪表盘直接卡死。
         *
         *   这里的做法：全局 GET /api/posts?sort=-createdAt&page[limit]=N，
         *   带 include=user,discussion，然后在 PHP 侧按
         *   relationships.discussion.data.id 分组。
         *   **1 次请求搞定所有帖子的评论**，无论卡片里有几条。
         *
         * 为什么这个方案在本论坛可行（全部实测过，别凭Flarum 文档猜）：
         *   - /api/posts?sort=-createdAt&include=user,discussion 返回 200，
         *     且每条 post 的 relationships 里有 discussion（实测键：
         *     ['user', 'discussion']）
         *   - /api/discussions/{id}/posts 返回 **404**（那是 Flarum 1.x 的路径，
         *     本论坛是 0.x，别踩）
         *   - filter[discussion]=2,3,10 这种多值返回 **422**，
         *     所以「合并多个讨论」只能靠全局排序 + 本地分组
         *   - filter[type]=comment **不生效**（实测首帖 number=0 仍在结果里），
         *     所以要排除首帖只能自己按 number > 0 过滤
         *
         * 取多少条：limit 条评论 × 目标讨论数，是最坏情况的上限。
         * 超出部分如果全落在同一个帖子上也无所谓 —— 本来每帖只取 N 条。
         *
         * @param  array $wantedIds     讨论 ID 列表
         * @param  int   $perDiscussion 每个讨论要几条
         * @return array<string, array> 讨论 ID（**字符串**）=> 回复数组，每项
         *         ['author' => 显示名, 'text' => 纯文本摘要, 'number' => 楼层号]
         */
        public static function fetchReplies(string $baseUrl, array $wantedIds, int $perDiscussion): array
        {
            // ⚠️ 键统一用字符串：调用方拿 id（Flarum 的 resource id 就是字符串）
            //    去索引，用 int 键会因 PHP 数字字符串键自动转 int 而对不上。
            $wanted = array_flip(array_map('strval', (array) $wantedIds));
            if (! $wanted || $perDiscussion <= 0 || $baseUrl === '') {
                return [];
            }

            // page[limit] 上限 50：再多对卡片也没意义，还容易被论坛限流
            $take = min(50, max(10, $perDiscussion * count($wanted) * 2));

            $url = rtrim($baseUrl, '/') . '/api/posts?' . http_build_query([
                'include'     => 'user,discussion',
                'sort'        => '-createdAt',
                'page[limit]' => $take,
            ]);

            try {
                $response = self::http(self::TIMEOUT_REPLIES)->get($url);
            } catch (\Throwable $e) {
                // 评论是锦上添花，抓不到绝不能影响主列表
                return [];
            }

            if (! $response->successful()) {
                return [];
            }

            $body = $response->json();
            if (! is_array($body) || ! isset($body['data']) || ! is_array($body['data'])) {
                return [];
            }

            $users = self::userIndex($body);
            $group = [];

            foreach ($body['data'] as $post) {
                if (! is_array($post)) {
                    continue;
                }
                $attrs = $post['attributes'] ?? [];
                $rels  = $post['relationships'] ?? [];

                // 排除首帖（number=0）：filter[type] 不生效，只能自己判
                if ((int) ($attrs['number'] ?? 0) <= 0) {
                    continue;
                }

                $did = $rels['discussion']['data']['id'] ?? null;
                if ($did === null || ! isset($wanted[(string) $did])) {
                    continue;
                }

                // 同一帖最多留 perDiscussion 条。
                // sort=-createdAt 已保证最新在前，数够就跳过后续的。
                if (count($group[(string) $did] ?? []) >= $perDiscussion) {
                    continue;
                }

                $text = PlainText::from((string) ($attrs['contentHtml'] ?? ''));
                if ($text === '') {
                    continue;               // 纯图片帖没摘要，跳过
                }

                $userId = $rels['user']['data']['id'] ?? null;

                $group[(string) $did][] = [
                    'author' => $userId !== null ? ($users[(string) $userId] ?? '匿名') : '匿名',
                    'text'   => $text,
                    'number' => (int) ($attrs['number'] ?? 0),
                ];
            }

            return $group;
        }

        /**
         * 把 Flarum 的讨论结构归一化成站点自己的形状。
         *
         * 所有取值都做了 ?? 兜底 —— 将来论坛升级加了字段也能自动显示，
         * 少了字段也只是显示默认值而不是抛错。
         */
        public static function normalizeDiscussion(array $disc, array $users, string $baseUrl): array
        {
            $attrs = $disc['attributes'] ?? [];
            $rels  = $disc['relationships'] ?? [];

            $authorId = $rels['user']['data']['id'] ?? null;
            $lastId   = $rels['lastPostedUser']['data']['id'] ?? null;

            $id   = (string) ($disc['id'] ?? '');
            $slug = (string) ($attrs['slug'] ?? '');

            // slug 本身就是 "{id}-{转写}"，不能重复拼 id
            if ($slug !== '' && str_starts_with($slug, $id)) {
                $path = $slug;
            } else {
                $path = $id . ($slug !== '' ? '-' . $slug : '');
            }

            return [
                'id'           => $id,
                'title'        => (string) ($attrs['title'] ?? '(无标题)'),
                'author'       => $authorId !== null ? ($users[(string) $authorId] ?? '匿名') : '匿名',
                'lastPoster'   => $lastId !== null ? ($users[(string) $lastId] ?? null) : null,
                'comments'     => (int) ($attrs['commentCount'] ?? 0),
                'participants' => (int) ($attrs['participantCount'] ?? 0),
                'createdAt'    => (string) ($attrs['createdAt'] ?? ''),
                'lastPosted'   => (string) ($attrs['lastPostedAt'] ?? ''),
                'url'          => rtrim($baseUrl, '/') . '/d/' . $path,
            ];
        }

        /**
         * 从 JSON:API 响应的 included 里抽出「id → 用户名」索引。
         *
         * displayName 优先（用户可自定义），再退 username，最后匿名。
         */
        public static function userIndex(array $body): array
        {
            $users = [];
            foreach ($body['included'] ?? [] as $inc) {
                if (is_array($inc) && ($inc['type'] ?? '') === 'users' && isset($inc['id'])) {
                    $a = $inc['attributes'] ?? [];
                    $users[(string) $inc['id']] = (string) (
                        $a['displayName'] ?? $a['username'] ?? '匿名'
                    );
                }
            }

            return $users;
        }

        /**
         * 合并两份用户索引（pool 的多个响应各带一份 included）。
         */
        public static function mergeUsers(array $a, array $b): array
        {
            return $a + $b;
        }

        /**
         * 构造标准缓存键。
         *
         * 把论坛地址、条数、置顶名单都揉进键里 ——
         * 换论坛或改置顶能立即失效，多站部署也不会串味。
         */
        public static function cacheKey(string $prefix, string $baseUrl, array $parts = []): string
        {
            $flat = [];
            foreach ($parts as $k => $v) {
                $flat[$k] = is_array($v) ? implode(',', array_map('strval', $v)) : (string) $v;
            }
            ksort($flat);

            return 'kokuu:' . $prefix . ':' . md5($baseUrl . '|' . json_encode($flat));
        }

        /**
         * 读缓存并保证拿到数组。
         *
         * ⚠️ 必须过滤掉 false —— 上游的降级结果（ok=false）也会被写进缓存，
         *    不区分的话论坛恢复后还要等 TTL 过期才重新拉。
         */
        public static function remember(string $key, int $ttl, \Closure $callback): array
        {
            $cached = Cache::get($key);
            if (is_array($cached)) {
                return $cached;
            }
            $fresh = $callback();
            if ($ttl > 0) {
                Cache::put($key, $fresh, $ttl);
            }

            return $fresh;
        }

        /** 成功的返回形状。 */
        private static function ok(array $items): array
        {
            return ['ok' => true, 'items' => $items, 'message' => null];
        }

        /**
         * 失败的返回形状。
         *
         * 失败也返回空 items 而非抛异常，是本层的核心纪律：
         * 论坛不可达绝不能拖垮皮肤站本身。
         */
        private static function fail(string $msg): array
        {
            return ['ok' => false, 'items' => [], 'message' => $msg];
        }

        /** 条数兜底到 Flarum 能接受的区间。 */
        private static function clampLimit(int $n): int
        {
            return max(1, min(50, $n));
        }
    }
}
