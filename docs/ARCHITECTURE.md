# 插件架构重构方案：可移植业务层 + 标准外观层

> 目标：
>
> 1. 抽奖 / 签到 / 积分 拆成独立插件，**脱离 KokuuSkin 也能用**
> 2. 外观插件提供**标准接口**，其他插件可选用其样式，但**不产生依赖**
>    关联：[REFACTOR-PLAN.md](REFACTOR-PLAN.md) · [DECOUPLING.md](DECOUPLING.md)

---

## 一、现状与差距

### 1.1 当前 credit 与 BlessingSkin 的耦合点

| 耦合类型     | 具体内容                                                                                  | 可移植性影响           |
| ------------ | ----------------------------------------------------------------------------------------- | ---------------------- |
| **用户模型** | `use App\Models\User`，直接读 `uid/score/nickname/email/permission/verified/last_sign_at` | **阻断级**：换框架就崩 |
| **路由钩子** | `Hook::addRoute()`（3 处）                                                                | 阻断级                 |
| **资源注入** | `Hook::addStyleFileToPage` / `addScriptFileToPage`（各 3 处）                             | 阻断级                 |
| **视图系统** | `View::addLocation` / `View::share`（5 处） / `view()->file`                              | 阻断级                 |
| **配置存储** | `app('options')`（4 处）                                                                  | 高                     |
| **事件系统** | `Event::listen('user.sign.after')`、`ConfigureUserMenu`                                   | 高                     |
| **布局接管** | `Filter::add('grid:admin.index')` / `grid:user.index`                                     | 高（且与外观强相关）   |
| **鉴权**     | `auth()->user()`、`$user->permission < User::ADMIN`                                       | 高                     |

**结论**：直接"搬出去"不可行。需要抽象层。

### 1.2 当前 coupon 的内部边界

`kokuu-coupon` 一个插件塞了两个不相干的功能：

| 功能   | 数据表                                                 | 行数占比（估） |
| ------ | ------------------------------------------------------ | -------------- |
| 兑换码 | `coupon_codes` / `coupon_redemptions`                  | ~50%           |
| 抽奖   | `lottery_prizes` / `lottery_draws` / `lottery_wallets` | ~50%           |

两者共享的只有 `kokuu_credit_ledger()` 加分调用，边界清晰，**可以安全拆分**。

> ⚠️ 这推翻了此前"删除整个 kokuu-coupon"的决定。
> 新决策：**删除兑换码，保留抽奖并拆为独立插件**。

---

## 二、目标架构

### 2.1 分层设计

```
┌─────────────────────────────────────────────────────────┐
│  Layer 3  宿主适配层（Host Adapter）                      │
│  kokuu-bs-bridge —— 唯一知道 BlessingSkin 存在的地方       │
│  · 用户模型适配  · Hook/Route 注册  · 视图注入  · 配置存储  │
└─────────────────────────────────────────────────────────┘
                          ↑ 实现接口
┌─────────────────────────────────────────────────────────┐
│  Layer 2  业务核心（Host-agnostic，纯 PHP）               │
│  kokuu-credit-core / kokuu-lottery-core                  │
│  · 领域逻辑  · 数据访问  · 不 import 任何 App\ 命名空间    │
└─────────────────────────────────────────────────────────┘
                          ↑ 消费
┌─────────────────────────────────────────────────────────┐
│  Layer 1  外观契约（UI Kit Contract）                     │
│  kokuu-appearance 暴露 CSS 变量 + 组件类名，无反向依赖     │
└─────────────────────────────────────────────────────────┘
```

### 2.2 插件清单

```
plugins/
├── yggdrasil-api/            官方，不动
├── single-player-limit/      官方，不动
│
├── kokuu-appearance/         外观（home+ui+quote 三合一）
│                             提供 UI Kit 契约
│
├── kokuu-credit/             积分核心 + 签到
│   ├── src/Core/             领域逻辑（可移植）
│   └── src/Host/             BlessingSkin 适配
│
├── kokuu-lottery/            ★新建：抽奖（从 coupon 拆出）
│   ├── src/Core/
│   └── src/Host/
│
删除：kokuu-coupon（兑换码部分）/ kokuu-forum / kokuu-core / kokuu-exchange
```

> 签到是否独立成 `kokuu-signin`？见 §六 待确认 1。

---

## 三、可移植性设计

### 3.1 核心原则：Core 层不 import 宿主命名空间

**反例（当前）**：

```php
use App\Models\User;

function kokuu_credit_dashboard_data(?User $user): array {
    return ['score' => $user->score, 'sign' => ...];
}
```

**正例（目标）**：

```php
namespace Kokuu\Credit\Core;

interface UserProvider {
    public function findById(int $id): ?array;      // 返回数组，不返回模型
    public function findByEmail(string $email): ?array;
    public function adjustScore(int $id, int $delta): int;
    public function isAdmin(?array $user): bool;
}

final class CreditService {
    public function __construct(private UserProvider $users) {}

    public function balance(int $uid): int { ... }
    public function ledger(int $uid, int $limit): array { ... }
}
```

### 3.2 三个必须抽象的接口

| 接口               | 职责                         | BlessingSkin 实现    | 其他框架实现     |
| ------------------ | ---------------------------- | -------------------- | ---------------- |
| `UserProvider`     | 用户查询、积分读写、权限判断 | `BsUserProvider`     | 各自实现         |
| `LedgerRepository` | 流水增删查、幂等             | `DbLedgerRepository` | 可复用（纯 SQL） |
| `ConfigStore`      | 配置读写                     | `BsOptionsStore`     | 各自实现         |

