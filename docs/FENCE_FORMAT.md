# Fence 格式规范

本文档定义了 TeXtend 插件支持的 fence 围栏块语法格式，供用户使用和开发者扩展参考。

## 基本语法

Fence 围栏块使用三个或更多冒号（`:::`）作为开始和结束标记：

```markdown
:::type[:variant] [{attributes}] [title]
内容区域
:::
```

### 语法结构说明

- **`type`**：必需，fence 类型名称（如 `tip`、`tabs` 等）
- **`variant`**：可选，同一类型下的变体（如 `details:open`）
- **`{attributes}`**：可选，自定义数据属性，格式为 `{key1: value1, key2: value2}`
- **`title`**：可选，标题文本，支持内联 Markdown

## 内置 Fence 类型

### 1. 信息提示类

#### `info` - 信息块

```markdown
:::info 这是一条信息
普通信息提示内容
:::
```

#### `tip` - 提示块

```markdown
:::tip 实用建议
这是一条提示信息
:::
```

#### `warning` - 警告块

```markdown
:::warning 注意事项
这是一条警告信息
:::
```

#### `danger` - 危险块

```markdown
:::danger 危险操作
这是一条危险警告
:::
```

#### `success` - 成功块

```markdown
:::success 操作成功
这是一条成功提示
:::
```

### 2. 交互组件类

#### `tabs` - 标签页

标签页用于组织多个相关内容块，使用 `===` 或 `===+` 分隔各个标签：

```markdown
:::tabs
===+ 默认打开的标签
这是第一个标签的内容

=== 第二个标签
这是第二个标签的内容

=== 第三个标签
支持 Markdown 内容
:::
```

**说明**：
- 使用 `===+` 可指定该标签为默认打开状态
- 第一个无 `+` 标记的标签将作为默认选项

#### `details` - 折叠详情

使用 HTML5 `<details>` 元素创建可折叠内容：

```markdown
:::details:open 默认展开
这里是详情内容，支持完整 Markdown 语法
:::

:::details 点击查看
默认折叠的内容
:::
```

**变体**：
- 无变体：默认折叠
- `open`：默认展开

### 3. 原始内容类

#### `raw` - 原始输出

不解析内部内容，直接原样输出：

```markdown
:::raw
<div class="custom-html">
  <p>这里的 HTML 不会被解析</p>
</div>
:::

:::raw:variant {id: custom-id}
带变体和属性的原样内容
:::
```

**说明**：
- 内容完全不被 Markdown 解析器处理
- 支持自定义 `variant` 类名（如 `variant-custom`）
- 支持通过 `{attributes}` 添加 data 属性

### 4. 布局类（已弃用但保留兼容）

#### `grid` - 网格布局

```markdown
:::grid
[图片1](url)
[图片2](url)
:::
```

#### `masonry` - 瀑布流布局

```markdown
:::masonry
![img1](url)

![img2](url)
:::
```

#### `collapse` - 折叠面板

```markdown
:::collapse 标题
折叠内容
:::
```

## 高级用法

### 自定义属性

使用 `{key: value}` 格式为 fence 添加自定义 data 属性：

```markdown
:::tip {icon: lightbulb, color: yellow} 带属性的提示
这里可以通过 CSS 选择器 `[data-icon="lightbulb"]` 定制样式
:::
```

### 标题支持

标题支持内联 Markdown 语法：

```markdown
:::info **重要** 提示
支持 **粗体**、*斜体*、`代码` 等内联语法
:::
```

### 嵌套使用

大多数 fence 类型支持嵌套：

```markdown
:::tips 外层提示
:::details 内层详情
这是嵌套的内容
:::
:::
```

## 渲染规则

1. **类型优先级**：特殊类型（`raw`、`tabs`、`details`）优先处理
2. **默认标题**：未提供标题时，使用类型名的大写形式作为默认标题
3. **内容解析**：除 `raw` 类型外，所有内容都会被 Markdown 解析器处理
4. **类名生成**：生成的 HTML 类名格式为 `fence fence-type[-variant]`

## HTML 输出结构

### 默认类型结构

```html
<div class="fence fence-type" data-key="value">
  <div class="fence-title">标题</div>
  <div class="fence-content">
    解析后的内容
  </div>
</div>
```

### Details 类型结构

```html
<details class="fence fence-details" open>
  <summary>标题</summary>
  <div class="details-content">
    解析后的内容
  </div>
</details>
```

### Tabs 类型结构

```html
<div class="fence fence-tabs">
  <div class="tabs-header">
    <button class="tab-button active" data-tab="tab-id">标签1</button>
    <button class="tab-button" data-tab="tab-id">标签2</button>
  </div>
  <div class="tabs-content">
    <div class="tab-pane active" data-pane="tab-id">内容1</div>
    <div class="tab-pane" data-pane="tab-id">内容2</div>
  </div>
</div>
```

### Raw 类型结构

```html
<div class="fence fence-raw variant-custom" data-key="value">
  原始内容（未解析）
</div>
```

## 扩展开发指南

### 添加新的 Fence 类型

如需添加新的 fence 类型，请按以下步骤操作：

1. **修改解析器**：在 `plugin/HyperDown.php` 的 `parseFence()` 方法中添加新的类型处理逻辑
2. **添加样式**：在 `assets/app.css` 中添加对应样式
3. **更新文档**：在此文档中记录新类型的用法和示例

### 推荐实践

- **单一职责**：每种 fence 类型专注于一个功能
- **渐进增强**：确保内容在不支持 JavaScript 时也能正常显示
- **语义化命名**：使用清晰、描述性的类型名称
- **样式隔离**：使用 `.fence-type` 选择器避免样式污染

## 版本历史

- **v1.0.0**：初始实现，支持基础信息类和布局类 fence
- 后续版本将在此记录新增类型和变更

## 相关文件

- 解析器实现：`plugin/HyperDown.php:951-1108`
- 样式定义：`assets/app.css`
- 前端交互：`assets/parser.js`
