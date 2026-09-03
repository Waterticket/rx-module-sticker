let assetVersion = '';
const assetUrl = name => new URL(`${name}${assetVersion}`, import.meta.url).href;

const RECENT_KEY = 'roundeditor.recentStickers';
const RECENT_LIMIT = 30;
const labels = {
    sticker: '스티커',
    stickerRecent: '최근 사용',
    stickerLoading: '스티커를 불러오는 중...',
    stickerEmpty: '사용할 수 있는 스티커가 없습니다.',
    stickerError: '스티커를 불러오지 못했습니다.',
};

function request(action, params) {
    return new Promise((resolve, reject) => {
        if (typeof window.exec_json !== 'function') {
            reject(new Error('The Rhymix sticker API is unavailable.'));
            return;
        }
        window.exec_json(`sticker.${action}`, params, resolve, response => {
            reject(new Error(response?.message || 'The sticker request failed.'));
            return false;
        });
    });
}

function pickerPacks(config) {
    return request('getStickerPickerList', { mid: config.mid || 'sticker' })
        .then(response => Array.isArray(response?.sticker) ? response.sticker : []);
}

function elements(config, stickerSrl) {
    return request('getStickerElemList', {
        mid: config.mid || 'sticker',
        sticker_srl: stickerSrl,
    }).then(response => Array.isArray(response?.stickerImage) ? response.stickerImage : []);
}

function resolveStickers(config, identities) {
    if (!identities.length) return Promise.resolve([]);
    return request('resolveStickers', {
        mid: config.mid || 'sticker',
        stickers: JSON.stringify(identities),
    }).then(response => Array.isArray(response?.stickers) ? response.stickers : []);
}

function recent() {
    try {
        const value = JSON.parse(localStorage.getItem(RECENT_KEY) || '[]');
        return Array.isArray(value) ? value.slice(0, RECENT_LIMIT) : [];
    } catch (_) {
        return [];
    }
}

function remember(item) {
    const identity = {
        sticker_srl: item.sticker_srl,
        sticker_file_srl: item.sticker_file_srl,
    };
    try {
        const others = recent().filter(saved =>
            String(saved.sticker_srl) !== String(identity.sticker_srl)
            || String(saved.sticker_file_srl) !== String(identity.sticker_file_srl));
        localStorage.setItem(RECENT_KEY, JSON.stringify([identity, ...others].slice(0, RECENT_LIMIT)));
    } catch (_) {}
}

function stickerAttrs(item, packTitle) {
    const video = item.type === 'video';
    return {
        stickerSrl: String(item.sticker_srl),
        fileSrl: String(item.sticker_file_srl),
        mediaType: video ? 'video' : 'image',
        src: item.poster || (video ? '' : item.url) || '',
        videoSrc: video ? item.url : null,
        title: [packTitle, item.name].filter(Boolean).join(' - '),
        width: 100,
        height: 100,
        displayWidth: '100px',
        displayHeight: '100px',
        extra: null,
    };
}

function stickerPreview(item, label) {
    const video = item.type === 'video';
    const media = document.createElement(video ? 'video' : 'img');
    media.dataset.src = (video ? item.url : item.poster || item.url) || '';
    if (video) {
        media.poster = item.poster || '';
        media.autoplay = true;
        media.muted = true;
        media.loop = true;
        media.playsInline = true;
        media.preload = 'none';
        media.setAttribute('aria-label', label);
    } else {
        media.alt = label;
        media.loading = 'lazy';
    }
    return media;
}

function packPreview(pack) {
    const video = pack?.type === 'video' && pack.url;
    const media = document.createElement(video ? 'video' : 'img');
    if (video) {
        media.src = pack.url;
        media.poster = pack.poster || pack.main_image || '';
        media.autoplay = true;
        media.muted = true;
        media.loop = true;
        media.playsInline = true;
        media.preload = 'metadata';
        media.setAttribute('aria-hidden', 'true');
    } else {
        media.src = pack?.poster || pack?.main_image || '';
        media.alt = '';
        media.loading = 'lazy';
    }
    return media;
}

