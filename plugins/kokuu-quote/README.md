# kokuu-quote

> KokuuSkin 提瓦特一言 —— 在后台顶栏右侧展示一条提瓦特大陆的短句，点击即可换一句。

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.0-informational)](package.json)

---

## 特性

- **顶栏一言**：在后台顶栏右侧（用户菜单左侧）展示一条短句，单行省略号截断
- **元素点缀**：句前的元素小方块随所属**七元素**变色（风蓝 / 岩金 / 雷紫 /
  水青 / 火橙 / 草绿 / 冰蓝）
- **一键换一句**：点击整条或 ↻ 均可切换，带淡入动画
- **响应式**：≤1100px 隐藏出处、≤640px 只保留元素点与刷新按钮，不挤占顶栏
- **可管理**：内置 40 条挪德卡莱「空月之歌」台词，可在配置页自由增删

首页**不显示**（保持落地页纯净）。

## 安装

### 方式一：后台上传（推荐）

1. **先安装并启用 [`kokuu-home`](https://github.com/KokuuStudio/kokuu-home)
   与 [`kokuu-ui`](https://github.com/KokuuStudio/kokuu-ui)**（本插件依赖二者）
2. 下载本插件的 `kokuu-quote-1.0.0.zip`
3. 管理后台 → **插件管理** → **安装插件** → 上传 zip
4. 在插件列表中**启用** `KokuuSkin 提瓦特一言`

### 方式二：命令行

```bash
php artisan plugin:enable kokuu-home
php artisan plugin:enable kokuu-ui
php artisan plugin:enable kokuu-quote
```

### ⚠️ 安装顺序很重要

Blessing Skin 扫描插件时，若发现 `require` 中的依赖**未启用**，会
**自动禁用本插件**。必须按 `kokuu-home` → `kokuu-ui` → `kokuu-quote`
的顺序启用。

## 配置

管理后台 → **插件管理** → `KokuuSkin 提瓦特一言` → **设置**

- **台词列表**：显示当前生效的全部台词，可逐条删除
- **添加台词**：填写正文 / 出处 / 主题标签 / 关联元素
- **恢复内置库**：删除自定义词库，回到内置 40 条

主题与元素下拉会**从词库动态提取**，新增台词后无需改动界面代码。

### 数据存储

| 内容 | 位置 |
| --- | --- |
| 内置词库（只读） | `plugins/kokuu-quote/quotes.php` |
| 你的增删结果 | `storage/plugins/kokuu-quote/quotes.custom.php` |

用户数据写在 `storage/` 而非插件目录 —— 插件目录在生产环境常为只读，
且升级 / 重装插件时会被覆盖。删除 `quotes.custom.php` 即回退内置库。

## 台词库

内置 **40 条**台词，**全部取自挪德卡莱主线「空月之歌」**，聚焦月神哥伦比娅
与霜月之子的故事。

| 主题 | 说明 |
| --- | --- |
| 月神 | 哥伦比娅的祈愿与独白 |
| 归乡 | 家与归属的意义 |
| 哲思 | 存在、真相与谎言 |
| 离别 / 重逢 | 旅途的相遇与分别 |
| 故事 | 魔女会讲述的月亮传说 |
| 对峙 | 与愚人众的交锋 |
| 日常 / 旅途 | 挪德卡莱众生的小片段 |

**长度约束**：顶栏为单行展示，台词 ≤ **22 字**、出处 ≤ **8 字**，
超长会在前端以 `…` 截断（完整内容可悬停查看 `title`）。
新增台词时建议保持简短，服务端也会做长度裁剪兜底。

每条台词含四个字段：

```php
[
    'text' => '月光永远联系着我们，不论日夜',
    'by'   => '菈乌玛',
    'tag'  => '月神',
    'el'   => '水',
]
```

## HTTP API

对外提供随机一言接口，可用于其他项目：

```http
GET /plugin/kokuu-quote/random
GET /plugin/kokuu-quote/random?tag=月神
GET /plugin/kokuu-quote/random?el=水
```

响应：

```json
{
  "text": "月光永远联系着我们，不论日夜",
  "by": "菈乌玛",
  "tag": "月神",
  "el": "水"
}
```

筛选无结果时自动回退到全库，保证始终返回一条。
前端亦暴露 `window.KokuuQuote.random()` 供同页脚本调用。

## 依赖

| 依赖 | 要求 |
| --- | --- |
| **kokuu-home** | `^1.0.0`（必须先启用） |
| **kokuu-ui** | `^1.0.0`（必须先启用） |
| blessing-skin-server | `^6.0` |
| php | `>= 8.1` |

## 文件结构

```
kokuu-quote/
├── bootstrap.php          # 引导：API 路由 + 词库读写
├── quotes.php             # 内置台词库（40 条 · 挪德卡莱「空月之歌」）
├── package.json           # 插件清单
├── views/
│   └── config.blade.php   # 原生配置视图（台词管理面板）
└── assets/                # 会被复制到 public/plugins/kokuu-quote/assets/
    ├── quote.js           #   顶栏一言渲染 + 换一句
    └── quote.css          #   样式（融入 Apple 扁平主题）
```

## 常见问题

**Q：顶栏没有显示一言？**
A：① 确认插件已启用且两个依赖也已启用；② 顶栏需要存在
`.main-header`（登录页无顶栏，会自动跳过，属正常）；③ `Ctrl+F5` 强制刷新。

**Q：点一下会换句吗？**
A：点击整条或 ↻ 按钮均可。请求失败时前端会显示一条内置兜底文案，不会空白。

**Q：想改配色 / 宽度？**
A：编辑 `assets/quote.css`，刷新即生效（无需重新构建）。
元素颜色映射在 `assets/quote.js` 的 `elColor()` 中。

**Q：新增台词后前端没变？**
A：确认浏览器已刷新；服务端有进程内缓存，同一请求内不会重复读盘，
跨请求会重新读取 `quotes.custom.php`。

## 停用 / 卸载

```bash
php artisan plugin:disable kokuu-quote
php artisan plugin:delete  kokuu-quote
```

停用后顶栏一言消失，**自定义词库不会被删除**（在 `storage/` 中，
重新启用后仍在）。若要彻底清除，删除
`storage/plugins/kokuu-quote/quotes.custom.php`。

## 版权声明

台词著作权归 **上海米哈游天命科技有限公司** 所有。
本插件仅作个人学习与非商业展示，**不随代码分发任何商业授权**。

- ✅ 个人学习、私有服务器、小圈子使用
- ⚠️ 公开运营前请自行评估风险，或用配置页把台词替换为你自己的文案
- ℹ️ 插件代码本身（框架、样式、交互）以 [MIT](LICENSE) 发布

「原神 / 提瓦特 / 挪德卡莱 / 哥伦比娅」等名称与相关元素的权利归各自权利人所有。
