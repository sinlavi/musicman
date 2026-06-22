/**
 * MusicMan Crawler Admin JS
 */

jQuery(document).ready(function($) {
    const API_ROOT = musicmanCrawler.root + 'musicman/v1';

    function apiCall(endpoint, method = 'GET', body = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': musicmanCrawler.nonce
            }
        };
        if (body) options.body = JSON.stringify(body);

        return fetch(API_ROOT + endpoint, options).then(res => res.json());
    }

    // Tab Switching
    $('.tabs .tab').on('click', function() {
        const tab = $(this).data('tab');
        $('.tabs .tab').removeClass('active').css('background', '#e0e0e0');
        $(this).addClass('active').css('background', '#fff');

        $('.tab-pane').hide();
        $(`#${tab}-pane`).show();

        if (tab === 'queue') loadQueue();
        if (tab === 'stats') loadStats();
    });

    $('#doSearchBtn').on('click', performSearch);
    $('#searchTerm').on('keypress', (e) => { if (e.key === 'Enter') performSearch(); });

    async function performSearch() {
        const term = $('#searchTerm').val();
        const entity = $('#entityType').val();
        const $container = $('#treeContainer');

        $container.html('<div class="empty-msg">Searching...</div>');

        try {
            const data = await apiCall(`/search?term=${encodeURIComponent(term)}&entity=${entity}&limit=50`);
            renderTree(data.results, $container);
        } catch (e) {
            $container.html('<div class="empty-msg">Error performing search.</div>');
        }
    }

    function renderTree(results, $container, isSub = false) {
        if (!results || results.length === 0) {
            if (!isSub) $container.html('<div class="empty-msg">No results found.</div>');
            return;
        }

        const $ul = $('<ul>').addClass(isSub ? 'tree-children' : 'tree-node');

        results.forEach(item => {
            const type = item.wrapperType || (item.kind === 'song' ? 'track' : (item.kind === 'music-artist' ? 'artist' : ''));
            const id = item.trackId || item.collectionId || item.artistId;
            const name = item.trackName || item.collectionName || item.artistName || 'Unknown';

            const hasChildren = (type === 'artist' || type === 'collection');

            const $li = $('<li>');
            const $toggle = $('<div>').addClass('tree-toggle').attr('data-type', type).attr('data-id', id);

            $toggle.append($('<input type="checkbox">').addClass('tree-checkbox').attr('data-id', id).attr('data-type', type));
            $toggle.append($('<i>').addClass('dashicons ' + getIcon(type)));

            const $label = $('<span>').addClass('node-label').append($('<span>').text(name));
            if (item.primaryGenreName) $label.append($('<span>').addClass('sub-info').text(item.primaryGenreName));
            $toggle.append($label);

            const $actions = $('<span>').addClass('node-actions');
            if (hasChildren) $actions.append($('<button>').addClass('button button-small tree-expand-btn').html('<i class="dashicons dashicons-arrow-right-alt2"></i>'));
            $actions.append($('<button>').addClass('button button-small tree-info-btn').html('<i class="dashicons dashicons-info"></i>'));
            $toggle.append($actions);

            $li.append($toggle);

            $label.on('click', (e) => {
                e.stopPropagation();
                updateWorkspace(item);
            });

            $toggle.find('.tree-info-btn').on('click', (e) => {
                e.stopPropagation();
                updateRightPanel(item);
            });

            if (hasChildren) {
                $toggle.find('.tree-expand-btn').on('click', async (e) => {
                    e.stopPropagation();
                    const isOpen = $li.hasClass('open');
                    if (!isOpen) {
                        $li.addClass('open');
                        if (!$li.find('.tree-children').length) {
                            const $loading = $('<div>').addClass('tree-loading').text('Loading...');
                            $li.append($loading);

                            const childEntity = (type === 'artist') ? 'album' : 'song';
                            const children = await apiCall(`/lookup?id=${id}&entity=${childEntity}`);
                            $loading.remove();
                            if (children.results) {
                                const kids = children.results.filter(r => r.wrapperType !== type);
                                renderTree(kids, $li, true);
                            }
                        }
                    } else {
                        $li.removeClass('open');
                    }
                });
            }

            $ul.append($li);
        });

        if (!isSub) $container.empty();
        $container.append($ul);
        if (!isSub) {
            $('#treeTotalCount').text(results.length + ' items');
            $('#treeBulkBar').show();
        }
    }

    function getIcon(type) {
        switch(type) {
            case 'track': return 'dashicons-format-audio';
            case 'collection': return 'dashicons-album';
            case 'artist': return 'dashicons-admin-users';
            default: return 'dashicons-media-default';
        }
    }

    async function updateWorkspace(item) {
        const type = item.wrapperType || 'track';
        const name = item.trackName || item.collectionName || item.artistName || '—';
        const id = item.trackId || item.collectionId || item.artistId;
        const $ws = $('#browseWorkspace');

        $ws.html(`
            <div style="padding: 20px;">
                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
                    ${item.artworkUrl100 ? `<img src="${item.artworkUrl100}" style="width: 100px; border-radius: 4px; border: 1px solid #ccc;">` : ''}
                    <div>
                        <h2 style="margin: 0;">${name}</h2>
                        <p style="color: #646970;">${type.toUpperCase()} · ID: ${id}</p>
                    </div>
                </div>

                <div id="ws-details" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px;">
                    <div class="ws-card" style="background: #f6f7f7; padding: 10px; border: 1px solid #c3c4c7;">
                        <label style="font-size: 10px; color: #646970; text-transform: uppercase;">Artist</label>
                        <div style="font-weight: bold;">${item.artistName || '—'}</div>
                    </div>
                    ${item.collectionName ? `
                    <div class="ws-card" style="background: #f6f7f7; padding: 10px; border: 1px solid #c3c4c7;">
                        <label style="font-size: 10px; color: #646970; text-transform: uppercase;">Collection</label>
                        <div style="font-weight: bold;">${item.collectionName}</div>
                    </div>` : ''}
                    <div class="ws-card" style="background: #f6f7f7; padding: 10px; border: 1px solid #c3c4c7;">
                        <label style="font-size: 10px; color: #646970; text-transform: uppercase;">Genre</label>
                        <div style="font-weight: bold;">${item.primaryGenreName || '—'}</div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <h3>Mirrors & Links</h3>
                    <div id="ws-mirrors" style="background: #fff; border: 1px solid #c3c4c7; padding: 10px;">Loading mirrors...</div>
                </div>
            </div>
        `);

        const res = await apiCall(`/mirrors?entityType=${type}&entityId=${id}`);
        if (res.success && res.mirrors) {
            let mHtml = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>Platform</th><th>Type</th><th>Quality</th><th>Link</th></tr></thead><tbody>';
            for (const [plat, mirrors] of Object.entries(res.mirrors)) {
                for (const [mType, mData] of Object.entries(mirrors)) {
                    mHtml += `<tr><td>${plat}</td><td>${mType}</td><td>${mData.quality || '—'}</td><td><a href="${mData.url}" target="_blank" class="button button-small">Open</a></td></tr>`;
                }
            }
            mHtml += '</tbody></table>';
            $('#ws-mirrors').html(mHtml);
        } else {
            $('#ws-mirrors').text('No mirrors set.');
        }
    }

    async function updateRightPanel(item) {
        const $container = $('#rightPanelContent');
        const type = item.wrapperType || 'track';
        const id = item.trackId || item.collectionId || item.artistId;
        const name = item.trackName || item.collectionName || item.artistName || '—';
        const artwork = item.artworkUrl100 || '';

        let html = `
            <div class="props-card">
                ${artwork ? `<img src="${artwork}" class="props-artwork">` : ''}
                <div class="props-title">${name}</div>
                <div class="props-row"><span>Type</span><strong>${type.toUpperCase()}</strong></div>
                <div class="props-row"><span>ID</span><strong>${id}</strong></div>
                <button class="button button-primary" style="width:100%; margin-top:10px;" id="props-add-to-queue">
                    Add to Queue
                </button>
            </div>
        `;
        $container.html(html);

        $('#props-add-to-queue').on('click', async () => {
            await apiCall('/queue', 'POST', { trackId: id });
            alert('Added to queue');
        });
    }

    async function loadQueue() {
        const $container = $('#admin-queue-list');
        $container.html('Loading queue...');
        const res = await apiCall('/queue?status=all');
        if (res.success && res.items) {
            let html = '<table class="wp-list-table widefat fixed striped"><thead><tr><th>ID</th><th>Track Name</th><th>Status</th><th>Added</th></tr></thead><tbody>';
            res.items.forEach(item => {
                const trackName = item.track_data ? item.track_data.trackName : item.track_id;
                html += `<tr><td>${item.id}</td><td>${trackName}</td><td><span class="status-badge status-${item.status}">${item.status}</span></td><td>${item.added_at}</td></tr>`;
            });
            html += '</tbody></table>';
            $container.html(html);
        }
    }

    async function loadStats() {
        const $container = $('#admin-stats-content');
        const res = await apiCall('/stats');
        if (res) {
            $container.html(`
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="ws-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7;">
                        <h4 style="margin-top: 0;">Database Totals</h4>
                        <p>Tracks: <strong>${res.track_count}</strong></p>
                        <p>Artists: <strong>${res.artist_count}</strong></p>
                        <p>Albums: <strong>${res.album_count}</strong></p>
                    </div>
                    <div class="ws-card" style="background: #fff; padding: 20px; border: 1px solid #c3c4c7;">
                        <h4 style="margin-top: 0;">Operation Totals</h4>
                        <p>Queue Size: <strong>${res.queue_count}</strong></p>
                    </div>
                </div>
            `);
        }
    }

    $('#treeBulkAddBtn').on('click', async () => {
        const selected = $('.tree-checkbox:checked');
        for (let i = 0; i < selected.length; i++) {
            const id = $(selected[i]).data('id');
            await apiCall('/queue', 'POST', { trackId: id });
        }
        alert('Added ' + selected.length + ' items to queue');
    });

});
