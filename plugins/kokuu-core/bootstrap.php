<?php

/*
 * KokuuSkin 共享内核（kokuu-core）
 *
 * 本插件**不含任何业务功能**，只放「被多个插件共用、且必须行为一致」的东西。
 * 判断某段代码该不该放这里的标准只有一条：
 *   —— 如果两个插件各自实现一遍，行为就会**不一样**（超时不同、降级不同、
 *      字段兜底不同），那就该抽到��里。
 * 反之，��看某段代码只服务一个业务功能，就留在那个插件里别动。
 *
 * 当前内容：FlarumClient
 *   统一 Flarum 论坛 API 的访问方式。抽出来的原因：
 *     - kokuu-forum 878 行 bootstrap ��有一半是 HTTP 调用与字段解析，
 *       却藏在「论坛聚合」这个不相关的名字下；
 *     - 超时值、降级行为、Flarum 字段名这些属于「对论坛的既有认知」，
 *       复制一份就意味着以后论坛升级要改两个地方，漏一个就静默出错。
 *
 * 依赖它的插件：kokuu-forum
 * 若要给 kokuu-credit 也接上（目前它只有一处自检 POST，收益有限），
 * 记得把 require 补上。
 */

return function ($plugin) {
    /*
     * 本插件目前不注入任何东西。
     * FlarumClient 是纯函数式工具类，在 bootstrap.php 顶层 require 时
     * 即已可用（见下方），无需注册到容器。
     *
     * 保留这个 return 闭包是因为 Blessing Skin 要求 bootstrap.php
     * 必须返回一个可调用闭包 —— 哪怕它是空的。
     */
};
