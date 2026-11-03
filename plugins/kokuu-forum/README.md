# kokuu-forum · 论坛聚合

在 Blessing Skin（皮肤站）里聚合 Flarum 论坛内容，让皮肤站作为主站承载入口，
论坛负责内容沉淀。

![设计目标](https://img.shields.io/badge/Blessing%20Skin-6.0-0FA3A3?style=flat-square)
![PHP](https://img.shields.io/badge/PHP-%3E%3D8.1-8B7FD4?style=flat-square)

---

## 功能

| 位置 | 内容 |
|------|------|
| 侧边栏 | 新增「社区」入口（带子菜单：最新讨论 / 前往论坛） |
| 讨论列表页 | 展示论坛最新讨论，含作者、相对时间、回复数、头像色 |
| 配置页 | 论坛地址、菜单名、展示条数、缓存秒数，含连通性自检 |
| JSON API | 服务端代理 Flarum JSON:API，前端可直接 fetch |

## 核心设计

### 为什么必须服务端代理

Flarum 默认**不返回 CORS 头**（实测 `OPTIONS /api/discussions` 返回 405，
无 `access-control-*`），浏览器无法跨域直读。所以由本插件用 Laravel `Http::get()`
代为请求，再吐出拍平后的普通 JSON，彻底规避跨域。

### 字段按实测写死，不臆测

本论坛 Flarum 版本返回的讨论属性只有：

```
title / slug / commentCount / participantCount
createdAt / lastPostedAt / lastPostNumber
```

**没有 `viewCount`，也没有 `firstPost`**（首帖正文不在列表接口里）。
用户名字段是 `displayName`（同时也有 `username`）。
代码里所有取值都做了 `??` 兜底，论坛升级加了字段能自动显示。

### 讨论链接拼接

Flarum 的 `slug` 本身就是 `"{id}-{标题转写}"`（实测 `"2-kokuuskin"`），
所以详情页是 `/d/{slug}`，**不能再拼一次 id**。代码里做了 `str_starts_with`
判断，万一某版本 slug 不含 id 则退回 `/d/{id}`。

### 配置保存必须删 options.php

Blessing Skin 的 `App\Services\Option` 构造函数**优先读 `storage/options.php`**，
存在即 `return`，根本不查数据库。所以配置写进 DB 后若不删这个文件，
永远读不出来（表现为「保存成功但配置没变」）。

本插件在保存后 `@unlink(storage_path('options.php'))`，
下次请求自动走 DB 分支重建。内核自己也是这么处理的（`UpdateController` 升级时删同一文件）。

> 注意：**所有**通过 `app('options')->set()` 写配置的 Blessing Skin 插件都有这个坑。
> `kokuu-home` 已同步修复。

### Twig 模板的三个限制

页面用 **Twig** 而非 Blade，因为内核后台布局（`user/base.twig`）是 Twig，
Blade 无法 `extends` 它；用 Blade 自包含 HTML 就拿不到内核侧边栏。

`rcrowe/twigbridge` 的限制：

1. 只有 `trans()` / `url()` / `auth_user()` 等桥接函数，**没有 `t()`**
2. **插件自定义的 `kokuu_forum_*` 函数在模板里一律不可调用**
3. 因此所有派生计算（头像色、相对时间、文案）都在 PHP 侧做完再传进模板

渲染方式：`view()->file($plugin->getPath().'/views/thread.twig')`。
Twig 的 `Loader::findTemplate()` 支持绝对路径，所以扩展名是 `.twig` 就会自动选 Twig 引擎。
**故意不放进 `resources/views/overrides/`** —— 那是站点级定制目录，插件不该污染它。

## 目录结构

```
kokuu-forum/
├── bootstrap.php              核心逻辑：配置读写、API 代理、菜单注入、3 个路由
├── package.json               插件元数据（依赖 kokuu-home ^1.0）
├── assets/
│   └── thread.css             讨论列表样式（复用 kokuu-home 的设计 token）
└── views/
    ├── thread.twig            讨论列表页（继承 user.base.twig）
    └── config.blade.php       配置页（Blade，因内核用 view()->file() 渲染 config）
```

## 安装

1. 上传到 `plugins/kokuu-forum/`
2. 资源同步到 `public/plugins/kokuu-forum/assets/`
3. 授权：`chown -R www-data:www-data plugins public/plugins && chmod -R 775`
4. 启用插件（依赖 `kokuu-home` 必须已启用）
5. **清路由缓存**：`php artisan route:cache && php artisan route:clear`
   —— 路由缓存生成时插件路由不在其中，不清会 404
6. **重载 PHP-FPM**：`systemctl reload php8.1-fpm`
   —— OPcache 会缓存旧的 routes-v7.php

访问 `/admin/plugins/config/kokuu-forum` 配置论坛地址。

## 路由

| 方法 | 路径 | 用途 |
|------|------|------|
| GET | `/plugin/kokuu-forum/thread` | 讨论列表页 |
| GET | `/plugin/kokuu-forum/api/threads?limit=N` | JSON API（1~20） |
| POST | `/plugin/kokuu-forum/settings` | 保存配置（仅管理员） |

## 安全

- 配置接口校验 `$user->permission >= User::ADMIN`（403）
- 论坛地址只允许 `http` / `https` scheme，拒绝 `javascript:` 等伪协议（400）
- `limit` 钳制在 1~20，防止被刷
- 前端所有用户数据经 `esc()` 转义后再拼进 HTML
- 条数/缓存秒数在后端二次钳制，不信任前端

## 优雅降级

论坛不可达（DNS 失败、超时、非 200、数据格式异常）时：

- API 返回 `code: 1` + 明确原因（`暂时无法连接论坛` / `论坛返回异常（HTTP xxx）`）
- 页面仍返回 **HTTP 200**，显示友好提示 + 重试按钮
- **绝不让论坛故障拖垮皮肤站本身**

## 已知限制

- 不做账号打通。论坛与皮肤站是两套独立系统（不同数据库、不同会话）。
  账号互通需要 `blessing/flarum-oauth-client` 走 OAuth2 SSO，是另一个插件的事。
- 只读。插件不往论坛写任何数据。
- 依赖论坛开放 API。若论坛将来关闭匿名访问，需改用带 token 的请求。

## 相关

- [kokuu-home](https://github.com/KokuuStudio/kokuu-home) —— 首页主题，本插件的视觉基础
- [kokuu-quote](https://github.com/KokuuStudio/kokuu-quote) —— 顶栏一言
- [Blessing Skin Server](https://github.com/Blessing-Skin/Blessing-Skin-Server) 6.0

## 版权

论坛内容著作权归各自作者所有。本插件仅做聚合展示。