function createPicker(context, insert) {
    const template = document.createElement('template');
    template.innerHTML = String(context.config.pickerTemplate || '').trim();
    const root = template.content.firstElementChild;
    if (!root) throw new Error('The sticker picker template is empty.');

    const previous = root.querySelector('[data-role="previous"]');
    const next = root.querySelector('[data-role="next"]');
    const list = root.querySelector('[data-role="pack-list"]');
    const title = root.querySelector('[data-role="pack-title"]');
    const grid = root.querySelector('[data-role="sticker-grid"]');
    const status = root.querySelector('[data-role="status"]');
    root.querySelector('[data-role="order-link"]').href = context.config.myListUrl || '/sticker/mylist';
    root.querySelector('[data-role="list-link"]').href = context.config.listUrl || '/sticker';

    const cache = new Map();
    let observer;
    let active;

    function render(items, pack) {
        const validItems = items.filter(item => item?.valid !== false);
        observer?.disconnect();
        observer = typeof IntersectionObserver === 'function'
            ? new IntersectionObserver(entries => entries.forEach(entry => {
                if (!entry.isIntersecting) return;
                const media = entry.target;
                if (media.dataset.src && !media.getAttribute('src')) {
                    media.setAttribute('src', media.dataset.src);
                }
                if (media.tagName === 'VIDEO') media.play().catch(() => {});
                observer.unobserve(media);
            }), { root: grid, rootMargin: '180px' })
            : null;

        grid.replaceChildren();
        if (!validItems.length) {
            status.textContent = labels.stickerEmpty;
            grid.append(status);
            return;
        }

        validItems.forEach(item => {
            const button = document.createElement('button');
            const label = item.name || item.title || labels.sticker;
            const media = stickerPreview(item, label);
            button.type = 'button';
            button.className = 'roundeditor__sticker-item';
            button.title = label;
            button.setAttribute('aria-label', label);
            button.append(media);
            button.addEventListener('click', () => {
                insert(item, pack?.title || item.title || '');
                remember(item);
            });
            grid.append(button);
            if (observer) observer.observe(media);
            else if (media.dataset.src) media.setAttribute('src', media.dataset.src);
        });
    }

    async function select(pack, button) {
        active = pack?.sticker_srl || 'recent';
        title.textContent = pack?.title || labels.stickerRecent;
        list.querySelectorAll('.roundeditor__sticker-pack').forEach(candidate => {
            candidate.setAttribute('aria-selected', String(candidate === button));
        });
        status.textContent = labels.stickerLoading;
        grid.replaceChildren(status);
        try {
            if (!cache.has(active)) {
                const items = pack
                    ? (await elements(context.config, pack.sticker_srl)).map(item => ({
                        ...item,
                        sticker_srl: item.sticker_srl || pack.sticker_srl,
                    }))
                    : await resolveStickers(context.config, recent());
                cache.set(active, items);
            }
            if (active === (pack?.sticker_srl || 'recent')) render(cache.get(active), pack);
        } catch (error) {
            status.textContent = error.message || labels.stickerError;
            grid.replaceChildren(status);
        }
    }

    function createPackTab(pack, name) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'roundeditor__sticker-pack';
        button.setAttribute('role', 'tab');
        button.setAttribute('aria-label', name);
        button.title = name;
        if (pack?.main_image || pack?.poster) {
            button.append(packPreview(pack));
        } else {
            const icon = document.createElement('span');
            icon.className = 'roundeditor__sticker-pack-icon';
            icon.textContent = '↺';
            icon.setAttribute('aria-hidden', 'true');
            button.append(icon);
        }
        const text = document.createElement('span');
        text.textContent = name;
        button.append(text);
        button.addEventListener('click', () => select(pack, button));
        return button;
    }

    function updatePaging() {
        previous.disabled = list.scrollTop <= 0;
        next.disabled = list.scrollTop + list.clientHeight >= list.scrollHeight - 1;
    }

    previous.addEventListener('click', () => list.scrollBy({ top: -58, behavior: 'smooth' }));
    next.addEventListener('click', () => list.scrollBy({ top: 58, behavior: 'smooth' }));
    list.addEventListener('scroll', updatePaging, { passive: true });

    const recentTab = createPackTab(null, labels.stickerRecent);
    list.append(recentTab);
    pickerPacks(context.config).then(packs => {
        packs.forEach(pack => list.append(createPackTab(pack, pack.title)));
        select(null, recentTab);
        setTimeout(updatePaging, 0);
    }).catch(error => {
        status.textContent = error.message || labels.stickerError;
    });

    root.addEventListener('roundeditor:close', () => {
        observer?.disconnect();
        root.querySelectorAll('video').forEach(video => video.pause());
    });
    return root;
}

function stickerNodeView() {
    return node => {
        let current = node;
        let observer;
        let media = null;
        const dom = document.createElement('span');
        dom.className = 'roundeditor__media roundeditor__media--sticker';
        dom.contentEditable = 'false';
        dom.draggable = true;

        function render(value) {
            observer?.disconnect();
            const video = value.attrs.mediaType === 'video' && value.attrs.videoSrc;
            if (!media || (Boolean(video) !== (media.tagName === 'VIDEO'))) {
                media?.remove();
                media = document.createElement(video ? 'video' : 'img');
                media.draggable = false;
                dom.prepend(media);
            }
            if (video) {
                media.src = value.attrs.videoSrc;
                media.poster = value.attrs.src;
                media.autoplay = true;
                media.muted = true;
                media.loop = true;
                media.playsInline = true;
                media.preload = 'metadata';
                observer = typeof IntersectionObserver === 'function'
                    ? new IntersectionObserver(entries => {
                        if (entries.some(entry => entry.isIntersecting)) media.play().catch(() => {});
                        else media.pause();
                    }, { rootMargin: '120px' })
                    : null;
                observer?.observe(media);
            } else {
                media.src = value.attrs.src;
                media.alt = value.attrs.title || '';
            }
            media.setAttribute('aria-label', value.attrs.title || labels.sticker);
            media.style.width = value.attrs.displayWidth || `${value.attrs.width || 100}px`;
            media.style.height = value.attrs.displayHeight || `${value.attrs.height || 100}px`;
            media.style.aspectRatio = `${value.attrs.width || 100} / ${value.attrs.height || 100}`;
            dom.style.width = media.style.width;
        }

        render(node);
        return {
            dom,
            update(next) {
                if (next.type !== current.type) return false;
                current = next;
                render(next);
                return true;
            },
            selectNode() { dom.classList.add('roundeditor__media--selected'); },
            deselectNode() { dom.classList.remove('roundeditor__media--selected'); },
            ignoreMutation() { return true; },
            destroy() {
                observer?.disconnect();
                if (media?.tagName === 'VIDEO') media.pause();
            },
        };
    };
}

