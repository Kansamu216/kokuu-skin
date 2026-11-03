# kokuu-credit 解耦方案

> 目标：让 `kokuu-credit` 不再依赖任何外观插件，外观层与业务层彻底分离
> 关联：[REFACTOR-PLAN.md](REFACTOR-PLAN.md) 阶段 3 的详细展开

---

## 一、耦合现状

### 1.1 依赖方向

```
kokuu-credit ──依赖──> kokuu-ui (apple.css)
     │                      │
     │                      └──依赖──> kokuu-home (theme.css/void.css)
     │
     └──依赖──> kokuu-home 的 CSS 变量 (--k-*)
```

**单向向下依赖**：credit 依赖外观，外观不依赖 credit（已确认无反向引用）。

### 1.2 具体的耦合点（5 处）

| #   | 位置                         | 耦合内容                                                                         | 严重度                       |
| --- | ---------------------------- | -------------------------------------------------------------------------------- | ---------------------------- |
| 1   | `bootstrap.php:1576`         | 硬编码路径 `Hook::addStyleFileToPage('/plugins/kokuu-ui/assets/apple.css', ...)` | **高**：插件改名即断         |
| 2   | `views/config.blade.php:43`  | `$__apple = url('/plugins/kokuu-ui/assets/apple.css')` 直接拼 URL                | **高**：同上                 |
| 3   | `assets/apple-scope.js`      | 专门给 plugin/\* 页面补 `kokuu-apple` 类，服务于 apple.css 的作用域              | **中**：整个文件是耦合产物   |
| 4   | `assets/dash.css`（500+ 行） | 大量 `body.kokuu-apple xxx` 和 `body:not(.kokuu-apple) xxx` 双写规则             | **中**：为兼容两种状态而翻倍 |
| 5   | `views/user/credit.twig` 等  | 视图里出现 `kokuu-ui`、`kokuu-forum` 字样                                        | **低**：多为注释             |

### 1.3 耦合的根源

外观插件用 **CSS 类名作用域**（`body.kokuu-apple`）来限定样式范围，而这个类是 `kokuu-ui/rebuild.js` **在运行时**加到 body 上的。

`rebuild.js` 只注入 `user / admin / skinlib` 三个前缀的页面，**不含 `plugin/`**。

于是 `kokuu-credit` 的自有页面（`/plugin/kokuu-credit/ledger`）拿不到这个类，apple.css 规则全部失效，页面退回 AdminLTE 紫渐变。credit 只能自己想办法：

- 手动引入 apple.css（耦合点 1、2）
- 自己写脚本加类名（耦合点 3）
- 在 dash.css 里为「有类」和「无类」两种状态各写一遍（耦合点 4）

**这是典型的"补丁摞补丁"**：外观层的设计缺陷（运行时加类 + 白名单前缀）逼着业务层去适配。

---

## 二、解耦目标

### 2.1 期望的依赖关系

```
kokuu-appearance  ──提供──>  CSS 变量 + 通用组件类（不依赖任何业务插件）
       ↑
       │ 只依赖「约定」，不依赖「具体插件」
       │
kokuu-credit      ──使用──>  变量与类名（不关心它们由谁提供）
```

**核心原则：credit 不应该知道 appearance 的存在。**

### 2.2 判定标准

解耦完成后，以下操作不应破坏 credit：

- [ ] 重命名 `kokuu-appearance` 插件目录
- [ ] 停用 / 卸载 appearance 插件
- [ ] 调整 appearance 的 CSS 文件结构
- [ ] 新增其他外观插件

---

## 三、解耦方案

### 3.1 建立 CSS 变量契约（替代硬编码路径）

**问题**：credit 直接引用 appearance 的 CSS 文件路径。

**方案**：appearance 通过全局 CSS 变量暴露设计令牌（design tokens），credit 只消费变量。

在 `kokuu-appearance/assets/theme.css` 的 `:root` 中定义：

```css
:root {
  /* 色彩令牌 */
  --k-ink: #1d1d1f;
  --k-surface: #ffffff;
  --k-rain: #0071e3;
  --k-border: rgba(0, 0, 0, 0.08);
  --k-radius: 12px;
  --k-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);

  /* 深色模式 */
}
body.dark-mode {
  --k-ink: #f5f5f7;
  --k-surface: #1c1c1e;
  /* ... */
}
```

