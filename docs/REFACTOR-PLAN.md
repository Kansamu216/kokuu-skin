# KokuuSkin 重构方案

> 编写时间：2026-11-03
> 目标：整理 BlessingSkin 皮肤站代码，下线论坛/下载页/面板服务，剥离论坛耦合，补上版本控制
> 备份：`Y:\kokuu-backup\20261103-1445\`（已验证可恢复）

---

## 一、现状

### 1.1 皮肤站本体

| 项       | 值                                                            |
| -------- | ------------------------------------------------------------- |
| 路径     | `/var/www/kokuu-auth`                                         |
| 版本     | BlessingSkin v4 定制版（Laravel 10 + PHP 8.1 + MariaDB 10.6） |
| 站点标题 | `KokuuAuth`                                                   |
| 用户     | 11（1 管理员 + 1 测试管理员 + 9 普通用户）                    |
| 数据     | 15 张皮肤、11 个角色、18 个衣柜项、590+ 条积分流水            |
| 最近活跃 | 2026-10-14                                                    |

### 1.2 插件清单（10 个，启用 8 个）

| 插件                  | 启用 | 行数 | 职责                                            | 结论         |
| --------------------- | ---- | ---- | ----------------------------------------------- | ------------ |
| `yggdrasil-api`       | ✅   | 1825 | Minecraft 外置登录认证                          | **保留**     |
| `kokuu-credit`        | ✅   | 1920 | 积分 + 签到 + 论坛 SSO                          | **重构保留** |
| `kokuu-coupon`        | ✅   | 1546 | 兑换码 + 抽奖                                   | **保留**     |
| `kokuu-ui`            | ✅   | 197  | 后台/侧边栏界面调整                             | **保留**     |
| `kokuu-home`          | ✅   | 152  | 首页主题                                        | **保留**     |
| `kokuu-quote`         | ✅   | 231  | 提瓦特一言                                      | **保留**     |
| `single-player-limit` | ✅   | 146  | 单角色限制                                      | **保留**     |
| `kokuu-core`          | ✅   | 553  | **只有 FlarumClient**，唯一使用者是 kokuu-forum | **删除**     |
| `kokuu-forum`         | ❌   | 655  | 论坛聚合，已停用                                | **删除**     |
| `kokuu-exchange`      | ❌   | 1133 | 积分兑换，已停用                                | **删除**     |

### 1.3 三个必须先解决的问题

**问题 A：代码不在版本控制里**

- 服务器 `/var/www/kokuu-auth` **不是 git 仓库**
- GitHub `kokuu-auth` 只有 `server/` + 3 个插件（coupon/credit/exchange）
- **另外 7 个插件在 GitHub 上完全不存在**
- 结论：任何改动都无版本可回退，必须先建立基线

**问题 B：kokuu-credit 与论坛深度耦合**

- 27 处论坛引用，含完整 SSO 实现（OAuth2 + PKCE + Passport）
- 拆解点：
  - 4 个 SSO 函数：`kokuu_sso_config` / `_save_config` / `_ensure_tables` / `_ensure_client` / `_user_payload` / `_user_from_token`（约 350 行，位于 529-820 行）
  - 3 条 SSO 路由：`api/sso-status`、`sso`、`api/sso-links`（1647-1729 行）
  - 论坛赚分逻辑：`forum_url` 配置、`score_thread` / `score_reply` / `score_liked` / `max_per_event` / `max_per_day` / `ttl` 等选项
  - `earn` 接口（840 行起）供论坛回调加分
- **Passport 全站仅被此 SSO 使用**，剥离后可从 composer 移除
- 论坛实际使用量：`kokuu_sso_links` 仅 3 条，Flarum 仅 3 个用户 / 9 帖 / 32 回复

**问题 C：数据残留**

- 论坛库 `kokuu_forum` 19 表（3 用户、9 讨论、32 帖）
- 积分相关 6 张表仍活跃：`credit_ledger`(128) / `coupon_codes`(20) / `coupon_redemptions`(0) / `lottery_prizes`(6) / `lottery_draws`(12) / `lottery_wallets`(3)
- 待清理孤儿表：`bridge_config`(6) / `bridge_exchange`(0) / `bridge_identity`(0) / `exchange_ledger`(2) / `exchange_orders`(2) / `ygg_log`(0) / `kokuu_sso_links`(3)

---

## 二、目标架构

### 2.1 整合原则

1. **官方插件市场不动** —— `yggdrasil-api`、`single-player-limit` 等上游插件保持原样，
   升级路径不受影响。只整合自研插件。
2. **按职责域合并，不按文件合并** —— 外观归外观，业务归业务，避免再造一个"什么都管"的巨插件。
3. **单向依赖** —— 业务插件可依赖核心，核心不反向依赖业务。

### 2.2 整合后的插件结构

> 📐 **可移植性与外观契约的完整设计见 [ARCHITECTURE.md](ARCHITECTURE.md)**
>
> 核心变化：
>
> - 业务插件拆为 **Core（可移植，不 import 宿主）** + **Host（BlessingSkin 适配）** 两层
> - 外观提供 **UI Kit 契约**（CSS 变量 + `k-` 前缀类名），业务插件可选使用、无反向依赖
> - **抽奖从 coupon 拆出为独立插件**，兑换码删除

```
plugins/
├── yggdrasil-api/          官方市场插件，不动
├── single-player-limit/    官方市场插件，不动
│
├── kokuu-appearance/       ★新建：外观整合（home + ui + quote）
│                           同时提供 UI Kit 契约
│
├── kokuu-credit/           积分 + 签到
│   ├── src/Core/           领域逻辑（可移植，无宿主依赖）
│   └── src/Host/           BlessingSkin 适配层
│
└── kokuu-lottery/          ★新建：抽奖（从 coupon 拆出）
    ├── src/Core/
    └── src/Host/

