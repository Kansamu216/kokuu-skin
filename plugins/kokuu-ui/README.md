# kokuu-ui

> KokuuSkin 后台界面重塑 —— 把 Blessing Skin 的后台（用户中心 / 皮肤库 / 管理后台）从 AdminLTE 原味改造成 Apple 扁平风格。

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.0-informational)](package.json)

---

## 这是什么

[kokuu-home](https://github.com/KokuuStudio/kokuu-home) 的**补充插件**。
前者负责品牌与首页，本插件负责把**登录后的所有界面**重塑为同一套视觉语言。

核心思路：**不重写 React 业务逻辑，只换「舞台」**。
Blessing Skin 后台的内容区是 React SPA，数据与交互（筛选、分页、衣柜管理、
3D 预览、通知等）全部照常运行；本插件在 DOM 层面重建外壳并覆盖视觉，
因此**功能零风险**，而观感是彻底重做。

## 特性

### Apple 扁平设计系统

| 维度 | 改造 |
| --- | --- |
| 整体 | AdminLTE 深蓝渐变侧栏 → 纯净白面 + 极淡分隔线，系统浅灰底 |
| 圆角 | 统一大圆角（卡片 18px、控件 12px、弹窗 24px） |
| 阴影 | 重阴影 → 细腻双层柔和阴影（几乎不可见但有层次） |
| 留白 | 内容区间距大幅放宽，让呼吸感承担层次 |
| 导航 | 顶栏 / 侧边栏半透明毛玻璃（Apple 标志性），内容卡片保持纯净 |
| 配色 | 中性灰阶为主，品牌色只用于强调与选中态 |
| 深色态 | 深色下同样是 Apple Dark 质感（纯黑底 + 分层灰面），不是简单变暗 |

### 流畅动画

- 页面淡入上移、卡片**逐个 stagger 浮现**
- 侧边栏项悬停微滑 + 左侧光条展开、图标悬停缩放
- 按钮按压回弹 `scale(.96)`、卡片悬停浮起、预览图放大
- Modal / Toast 丝滑入场
- **尊重系统「减弱动效」偏好**（`prefers-reduced-motion`）

### 提瓦特元素（克制点缀）

- 内容区角落超淡的七元素同心环水印
- 区块标题旁 `✦` 元素徽标
- 侧栏分组标题加像素方块前缀
- 页脚「提瓦特大陆 · KokuuSkin」+ 渐变条

## 安装

### 方式一：后台上传（推荐）

1. **先安装并启用 [`kokuu-home`](https://github.com/KokuuStudio/kokuu-home)**（本插件依赖它）
2. 下载本插件的 `kokuu-ui-1.0.0.zip`
3. 管理后台 → **插件管理** → **安装插件** → 上传 zip
4. 在插件列表中**启用** `KokuuSkin 后台界面重塑`

### 方式二：命令行

```bash
php artisan plugin:enable kokuu-ui
```

### ⚠️ 安装顺序很重要

Blessing Skin 在扫描插件时，若发现 `require` 中的依赖**未启用**，会
**自动禁用本插件**。因此必须按 `kokuu-home` → `kokuu-ui` 的顺序启用：

```bash
php artisan plugin:enable kokuu-home
php artisan plugin:enable kokuu-ui
```

## 依赖

| 依赖 | 要求 |
| --- | --- |
| **kokuu-home** | `^1.0.0`（必须先启用） |
| blessing-skin-server | `^6.0` |
| php | `>= 8.1` |

## 文件结构

```
kokuu-ui/
├── bootstrap.php       # 引导：仅注入 CSS 与 rebuild.js
├── package.json        # 插件清单
└── assets/             # 会被复制到 public/plugins/kokuu-ui/assets/
    ├── apple.css       #   Apple 扁平设计系统（自带完整变量定义）
    ├── motion.css      #   动画系统
    ├── teyvat.css      #   提瓦特元素点缀
    └── rebuild.js      #   DOM 层面重建后台外壳
```

`apple.css` 在 `:root` 中**自行定义**了全部 `--a-*` 变量，
即使 `kokuu-home` 的 `theme.css` 缺失，样式依然完整可用。

## 覆盖范围

| 页面 | 是否生效 |
| --- | --- |
| 用户中心 `/user/*` | ✅ |
| 管理后台 `/admin/*` | ✅ |
| 皮肤库 `/skinlib*` | ✅ |
| 首页 `/` | ❌ 有意不介入（由 kokuu-home 负责） |

## 已知边界

- **只改视觉，不改布局结构**：本插件在 DOM 层面调整外壳（侧边栏形态、
  内容区间距、卡片质感），但不重写 React 组件的内部结构。若需要改动
  组件树（如卡片网格列数、详情页分栏），必须改内核 React 源码并
  `npm run build` —— 那样会与官方更新冲突，本插件刻意不走这条路。
- `rebuild.js` 在 `</body>` 前执行，DOM 已就绪，无需等待。

## 常见问题

**Q：启用后没变化？**
A：① 确认 `kokuu-home` 已启用（依赖不满足会被自动禁用）；
② 按 `Ctrl+F5` 强制刷新；③ 确认 `public/plugins/kokuu-ui/assets/apple.css` 存在。

**Q：控制台报 JS 错误？**
A：`rebuild.js` 对每一步都做了存在性判断，缺元素会静默跳过。
若确实报错，请检查是否误改了选择器。

**Q：能关掉动画吗？**
A：可以。系统开启「减弱动效」时本插件会自动关闭动画；
也可直接在浏览器设置里开启该选项，或编辑 `motion.css` 删除注入规则。

## 停用 / 卸载

```bash
php artisan plugin:disable kokuu-ui
php artisan plugin:delete  kokuu-ui
```

停用后后台恢复为 AdminLTE 原生外观，**不影响数据**。

## 许可

代码以 [MIT](LICENSE) 发布。
「原神 / 提瓦特」相关名称与元素引用仅作个人学习与非商业展示，
商标与内容权利归各自权利人所有。
