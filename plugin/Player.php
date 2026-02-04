<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8" />
    <meta name="renderer" content="webkit" />
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0, shrink-to-fit=no, viewport-fit=cover" />
    <title>H.265 Video Player</title>
    <link href="./assets/plyr.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            -webkit-tap-highlight-color: transparent;
            outline: none;
            text-decoration: none;
        }

        html,
        body,
        #player {
            width: 100%;
            height: 100%;
            overflow: hidden;
        }
    </style>
</head>

<body>
    <?php
    // 视频格式与 MIME 类型映射
    $videoMimeTypes = array(
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'ogg'  => 'video/ogg',
        'ogv'  => 'video/ogg',
        'mov'  => 'video/quicktime',
        'm3u8' => 'application/x-mpegURL'
    );

    function getMimeTypeFromUrl($url)
    {
        $headers = @get_headers($url, 1);
        if ($headers && strpos($headers[0], '200')) {
            return isset($headers['Content-Type']) ? $headers['Content-Type'] : null;
        }
        return null;
    }

    function getMimeTypeFromExtension($url)
    {
        global $videoMimeTypes;
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
        return isset($videoMimeTypes[$ext]) ? $videoMimeTypes[$ext] : 'video/mp4';
    }

    function isYoutube($url)
    {
        return strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false;
    }

    function isVimeo($url)
    {
        return strpos($url, 'vimeo.com') !== false;
    }

    function convertYoutubeUrlToEmbedUrl($url)
    {
        $from = (isSecure() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'];
        $parsedUrl = parse_url($url);

        if ($parsedUrl['host'] === 'youtu.be') {
            $videoId = ltrim($parsedUrl['path'], '/');
        } elseif (strpos($parsedUrl['host'], 'youtube.com') !== false) {
            parse_str($parsedUrl['query'], $queryParams);
            $videoId = isset($queryParams['v']) ? $queryParams['v'] : null;
        } else {
            return null;
        }

        return "https://www.youtube.com/embed/" . $videoId . "?" . http_build_query(array(
            'origin' => $from,
            'iv_load_policy' => 3,
            'modestbranding' => 1,
            'playsinline' => 1,
            'showinfo' => 0,
            'rel' => 0,
            'enablejsapi' => 1
        ));
    }

    function convertVimeoUrlToEmbedUrl($url)
    {
        $parsedUrl = parse_url($url);

        // 支持 vimeo.com/xxxxx 和 vimeo.com/channels/xxxxx/xxxxx 等格式
        $path = rtrim($parsedUrl['path'], '/');
        $segments = explode('/', $path);

        // 获取最后一个数字部分作为视频 ID
        $videoId = null;
        foreach (array_reverse($segments) as $segment) {
            if (is_numeric($segment)) {
                $videoId = $segment;
                break;
            }
        }

        if (!$videoId) {
            return null;
        }

        $params = array(
            'autoplay' => !empty($_GET['autoplay']) ? '1' : '0',
            'byline' => '0',
            'portrait' => '0',
            'title' => '0'
        );

        return "https://player.vimeo.com/video/" . $videoId . "?" . http_build_query($params);
    }

    function convertBilibiliUrlToEmbedUrl($url)
    {
        $parsedUrl = parse_url($url);

        // 支持 b23.tv 短链接跳转
        if (strpos($parsedUrl['host'], 'b23.tv') !== false) {
            // 短链接需要通过服务端获取真实 URL,这里简单处理直接返回原链接
            // 实际使用时可能需要通过 cURL 获取重定向后的地址
            return null;
        }

        // 支持多种 Bilibili URL 格式
        // - https://www.bilibili.com/video/BVxxxxxxxxxx
        // - https://www.bilibili.com/video/avxxxxxxxxxx
        // - https://b23.tv/xxxxxxx (短链接,需要重定向)

        $path = $parsedUrl['path'];
        $bvid = null;
        $aid = null;
        $page = 1;

        // 提取 BV 号
        if (preg_match('#/video/(BV[a-zA-Z0-9]+)#i', $path, $matches)) {
            $bvid = $matches[1];
        }
        // 提取 AV 号
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

        // 使用 Bilibili HTML5 移动端播放器
        $id = !empty($bvid) ? $bvid : $aid;
        $idType = !empty($bvid) ? 'bvid' : 'aid';
        $autoplay = !empty($_GET['autoplay']) ? 'true' : 'false';

        return "https://www.bilibili.com/blackboard/html5mobileplayer.html?" . $idType . "=" . $id . "&page=" . $page . "&fjw=" . $autoplay;
    }

    function isSecure()
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443;
    }
    ?>

    <?php if (isset($_GET['url'])): ?>
        <?php if (isYoutube($_GET['url'])): ?>
            <div class="plyr__video-embed" id="player">
                <iframe
                    src="<?php echo htmlspecialchars(convertYoutubeUrlToEmbedUrl($_GET['url'])); ?>"
                    allowfullscreen
                    allowtransparency
                    <?php if (!empty($_GET['autoplay'])): ?> allow="autoplay" <?php endif; ?>></iframe>
            </div>
        <?php elseif (isVimeo($_GET['url'])): ?>
            <div class="plyr__video-embed" id="player">
                <iframe
                    src="<?php echo htmlspecialchars(convertVimeoUrlToEmbedUrl($_GET['url'])); ?>"
                    allowfullscreen
                    allowtransparency
                    <?php if (!empty($_GET['autoplay'])): ?> allow="autoplay" <?php endif; ?>></iframe>
            </div>
        <?php else: ?>
            <video id="player" playsinline controls <?php if (isset($_GET['poster'])): ?> data-poster="<?php echo htmlspecialchars($_GET['poster']); ?>" <?php endif; ?>>
                <source src="<?php echo htmlspecialchars($_GET['url']); ?>" type="<?php
                $mimeType = isset($_GET['mime']) ? $_GET['mime'] : null;
                if (empty($mimeType)) {
                    $mimeType = getMimeTypeFromUrl($_GET['url']);
                }
                if (empty($mimeType)) {
                    $mimeType = getMimeTypeFromExtension($_GET['url']);
                }
                echo htmlspecialchars($mimeType);
                ?>" />
                <?php if (isset($_GET['caption'])): ?>
                    <track kind="captions" label="字幕" src="<?php echo htmlspecialchars($_GET['caption']); ?>" srclang="<?php echo htmlspecialchars(isset($_GET['caption-lang']) ? $_GET['caption-lang'] : 'en'); ?>" default />
                <?php endif; ?>
            </video>
            <script src="./assets/plyr.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    new Plyr('#player', {
                        controls: ['play', 'progress', 'current-time', 'mute', 'volume', 'fullscreen'],
                        autoplay: <?php echo json_encode(!empty($_GET["autoplay"])); ?>,
                        keyboard: {
                            focused: true,
                            global: false
                        },
                        tooltips: {
                            controls: true
                        },
                        hideControls: false
                    });
                });
            </script>
        <?php endif; ?>
    <?php else: ?>
        <h1>请提供视频URL</h1>
    <?php endif; ?>
</body>

</html>