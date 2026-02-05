<?php

namespace TypechoPlugin\TeXtend;

use Typecho\Cookie;
use Typecho\Db;
use Utils\Helper;
use Widget\Archive;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * 统计功能类
 *
 * 负责文章浏览数和点赞数的输出与附加
 */
class Stat
{
    /**
     * 更新数据库表结构，添加统计字段
     * @throws \Typecho\Db\Exception
     */
    public static function tableUpdate()
    {
        $db = Db::get();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'contents';
        $adapter = $db->getAdapterName();

        // 迁移 views -> viewsNum
        Plugin::migrateColumn($db, $tableName, $adapter, 'views', 'viewsNum', 'INT', 0);
        // 迁移 agree -> likesNum
        Plugin::migrateColumn($db, $tableName, $adapter, 'agree', 'likesNum', 'INT', 0);
    }

    /**
     * 删除统计字段
     * @throws \Typecho\Db\Exception
     */
    public static function removeStatColumns()
    {
        $db = Db::get();
        $prefix = $db->getPrefix();
        $tableName = $prefix . 'contents';
        $adapter = $db->getAdapterName();

        // 删除 viewsNum 字段
        Plugin::removeColumn($db, $tableName, $adapter, 'viewsNum');
        // 删除 likesNum 字段
        Plugin::removeColumn($db, $tableName, $adapter, 'likesNum');
    }

    /**
     * 过滤并补充文章统计字段
     *
     * 当数据中缺少浏览数字段时，从数据库查询并合并统计信息
     *
     * @param array $row 文章数据行
     * @return array 包含完整统计信息的文章数据
     */
    public static function filter($row)
    {
        if (!array_key_exists('viewsNum', $row) && $row['cid']) {
            $db = Db::get();
            $result = $db->fetchRow($db->select('viewsNum, likesNum')->from('table.contents')->where('cid = ?', $row['cid'])->limit(1));
            $row = array_merge($row, $result);
        }
        return $row;
    }


    /**
     * 输出文章浏览数
     *
     * @param mixed $self Archive 对象
     * @param mixed ...$args 格式化参数，如 '%d 次'
     * @return void
     */
    public static function callViewsNum($self, ...$args)
    {
        Plugin::formatNum($self, 'viewsNum', ...$args);
    }

    /**
     * 输出文章点赞数
     *
     * @param mixed $self Archive 对象
     * @param mixed ...$args 格式化参数，如 '%d 人'
     * @return void
     */
    public static function callLikesNum($self, ...$args)
    {
        Plugin::formatNum($self, 'likesNum', ...$args);
    }

    /**
     * 附加统计 HTML 到内容末尾
     *
     * @param string $content 文章内容
     * @param \Widget\Archive $archive 归档对象
     * @return string
     * @throws \Typecho\Plugin\Exception
     */
    public static function attachStat(string $content, Archive $archive): string
    {
        $options = Helper::options()->plugin('TeXtend');

        // 仅在单篇文章页面且启用选项时附加
        if ($archive->is('single') && isset($options->autoAttachStat) && $options->autoAttachStat == 1) {
            $content .= self::generateStatHtml($archive);
        }

        return $content;
    }

    /**
     * 生成统计 HTML
     *
     * @param \Widget\Archive $archive 归档对象
     * @return string
     */
    private static function generateStatHtml(Archive $archive): string
    {
        $viewsNum = intval($archive->viewsNum);
        $likesNum = intval($archive->likesNum);
        $cid = $archive->cid;

        // 检查用户是否已点赞
        $likedClass = '';
        $likedIconFill = 'fill="none"';
        $likes = Cookie::get('__post_likes');
        if (!empty($likes)) {
            $likesArray = explode(',', $likes);
            if (in_array($cid, $likesArray)) {
                $likedClass = ' tex-liked';
                $likedIconFill = 'fill="currentColor"';
            }
        }

        $viewsIcon = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
        $likeIcon = '<svg class="tex-like-icon" width="18" height="18" viewBox="0 0 24 24" ' . $likedIconFill . ' stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

        return <<<HTML
<div class="tex-post-stats">
    <div class="tex-stat-item tex-stat-views">
        <span class="tex-stat-icon">{$viewsIcon}</span>
        <span class="tex-stat-label">浏览</span>
        <span class="tex-stat-value">{$viewsNum}</span>
    </div>
    <div class="tex-stat-item tex-stat-like{$likedClass}" data-like-btn>
        <span class="tex-stat-icon">{$likeIcon}</span>
        <span class="tex-stat-label">点赞</span>
        <span class="tex-stat-value" data-cid="{$cid}">{$likesNum}</span>
    </div>
</div>
HTML;
    }
}
