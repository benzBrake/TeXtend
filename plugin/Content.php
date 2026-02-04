<?php

namespace TypechoPlugin\TeXtend;

use Typecho\Common;
use Utils\Helper;
use Widget\Base\Contents;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;


class Content
{
    public static function parser(string $content, Contents $widget, ?string $lastResult)
    {
        if ($lastResult) $text = $lastResult;

        // 隐藏代码块
        $blocks = [];
        $codeHolder = "<pre>__BLOCK__</pre>";
        $codeHolderLen = strlen($codeHolder);
        $content = preg_replace_callback('/(?:<pre>.*?<\/pre>|<code>.*?<\/code>)/ism', function ($match) use (&$blocks, $codeHolder) {
            $blocks[] = $match[0];
            return $codeHolder;
        }, $content);


        // Shortcode fallback
        $content = self::parseXPlayerShortcode($content);
        $content = self::parseXBilibiliShortcode($content);

        $content = self::parseGithubLink($content);

        if (strpos($content, 'grace-links-video-wrapper') === false) {
            $content = self::parseVideoLinks($content, $widget);
        }

        // 恢复代码块

        if (count($blocks)) {
            foreach ($blocks as $block) {
                $pos = strpos($content, $codeHolder);
                if ($pos !== false) {
                    $content = substr_replace($content, $block, $pos, $codeHolderLen);
                }
            }
        }

        // Grid 类型去除 <p> 和 <br> 包裹
        $content = preg_replace_callback('/<div class="fence fence-grid"[^>]*>.*?<div class="fence-content">(.*?)<\/div>/s', function ($matches) {
            $innerContent = preg_replace('/<\/?p>\n?|<br>\n?/', '', $matches[1]);
            return str_replace($matches[1], $innerContent, $matches[0]);
        }, $content);

        // Masonry 类型去除 <p> 和 <br> 包裹，并包裹为 masonry-item
        $content = preg_replace_callback('/<div class="fence fence-masonry"[^>]*>.*?<div class="fence-content">(.*?)<\/div>/s', function ($matches) {
            $innerContent = preg_replace('/<\/?p>\n?|<br>\n?/', "\n", $matches[1]);
            // 按双换行分割内容项，每个包裹为 masonry-item
            $items = preg_split('/\n\s*\n/', $innerContent);
            $items = array_map(function($item) {
                $item = trim($item);
                return $item ? "<div class=\"masonry-item\"><div class=\"masonry-item-inner\">{$item}</div></div>" : '';
            }, $items);
            $wrappedContent = implode("\n", array_filter($items));
            // 去除可能残留的空标签
            $wrappedContent = preg_replace('/<p>\s*<\/p>/', '', $wrappedContent);
            // 用 masonry-wrapper 包裹，隔离 fence-content 的 padding
            $wrappedContent = "<div class=\"masonry-wrapper\">{$wrappedContent}</div>";
            return str_replace($matches[1], $wrappedContent, $matches[0]);
        }, $content);

        // 附加统计到单篇文章末尾
        if ($widget instanceof \Widget\Archive) {
            $content = Stat::attachStat($content, $widget);
        }

        return $content;
    }