删除：kokuu-coupon（兑换码部分）/ kokuu-forum / kokuu-core / kokuu-exchange
```

### 2.3 为什么这样合并

**外观三合一（kokuu-home + kokuu-ui + kokuu-quote）**

三者本来就是一条依赖链，且互相纠缠：

| 现状                                            | 问题                                           |
| ----------------------------------------------- | ---------------------------------------------- |
| `kokuu-ui` require `kokuu-home`                 | 依赖未满足会被**自动禁用**，安装顺序错了就白屏 |
| `kokuu-quote` require `kokuu-home` + `kokuu-ui` | 三个插件必须按序启用，运维负担                 |
| `teyvat.css` 从 ui 迁到 quote                   | 已经迁过一次，说明边界本来就模糊               |
| `kokuu-ui` 接管 `yggdrasil-api` 的 `dnd.twig`   | 外观插件改别人视图，职责越界                   |
| 三套 CSS 变量体系（`--a-*` 等）                 | 需要保证加载顺序，否则配色错乱                 |

合并后：**一次启用，无顺序依赖，CSS 加载顺序固定**。

> ⚠️ 合并后 `kokuu-credit` 的 `apple-scope.js` 引用路径需同步更新
> （当前 `kokuu-credit:1576` 引用 `/plugins/kokuu-ui/assets/apple.css`）。

**删除 kokuu-coupon（兑换码 + 抽奖）**

- 依赖方向单一：`coupon → credit`，`credit` 不反向依赖，删除干净
- 无其他插件依赖它
- 涉及 11 条路由、5 张数据表、4 个 options

**保留 kokuu-credit** —— 但需移除 coupon 相关入口与菜单挂载点。

### 2.4 服务器最终形态

```
nginx + php8.1-fpm + mariadb + redis + ssh
  └── KokuuAuth
      ├── yggdrasil-api          （官方，外置登录）
      ├── single-player-limit    （官方，单角色限制）
      ├── kokuu-appearance       （自研，外观）
      └── kokuu-credit           （自研，积分签到）
