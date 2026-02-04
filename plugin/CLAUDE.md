# TeXtend 插件开发规范

本文档定义了 TeXtend Typecho 插件的开发规范，确保代码的一致性和可维护性。

## 项目架构

```
TeXtend/
├── assets/
│   ├── app.css       # 全局样式
│   └── parser.js     # 前端解析器
├── HyperDown.php     # Markdown 语法解析器（语法扩充）
├── Content.php       # HTML 内容解析器（样式转换）
└── Plugin.php        # 插件入口
```

---

## 文件职责划分

### 1. 全局样式 (`assets/app.css`)

**职责**: 定义所有组件的 CSS 样式

**规则**:
- 使用 BEM 或语义化类命名
- 保持 CSS 与 JavaScript 解耦
- 支持暗色模式 (`@media (prefers-color-scheme: dark)`)
- 响应式设计使用媒体查询

**示例**:
```css
/* 组件基础样式 */
.component {
    border: 1px solid #ccc;
    border-radius: .25em;
}

/* 变体样式 */
.component-variant {
    background-color: #f3f4f6;
}

/* 暗色模式 */
@media (prefers-color-scheme: dark) {
    .component {
        border-color: #484f58;
    }
}
```

---

### 2. 后端语法解析器 (`HyperDown.php`)

**职责**: 扩展 Markdown 语法，将自定义语法转换为 HTML

**适用场景**:
- 新的 Markdown 语法块（如 `:::fence`）
- 语法层面的解析逻辑
- 需要递归解析的内容

**规则**:
- 在 `$blockParsers` 中注册新的块级解析器
- 实现 `parseBlock{Type}` 方法识别语法块
- 实现 `parse{Type}` 方法生成 HTML
- 保持与现有解析器的优先级协调

**示例**:
```php
// 1. 在 $blockParsers 中注册
private $blockParsers = [
    // ...
    ['fence', 75],  // 优先级 75
    // ...
];

// 2. 实现块识别方法
private function parseBlockFence(?array $block, int $key, string $line): bool
{
    if (preg_match('/^(\\s*):{3,}(.*)$/', $line, $matches)) {
        if ($this->isBlock('fence')) {
            $this->setBlock($key)->endBlock();
        } else {
            $this->startBlock('fence', $key, trim($matches[2]));
        }
        return false;
    }
    // ...
}

// 3. 实现 HTML 生成方法
private function parseFence(array $lines, string $info, int $start): string
{
    $content = trim(implode("\\n", array_slice($lines, 1, -1)));
    // 解析 info 字符串，生成 HTML
    return "<div class=\"fence\">{$content}</div>";
}
```

---

### 3. 后端内容解析器 (`Content.php`)

**职责**: 解析和转换 HTML 内容中的特定模式

**适用场景**:
- 短代码解析（如 `[x-player]`）
- 链接转换（如 GitHub 卡片）
- HTML 标签后处理
- 视频/媒体嵌入

**规则**:
- 使用 `preg_replace_callback` 进行正则替换
- 保护代码块不被误解析（使用 placeholder 机制）
- 静态方法命名格式: `parse{Feature}`
- 辅助方法命名格式: `generate{Feature}`

**示例**:
```php
public static function parser(string $content, Contents $widget, ?string $lastResult)
{
    // 1. 保护代码块
    $blocks = [];
    $codeHolder = "<pre>__BLOCK__</pre>";
    $content = preg_replace_callback('/(?:<pre>.*?<\/pre>|<code>.*?<\/code>)/ism', function ($match) use (&$blocks, $codeHolder) {
        $blocks[] = $match[0];
        return $codeHolder;
    }, $content);

    // 2. 执行解析
    $content = self::parseXPlayerShortcode($content);

    // 3. 恢复代码块
    foreach ($blocks as $block) {
        $pos = strpos($content, $codeHolder);
        if ($pos !== false) {
            $content = substr_replace($content, $block, $pos, strlen($codeHolder));
        }
    }

    return $content;
}

private static function parseXPlayerShortcode(string $content): string
{
    $pattern = '/\\[x-player\\s+src\\s*=\\s*(["\\'])([^"\\']+)\\1\\s*\\/\\]/i';
    return preg_replace_callback($pattern, function ($matches) {
        return self::generateVideoTag($matches[2]);
    }, $content);
}
```

