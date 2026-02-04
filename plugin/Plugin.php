<?php

namespace TypechoPlugin\TeXtend;

use Typecho\Cookie;
use Typecho\Db;
use Typecho\Plugin as Plg;
use Typecho\Plugin\PluginInterface;
use Typecho\Widget\Helper\Form;
use Utils\Helper;
use Utils\HyperDown as OriginalHyperDown;
use Widget\User;

if (!defined('__TYPECHO_ROOT_DIR__')) exit;


/**
 * Typecho 全能扩展插件
 *
 * @package TeXtend
 * @author Ryan
 * @version 1.0.0
 * @since 1.2.0
 * @link https://doufu.ru
 *
 */
class Plugin implements PluginInterface
{
    public static function activate()
    {
        $plugins = Plg::export();
        if (array_key_exists('TeStat', $plugins)) {
            throw new Plg\Exception(_t('本插件不能与 TeStat 插件同时使用'));
        }
        Stat::tableUpdate();
        Plg::factory('\Widget\Archive')->singleHandle = [Plugin::class, 'singleHandle'];
        Plg::factory('\Widget\Archive')->select = [Plugin::class, 'select'];
        Plg::factory('\Widget\Archive')->callViewsNum = [Stat::class, 'callViewsNum'];
        Plg::factory('\Widget\Archive')->callLikesNum = [Stat::class, 'callLikesNum'];
        Plg::factory('\Widget\Base\Contents')->markdown = [Plugin::class, 'markdown'];
        Plg::factory('\Widget\Base\Contents')->contentEx = [Content::class, 'parser'];
        Plg::factory('\Widget\Base\Contents')->excerptEx = [Content::class, 'parser'];
        Plg::factory('\Widget\Archive')->footer = [Plugin::class, 'addFooter'];
        // 添加路由
        Helper::addAction('likes', '\TypechoPlugin\TeXtend\Action');
        // 后台编辑器增强
        Plg::factory('admin/footer.php')->end = [Plugin::class, 'adminFooter'];
    }

    public static function deactivate()
    {
        $options = Helper::options()->plugin('TeXtend');
        if ($options->removeStatData == 1) {
            Stat::removeStatColumns();
        }
        // 删除路由
        Helper::removeAction('likes');
    }

    public static function config(Form $form)
    {
        $form->addInput(new Form\Element\Radio(
                'usePluginHyperDown',
                [
                        '0' => _t('使用 Typecho 内置的 HyperDown'),
                        '1' => _t('使用插件自带的 HyperDown（支持 fence block）')
                ],
                1,
                _t('Markdown 解析器'),
                _t('选择 Markdown 解析器。')
        ));
        $form->addInput(new Form\Element\Radio(
                'removeStatData',
                ['0' => _t('不删除'), '1' => _t('删除')],
                0,
                _t('删除统计数据'),
                _t('禁用插件时删除插件激活后产生的统计的数据。')));
        $form->addInput(new Form\Element\Radio(
                'autoAttachStat',
                ['0' => _t('不启用'), '1' => _t('启用')],
                0,
                _t('文章末尾附加统计'),
                _t('自动在文章内容末尾附加浏览数和点赞功能。')
        ));
    }

    public static function personalConfig(Form $form)
    {

    }

    /**
     * Markdown 解析器
     * 根据配置选择使用插件 HyperDown 或原始 HyperDown
     */
    public static function markdown(string $text): string
    {
        $options = Helper::options();
        $plugin = $options->plugin('TeXtend');

        // 根据配置选择解析器
        if ($plugin && $plugin->usePluginHyperDown && $plugin->usePluginHyperDown == 1) {
            // 使用插件的 HyperDown（支持 fence block）
            $parser = new HyperDown();
        } else {
            // 使用原始的 HyperDown
            $parser = new OriginalHyperDown();
        }

//        $parser->enableHtml(true);
        return $parser->makeHtml($text);
    }

    /**
     * 输出带 hash 的静态资源 URL
     *
     * @param string $path 资源相对路径（如 assets/app.css）
     * @return string 带 hash 的完整 URL
     */
    public static function assetUrl(string $path): string
    {
        $pluginPath = __TYPECHO_ROOT_DIR__ . '/usr/plugins/TeXtend/' . $path;
        $hash = file_exists($pluginPath) ? substr(md5_file($pluginPath), 0, 8) : '';
        $baseUrl = Helper::options()->pluginUrl . '/TeXtend/' . $path;
        return $hash ? $baseUrl . '?v=' . $hash : $baseUrl;
    }

