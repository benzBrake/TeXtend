# TeXtend

> Typecho 全能扩展插件

[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![Typecho](https://img.shields.io/badge/Typecho-1.2+-orange.svg)](https://typecho.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-purple.svg)](https://php.net)

TeXtend 是一款功能强大的 Typecho 博客插件，提供 Markdown 语法扩展、内容增强、文章统计和媒体嵌入等全方位功能。

## 功能特性

### Markdown 扩展语法

- **Fence Block** - 自定义内容块系统，支持多种类型：
  - `:::tip` - 提示块
  - `:::warning` - 警告块
  - `:::danger` - 危险块
  - `:::info` - 信息块
  - `:::success` - 成功块
  - `:::grid` - 网格布局
  - `:::masonry` - 瀑布流布局
  - `:::collapse` - 折叠面板

### 内容增强

- **GitHub/Gitee 仓库卡片** - 自动将仓库链接转换为精美的信息卡片
- **智能视频嵌入** - 支持直接粘贴视频链接自动转换为播放器
  - YouTube
  - Vimeo
  - Bilibili（支持 BV/AV 号）
  - 本地视频文件（mp4, webm, ogg 等）

### 短代码支持（Deprecated）
这是以前 AAEditor 支持的短代码，这里 fallback 支持
```markdown
[x-player src="视频URL" autoplay="off" /]
[x-bilibili id="BV1xx" p="1" /]
```

### 文章统计

- 浏览数统计（Cookie 防刷）
- 点赞功能（AJAX 交互）
- 可选在文章末尾自动附加统计模块

### 后台增强

- 编辑器工具栏快捷插入 Fence Block

## 安装

### 方式一：手动安装

1. 下载最新版本 [Releases](https://github.com/benzBrake/TeXtend/releases)
2. 将 `TeXtend` 文件夹上传到 `/usr/plugins/` 目录
3. 在后台 **控制台 → 插件** 中启用 TeXtend

### 方式二：Git 克隆

```bash
cd /usr/plugins
git clone https://github.com/benzBrake/TeXtend.git
```

## 配置

插件启用后，可在 **设置 → TeXtend** 中配置：

| 选项 | 说明 |
|------|------|
| Markdown 解析器 | 选择使用内置 HyperDown 或插件扩展版本 |
| 删除统计数据 | 禁用插件时是否删除统计相关数据 |
| 文章末尾附加统计 | 自动在文章末尾添加浏览和点赞功能 |

## 使用指南

### Fence Block 语法

```markdown
:::tip 标题（可选）
这是一条提示信息
:::

:::warning
这是一条警告信息
:::

:::grid
[图片1](url)
[图片2](url)
:::

:::masonry
![img1](url)

![img2](url)
:::
```

### GitHub/Gitee 卡片

直接在文章中粘贴仓库链接，插件会自动转换：

```markdown
访问 [https://github.com/user/repo](https://github.com/user/repo) 查看更多
```

### 视频嵌入

#### 方式一：直接粘贴链接

```markdown
https://www.youtube.com/watch?v=xxxxx
https://www.bilibili.com/video/BV1xx
```

#### 方式二：使用短代码

```markdown
[x-bilibili id="BV1xx" p="2" autoplay="off" /]
```

### 统计调用

在模板中调用浏览数和点赞数：

```php
<?php $this->callViewsNum('%d 次浏览'); ?>
<?php $this->callLikesNum('%d 人点赞'); ?>
```

## 项目结构

```
TeXtend/
├── assets/
│   ├── app.css       # 全局样式
│   ├── parser.js     # 前端解析器
│   ├── editor.js     # 后台编辑器增强
│   ├── masonry.pkgd.min.js  # Masonry 布局库
│   ├── plyr.css      # 视频播放器样式
│   └── plyr.js       # 视频播放器脚本
├── HyperDown.php     # Markdown 语法解析器
├── Content.php       # HTML 内容解析器
├── Stat.php          # 统计功能
├── Action.php        # AJAX 操作处理
├── Player.php        # 视频播放器代理
└── Plugin.php        # 插件入口
```

## 开发

### 新增 Fence Block 类型

1. 在 `HyperDown.php` 中注册解析器
2. 在 `assets/app.css` 中添加样式
3. 在 `assets/parser.js` 中添加前端处理（如需要）

详见 [CLAUDE.md](CLAUDE.md) 开发规范。

## 常见问题

### 与其他插件的兼容性

- 不能与 **TeStat** 插件同时使用（功能重叠）
- 建议禁用其他 Markdown 解析相关插件

### 静态资源加载异常

插件会自动为静态资源添加 hash 版本号，如遇缓存问题请清除浏览器缓存。

### PJAX 兼容

插件已内置 PJAX 兼容处理，前端组件会在页面切换后自动重新加载。

## 更新日志

### v1.0.0
- 初始版本发布
- 支持 Fence Block 语法
- 集成 GitHub/Gitee 卡片
- 支持多平台视频嵌入
- 浏览数和点赞统计

## 贡献

欢迎提交 Issue 和 Pull Request！

## 许可

MIT License

## 作者

Ryan - [doufu.ru](https://doufu.ru)

## 鸣谢

- [Typecho](https://typecho.org) - 优秀的博客系统
- [HyperDown](https://github.com/eryue/hyperdown) - Markdown 解析器
- [Plyr](https://plyr.io) - HTML5 视频播放器
