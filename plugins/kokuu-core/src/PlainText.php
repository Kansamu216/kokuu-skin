<?php

namespace KokuuStudio\KokuuCore;

if (! class_exists(PlainText::class, false)) {
    /**
     * 把 Flarum 的 contentHtml 洗成一段安全的纯文本。
     *
     * ═══════════════════════════════════════════════════════════
     *  这是整条 Flarum 数据链上唯一的 XSS 入口，必须处理干净
     * ═══════════════════════════════════════════════════════════
     *  contentHtml 是论坛侧渲染好的 HTML，可能是
     *  <p>、<a href>、<strong>、<img src>、<blockquote> 等。
     *  论坛本身是半可信的（任何注册用户都能发帖），
     *  如果把这段 HTML 直接塞进仪表盘的模板或 JS 里，
     *  一个精心构造的 <img onerror> 或 <script> 就能在皮肤站域下执行
     *  —— 皮肤站是登录态页面，等于直接偷走用户的 CSRF token。
     *
     * 做法：先剥标签，再反转义实体，最后压缩空白。
     *
     * ⚠️ 反转义必须放在 strip_tags **之后**：
     *    否则 &lt;script&gt; 会被剥成空、再把实体解回真实标签，
     *    等于给攻击者造了一条绕过路径。
     *
     * ⚠️ 为什么不用 e() / htmlspecialchars：
     *    那是「保留 HTML 只做实体转义」，语义和这里要的
     *    「彻底转纯文本」不同。卡片里只需要一句摘要文字，任何标签都是多余的。
     */
    final class PlainText
    {
        /**
         * @param string $html Flarum 的 contentHtml
         * @param int    $limit 最多保留多少字符
         */
        public static function from(string $html, int $limit = 90): string
        {
            if ($html === '') {
                return '';
            }

            // 1) 块级标签换成空格，否则 <p>a</p><p>b</p> 会粘成 "ab"
            $text = preg_replace('#<(br|/p|/div|/li|/blockquote|/h[1-6])[^>]*>#i', ' ', $html);
            // 2) 剥掉所有剩余标签（script/style 的内容也一并丢掉）
            $text = strip_tags((string) $text);
            // 3) 反转义实体：&lt; &gt; &amp; &quot; &#039; &nbsp; &hellip; 等
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // 4) 压缩连续空白（含不换行空格）为单个空格并去首尾
            $text = preg_replace('/[\s\x{00a0}]+/u', ' ', $text);
            $text = trim((string) $text);

            // 5) 按字符截断（mb_substr 多字节安全，中文不会被切半个字）
            if (mb_strlen($text) > $limit) {
                $text = rtrim(mb_substr($text, 0, $limit)) . '…';
            }

            return $text;
        }
    }
}