---

### 4. 前端解析器 (`assets/parser.js`)

**职责**: 前端动态处理和交互

**适用场景**:
- 自定义 Web Components（如 `<x-github>`）
- 动态样式设置（CSS 变量）
- API 数据获取和渲染
- PJAX 兼容的重新加载

**规则**:
- 使用 `window.TeXtend` 命名空间
- `init()` 方法注册初始化逻辑
- `reload()` 方法处理 PJAX 重新加载
- 私有方法使用 `#` 前缀

**示例**:
```javascript
window.TeXtend = {
    init: function () {
        this.#registerCustomElements();
        document.addEventListener('DOMContentLoaded', _ => {
            this.reload();
        });
        document.addEventListener('pjax:complete', _ => {
            this.reload();
        });
    },

    reload: function () {
        // 处理动态样式
        document.querySelectorAll('.component').forEach(el => {
            if (el.dataset.option) el.style.setProperty('--option', el.dataset.option);
        });
    },

    #registerCustomElements: function () {
        customElements.define('x-component', class XComponent extends HTMLElement {
            constructor() {
                super();
                this.init();
            }

            async init() {
                // 组件逻辑
            }
        });
    },
};

window.TeXtend.init();
```

---

## 新增功能开发流程

### 场景 1: 新增 Markdown 语法块

1. **`HyperDown.php`**: 添加块识别和解析逻辑
2. **`assets/app.css`**: 添加对应的样式

### 场景 2: 新增短代码

1. **`Content.php`**: 添加短代码解析方法
2. **`assets/app.css`** (可选): 添加输出样式

### 场景 3: 新增前端组件

1. **`Content.php`**: 输出组件占位标签
2. **`assets/parser.js`**: 注册 Custom Element
3. **`assets/app.css`**: 添加组件样式

### 场景 4: 仅样式调整

1. **`assets/app.css`**: 添加或修改样式

---

## 代码风格规范

### PHP
- 遵循 PSR-12 编码标准
- 使用 `namespace TypechoPlugin\TeXtend;`
- 私有方法使用 `private` 前缀或 `#` 语法（PHP 8.1+）
- 方法命名使用驼峰式 `camelCase`

### CSS
- 使用缩写属性（如 `margin` 而非 `margin-top` 等）
- 颜色使用十六进制简写（如 `#ccc` 而非 `#cccccc`）
- 使用相对单位（`em`, `rem`, `%`）而非绝对单位（`px`）
- 避免不必要的嵌套

### JavaScript
- 使用严格模式
- 私有方法使用 `#` 前缀
- 事件监听器使用箭头函数
- 避免全局变量污染

---

## 常见模式

### 代码块保护模式

用于避免在 HTML 解析时破坏代码块：

```php
$blocks = [];
$holder = "<pre>__HOLDER__</pre>";

// 保护
$content = preg_replace_callback('/<pre>.*?<\/pre>/ism', function ($match) use (&$blocks, $holder) {
    $blocks[] = $match[0];
    return $holder;
}, $content);

// 处理...

// 恢复
foreach ($blocks as $block) {
    $content = preg_replace('/' . preg_quote($holder, '/') . '/', $block, $content, 1);
}
```

### PJAX 兼容模式

确保前端逻辑在 PJAX 导航后正常工作：

```javascript
document.addEventListener('DOMContentLoaded', _ => {
    this.reload();
});
document.addEventListener('pjax:complete', _ => {
    this.reload();
});
```

### 暗色模式支持

```css
.component {
    color: #333;
}

@media (prefers-color-scheme: dark) {
    .component {
        color: #ccc;
    }
}
```
