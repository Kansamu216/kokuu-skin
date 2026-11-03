# KokuuSkin UI Kit 契约

> 版本 2.0 · 最后更新 2026-11-03
>
> 本文件定义**外观插件与业务插件之间的标准接口**。
> 业务插件只要按此约定输出类名，即可自动获得 KokuuSkin 的视觉风格，
> **且不依赖外观插件**——外观插件停用后仍可正常工作（配色退化但不错乱）。

---

## 一、设计原则

### 1.1 类名即契约

业务插件**只写类名**，不 import 任何外观代码：

```html
<div class="k-card">
  <div class="k-card__head">积分流水</div>
  <div class="k-card__body">
    <table class="k-table">
      ...
    </table>
  </div>
</div>
```

### 1.2 无依赖保证

每个样式属性都带 fallback，外观插件停用后仍可读：

```css
.k-card {
  background: var(--k-surface, #fff);
}
```

### 1.3 为什么用 `k-` 前缀做作用域

旧版依赖 `body.kokuu-apple` 运行时加类，导致：

- `rebuild.js` 只对 user/admin/skinlib 注入，`plugin/*` 被漏掉
- 业务插件要为「有类/无类」双写样式（dash.css 曾 500+ 行）

改用类名前缀后，**类名本身就是作用域**，无需运行时判断。

---

## 二、设计令牌（CSS 变量）

所有令牌定义在 `kokuu-appearance/assets/tokens.css`。

### 2.1 品牌色（不随主题变化）

| 令牌              | 浅色值    | 用途             |
| ----------------- | --------- | ---------------- |
| `--k-brand-ink`   | `#2B1F52` | 品牌主色（深紫） |
| `--k-brand-rain`  | `#0FA3A3` | 强调色（青）     |
| `--k-brand-lilac` | `#8B7FD4` | 次强调（淡紫）   |
| `--k-brand-moon`  | `#C9BCF0` | 点缀（月白紫）   |

### 2.2 语义色彩（随主题变化）

| 令牌            | 浅色                 | 深色                    |
| --------------- | -------------------- | ----------------------- |
| `--k-bg`        | `#F7F5F0` 暖纸白     | `#0B0716`               |
| `--k-surface`   | `#FFFFFF`            | `#17112B`               |
| `--k-surface-2` | `#FAF9F6`            | `#1F1839`               |
| `--k-surface-3` | `#F3F1EC`            | `#272041`               |
| `--k-text`      | `#241A45`            | `#EDEAFF`               |
| `--k-text-2`    | `#6B6483`            | `#A9A2C4`               |
| `--k-text-3`    | `#9A94AC`            | `#6F6889`               |
| `--k-border`    | `rgba(43,31,82,.10)` | `rgba(167,139,250,.16)` |

### 2.3 状态色

`--k-success` / `--k-warning` / `--k-danger` / `--k-info`，
以及对应的 `-bg` 背景色变体。

### 2.4 形状与阴影

| 令牌                    | 值              | 用途          |
| ----------------------- | --------------- | ------------- |
| `--k-r-xs` ~ `--k-r-xl` | 8/10/12/16/22px | 圆角标度      |
| `--k-r-pill`            | 999px           | 胶囊          |
| `--k-elev-1/2/3`        | 三级阴影        | 层次          |
| `--k-elev-glow`         | 聚焦光圈        | focus-visible |

### 2.5 间距与字体

- 间距：`--k-s-1`(4px) 到 `--k-s-9`(56px)，4 的倍数
- 字体：`--k-font-sans` / `--k-font-mono`
- 字号：`--k-fs-xs`(12) 到 `--k-fs-3xl`(34)
- 字重：`--k-fw-normal`(400) / `medium`(500) / `bold`(650) / `black`(800)

### 2.6 动效

- 缓动：`--k-ease` / `--k-ease-out`
- 时长：`--k-dur-fast`(.15s) / `base`(.25s) / `slow`(.38s)

---

## 三、组件类名

所有组件定义在 `kokuu-appearance/assets/ui-kit.css`。

### 3.1 卡片

```html
<div class="k-card">
  <div class="k-card__head">
    标题
    <button class="k-btn k-btn--sm">操作</button>
  </div>
  <div class="k-card__body">内容</div>
  <div class="k-card__foot">
    <button class="k-btn k-btn--primary">确定</button>
  </div>
</div>
```

修饰符：

- `.k-card--hover` — 可交互卡片（悬停上浮）
- `.k-card--accent` — 品牌色描边
- `.k-card__body--flush` — 无内边距（表格用）
- `.k-card__sub` — 副标题

### 3.2 按钮

```html
<button class="k-btn k-btn--primary">主按钮</button>
<button class="k-btn">次按钮</button>
<button class="k-btn k-btn--ghost">幽灵</button>
<button class="k-btn k-btn--danger">危险</button>
<button class="k-btn k-btn--ink">品牌深色</button>
```

