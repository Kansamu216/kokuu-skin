# tools — 开发工具

本目录存放**生成 assets/ 下图片的脚本**，仅作源码留存。

Blessing Skin 的 `CopyPluginAssets` 只会把 `assets/` 同步到
`public/plugins/<name>/assets/`，因此本目录**不会**被发布到线上。

| 脚本 | 产物 | 说明 |
| --- | --- | --- |
| `gen_moon_art.py` | `assets/moon-columbina.png` | 首页默认插画（未配置自定义图片时使用） |
| `gen_mc_scenes.py` | `assets/scenes/01..06-*.png` | 首页轮播的 6 张 MC 场景图 |

两者均**零第三方依赖**（只用 Python 标准库手写 PNG 编码），
在没有任何图形库的环境下也能运行：

```bash
python tools/gen_moon_art.py
python tools/gen_mc_scenes.py
```

想调整构图 / 配色，改脚本里的参数后重跑即可。