credit 的 `credit.css` / `dash.css` 改为：

```css
/* 不依赖 body.kokuu-apple，直接消费变量 */
.kcDash-card {
  background: var(--k-surface, #fff);
  color: var(--k-ink, #1d1d1f);
  border-radius: var(--k-radius, 12px);
}
```

**关键**：每个 `var()` 都带 fallback（现状已部分如此），即使 appearance 未启用，credit 也能正常显示。

### 3.2 移除 apple-scope.js

**问题**：这个文件存在的唯一理由是补 `kokuu-apple` 类。

**方案**：改为让 appearance 在 `bootstrap.php` 里对所有页面注入作用域类，而不是由 `rebuild.js` 在运行时按前缀加。

```php
// kokuu-appearance/bootstrap.php
// 不再依赖 rebuild.js 的运行时加类，改为全站注入
Hook::addScriptFileToPage($plugin->assets('apple-scope.js'), ['*']);
```

或者更彻底：**放弃 `body.kokuu-apple` 作用域前缀**，改为在 `apple.css` 里用 `:root` 变量 + 直接类名。这样根本不需要加类。

> 推荐后者。作用域前缀本来是为了「不影响内核默认样式」，但既然全站都要应用主题，前缀就是多余的一层。

### 3.3 dash.css 去掉双写

**问题**：`body.kokuu-apple .kcDash-card` 和 `body:not(.kokuu-apple) .kcDash-card` 各写一遍。

**方案**：3.2 完成后，`kokuu-apple` 类不再有意义，双写规则合并为一份：

```css
/* 之前 */
body.kokuu-apple .kcDash-card {
  ...;
}
body:not(.kokuu-apple) .kcDash-card {
  ...;
}

/* 之后 */
.kcDash-card {
  ...;
}
```

预计 `dash.css` 可从 500+ 行减到 250 行左右。

### 3.4 视图中的路径引用

`views/config.blade.php:43` 的 `$__apple = url('/plugins/kokuu-ui/assets/apple.css')`：

**方案 A**（推荐）：删除。配置页也走 3.1 的变量契约。
**方案 B**：如果配置页需要特殊的 Apple 样式，把它内联进 `views/config.blade.php` 的 `<style>` 块，自包含。

### 3.5 清理跨插件注释

`views/*.twig`、`assets/*.css` 中提到 `kokuu-ui` / `kokuu-forum` 的注释，随对应插件删除一并清理。

---

## 四、执行顺序

解耦必须**在 appearance 整合之后**做，否则要改两次：

```
阶段 2.5  外观三合一 → kokuu-appearance
              ↓
阶段 3.1  建立 CSS 变量契约（appearance 侧）
              ↓
阶段 3.2  credit 改为消费变量（移除硬编码路径）
              ↓
阶段 3.3  移除 apple-scope.js，合并 dash.css 双写
              ↓
阶段 3.4  验证：停用 appearance 后 credit 仍可用
```

---

## 五、验证清单

解耦是否成功，用这几个测试：

- [ ] **停用测试**：`plugins_enabled` 移除 appearance，credit 页面仍可正常显示（配色退化但不错乱）
- [ ] **重命名测试**：把 `kokuu-appearance` 改成 `kokuu-appearance-test`，credit 无报错
- [ ] **深色模式**：切换深色主题，credit 所有页面跟随
- [ ] **视觉一致性**：仪表盘、流水页、配置页三处观感统一
- [ ] **无 404**：浏览器 Network 面板无 CSS/JS 404

---

## 六、待确认

1. **作用域前缀是否彻底放弃**：`body.kokuu-apple` 这套机制要不要保留？

   - 放弃：更简洁，但需要确保 apple.css 不污染内核默认样式
   - 保留：更安全，但需要解决 plugin/\* 页面的加类问题

2. **CSS 变量命名**：现有是 `--k-*`（kokuu），是否统一改为 `--kokuu-*`？

3. **是否引入构建工具**：目前 CSS 是手写的，是否需要引入 PostCSS/Sass 来管理变量？
   - 优点：变量复用、嵌套、自动前缀
   - 缺点：增加构建复杂度，服务器上需要 Node 环境
