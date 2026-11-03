# kokuu-home

> KokuuSkin 全站主题 —— 为 [Blessing Skin Server](https://github.com/bs-community/blessing-skin-server) 注入统一的品牌视觉与可自定义首页。

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Version](https://img.shields.io/badge/version-1.0.0-informational)](package.json)

---

## 特性

| 页面 | 效果 |
| --- | --- |
| **首页** `/` | Apple 扁平风格落地页，由 `home.js` 整体接管渲染：像素网格纹理、像素方块粒子、**6 张 MC 场景自动轮播**（可换成自定义图片）、月神意象点缀 |
| **登录 / 注册** `/auth/*` | 重皮肤为 KokuuSkin 卡片，**完整保留内核表单与提交逻辑** |
| **全站** `*` | 顶栏 / 侧边栏 / 品牌位统一为纯文字「KokuuSkin」标识，替换浏览器 favicon |

**双主题**：浅色「纸雨」与深色「虚空」两态，都是 Apple 扁平语言
（浅色 = 纯净白面 + 大留白；深色 = 纯黑底 + 分层灰面，非简单调暗）。
首页右上角有独立切换按钮并记忆偏好；后台用 AdminLTE 自带的 ☀/🌙 开关。

**表单式自定义首页**：标题、标语、介绍、右侧图片、页脚文案、联系邮箱
全部可在后台表单填写，保存即生效，**无需改代码、无需重新构建前端**。

## 安装

### 方式一：后台上传（推荐）

1. 下载本插件的 `kokuu-home-1.0.0.zip`
2. 管理后台 → **插件管理** → **安装插件** → 上传 zip
3. 安装完成后，在插件列表中**启用** `KokuuSkin 全站主题`

### 方式二：命令行

```bash
cd /path/to/blessing-skin-server
# 把 zip 解压到 plugins/ 目录后
php artisan plugin:enable kokuu-home
```

> 安装后请确认 `public/plugins/kokuu-home/assets/` 已自动生成
> （由核心的 `CopyPluginAssets` 监听插件启用事件复制）。

## 配置

管理后台 → **插件管理** → `KokuuSkin 全站主题` → **设置**

| 字段 | 说明 | 留空时 |
| --- | --- | --- |
| 大标题 | 首屏 H1 | `KokuuSkin` |
| 一句话标语 | 标题下方强调句 | `让每一次登录，都落在正确的像素上` |
| 站点介绍 | 首屏正文 | 内置默认文案 |
| 右侧图片 | 站内路径（如 `/plugins/kokuu-home/assets/scenes/01-mineshaft.png`）或 http(s) 外链 | 使用内置像素插画并开启 6 图轮播 |
| 页脚文案 | 页脚一句话 | `像素有形，月光无界` |
| 联系邮箱 | 页脚联系入口 | `hi@kokuu.org` |

配置存储在 `options` 表（键前缀 `kokuu_home_`），空值自动回退默认，
因此**全新安装无需任何配置即可正常显示**。

## 文件结构

```
kokuu-home/
├── bootstrap.php          # 引导：Hook 注入、配置注入、保存 API
├── package.json           # 插件清单（manifest）
├── views/
│   └── config.blade.php   # 原生配置视图（插件管理页「设置」按钮打开）
├── assets/                # 会被复制到 public/plugins/kokuu-home/assets/
│   ├── home.css           #   首页样式（Apple 扁平 + 像素点缀）
│   ├── home.js            #   首页渲染 + 轮播 + 主题切换
│   ├── theme.css          #   后台外壳统一配色
│   ├── void.css           #   深色「虚空」主题
│   ├── auth.css / auth.js #   登录注册页重皮肤
│   ├── shell.js           #   品牌文字 / favicon / 标题
│   ├── favicon.png
│   ├── moon-columbina.png #   内置默认插画
│   └── scenes/*.png       #   6 张 MC 场景轮播图
└── tools/                 # 开发工具（不会发布到 public/）
    ├── gen_moon_art.py    #   生成 moon-columbina.png
    ├── gen_mc_scenes.py   #   生成 scenes/*.png
    └── README.md
```

> `assets/` 下的图片由 `tools/` 中的纯 Python 脚本绘制（零第三方依赖，
> 手写 PNG 编码）。想改构图或配色，改脚本参数后 `python tools/xxx.py` 重跑即可。

## 依赖

| 依赖 | 要求 |
| --- | --- |
| blessing-skin-server | `^6.0` |
| php | `>= 8.1` |

本插件**无插件依赖**，可独立安装。

## 设计约束（重要）

本插件**不修改内核任何模板文件**，全部视觉通过 `Hook` 注入实现，
因此可以直接 `git pull` 官方 dev 分支更新内核，无需重新套用改动。

由于首页采用「客户端接管」（`home.js` 在 `</body>` 前整体替换 body 内容），
`curl` 看不到渲染后的 DOM —— 属预期行为，浏览器打开即可看到完整效果。

## 常见问题

**Q：首页没有样式 / 是一片空白？**
A：确认插件已**启用**（不是仅安装），且 `public/plugins/kokuu-home/assets/home.css` 存在。
若刚改过 `bootstrap.php`，需重启 `php artisan serve`（事件监听在启动时注册）。

**Q：浏览器标签仍显示 "Blessing Skin"？**
A：标题由 `shell.js` / `home.js` 在运行时改写，请按 `Ctrl+F5` 强制刷新清 JS 缓存。

**Q：轮播不转？**
A：页面切到后台标签页时会自动暂停（省电）；回到前台即恢复。默认 5.2 秒切换一次，底部有进度条指示。

**Q：配置页在哪？**
A：管理后台 → 插件管理 → 本插件 → 「设置」按钮（走 Blessing Skin 原生插件配置机制，路由 `/admin/plugins/config/kokuu-home`）。

**Q：想改首页图片？**
A：两种方式 —— ① 配置页「右侧图片」填一个地址（切换为单图模式）；
② 替换 `assets/scenes/` 下的图片并保持文件名不变（保持轮播）。

## 停用 / 卸载

```bash
php artisan plugin:disable kokuu-home   # 停用
php artisan plugin:delete  kokuu-home   # 卸载（删除目录）
```

停用后页面自动恢复为 Blessing Skin 原生外观，**不影响数据**。

## 许可

代码以 [MIT](LICENSE) 发布。
「Minecraft」相关美术与文案引用仅作个人学习与非商业展示；
像素插画为本仓库用代码生成，不含任何第三方素材。