    public static function addFooter()
    {
        ?>
        <script src="<?php echo Plugin::assetUrl('assets/masonry.pkgd.min.js'); ?>"></script>
        <script src="<?php echo Plugin::assetUrl('assets/parser.js'); ?>"></script>
        <link rel="stylesheet" href="<?php echo Plugin::assetUrl('assets/app.css'); ?>">
        <?php
    }

    /**
     * 后台编辑器增强
     * 加载 fence block 快捷插入工具栏
     */
    public static function adminFooter()
    {
        // 仅在文章/页面编辑页面加载
        $request = \Typecho\Request::getInstance();
        $uri = $request->getRequestUri();
        if ($request->is('cid') && strpos($uri, '/write-') !== false) {
            ?>
            <script src="<?php echo Plugin::assetUrl('assets/editor.js'); ?>"></script>
            <?php
        }
    }

    /**
     * 通用列迁移函数
     *
     * @param Db $db 数据库实例
     * @param string $tableName 表名（含前缀）
     * @param string $adapter 适配器名称
     * @param string $oldColumn 旧列名（存在则重命名）
     * @param string $newColumn 新列名
     * @param string $type 列类型（INT, VARCHAR, TEXT 等）
     * @param mixed $default 默认值
     */
    public static function migrateColumn($db, $tableName, $adapter, $oldColumn, $newColumn, $type, $default)
    {
        $dbType = self::getDbType($adapter);
        $quote = self::getIdentifierQuote($adapter);

        $hasOld = self::columnExists($db, $tableName, $oldColumn, $dbType);
        $hasNew = self::columnExists($db, $tableName, $newColumn, $dbType);

        if ($hasOld) {
            // 重命名旧列
            // MySQL 8.0+ 支持 RENAME COLUMN，但为了兼容性使用 CHANGE COLUMN
            $defaultStr = is_string($default) ? "'{$default}'" : $default;
            $db->query(sprintf(
                    'ALTER TABLE %s%s%s CHANGE %s%s%s %s%s%s %s DEFAULT %s',
                    $quote, $tableName, $quote,
                    $quote, $oldColumn, $quote,
                    $quote, $newColumn, $quote,
                    $type, $defaultStr
            ));
        } elseif (!$hasNew) {
            // 添加新列
            $defaultStr = is_string($default) ? "'{$default}'" : $default;
            $db->query(sprintf(
                    'ALTER TABLE %s%s%s ADD COLUMN %s%s%s %s DEFAULT %s',
                    $quote, $tableName, $quote,
                    $quote, $newColumn, $quote,
                    $type, $defaultStr
            ));
        }
    }

    /**
     * 获取数据库类型标识
     */
    public static function getDbType($adapter)
    {
        if ($adapter === 'Pdo_Mysql' || $adapter === 'Mysql') {
            return 'mysql';
        } elseif ($adapter === 'Pdo_Pgsql' || $adapter === 'Pgsql') {
            return 'pgsql';
        } elseif ($adapter === 'Pdo_SQLite' || $adapter === 'SQLite') {
            return 'sqlite';
        }
        return 'mysql';
    }

    /**
     * 获取标识符引号
     */
    public static function getIdentifierQuote($adapter)
    {
        if ($adapter === 'Pdo_Pgsql' || $adapter === 'Pgsql') {
            return '"';
        }
        return '`';
    }

    public static function columnExists($db, $table, $column, $type)
    {
        if ($type === 'sqlite') {
            $result = $db->fetchAll(sprintf("PRAGMA table_info('%s')", $table));
            foreach ($result as $row) {
                if (isset($row['name']) && $row['name'] === $column) {
                    return true;
                }
            }
            return false;
        }
        // MySQL/MariaDB, PostgreSQL
        $schemaFunc = $type === 'mysql' ? 'DATABASE()' : 'CURRENT_SCHEMA()';
        $sql = sprintf(
                "SELECT COUNT(*) as count FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = '%s'
             AND COLUMN_NAME = '%s'",
                $schemaFunc,
                $table,
                $column
        );
        $result = $db->fetchAll($sql);
        return $result && $result[0]['count'] > 0;
    }