const stickerSpec = {
    inline: true,
    group: 'inline',
    atom: true,
    draggable: true,
    selectable: true,
    attrs: {
        stickerSrl: { default: null },
        fileSrl: { default: null },
        mediaType: { default: 'image' },
        src: { default: '' },
        videoSrc: { default: null },
        title: { default: '' },
        width: { default: 100 },
        height: { default: 100 },
        displayWidth: { default: '100px' },
        displayHeight: { default: '100px' },
        extra: { default: null },
    },
    parseDOM: [{
        tag: 'img[data-rx-sticker]',
        priority: 100,
        getAttrs(element) {
            const [stickerSrl, fileSrl] = String(element.getAttribute('data-rx-sticker') || '').split('|');
            const width = element.getAttribute('width') || 100;
            const height = element.getAttribute('height') || 100;
            return {
                stickerSrl: stickerSrl || null,
                fileSrl: fileSrl || null,
                mediaType: element.getAttribute('data-rx-sticker-type') || 'image',
                src: element.getAttribute('src') || '',
                videoSrc: element.getAttribute('data-rx-sticker-video-src') || null,
                title: element.getAttribute('alt') || '',
                width,
                height,
                displayWidth: element.style.getPropertyValue('width') || `${width}px`,
                displayHeight: element.style.getPropertyValue('height') || `${height}px`,
                extra: null,
            };
        },
    }],
    toDOM(node) {
        return ['img', {
            src: node.attrs.src,
            alt: node.attrs.title,
            width: node.attrs.width,
            height: node.attrs.height,
            style: `width:${node.attrs.displayWidth};height:${node.attrs.displayHeight}`,
            'data-rx-sticker': `${node.attrs.stickerSrl}|${node.attrs.fileSrl}`,
            'data-rx-sticker-type': node.attrs.mediaType,
            'data-rx-sticker-video-src': node.attrs.videoSrc,
        }];
    },
};

export function registerStickerExtension(version = '') {
    assetVersion = version;
    const registration = window.RoundEditor.extensions.register({
        id: 'rhymix.sticker',
        version: '1.0.0',
        apiVersion: '^1.0',
        requires: { capabilities: ['content.insertHTML'] },
        schema: { nodes: { sticker: { fallback: 'raw-inline', spec: stickerSpec } } },
        create(context) {
            let picker = null;
            context.assets.addStyle({
                id: 'sticker-roundeditor',
                href: assetUrl('roundeditor.css'),
                scope: 'document',
            });

            function insert(item, packTitle) {
                if (!item?.sticker_srl || !item?.sticker_file_srl) return false;
                const node = context.schema.nodes.sticker.create(stickerAttrs(item, packTitle));
                context.editor.commands.execute('rhymix.sticker.insert', { node });
                return true;
            }

            return {
                commands: {
                    insert({ state, dispatch }, params) {
                        if (!params?.node || !dispatch) return Boolean(params?.node);
                        dispatch(state.tr.replaceSelectionWith(params.node).scrollIntoView());
                        return true;
                    },
                    openPicker({ dispatch }) {
                        if (!dispatch) return true;
                        if (picker?.open) return picker.close();
                        picker = context.ui.openPanel({
                            id: 'picker',
                            title: labels.sticker,
                            content: createPicker(context, insert),
                            onClose() { picker = null; },
                        });
                        return true;
                    },
                },
                toolbar: [{
                    id: 'sticker',
                    label: labels.sticker,
                    command: 'openPicker',
                    icon: {
                        type: 'svg',
                        svg: '<circle cx="12" cy="12" r="9"/><circle cx="9" cy="10" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="10" r="1" fill="currentColor" stroke="none"/><path d="M8 14.5c1 1.3 2.3 2 4 2s3-.7 4-2"/>',
                    },
                    group: 'sticker',
                    order: 0,
                    placement: { before: 'components' },
                }],
                nodeViews: { sticker: stickerNodeView() },
                destroy() {
                    if (picker?.open) picker.close();
                },
            };
        },
    });

    if (!registration.accepted) throw registration.error;
}
