window.TeXtend = {
    init: function () {
        this.registerCustomElements();
        document.addEventListener('DOMContentLoaded', _ => {
            this.reload();
        });
        document.addEventListener('pjax:complete', _ => {
            this.reload();
        });
    },

    reload: function () {
        this.initLikes();
        // Grid 参数支持
        document.querySelectorAll('.fence-grid').forEach(el => {
            if (el.dataset.columns) el.style.setProperty('--columns', `repeat(${el.dataset.columns}, 1fr)`);
            if (el.dataset.gap) el.style.setProperty('--gap', el.dataset.gap);
            if (el.dataset.minWidth) el.style.setProperty('--min-width', el.dataset.minWidth);
        });

        // Tabs 交互初始化
        document.querySelectorAll('.fence-tabs').forEach(container => {
            const buttons = container.querySelectorAll('.tab-button');
            const panes = container.querySelectorAll('.tab-pane');

            buttons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.dataset.tab;

                    // 移除所有 active 类
                    buttons.forEach(b => b.classList.remove('active'));
                    panes.forEach(p => p.classList.remove('active'));

                    // 添加 active 类到当前选中的 tab
                    button.classList.add('active');
                    container.querySelector(`[data-pane="${tabId}"]`).classList.add('active');
                });
            });
        });

        // Masonry 初始化
        document.querySelectorAll('.fence-masonry .masonry-wrapper').forEach(el => {
            // 检查是否有 masonry-item 元素
            const items = el.querySelectorAll('.masonry-item');
            if (items.length === 0) {
                console.warn('No masonry-item found, skipping Masonry init');
                return;
            }

            // 获取配置
            const parent = el.closest('.fence-masonry');
            const gapStr = parent.dataset.gap || '16px';
            const columns = parent.dataset.columns || '3';
            const colCount = parseInt(columns);
            const gapNum = parseInt(gapStr) || 16;

            // 设置 CSS 变量
            el.style.setProperty('--masonry-gap', gapNum + 'px');
            el.style.setProperty('--masonry-columns', colCount);

            // 获取容器实际宽度
            const containerWidth = el.offsetWidth;
            // 计算每列的实际宽度（包含间距）
            const columnWidth = (containerWidth - gapNum * (colCount - 1)) / colCount;

            // 设置每个 item 的宽度（固定像素值）
            items.forEach(item => {
                item.style.width = columnWidth + 'px';
                item.style.marginBottom = gapNum + 'px';
            });

            // 初始化 Masonry，不使用 percentPosition
            const msnry = new Masonry(el, {
                itemSelector: '.masonry-item',
                columnWidth: columnWidth,
                gutter: gapNum,
                percentPosition: false,
                transitionDuration: 0
            });

            console.log(`Masonry: ${colCount} cols, ${columnWidth}px each, container ${containerWidth}px`);
        });
    },

    initLikes: function () {
        document.querySelectorAll('[data-like-btn] .tex-stat-value[data-cid]').forEach(el => {
            const cid = el.dataset.cid;
            const parent = el.closest('[data-like-btn]');

            // 已点赞状态跳过（CSS 已设置 cursor: default）
            if (parent.classList.contains('tex-liked')) {
                return;
            }

            parent.addEventListener('click', async _ => {
                if (parent.classList.contains('tex-liked') || parent.classList.contains('tex-loading')) {
                    return;
                }

                parent.classList.add('tex-loading');

                try {
                    const response = await fetch(`/action/likes?cid=${cid}`);
                    const result = await response.json();

                    if (result.status === 1) {
                        const currentNum = parseInt(el.textContent) || 0;
                        el.textContent = currentNum + 1;
                        parent.classList.add('tex-liked');

                        // 触发跳动动画
                        parent.classList.remove('tex-animate');
                        void parent.offsetWidth; // 触发重绘
                        parent.classList.add('tex-animate');
                    }
                } catch (error) {
                    console.error('点赞请求失败:', error);
                } finally {
                    parent.classList.remove('tex-loading');
                }
            });
        });
    },

    registerCustomElements: function () {
        customElements.define('x-github', class XGithub extends HTMLElement {
            constructor() {
                super();
                this.init();
            }

            async init() {
                const url = this.getAttribute("url")?.trim();
                if (!url) {
                    this.innerHTML = ``;
                    return;
                }

                const is_github = url.includes("github.com");
                const is_gitee = url.includes("gitee.com");
                const regex = this.getRegex(is_github, is_gitee);
                const match = regex.exec(url);

                if (!match) {
                    this.innerHTML = `Invalid URL format.`;
                    return;
                }

                const platform = is_github ? 'github' : 'gitee';
                const isRepo = match[1].length > 0 && match[2] === '/' && match[3].length > 0;

                if (isRepo) {
                    const [, user, , repo] = match;
                    const cacheKey = `${platform}-repo:${user}/${repo}`;
                    const cache = this.getWithExpiry(cacheKey);

                    if (cache) {
                        const json = JSON.parse(cache);
                        this.renderRepo(json, is_gitee);
                    } else {
                        const api = this.getRepoApiUrl(is_github, is_gitee, user, repo);
                        if (!api) return;

                        try {
                            const response = await fetch(api);
                            const json = await response.json();
                            this.renderRepo(json, is_gitee);
                            this.setWithExpiry(cacheKey, JSON.stringify(json), 86400);
                        } catch (error) {
                            console.error('Error fetching API:', error);
                        }
                    }
                } else {
                    const user = match[3].length ? match[3] : match[1];
                    const cacheKey = `${platform}-user:${user}`;
                    const cache = this.getWithExpiry(cacheKey);

                    if (cache) {
                        const json = JSON.parse(cache);
                        this.renderUser(json, is_gitee);
                    } else {
                        const api = this.getUserApiUrl(is_github, is_gitee, user);
                        if (!api) return;

                        try {
                            const response = await fetch(api);
                            const json = await response.json();
                            this.renderUser(json, is_gitee);
                            this.setWithExpiry(cacheKey, JSON.stringify(json), 86400);
                        } catch (error) {
                            console.error('Error fetching API:', error);
                        }
                    }
                }
            }

            getRegex(is_github, is_gitee) {
                if (is_github) {
                    return /(?:git@|https?:\/\/)github.com\/([^\/]*)(\/?)(?<=\/)([.\w-]*=?)/is;
                } else if (is_gitee) {
                    return /https?:\/\/gitee.com\/([^\/]*)(\/?)(?<=\/)([.\w-]*=?)/is;
                } else {
                    return /([^\/]+)\/([^\/]+)/i;
                }
            }

            getRepoApiUrl(is_github, is_gitee, user, repo) {
                if (is_github) {
                    return `https://api.github.com/repos/${user}/${repo}`;
                } else if (is_gitee) {
                    return `https://gitee.com/api/v5/repos/${user}/${repo}`;
                }
                return null;
            }

            getUserApiUrl(is_github, is_gitee, user) {
                if (is_github) {
                    return `https://api.github.com/users/${user}`;
                } else if (is_gitee) {
                    return `https://gitee.com/api/v5/users/${user}`;
                }
            }

            renderRepo(json, is_gitee) {
                const icon = is_gitee ? this.giteeIcon() : this.githubIcon();
                this.innerHTML = this.parseRepoHTML(json, icon);
                if (is_gitee) {
                    this.querySelector('.download-zip').style.display = 'none';
                }
            }

            renderUser(json, is_gitee) {
                const icon = is_gitee ? this.giteeIcon() : this.githubIcon();
                this.innerHTML = this.parseUserHTML(json, icon);
            }

            parseRepoHTML(json, icon) {
                return `<div class="x-github">
                    <div class="x-github-title">
                        <span class="icon">${icon}</span>
                        <a class="user reset" href="${json.owner.html_url}" target="_blank">${json.owner.login}</a>
                        <span>/</span>
                        <a class="x-github-repository reset" href="${json.html_url}" target="_blank">${json.name}</a>
                        <div class="x-github-statics">
                            <span class="forks">${this.forksIcon()}${json.forks_count}</span>
                            <span class="slash">/</span>
                            <span class="stars">${this.starsIcon()}${json.stargazers_count}</span>
                        </div>
                    </div>
                    <div class="x-github-content">${json.description}</div>
                    <div class="x-github-footer">
                        <a class="x-github-btn secondary reset" href="${json.html_url}" target="_blank"><span class="x-github-btn-content">仓库</span></a>
                        <a class="x-github-btn warning download-zip reset" href="${json.html_url}/zipball/master" target="_blank"><span class="x-github-btn-content">下载 zip 文件</span></a>
                    </div>
                </div>`;
            }

            parseUserHTML(json, icon) {
                return `<div class="x-github x-github-user">
                    <a class="reset" href="${json.html_url}" target="_blank">
                        <span class="icon">${icon}</span>
                        <span class="name">${json.login}(${json.name})</span>
                    </a>
                </div>`;
            }

            giteeIcon() {
                return `<svg fill="#C71D23" width="16px" height="16px" viewBox="0 0 24 24" role="img" xmlns="http://www.w3.org/2000/svg">
                    <path d="M11.984 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.016 0zm6.09 5.333c.328 0 .593.266.592.593v1.482a.594.594 0 0 1-.593.592H9.777c-.982 0-1.778.796-1.778 1.778v5.63c0 .327.266.592.593.592h5.63c.982 0 1.778-.796 1.778-1.778v-.296a.593.593 0 0 0-.592-.593h-4.15a.592.592 0 0 1-.592-.592v-1.482a.593.593 0 0 1 .593-.592h6.815c.327 0 .593.265.593.592v3.408a4 4 0 0 1-4 4H5.926a.593.593 0 0 1-.593-.593V9.778a4.444 4.444 0 0 1 4.445-4.444h8.296z"/>
                </svg>`;
            }

            githubIcon() {
                return `<svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 496 512" xmlns="http://www.w3.org/2000/svg">
                    <path fill="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M165.9 397.4c0 2-2.3 3.6-5.2 3.6-3.3.3-5.6-1.3-5.6-3.6 0-2 2.3-3.6 5.2-3.6 3-.3 5.6 1.3 5.6 3.6zm-31.1-4.5c-.7 2 1.3 4.3 4.3 4.9 2.6 1 5.6 0 6.2-2s-1.3-4.3-4.3-5.2c-2.6-.7-5.5.3-6.2 2.3zm44.2-1.7c-2.9.7-4.9 2.6-4.6 4.9.3 2 2.9 3.3 5.9 2.6 2.9-.7 4.9-2.6 4.6-4.6-.3-1.9-3-3.2-5.9-2.9zM244.8 8C106.1 8 0 113.3 0 252c0 110.9 69.8 205.8 169.5 239.2 12.8 2.3 17.3-5.6 17.3-12.1 0-6.2-.3-40.4-.3-61.4 0 0-70 15-84.7-29.8 0 0-11.4-29.1-27.8-36.6 0 0-22.9-15.7 1.6-15.4 0 0 24.9 2 38.6 25.8 21.9 38.6 58.6 27.5 72.9 20.9 2.3-16 8.8-27.1 16-33.7-55.9-6.2-112.3-14.3-112.3-110.5 0-27.5 7.6-41.3 23.6-58.9-2.6-6.5-11.1-33.3 2.6-67.9 20.9-6.5 69 27 69 27 20-5.6 41.5-8.5 62.8-8.5s42.8 2.9 62.8 8.5c0 0 48.1-33.6 69-27 13.7 34.7 5.2 61.4 2.6 67.9 16 17.7 25.8 31.5 25.8 58.9 0 96.5-58.9 104.3-114.8 110.5 9.2 8 17.3 23.2 17.3 47.1 0 33.7-.3 74.9-.3 82.7 0 6.5 4.6 14.7 17.6 12.1C426.2 457.9 496 362.9 496 252 496 113.3 383.5 8 244.8 8z"/>
                </svg>`;
            }

            forksIcon() {
                return `<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true">
                    <path fill-rule="evenodd" fill="currentColor" d="M5 3.09V12.9c-.6.3-1 .8-1 1.4 0 .8.8 1.7 2 1.7s2-.9 2-1.7c0-.6-.4-1.1-1-1.4v-4H9v.9c-.6.3-1 .8-1 1.4 0 .8.8 1.7 2 1.7s2-.9 2-1.7c0-.6-.4-1.1-1-1.4V6.09c.6-.3 1-.8 1-1.4 0-.8-.8-1.7-2-1.7s-2 .9-2 1.7c0 .6.4 1.1 1 1.4v2H7v-2c.6-.3 1-.8 1-1.4 0-.8-.8-1.7-2-1.7s-2 .9-2 1.7c0 .6.4 1.1 1 1.4z"></path>
                </svg>`;
            }

            starsIcon() {
                return `<svg viewBox="0 0 16 16" width="16" height="16" aria-hidden="true">
                    <path fill-rule="evenodd" fill="currentColor" d="M8 12.7l-4.3 2.3c-.5.3-1-.2-.8-.8l.8-4.7-3.5-3.4c-.4-.4-.2-1.1.4-1.2l4.8-.7 2.2-4.5c.3-.5 1-.5 1.2 0l2.2 4.5 4.8.7c.6.1.8.8.4 1.2l-3.5 3.4.8 4.7c.1.6-.5 1.1-.9.8L8 12.7z"></path>
                </svg>`;
            }

            setWithExpiry(key, value, ttl) {
                const now = new Date();
                const item = {
                    value: value,
                    expiry: now.getTime() + ttl * 1000,
                };
                localStorage.setItem(key, JSON.stringify(item));
            }

            getWithExpiry(key) {
                const itemStr = localStorage.getItem(key);
                if (!itemStr) return null;

                const item = JSON.parse(itemStr);
                const now = new Date();
                if (now.getTime() > item.expiry) {
                    localStorage.removeItem(key);
                    return null;
                }
                return item.value;
            }
        });
    },
};

window.TeXtend.init();
