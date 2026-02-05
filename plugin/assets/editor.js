/**
 * TeXtend 编辑器增强脚本
 * 为 Typecho 默认编辑器添加 fence block 快捷插入功能
 */

(function ($) {
    // 仅在后台编辑页面运行
    if ($('#text').length === 0) return;

    // Fence block 模板配置
    const fenceTemplates = {
        info: {
            label: 'Info',
            name: '信息提示',
            template: '::: info\n\n:::',
            description: '信息提示块'
        },
        success: {
            label: 'Success',
            name: '成功提示',
            template: '::: success\n\n:::',
            description: '成功提示块'
        },
        warning: {
            label: 'Warning',
            name: '警告提示',
            template: '::: warning\n\n:::',
            description: '警告提示块'
        },
        danger: {
            label: 'Danger',
            name: '危险提示',
            template: '::: danger\n\n:::',
            description: '危险提示块'
        },
        tip: {
            label: 'Tip',
            name: '小贴士',
            template: '::: tip\n\n:::',
            description: '小贴士块'
        },
        details: {
            label: 'Details',
            name: '折叠详情',
            template: '::: details 标题\n\n:::',
            description: '可折叠详情块'
        },
        'details-open': {
            label: 'Details+',
            name: '展开详情',
            template: '::: details:open 标题\n\n:::',
            description: '默认展开的详情块'
        },
        raw: {
            label: 'Raw',
            name: '原始内容',
            template: '::: raw\n\n:::',
            description: '原始内容（不解析）'
        },
        grid: {
            label: 'Grid',
            name: '网格布局',
            template: '::: grid {columns: 2}\n\n:::',
            description: '网格布局'
        },
        masonry: {
            label: 'Masonry',
            name: '瀑布流',
            template: '::: masonry {columns: 3, gap: 16px}\n- 内容项1\n- 内容项2\n- 内容项3\n:::',
            description: '瀑布流布局'
        },
        tabs: {
            label: 'Tabs',
            name: '多标签',
            template: '::: tabs\n=== 标签1\n标签1内容\n\n===+ 标签2（默认显示）\n标签2内容\n\n=== 标签3\n标签3内容\n:::',
            description: '多标签页'
        }
    };

    /**
     * 在光标位置插入文本
     */
    function insertText(text) {
        const textarea = $('#text')[0];
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const value = textarea.value;

        textarea.value = value.substring(0, start) + text + value.substring(end);

        // 设置光标位置到插入内容的中间
        const newPos = start + text.indexOf('\n\n') + 2;
        textarea.setSelectionRange(newPos, newPos);
        textarea.focus();
    }

    /**
     * 注入样式
     */
    function injectStyles() {
        $('<style>').text(`
            .wmd-button.tex-fence-btn span {
                display: flex;
                width: auto !important;
                justify-content: center;
                align-items: center;
                border: 1px solid #ccc;
                padding: 0 4px;
            }
        `).appendTo('head');
    }

    /**
     * 创建工具栏按钮
     */
    function createButtons() {
        const buttons = [];
        for (const [key, config] of Object.entries(fenceTemplates)) {
            const li = $('<li>')
                .attr({
                    id: `wmd-${key}-button-aaeditor`,
                    name: config.name,
                    title: config.description
                })
                .addClass('wmd-button tex-fence-btn')
                .append($('<span>').text(config.label))
                .on('click', function () {
                    insertText(config.template);
                });
            buttons.push(li);
        }
        return buttons;
    }

    // 初始化
    function init() {
        injectStyles();
        const tb = $('[id^=wmd-button-row]');
        if (tb.length) {
            tb.css('height', 'auto');
            const buttons = createButtons();
            buttons.forEach(function (btn) {
                tb.append(btn);
            });
        }
    }

    $(document).ready(function () {
        init();
    });
})(jQuery);
