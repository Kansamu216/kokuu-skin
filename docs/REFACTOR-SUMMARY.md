# KokuuSkin 重构总结

> 完成时间：2026-11-03
> 仓库：https://github.com/Kansamu216/kokuu-skin
> 基线 tag：`pre-refactor-20261103`

## 一、成果概览

| 指标                 | 重构前       | 重构后                     |
| -------------------- | ------------ | -------------------------- |
| 插件数量             | 10 个        | 4 个自研 + 2 个官方 = 6 个 |
| 运行中的附属服务     | 5 个         | 0                          |
| nginx 站点           | 3 个         | 1 个                       |
| 数据库表             | 30 张        | 16 张                      |
| kokuu-credit 代码    | 1920 行      | 1085 行（-43%）            |
| 硬编码色值（主题层） | 22 处        | 0                          |
| 设计系统             | 3 套令牌混用 | 1 套 tokens.css            |

## 二、最终架构

```
plugins/
├── yggdrasil-api/          官方市场插件（外置登录）
├── single-player-limit/    官方市场插件（单角色限制）
├── kokuu-appearance/       外观 + UI Kit 契约
├── kokuu-credit/           积分账本（Core/Host 分层）
├── kokuu-lottery/          抽奖（Core/Host 分层，可移植）
└── kokuu-signin/           签到（Core/Host 分层，可移植）
```

**所有自研插件均采用 Core/Host 分层**：

- `src/Core/` — 领域逻辑，零宿主依赖，可脱离 BlessingSkin 复用
- `src/Host/` — BlessingSkin 适配层，唯一知道 Laravel 的地方

## 三、执行阶段

| 阶段 | 内容                               | 提交                          |
| ---- | ---------------------------------- | ----------------------------- |
| 0    | git 基线 + 推 GitHub + tag         | `34a49dd`                     |
| 1    | 下线论坛/下载页/网盘/面板/状态监控 | `0932fb5`                     |
| 2    | 删除 forum/core/exchange           | `0932fb5`                     |
| 2.5  | 外观三合一 + 四层设计系统          | `fa79e73`                     |
| —    | 首页/登录页/内容页现代化重构       | `cb8c7fc` `b90ad00` `a8625a7` |
| 2.6  | 抽奖拆为 kokuu-lottery + 删 coupon | `b00dc92` `af731ce`           |
| 3    | credit 剥离 SSO/论坛               | `52d99af`                     |
| 3.x  | 签到拆为 kokuu-signin              | `a364e44`                     |
| 5    | 修复皮肤上传 500 + 衣柜入口        | `f6edc06`                     |
| 3 补 | credit Core/Host 分层              | `d3c2049`                     |

## 四、关键收益

### 4.1 可移植性

三个业务插件（credit / lottery / signin）的核心逻辑均可脱离皮肤站运行，
已用「内存实现 + 纯函数测试」验证。

### 4.2 外观解耦

- 建立 `tokens.css`（设计令牌唯一真相源）与 `ui-kit.css`（标准组件库）
- 废弃 `body.kokuu-apple` 作用域前缀，改用 `k-` 类名前缀
- 业务插件可选使用外观，Appearance 停用后仍可正常工作

### 4.3 可维护性

- kokuu-credit 从 1920 行降到 1085 行
- 删除 SSO（约 390 行）、论坛加分链路、兑换码
- 所有 PSR-4 类一类一文件

## 五、数据安全

- 重构前完整备份：`Y:\kokuu-backup\20261103-1445\`（已验证 28/28 哈希一致）
- 数据库清理前备份：`/root/archive-20261103/db-cleanup/`
- 所有被删插件、服务、配置均归档在 `/root/archive-20261103/`
- 回滚锚点：git tag `pre-refactor-20261103`

## 六、遗留事项

1. R2 桶未清空（NAS 曾断连，文件清单未拉取）—— 需确认是否保留
2. `kokuu-appearance` 的 `body.kokuu-apple` 机制仍有部分残留，
   业务插件已改用 `k-` 类名，但旧 CSS 的兼容层可后续清理
3. 服务器 `node_modules`（649MB）已保留，便于后续前端构建