### 3.3 前端资源也要抽象

当前 `Hook::addStyleFileToPage($plugin->assets('credit.css'), ['admin', ...])` 与宿主强绑。

**方案**：Core 层只输出「需要哪些资源 + 在哪些页面」，由 Host 层决定如何注入：

```php
// Core
final class AssetManifest {
    public function styles(): array { return ['credit.css', 'dash.css']; }
    public function scripts(): array { return ['credit.js', 'sign.js']; }
    public function pages(): array { return ['admin', 'admin/*', 'user', 'user/*']; }
}
```

### 3.4 可移植性验收标准

Core 层应满足：

- [ ] `grep -r "App\\\\" src/Core/` 无结果
- [ ] `grep -r "Blessing" src/Core/` 无结果
- [ ] `grep -r "Hook::\\|Filter::\\|View::" src/Core/` 无结果
- [ ] 可写一个 50 行的 CLI 测试脚本，脱离 Laravel 跑通「加分 → 查余额 → 查流水」

---

## 四、外观契约（UI Kit）

### 4.1 设计目标

其他插件**可选**使用外观，但**不依赖**它：

```php
// 业务插件不写 use KokuuAppearance\...
// 只输出标准类名，样式由外观插件提供
```

### 4.2 契约内容

**CSS 变量（design tokens）**

```css
:root {
  /* 表面 */
  --k-surface: #ffffff;
  --k-surface-2: #f5f5f7;
  --k-ink: #1d1d1f;
  --k-ink-muted: #6e6e73;
  --k-border: rgba(0, 0, 0, 0.08);

  /* 强调 */
  --k-accent: #0071e3;
  --k-success: #34c759;
  --k-warning: #ff9f0a;
  --k-danger: #ff3b30;

  /* 形状 */
  --k-radius: 12px;
  --k-radius-sm: 8px;
  --k-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  --k-shadow-lg: 0 8px 24px rgba(0, 0, 0, 0.08);

  /* 间距 */
  --k-space-1: 4px;
  --k-space-2: 8px;
  --k-space-3: 16px;
  --k-space-4: 24px;
}
body.dark-mode {
  /* 覆盖 */
}
```

**组件类名（BEM 风格，带 `k-` 前缀）**

```css
.k-card        /* 卡片容器 */
/* 卡片容器 */
.k-card__head  /* 卡片头部 */
.k-card__body  /* 卡片主体 */
.k-card__foot  /* 卡片底部 */

.k-btn         /* 按钮基础 */
.k-btn--primary
.k-btn--ghost
.k-btn--danger

.k-table       /* 表格 */
.k-badge       /* 徽章 */
.k-field       /* 表单字段 */
.k-empty       /* 空状态 */
.k-stat; /* 统计数字 */
```

### 4.3 使用方式（无依赖）

业务插件的视图里：

```html
<!-- 有外观插件时：自动应用 Apple 风格 -->
<div class="k-card">
  <div class="k-card__head">积分流水</div>
  <div class="k-card__body">
    <table class="k-table">
      ...
    </table>
  </div>
</div>
```

```css
/* 业务插件自带兜底样式，保证无外观插件时也能用 */
.k-card {
  background: var(--k-surface, #fff);
  border-radius: var(--k-radius, 12px);
}
```

**关键**：业务插件自带最小兜底样式（带 fallback 的变量），外观插件提供增强版。两者共存时外观优先（后加载覆盖）。

### 4.4 为什么不用 CSS 类作用域前缀

当前 `body.kokuu-apple` 的做法导致：

- 需要运行时加类（`rebuild.js`）
- 需要白名单前缀（`plugin/*` 被漏掉）
- 业务插件要为「有类/无类」双写样式（`dash.css` 500+ 行）

**改为 `k-` 前缀类名后**：类名本身就是作用域，无需运行时判断，无需双写。

---

## 五、执行顺序

```
阶段 A  外观三合一 → kokuu-appearance
            ↓
阶段 B  定义 UI Kit 契约（变量 + 类名），appearance 提供增强样式
            ↓
阶段 C  credit 改造：剥离 Core / Host，改用 k- 类名
            ↓
阶段 D  coupon 拆分：抽奖独立为 kokuu-lottery，兑换码删除
            ↓
阶段 E  验证可移植性（CLI 测试 Core 层）
            ↓
阶段 F  验证外观可选性（停用 appearance，全部页面仍可用）
```

---

## 六、待确认

1. **签到是否独立插件**

   - 签到逻辑约 300 行，依赖 `users.last_sign_at`
   - 独立的好处：可移植性更好
   - 不独立的好处：签到与积分流水强相关（签到必产生流水），拆开要处理跨插件调用

2. **Core 层是否发布为 Composer 包**

   - 发布：`kokuu/credit-core` 可被任何 PHP 项目 `composer require`
   - 不发布：仅作为插件内部目录结构
   - 若目标是"脱离皮肤站也能用"，建议发布

3. **UI Kit 是否独立插件**

   - 独立 `kokuu-ui-kit`：其他项目也能用这套样式
   - 并入 `kokuu-appearance`：少一个插件，但外观与主题绑死

4. **现有 590 条积分流水如何处理**

   - 拆分后 `credit_ledger` 表结构是否变化
   - 建议保持不变，只改代码结构

5. **抽奖的奖品配置**
   - 当前 6 个奖品写死在代码里（`kokuu_coupon_ensure_default_prizes`）
   - 拆分时是否改为可配置