    /**
     * 解析 HTML 字符串中的 GitHub 和 Gitee 链接。
     * 查找匹配的链接，并用自定义的 <x-github> 标签替换掉整个 <a> 标签。
     *
     * @param string $content 包含 HTML 代码的字符串。
     * @return string 处理后的 HTML 字符串。
     */
    public static function parseGithubLink(string $content): string
    {
        // 这个正则表达式旨在匹配完整的 <a> 标签，并捕获其 href 属性的值。
        // 它会处理 href 中可能存在的单引号或双引号。
        // 它还会处理 <a> 标签上的其他属性。
        $linkRegex = '/<a\s+[^>]*href\s*=\s*(["\'])(https?:\/\/(?:github\.com|gitee\.com)\/[^"\']+)\1[^>]*>/i';

        // 使用 preg_replace_callback 对每个匹配到的 <a> 标签进行自定义处理
        return preg_replace_callback($linkRegex, function ($matches) {
            // $matches[0] 是完整匹配到的 <a> 标签，例如: <a href="https://github.com/user/repo">Link</a>
            // $matches[1] 是 href 值的引号，例如: "
            // $matches[2] 是捕获到的完整 URL，例如: https://github.com/user/repo

            $url = $matches[2];

            // 根据域名选择相应的内部解析正则
            if (strpos($url, 'github.com') !== false) {
                // GitHub 正则：匹配 https://github.com/owner/repo 或 git@github.com:owner/repo.git 等格式
                $internalRegex = '/(?:git@|https?:\/\/)github\.com\/([^\/]+)(?:\/|:)([^\/\s#?]+(?:\/[^\/\s#?]+)*)?/i';
            } elseif (strpos($url, 'gitee.com') !== false) {
                // Gitee 正则：匹配 https://gitee.com/owner/repo
                $internalRegex = '/https?:\/\/gitee\.com\/([^\/]+)\/([^\/\s#?]+(?:\/[^\/\s#?]+)*)?/i';
            } else {
                // 如果不是我们关心的域名，则返回原始链接，不做任何处理
                return $matches[0];
            }

            // 执行内部解析
            if (preg_match($internalRegex, $url, $urlParts)) {
                // $urlParts[1] 是用户/组织名 (owner)
                // $urlParts[2] 是仓库名 (repo)，可能包含路径
                if (!empty($urlParts[1])) {
                    // 创建新的 <x-github> 标签
                    // 使用 htmlspecialchars 来确保 URL 属性是安全的
                    $newTag = '<x-github url="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></x-github>';
                    return $newTag;
                }
            }

            // 如果内部正则不匹配（例如，链接格式错误），则返回原始链接
            return $matches[0];
        }, $content);
    }