    /**
     * 通用列删除函数
     *
     * @param Db $db 数据库实例
     * @param string $tableName 表名（含前缀）
     * @param string $adapter 适配器名称
     * @param string $column 要删除的列名
     */
    public static function removeColumn($db, $tableName, $adapter, $column)
    {
        $dbType = self::getDbType($adapter);
        $quote = self::getIdentifierQuote($adapter);

        // 检查列是否存在
        if (!self::columnExists($db, $tableName, $column, $dbType)) {
            return;
        }

        // SQLite 不支持直接 DROP COLUMN，需要重建表
        if ($dbType === 'sqlite') {
            self::removeColumnSQLite($db, $tableName, $column);
            return;
        }

        // MySQL/MariaDB, PostgreSQL 支持 DROP COLUMN
        $db->query(sprintf(
                'ALTER TABLE %s%s%s DROP COLUMN %s%s%s',
                $quote, $tableName, $quote,
                $quote, $column, $quote
        ));
    }

    /**
     * SQLite 删除列（通过重建表实现）
     */
    public static function removeColumnSQLite($db, $tableName, $column)
    {
        // 获取表结构
        $result = $db->fetchAll(sprintf("PRAGMA table_info('%s')", $tableName));
        $columns = [];
        foreach ($result as $row) {
            if (isset($row['name']) && $row['name'] !== $column) {
                $columns[] = $row['name'];
            }
        }

        if (empty($columns)) {
            return;
        }

        $columnList = implode(', ', $columns);
        $tempTable = $tableName . '_temp';

        // 创建临时表
        $db->query(sprintf("CREATE TABLE %s (%s)", $tempTable, $columnList));

        // 复制数据
        $db->query(sprintf("INSERT INTO %s SELECT %s FROM %s", $tempTable, $columnList, $tableName));

        // 删除原表
        $db->query(sprintf("DROP TABLE %s", $tableName));

        // 重命名临时表
        $db->query(sprintf("ALTER TABLE %s RENAME TO %s", $tempTable, $tableName));
    }

    public static function singleHandle($archive)
    {
        if ($archive->is('single')) {
            $cid = $archive->cid;
            $views = Cookie::get('__post_views');
            if (empty($views)) {
                $views = [];
            } else {
                $views = explode(',', $views);
            }
            if (!in_array($cid, $views)) {
                $db = Db::get();
                $db->query($db->update('table.contents')->rows(array('viewsNum' => (int)$archive->viewsNum + 1))->where('cid = ?', $cid));
                array_push($views, $cid);
                $views = implode(',', $views);
                Cookie::set('__post_views', $views);
            }
        }
    }

    public static function select($archive)
    {
        $user = User::alloc();
        if ('post' == $archive->parameter->type || 'page' == $archive->parameter->type) {
            if ($user->hasLogin()) {
                $select = $archive->select('*')->where(
                        'table.contents.status = ? OR table.contents.status = ? 
                                OR (table.contents.status = ? AND table.contents.authorId = ?)',
                        'publish',
                        'hidden',
                        'private',
                        $user->uid
                );
            } else {
                $select = $archive->select('*')->where(
                        'table.contents.status = ? OR table.contents.status = ?',
                        'publish',
                        'hidden'
                );
            }
        } else {
            if ($user->hasLogin()) {
                $select = $archive->select('*')->where(
                        'table.contents.status = ? OR (table.contents.status = ? AND table.contents.authorId = ?)',
                        'publish',
                        'private',
                        $user->uid
                );
            } else {
                $select = $archive->select('*')->where('table.contents.status = ?', 'publish');
            }
        }
        $select->where('table.contents.created < ?', Helper::options()->time);
        return $select;
    }

    /**
     * 输出数值的通用格式化方法
     *
     * @param mixed $self Archive 对象
     * @param string $field 字段名
     * @param mixed ...$args 格式化参数
     * @return void
     */
    public static function formatNum($self, string $field, ...$args)
    {
        if (empty($args)) {
            $args[] = '%d';
        }

        $num = intval($self->$field);
        echo sprintf($args[$num] ?? array_pop($args), $num);
    }
}