尺寸：`.k-btn--sm` / `.k-btn--lg` / `.k-btn--block`

### 3.3 表格

```html
<div class="k-table-wrap">
  <table class="k-table">
    <thead>
      <tr>
        <th>用户</th>
        <th class="k-num">积分</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Kokuu</td>
        <td class="k-num">100</td>
      </tr>
    </tbody>
  </table>
</div>
```

`.k-table-wrap` 提供横向滚动与容器查询；`.k-num` 是等宽数字右对齐。

### 3.4 徽章

```html
<span class="k-badge">默认</span>
<span class="k-badge k-badge--success">已签</span>
<span class="k-badge k-badge--warning">待定</span>
<span class="k-badge k-badge--danger">失败</span>
<span class="k-badge k-badge--info">信息</span>
```

### 3.5 表单

```html
<div class="k-field">
  <label class="k-field__label">
    名称
    <span class="k-field__hint">补充说明</span>
  </label>
  <input class="k-input" type="text" />
</div>
```

也支持 `.k-select` / `.k-textarea`。

### 3.6 统计

```html
<div class="k-stat">
  <div class="k-stat__value">1,234</div>
  <div class="k-stat__label">总积分</div>
</div>
```

### 3.7 空状态

```html
<div class="k-empty">
  <div class="k-empty__icon">📭</div>
  暂无数据
</div>
```

### 3.8 提示条

```html
<div class="k-alert k-alert--info">提示内容</div>
<div class="k-alert k-alert--warning">警告</div>
<div class="k-alert k-alert--danger">错误</div>
<div class="k-alert k-alert--success">成功</div>
```

### 3.9 网格

```html
<div class="k-grid k-grid--3">
  <div class="k-card">...</div>
  <div class="k-card">...</div>
  <div class="k-card">...</div>
</div>
```

`.k-grid--2/3/4`，窄屏自动降级为单列。

### 3.10 其他

- `.k-divider` — 分割线
- `.k-container` — 最大宽度容器
- `.k-link` — 带下划线滑入效果的链接
- `.k-skeleton` — 骨架屏
- `.k-spinner` — 加载指示

---

## 四、动效工具类

定义在 `kokuu-appearance/assets/motion.css`。

| 类名               | 效果                           |
| ------------------ | ------------------------------ |
| `.k-animate-up`    | 淡入上浮                       |
| `.k-animate-scale` | 淡入缩放                       |
| `.k-animate-slide` | 侧滑进入                       |
| `.k-stagger`       | 容器加此 class，子项依次淡入   |
| `.k-reveal`        | 滚动进入视口时淡入（渐进增强） |
| `.k-glow-border`   | 渐变流光边框                   |
| `.k-badge--live`   | 呼吸光晕                       |

---

## 五、业务插件接入示例

### 5.1 一个完整的业务面板

```html
<div class="k-container">
  <div class="k-grid k-grid--2">
    <div class="k-card k-card--hover">
      <div class="k-card__head">积分余额</div>
      <div class="k-card__body">
        <div class="k-stat">
          <div class="k-stat__value">{{ balance }}</div>
          <div class="k-stat__label">当前积分</div>
        </div>
      </div>
    </div>

    <div class="k-card">
      <div class="k-card__head">
        最近记录
        <span class="k-badge k-badge--info">{{ count }} 条</span>
      </div>
      <div class="k-card__body k-card__body--flush">
        <div class="k-table-wrap">
          <table class="k-table">
            <thead>
              <tr>
                <th>时间</th>
                <th class="k-num">变化</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>10:00</td>
                <td class="k-num">+10</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
```

### 5.2 配套的兜底样式（可选但推荐）

业务插件自带一份最小样式，确保外观插件未启用时也可读：

```css
/* kokuu-lottery/assets/lottery.css 开头 */
.k-card {
  background: var(--k-surface, #fff);
  border-radius: var(--k-radius, 12px);
}
.k-btn--primary {
  background: var(--k-accent, #0fa3a3);
  color: #fff;
}
```

---

## 六、验收标准

业务插件按此契约实现后，应满足：

- [ ] 停用 `kokuu-appearance` 后，页面仍能正常渲染（配色退化但不错乱）
- [ ] 重命名 appearance 插件目录，业务页面无 404
- [ ] 深色模式（`body.dark-mode`）下所有组件跟随变色
- [ ] 浏览器 Network 面板无 CSS/JS 404
- [ ] 移动端（<640px）布局正常降级

---

## 七、不在契约内的部分

以下属于 `kokuu-appearance` 的内部实现，**业务插件不应依赖**：

- 具体的 CSS 文件结构（theme/void/apple 分层）
- `body.kokuu-apple` 等运行时类名（正在废弃）
- 首页 `home.js` 的 DOM 结构
- 后台外壳重建（`rebuild.js`）的具体行为

这些可以随时调整而不影响按契约实现的业务插件。