```

下线：`chat.kokuu.org`（Flarum）/ `xz.kokuu.org` / `kokoo-download` / `kokuu-drive` / `panel.kokuu.org`

---

## 三、执行计划

### 阶段 0：建立版本基线（必做，先于一切改动）

**0.1 本地全量纳管**

```bash
cd /var/www/kokuu-auth
git init
# .gitignore 排除 vendor/ node_modules/ storage/logs/ storage/framework/ public/app/*.map .env
git add -A && git commit -m "baseline: 服务器实际运行版本 2026-11-03"
```

**0.2 推到 GitHub**

- 新建分支 `server-baseline` 或直接推 `main`
- 仓库结构建议对齐现有 monorepo：`server/` + `plugins/`
- 需处理：`.env` 不入库（已在 .gitignore），`config.php` 同理

**0.3 打 tag**

```bash
git tag -a pre-refactor-20261103 -m "重构前基线"
git push origin main --tags
```

> ⚠️ 此阶段完成前**不动任何业务代码**。这是唯一的回退锚点。

---

### 阶段 1：下线服务（可独立执行，风险最低）

**1.1 论坛（Flarum）**

```bash
systemctl stop nginx   # 先停 nginx 避免 502
# 移除 nginx 配置中 chat.kokuu.org 的 server 块（/etc/nginx/sites-enabled/kokuu 内）
nginx -t && systemctl start nginx
# 归档站点目录
tar czf /root/archive-flarum-$(date +%F).tar.gz -C /var/www flarum
rm -rf /var/www/flarum
```

- 论坛库 `kokuu_forum` 保留不删（仅 2.3 MB，留着无害）
- 证书 `chat.kokuu.org` 停止续期

**1.2 下载页 + 网盘 + 面板**

```bash
systemctl disable --now paperland-download kokoo-download kokuu-drive kokuupanel
rm /etc/nginx/sites-enabled/paperland-download /etc/nginx/sites-enabled/kokuupanel
nginx -t && systemctl reload nginx
# 归档目录后删除
tar czf /root/archive-services-$(date +%F).tar.gz -C /opt paperland-download kokoo-download kokuu-drive -C /srv kokuu-panel
```

- 域名 `xz.kokuu.org` / `panel.kokuu.org` 的 DNS 与证书停止维护
- **注意**：`kokuu-drive` / `kokoo-download` 的文件实体在 Cloudflare R2，本地只有 12K 元数据；删除前确认 R2 桶是否还要保留

**1.3 清理 nginx**

- 最终只留 `auth.kokuu.org` / `kokuu.org` 一个 server 块
- 删除 `default_server` 的冗余跳转

---

### 阶段 2：删除插件

**2.1 删除论坛/核心/兑换（无整合）**

```bash
cd /var/www/kokuu-auth/plugins
# 先从 plugins_enabled 移除，再删目录（顺序不能反，否则白屏）
php artisan plugin:disable kokuu-forum     # 已停用，跳过
# 直接改 options 表移除 core / exchange
mariadb kokuu -e "UPDATE options SET option_value='[...]' WHERE option_name='plugins_enabled';"
mv kokuu-forum kokuu-core kokuu-exchange /root/plugins-removed/
```

> ⚠️ **顺序关键**：`kokuu-core` 是启用状态，直接删目录会导致所有页面 500。必须先改 `plugins_enabled` 移除它，再删目录。

**2.2 删除 kokuu-coupon（仅兑换码部分，抽奖保留）**

依赖检查已完成：`coupon → credit` 单向，`credit` 不反向依赖，无其他插件依赖它。

> ⚠️ **决策变更**：原计划删除整个 coupon，现改为**只删兑换码**，
> 抽奖拆分为独立插件 `kokuu-lottery`（详见阶段 2.6）。

**兑换码部分**（删除）：

```bash
# 1) 归档
tar czf /root/plugins-removed/kokuu-coupon-$(date +%F).tar.gz -C /var/www/kokuu-auth/plugins kokuu-coupon
# 2) 抽奖代码先迁出到 kokuu-lottery（见阶段 2.6），再删原目录
rm -rf /var/www/kokuu-auth/plugins/kokuu-coupon
```

**需连带清理**：

- [ ] `kokuu-credit` 中 coupon 的菜单挂载点（`bootstrap.php:998-1015`）
- [ ] `kokuu-credit` 中 coupon 的 `side_menu` 过滤器引用（`bootstrap.php:1029-1036`）
- [ ] 兑换码 2 张表：`coupon_codes`(20) / `coupon_redemptions`(0)
- [ ] 4 个 options：`kokuu_coupon_draw_cooldown` / `kokuu_coupon_draw_cost` / `kokuu_coupon_daily_draw_limit` / `kokuu_coupon_notice`
      → 其中 `draw_*` 三个**迁移**到 `kokuu-lottery` 配置，不删除
- [ ] 语言包中兑换码相关键

> ⚠️ 数据先 dump 再删表。抽奖数据（`lottery_*` 三张表）**保留**，随抽奖插件迁移。

**连带清理**

- `composer.json` 移除 `laravel/passport`（确认阶段 3 完成后）
- `composer dump-autoload`

---

### 阶段 2.5：外观插件整合（kokuu-appearance）

这是结构性改动，建议**单独一次提交**，便于回滚。

**2.5.1 新建插件骨架**

```
kokuu-appearance/
├── package.json          name: kokuu-appearance, 无 require 依赖
├── bootstrap.php         合并三个 bootstrapper
├── assets/               合并三个 assets 目录
├── views/config.blade.php  合并三个配置页为标签页
├── views/ygg/dnd.twig    从 kokuu-ui 迁入
└── lang/                 从 kokuu-ui 迁入
```

**2.5.2 迁移映射**

| 来源                                 | 去向                       | 备注                                        |
| ------------------------------------ | -------------------------- | ------------------------------------------- |
| `kokuu-home/assets/*`                | `kokuu-appearance/assets/` | theme/void/home/auth/shell                  |
| `kokuu-ui/assets/*`                  | 同上                       | apple/motion/rebuild/apple-scope/footer/ygg |
| `kokuu-quote/assets/*`               | 同上                       | quote/teyvat                                |
| `kokuu-home/views/config.blade.php`  | 合并为标签页               | 首页配置                                    |
| `kokuu-ui/views/ygg/dnd.twig`        | `views/ygg/`               | 保持视图接管逻辑                            |
| `kokuu-quote/views/config.blade.php` | 合并为标签页               | 台词管理                                    |
| `kokuu-quote/quotes.php`             | `kokuu-appearance/`        | 内置台词库                                  |

**2.5.3 bootstrap.php 合并要点**

CSS/JS 注入顺序必须保持，否则配色错乱：

```php
// 1. 基础主题（原 kokuu-home）
Hook::addStyleFileToPage($plugin->assets('theme.css'), ['*']);
Hook::addStyleFileToPage($plugin->assets('void.css'), ['*']);
// 2. Apple 层（原 kokuu-ui，依赖上层的 CSS 变量）
Hook::addStyleFileToPage($plugin->assets('apple.css'), ['*']);
Hook::addStyleFileToPage($plugin->assets('motion.css'), ['*']);
// 3. 功能层（原 kokuu-quote）
Hook::addStyleFileToPage($plugin->assets('quote.css'), ['*']);
Hook::addStyleFileToPage($plugin->assets('teyvat.css'), [...]);
```

**必须保留的逻辑**：

- [ ] `RenderingHeader` 监听注入 `window.KOKUU_CONFIG`（首页文案）
- [ ] `replaceNamespace('Yggdrasil', ...)` 视图接管（含原目录回落）
- [ ] `side_menu` 过滤器精简菜单（含管理员判断）
- [ ] 保存配置后 `@unlink(storage_path('options.php'))`（否则配置不生效）
- [ ] 台词库读写 `storage/plugins/kokuu-quote/quotes.custom.php`

> ⚠️ 台词库路径建议改为 `storage/plugins/kokuu-appearance/`，需迁移已有自定义台词（当前无自定义文件，仅内置库）。

**2.5.4 更新外部引用**

- [ ] `kokuu-credit:1576` 引用 `/plugins/kokuu-ui/assets/apple.css` → 改为 `kokuu-appearance`
- [ ] `kokuu-credit` 中所有 `kokuu-ui` 相关注释与路径
- [ ] `plugins_enabled` 中三个插件条目替换为一个

**2.5.5 验证**

- [ ] 首页、鉴权页、用户中心、皮肤库、后台五个区域配色一致
- [ ] 深色主题切换正常
- [ ] 一言在顶栏正常显示与切换
- [ ] Yggdrasil 配置卡片为定制版（非上游原生）
- [ ] 侧边栏无「我的举报」「高级功能」「论坛积分流水」

---

### 阶段 2.6：抽奖拆分为 kokuu-lottery

从 `kokuu-coupon` 中剥离抽奖，成为独立插件。

**2.6.1 迁移映射**

| 内容     | 来源                                                                    | 去向                                |
| -------- | ----------------------------------------------------------------------- | ----------------------------------- |
| 数据表   | `lottery_prizes`(6) / `lottery_draws`(12) / `lottery_wallets`(3)        | 结构不变，随插件管理                |
| 核心函数 | `kokuu_coupon_draw()` / `_draw_cooldown_left()` / `_daily_draw_count()` | `src/Core/LotteryService.php`       |
| 表初始化 | `kokuu_coupon_ensure_tables()` 中 lottery 部分                          | `src/Host/Migrations.php`           |
| 默认奖品 | `kokuu_coupon_ensure_default_prizes()`                                  | `src/Core/PrizePool.php`            |
| 路由     | `/plugin/kokuu-coupon/draw` 等                                          | `/plugin/kokuu-lottery/draw`        |
| 配置     | `kokuu_coupon_draw_cost` / `_draw_cooldown` / `_daily_draw_limit`       | `kokuu_lottery_*`                   |
| 视图     | `views/user/coupon/draw.twig`                                           | `views/user/lottery/draw.twig`      |
| 资产     | `assets/draw.js` / `draw.css`                                           | `assets/lottery.js` / `lottery.css` |

**2.6.2 保留的耦合（通过接口，不直接调用）**

抽奖中奖后需要加分，当前直接调 `kokuu_credit_ledger()`：

```php
if (! function_exists('kokuu_credit_ledger')) { ... }
kokuu_credit_ledger($uid, $value, 'coupon', 'coupon:' . $rid, $code, '兑换码');
```

改为接口注入：

```php
// Core 定义
interface ScoreSink {
    public function award(int $uid, int $amount, string $eventId, string $note): void;
}

// Host 层：若 credit 插件存在则注入实现，否则用 NullSink
```

**好处**：抽奖插件可独立运行，没有积分系统时奖品降级为「仅记录不发分」。

**2.6.3 验证**

- [ ] 抽奖页面正常，可抽
- [ ] 中奖后积分正确入账（有 credit 时）
- [ ] 停用 credit 后抽奖仍可用（奖品仅记录）
- [ ] 历史抽奖记录（12 条）可查
- [ ] 冷却 / 每日次数限制生效

---

### 阶段 2.7：定义 UI Kit 契约

外观插件对外暴露标准样式接口，其他插件可选使用、无依赖。

**2.7.1 CSS 变量（design tokens）**

在 `kokuu-appearance/assets/tokens.css` 中定义：

```css
:root {
  /* 表面 */    --k-surface / --k-surface-2 / --k-ink / --k-ink-muted / --k-border
  /* 强调 */    --k-accent / --k-success / --k-warning / --k-danger
  /* 形状 */    --k-radius / --k-radius-sm / --k-shadow / --k-shadow-lg
  /* 间距 */    --k-space-1..4
}
body.dark-mode { /* 覆盖同名变量 */ }
```

**2.7.2 组件类名（`k-` 前缀，BEM）**

```
.k-card / .k-card__head / .k-card__body / .k-card__foot
.k-btn / .k-btn--primary / .k-btn--ghost / .k-btn--danger
.k-table / .k-badge / .k-field / .k-empty / .k-stat
```

**2.7.3 使用约定**

- 业务插件视图**只写类名**，不 import 任何外观代码
- 业务插件自带**带 fallback 的兜底样式**：
  ```css
  .k-card {
    background: var(--k-surface, #fff);
    border-radius: var(--k-radius, 12px);
  }
  ```
- 外观插件提供增强版，两者共存时外观优先（后加载覆盖）

**2.7.4 废弃作用域前缀**

- [ ] 移除 `body.kokuu-apple` 机制
- [ ] 删除 `apple-scope.js`（credit 和 ui 各一份）
- [ ] `apple.css` 改为 `:root` 变量 + 直接类名
- [ ] `dash.css` 去掉 `body.kokuu-apple` / `body:not(.kokuu-apple)` 双写（500+ → 约 250 行）

> 详见 [DECOUPLING.md](DECOUPLING.md)

**2.7.5 验证**

- [ ] 停用 appearance，所有业务插件页面仍可用（配色退化但不错乱）
- [ ] 重命名 appearance 目录，无 404
- [ ] 深色模式跟随正常

---

### 阶段 3：重构 kokuu-credit

> 📄 **详细解耦方案见 [DECOUPLING.md](DECOUPLING.md)**
>
> 核心结论：credit 与外观的耦合根源是「appearance 用 `body.kokuu-apple` 类名做作用域，
> 而这个类由 rebuild.js 运行时按前缀白名单添加，不含 `plugin/*`」。
> credit 为此付出了 5 处适配代价（硬编码路径、补类脚本、CSS 双写）。
> 解耦方向：改为 CSS 变量契约，credit 不感知 appearance 的存在。

**3.1 删除 SSO 模块**（约 350 行）

- 删函数：`kokuu_sso_config`、`kokuu_sso_save_config`、`kokuu_sso_ensure_tables`、`kokuu_sso_user_payload`、`kokuu_sso_ensure_client`、`kokuu_sso_user_from_token`（529-820 行整段）
- 删路由：`api/sso-status`（1647）、`sso`（1665）、`api/sso-links`（1691）
- 删视图中的 SSO 配置区块

**3.2 删除论坛赚分逻辑**

- 删 `forum_url` 配置项及 `earn` 接口（840 行起）——**需确认**：`earn` 是否还用于其他场景
- 删配置项：`score_thread`、`score_reply`、`score_liked`、`max_per_event`、`max_per_day`、`ttl`、`secret`
- 删 `kokuu_credit_source_labels` 中论坛相关来源

**3.3 保留**

- 签到（`user.sign.after` 事件监听，1473 行）
- 流水（`credit_ledger`）
- 管理端：`api/users`、`api/adjust`、`api/admin/*`、`api/ledger`
- 仪表盘数据 `kokuu_credit_dashboard_data`

**3.4 移除 coupon 遗留入口**

- 删 `ConfigureUserMenu` 监听中 coupon 的 children 占位（`bootstrap.php:998-1015`）
- 删 `side_menu` 过滤器中 coupon 相关逻辑（`bootstrap.php:1029-1036`）
- 更新 `apple.css` 引用路径为 `kokuu-appearance`

**3.5 清理孤儿选项**

```sql
DELETE FROM options WHERE option_name LIKE 'kokuu_sso_%'
   OR option_name LIKE 'kokuu_forum_%'
   OR option_name LIKE 'kokuu_exchange_%'
   OR option_name IN ('kokuu_credit_forum_url','kokuu_credit_forum_callback',
                      'kokuu_credit_score_thread','kokuu_credit_score_reply',
                      'kokuu_credit_score_liked','kokuu_credit_max_per_event',
                      'kokuu_credit_max_per_day','kokuu_credit_ttl',
                      'kokuu_credit_secret');
```

---

### 阶段 4：数据库清理

**4.1 删除废弃表**

```sql
DROP TABLE IF EXISTS kokuu.bridge_config, kokuu.bridge_exchange,
  kokuu.bridge_identity, kokuu.exchange_ledger, kokuu.exchange_orders,
  kokuu.ygg_log, kokuu.kokuu_sso_links;
DROP DATABASE IF EXISTS kokuu_forum;
```

> 执行前先 `mysqldump` 单独备份这几张表

**4.2 确认保留**
`users` / `players` / `textures` / `user_closet` / `uuid` / `options` / `credit_ledger` /
`coupon_codes` / `coupon_redemptions` / `lottery_prizes` / `lottery_draws` / `lottery_wallets` /
`oauth_*`（Passport 表，若彻底移除 SSO 可一并删）/ `migrations` / `jobs` / `notifications`

---

### 阶段 5：Bug 修复

#### 5.1 【P0·线上用户已遇到】皮肤名称超长导致数据库 500

**现象**：用户在衣柜/皮肤库上传皮肤时，弹出「严重错误」：

```
SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name' at row 1
```

SQL 中 `name` 的值是一串 64 位哈希（如 `21cc4cb0a5a78be...`），而 `textures.name` 是 `varchar(50)`。

**根因（三层）**：

1. **后端无长度校验**（主因）
   `SkinlibController.php:201` 与 `rename` 的 `:411`：

   ```php
   'name' => [
       'required',
       option('texture_name_regexp') ? 'regex:'.option('texture_name_regexp') : 'string',
   ],
   ```

   `texture_name_regexp` 当前为空字符串，规则退化成 `'string'`。
   Laravel 的 `string` 规则**只校验类型不校验长度** → 64 字符直达数据库 → 抛 1406。
   讽刺的是语言包 `skinlib.yml:10` 明写「材质名称应该小于 32 个字节」，**该约束从未实现**。

2. **前端自动填名有缺陷**
   `public/app/414.17e80e9.js`：

   ```js
   !t && n.name.endsWith('.png') && a(n.name.slice(0, n.name.length - 4))
   ```

   仅当文件名**严格以 `.png` 结尾**才自动填名。遇到无扩展名、大写 `.PNG`、或哈希命名的文件时名称框留空。

3. **兜底缺失**
   前端本应在名称为空时拦截（`if (!t) return error('skinlib.emptyTextureName')`），
   但实际请求仍发出，说明运行的前端产物与源码不一致，或存在其他提交入口（如 Yggdrasil `uploadTexture`）。

**修复**：

- [ ] `SkinlibController.php:201`（upload）补 `'string', 'max:50'`
- [ ] `SkinlibController.php:411`（rename）同样补 `'string', 'max:50'`
- [ ] 前端自动填名改为大小写不敏感 + 无扩展名兜底：
      `js
    !t && (() => {
      const base = n.name.replace(/\.png$/i, '');
      if (base) a(base.slice(0, 50));
    })();
    `
- [ ] 名称输入框加 `maxLength={50}`
- [ ] 语言包改为与实际约束一致（32 字节 vs 50 字符，二选一后统一）

> 后端改动即时生效（清 opcache 即可）；前端改动需 `yarn build` 重新构建。
> **后端修好后，即使前端仍有问题，用户看到的也是友好提示而非数据库 500。**

#### 5.2 衣柜页上传入口可发现性差（用户已反馈）

**现象**：用户在群里反馈「直接去衣柜里面，上传不了」「必须从右上角才能上传」。

**根因**：衣柜页的「上传材质」是 `nav-item d-none d-md-block`，
`d-md-block` 意味着**视口 <768px 时完全隐藏**（手机端看不到），
且它混在「皮肤 / 披风」标签页那一排里，视觉上不像按钮。

**修复**：

- [ ] 衣柜卡片头部右侧、搜索框旁加显眼的实心按钮（不依赖响应式类）：
      `` jsx
    <a href={`${blessing.base_url}/skinlib/upload`}
       className="btn btn-success btn-sm ml-2">
      <i className="fas fa-upload mr-1"></i>上传材质
    </a>
     ``
- [ ] 保留原 tab 位置或直接移除，避免重复入口

> 需改 `resources/assets/js/` 源码后 `yarn build`，不能直接改编译产物。

#### 5.3 其他已确认问题

| #   | 问题                                 | 位置                             | 影响                                            | 优先级 |
| --- | ------------------------------------ | -------------------------------- | ----------------------------------------------- | ------ |
| 1   | 论坛积分流水菜单对普通用户 403       | `kokuu-credit:1729` + `kokuu-ui` | 已在 kokuu-ui 侧规避，credit 侧路由权限仍不匹配 | P2     |
| 2   | SSO 配置前缀错误（注释自述）         | `kokuu-credit:568`               | 历史遗留，随 SSO 删除自动消失                   | —      |
| 3   | `ygg_log` 表 0 行但仍在写            | —                                | 待确认是否禁用日志                              | P3     |
| 4   | `max_texture_width` 设 8192 形同虚设 | `options`                        | 只在 SkinlibController 生效，衣柜上传路径不检查 | P2     |
| 5   | 存在 1024×512 披风（tid=14）         | `textures`                       | 标准披风应 64×32/64×64，渲染会拉伸              | P3     |
| 6   | `max_upload_file_size` 单位语义模糊  | `options`                        | 1024 与 PHP 2M 上限关系不明                     | P3     |
| 7   | nginx 敏感路径被大量扫描             | —                                | `.env`/`.git/config` 各 30-70 次探测，均 403    | P2     |

---

## 四、风险与回滚

| 风险           | 缓解                                                        |
| -------------- | ----------------------------------------------------------- |
| 删插件后白屏   | 先改 `plugins_enabled` 再删目录；阶段 2 前先备份 options 表 |
| SSO 剥离不干净 | 保留 `pre-refactor-20261103` tag，随时 `git checkout`       |
| 积分数据丢失   | 阶段 4 前单独 dump 相关表                                   |
| 用户数据丢失   | 已全量备份至 NAS，另有 `all-databases.sql.gz`               |
| 域名/证书失效  | 下线域名不删 DNS 记录，保留 30 天                           |

**回滚方式**：`git checkout pre-refactor-20261103` + 恢复数据库 dump + `Y:\kokuu-backup\20261103-1445\RESTORE.md`

---

## 五、执行顺序建议

```
阶段 0（版本基线）      ← 必须先做，唯一回退锚点
    ↓
阶段 1（下线服务）      ← 可并行，风险最低，先见效
    ↓
阶段 2（删插件）        ← forum/core/exchange + coupon 兑换码部分
    ↓
阶段 2.5（外观整合）    ← ★新建 kokuu-appearance
    ↓
阶段 2.6（抽奖拆分）    ← ★新建 kokuu-lottery
    ↓
阶段 2.7（UI Kit 契约） ← ★定义变量与类名，废弃作用域前缀
    ↓
阶段 3（重构 credit）   ← 剥离 SSO/论坛 + Core/Host 分层
    ↓
阶段 4（清理数据库）
    ↓
阶段 5（修 bug）        ← 5.1 皮肤上传 500（P0，线上已发生）
                          5.2 衣柜上传入口（P1，用户已反馈）
```

> 阶段 2.5 / 2.6 / 2.7 都是**纯结构迁移**，建议各自独立提交，
> 出问题时 `git revert` 即可，不牵连业务逻辑。

---

## 六、已确认的决策

| 项             | 决策                                                                            |
| -------------- | ------------------------------------------------------------------------------- |
| 下载页范围     | 下线 `xz.kokuu.org` / `kokoo-download` / `kokuu-drive` / `kokuu-panel` 全部四项 |
| 积分/签到/抽奖 | **拆为独立插件，Core 层可移植，脱离皮肤站也能用**                               |
| 兑换码         | **删除**（原 coupon 插件中抽奖部分保留并独立）                                  |
| 外观插件       | **home + ui + quote 三合一为 kokuu-appearance**                                 |
| 外观契约       | **提供 UI Kit（CSS 变量 + `k-` 类名），业务插件可选使用、无依赖**               |
| 作用域前缀     | **废弃 `body.kokuu-apple`**，改用类名前缀做作用域                               |
| 官方插件市场   | **不改动**（yggdrasil-api / single-player-limit 保持原样）                      |
| 代码基线       | 以服务器为准，补齐后推到 GitHub                                                 |
| 执行方式       | 先出方案文档，审核后再动手                                                      |
| `earn` 接口    | **已查证：仅论坛回调使用，访问日志 0 次命中，可删**                             |
| R2 桶          | **桶也一并清空**（清空前先拉文件清单存 NAS）                                    |
| Passport       | 彻底移除，5 张 `oauth_*` 表一并删除                                             |
| GitHub 推送    | 服务器生成 SSH 部署密钥                                                         |
| 论坛库         | 先导出到 NAS 再删库                                                             |
| 皮肤上传 bug   | **并到阶段 5，不单独处理**                                                      |

## 七、遗留待确认

**架构相关**（详见 [ARCHITECTURE.md](ARCHITECTURE.md) §六）：

1. **签到是否独立成 `kokuu-signin` 插件？** 约 300 行，与积分流水强相关。
2. **Core 层是否发布为 Composer 包？**（如 `kokuu/credit-core`）若要"脱离皮肤站可用"，建议发布。
3. **UI Kit 是否独立成 `kokuu-ui-kit` 插件？** 独立则其他项目也能复用样式。

**环境相关**：

4. **R2 凭据**：清空桶需要 R2 API key，或确认服务器 `config.json` 内凭据仍有效。
5. **前端构建环境**：服务器上能否跑 `yarn build`？影响阶段 5.1/5.2 的前端改动方式。
6. **插件命名**：`kokuu-appearance` / `kokuu-lottery` 是否接受？

**数据相关**：

7. **兑换码数据**：20 个未使用码先 dump 到 NAS 再删，还是直接删？
8. **抽奖奖品**：当前 6 个奖品写死在代码里，拆分时是否改为可配置？