    /**
     * 解析 x-player 短代码，还原为原始 YouTube URL。
     * [x-player src="URL" autoplay="off" /] -> URL
     * [x-player src="URL" autoplay="true" /] -> URL&autoplay=true
     *
     * @param string $content 包含短代码的字符串。
     * @return string 处理后的字符串。
     */
    public static function parseXPlayerShortcode(string $content): string
    {
        // 匹配 [x-player src="..." autoplay="..." /] 格式的短代码
        $pattern = '/\[x-player\s+src\s*=\s*(["\'])([^"\']+)\1(?:\s+autoplay\s*=\s*(["\']?)([^"\'\s\/]+)\3)?\s*\/?\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            // $matches[2] 是 src 的值（URL）
            $url = $matches[2];

            // $matches[4] 是 autoplay 的值（如果存在）
            $autoplay = $matches[4] ?? null;

            // 如果 autoplay 为 true/on/yes，则添加参数
            if (in_array(strtolower($autoplay ?? ''), ['true', 'on', 'yes', '1'])) {
                $separator = strpos($url, '?') !== false ? '&' : '?';
                $url = $url . $separator . 'autoplay=true';
            }

            return $url;
        }, $content);
    }

    /**
     * 解析 x-bilibili 短代码，转换为 Bilibili iframe 嵌入。
     * [x-bilibili id="BV1Nd4y1Q7yA" /]
     * [x-bilibili id="BV1Nd4y1Q7yA" autoplay="off" /]
     * [x-bilibili id="av12345" p="2" /]
     *
     * @param string $content 包含短代码的字符串。
     * @return string 处理后的字符串。
     */
    public static function parseXBilibiliShortcode(string $content): string
    {
        // 匹配 [x-bilibili id="..." p="..." autoplay="..." /] 格式的短代码
        $pattern = '/\[x-bilibili\s+id\s*=\s*(["\'])([^"\']+)\1(?:\s+p\s*=\s*(["\']?)(\d+)\3)?(?:\s+autoplay\s*=\s*(["\']?)([^"\'\s\/]+)\5)?\s*\/?\]/i';

        return preg_replace_callback($pattern, function ($matches) {
            // $matches[2] 是 id 的值（BV号或AV号）
            $id = $matches[2];
            // $matches[4] 是 p 的值（分集，默认1）
            $page = isset($matches[4]) ? intval($matches[4]) : 1;

            // 判断是 BV 号还是 AV 号
            if (preg_match('/^BV[a-zA-Z0-9]+$/i', $id)) {
                $idType = 'bvid';
            } elseif (preg_match('/^av(\d+)$/i', $id, $m)) {
                $idType = 'aid';
                $id = $m[1];
            } else {
                // 无效格式，返回原样
                return $matches[0];
            }

            // 构建 Bilibili URL 用于 generateBilibiliIframe
            $url = 'https://www.bilibili.com/video/' . ($idType === 'bvid' ? $id : 'av' . $id);
            if ($page > 1) {
                $url .= '?p=' . $page;
            }

            return self::generateBilibiliIframe($url);
        }, $content);
    }

    public static function parseVideoLinks(string $content, Contents $widget)
    {
        $extPattern = implode('|', ['mp4', 'webm', 'ogg', 'ogv', 'mov', 'm3u8']);

        // 第一步：把视频文件链接从 <a> 标签中剥离出来
        $content = preg_replace(
            '#<a\s+[^>]*href=["\']([^"\']+\.(' . $extPattern . '))["\'][^>]*>.*?</a>#i',
            '$1',
            $content
        );

        // 第二步：把 YouTube/Vimeo/Bilibili 链接从 <a> 标签中剥离出来
        $content = preg_replace_callback(
            '#<a\s+[^>]*href=["\']((https?:)?//(?:www\.)?(?:youtube\.com/watch\?v=[a-zA-Z0-9_-]+|youtu\.be/[a-zA-Z0-9_-]+|vimeo\.com/\d+|bilibili\.com/video/(?:BV[a-zA-Z0-9]+|av\d+)(?:/[^\s\'"]*)?(\?[^\s\'"]*)?))["\'][^>]*>.*?</a>#i',
            fn($m) => strpos($m[1], '//') === 0 ? 'https:' . $m[1] : $m[1],
            $content
        );

        // 第五步：处理所有裸的视频链接（文件 + 平台）
        $content = preg_replace_callback(
            '#https?://[^\s<>\'"]+\.(?:' . $extPattern . ')|https?://(?:www\.)?(?:youtube\.com/watch\?v=[a-zA-Z0-9_-]+|youtu\.be/[a-zA-Z0-9_-]+|vimeo\.com/\d+|bilibili\.com/video/(?:BV[a-zA-Z0-9]+|av\d+)(?:/[^\s<>\'"]*)?(\?[^\s<>\'"]*)?)#i',
            fn($m) => self::generateVideoTag($m[0]),
            $content
        );

        return $content;
    }

    /**
     * 生成 iframe 播放器标签
     */
    private static function generateVideoTag($url)
    {
        // 检测是否为 Bilibili 链接
        if (strpos($url, 'bilibili.com') !== false) {
            return self::generateBilibiliIframe($url);
        }

        // 其他平台走 Player.php
        $playerUrl = Common::url('/TeXtend/Player.php?url=' . urlencode($url), Helper::options()->pluginUrl);
        return '<div class="x-video-wrapper">'
            . '<iframe src="' . $playerUrl . '" allowfullscreen frameborder="0"></iframe>'
            . '</div>';
    }

    /**
     * 生成 Bilibili 直接 iframe
     */
    private static function generateBilibiliIframe($url)
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'];
        $bvid = null;
        $aid = null;
        $page = 1;

        // 提取 BV 号
        if (preg_match('#/video/(BV[a-zA-Z0-9]+)#i', $path, $matches)) {
            $bvid = $matches[1];
        } // 提取 AV 号
        elseif (preg_match('#/video/av(\d+)#i', $path, $matches)) {
            $aid = $matches[1];
        }

        // 提取分集参数
        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
            if (isset($queryParams['p'])) {
                $page = intval($queryParams['p']);
            }
        }

        $id = !empty($bvid) ? $bvid : $aid;
        $idType = !empty($bvid) ? 'bvid' : 'aid';
        $src = "https://www.bilibili.com/blackboard/html5mobileplayer.html?" . $idType . "=" . $id . "&page=" . $page . "&fjw=false";

        return '<div class="bilibili-player-wrapper">'
            . '<iframe src="' . $src . '" '
            . 'allowfullscreen '
            . 'allowtransparency '
            . 'scrolling="no" '
            . 'border="0" '
            . 'frameborder="0" '
            . 'framespacing="0"></iframe>'
            . '</div>';
    }
}
