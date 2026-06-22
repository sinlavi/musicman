/**
 * MusicMan Theme App JS - Full Implementation
 */

const API_ROOT = musicmanSettings.root + 'musicman/v1';

document.addEventListener('DOMContentLoaded', () => {
    initSearch();
    if (document.getElementById('queueBody')) {
        loadDownloadQueue();
        setInterval(loadDownloadQueue, 15000);
        initDownloadQueueControls();
    }
    initAudioPlayer();
    initMobileControls();
    initActionButtons();
    initBulkControls();
});

async function apiCall(endpoint, method = 'GET', body = null) {
    const options = {
        method,
        headers: {
            'Content-Type': 'application/json',
            'X-WP-Nonce': musicmanSettings.nonce
        }
    };
    if (body) options.body = JSON.stringify(body);

    const response = await fetch(API_ROOT + endpoint, options);
    return await response.json();
}

function initSearch() {
    const searchBtn = document.getElementById('doSearchBtn');
    const searchTerm = document.getElementById('searchTerm');

    if (searchBtn) {
        searchBtn.addEventListener('click', performSearch);
    }
    if (searchTerm) {
        searchTerm.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') performSearch();
        });
    }
}

async function performSearch() {
    const term = document.getElementById('searchTerm').value;
    const entity = document.getElementById('entityType').value;
    const container = document.getElementById('treeContainer');

    container.innerHTML = '<div class="empty-msg"><i class="fas fa-spinner fa-pulse"></i> Searching...</div>';

    try {
        const data = await apiCall(`/search?term=${encodeURIComponent(term)}&entity=${entity}&limit=50`);
        renderTree(data.results, container);
    } catch (e) {
        container.innerHTML = '<div class="empty-msg">Error performing search.</div>';
    }
}

function renderTree(results, container, isSub = false) {
    if (!results || results.length === 0) {
        if (!isSub) container.innerHTML = '<div class="empty-msg">No results found.</div>';
        return;
    }

    const ul = document.createElement('ul');
    ul.className = isSub ? 'tree-children' : 'tree-node';

    results.forEach(item => {
        const li = document.createElement('li');
        const type = item.wrapperType || (item.kind === 'song' ? 'track' : (item.kind === 'music-artist' ? 'artist' : ''));
        const id = item.trackId || item.collectionId || item.artistId;
        const name = item.trackName || item.collectionName || item.artistName || 'Unknown';

        const hasChildren = (type === 'artist' || type === 'collection');

        li.innerHTML = `
            <div class="tree-toggle" data-type="${type}" data-id="${id}">
                <input type="checkbox" class="tree-checkbox" data-id="${id}" data-type="${type}">
                <i class="fas ${getIcon(type)}"></i>
                <span class="node-label">
                    <span>${name}</span>
                    ${item.primaryGenreName ? `<span class="sub-info">${item.primaryGenreName}</span>` : ''}
                </span>
                <span class="node-actions">
                    ${hasChildren ? `<button class="tree-expand-btn"><i class="fas fa-chevron-right"></i></button>` : ''}
                    <button class="tree-info-btn"><i class="fas fa-info-circle"></i></button>
                </span>
            </div>
        `;

        const toggle = li.querySelector('.tree-toggle');
        const label = li.querySelector('.node-label');
        const expandBtn = li.querySelector('.tree-expand-btn');
        const infoBtn = li.querySelector('.tree-info-btn');

        label.addEventListener('click', (e) => {
            e.stopPropagation();
            if (item.wp_permalink) {
                window.location.href = item.wp_permalink;
            } else {
                apiCall(`/lookup?id=${id}`).then(res => {
                    if (res.results && res.results[0].wp_permalink) {
                        window.location.href = res.results[0].wp_permalink;
                    }
                });
            }
        });

        infoBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            updateRightPanel(item);
        });

        if (hasChildren && expandBtn) {
            expandBtn.addEventListener('click', async (e) => {
                e.stopPropagation();
                const isOpen = li.classList.contains('open');
                if (!isOpen) {
                    li.classList.add('open');
                    expandBtn.innerHTML = '<i class="fas fa-chevron-down"></i>';
                    if (!li.querySelector('.tree-children')) {
                        const loading = document.createElement('div');
                        loading.className = 'tree-loading';
                        loading.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';
                        li.appendChild(loading);

                        const childEntity = (type === 'artist') ? 'album' : 'song';
                        const children = await apiCall(`/lookup?id=${id}&entity=${childEntity}`);
                        loading.remove();
                        if (children.results) {
                            const kids = children.results.filter(r => r.wrapperType !== type);
                            renderTree(kids, li, true);
                        }
                    }
                } else {
                    li.classList.remove('open');
                    expandBtn.innerHTML = '<i class="fas fa-chevron-right"></i>';
                }
            });
        }

        ul.appendChild(li);
    });

    if (!isSub) container.innerHTML = '';
    container.appendChild(ul);
    if (!isSub) {
        document.getElementById('treeTotalCount').textContent = results.length + ' items';
        document.getElementById('treeBulkBar').style.display = 'flex';
    }
}

function getIcon(type) {
    switch(type) {
        case 'track': return 'fa-music';
        case 'collection': return 'fa-dot-circle';
        case 'artist': return 'fa-user';
        default: return 'fa-file';
    }
}

async function updateRightPanel(item) {
    const container = document.getElementById('rightPanelContent');
    const type = item.wrapperType || (item.kind === 'song' ? 'track' : (item.kind === 'music-artist' ? 'artist' : ''));
    const id = item.trackId || item.collectionId || item.artistId;
    const name = item.trackName || item.collectionName || item.artistName || '—';
    const artist = item.artistName || '—';
    const artwork = item.artworkUrl100 || '';

    let html = `
        <div class="props-card">
            ${artwork ? `<img src="${artwork}" class="props-artwork">` : ''}
            <div class="props-title">${name}</div>
            <div class="props-row"><span>Type</span><strong>${type.toUpperCase()}</strong></div>
            <div class="props-row"><span>Artist</span><strong>${artist}</strong></div>
            <div class="props-row"><span>ID</span><strong>${id}</strong></div>
    `;

    if (type === 'track') {
        html += `
            <button class="btn btn-play" style="width:100%; margin-top:10px;" onclick="playTrack('${item.previewUrl}', '${name}', '${artist}', '${artwork}')">
                <i class="fas fa-play"></i> Play Preview
            </button>
            <button class="btn btn-success" style="width:100%; margin-top:5px;" data-add-to-queue="${id}">
                <i class="fas fa-download"></i> Add to Download Queue
            </button>
            <hr>
            <div id="props-mirrors">Loading mirrors...</div>
            <hr>
            <div id="props-lyrics">Loading lyrics...</div>
        `;

        setTimeout(async () => {
            const mirrorsCont = document.getElementById('props-mirrors');
            const lyricsCont = document.getElementById('props-lyrics');

            try {
                const res = await apiCall(`/mirrors?entityType=track&entityId=${id}`);
                if (res.success && res.mirrors) {
                    let mHtml = '<strong>Mirrors</strong><div class="mirror-box">';
                    for (const [plat, mirrors] of Object.entries(res.mirrors)) {
                        mHtml += `<div class="mirror-item"><span class="mirror-platform">${plat}</span>`;
                        for (const [mType, mData] of Object.entries(mirrors)) {
                            mHtml += `<a href="${mData.url}" target="_blank" class="mirror-link">${mType}</a>`;
                        }
                        mHtml += '</div>';
                    }
                    mHtml += '</div>';
                    if (mirrorsCont) mirrorsCont.innerHTML = mHtml;
                } else if (mirrorsCont) mirrorsCont.innerHTML = 'No mirrors found.';

                const lyRes = await apiCall(`/lyrics?id=${id}`);
                if (lyRes.success && lyRes.lyrics) {
                    const lyrics = JSON.parse(lyRes.lyrics);
                    if (lyricsCont) lyricsCont.innerHTML = `<strong>Lyrics</strong><div class="lyrics-box">${lyrics.plainLyrics || lyrics.syncedLyrics || 'No text found.'}</div>`;
                } else if (lyricsCont) lyricsCont.innerHTML = 'Lyrics not found.';
            } catch (e) {
                if (mirrorsCont) mirrorsCont.innerHTML = '';
                if (lyricsCont) lyricsCont.innerHTML = '';
            }
        }, 100);
    }

    html += `</div>`;
    container.innerHTML = html;
    document.getElementById('rightPanel').classList.add('open-drawer');
}

let currentQueueItems = [];

async function loadDownloadQueue() {
    const tbody = document.getElementById('queueBody');
    if (!tbody) return;
    const filter = document.getElementById('queueStatusFilter').value;
    try {
        const data = await apiCall(`/queue?status=${filter}`);
        if (data.items) {
            currentQueueItems = data.items;
            filterDownloadQueueItems();
        }
    } catch (e) {}
}

function filterDownloadQueueItems() {
    const searchTerm = document.getElementById('queueSearchFilter').value.toLowerCase();
    const sortBy = document.getElementById('queueSortBy').value;

    let filtered = currentQueueItems.filter(item => {
        const name = (item.track_data ? item.track_data.trackName : item.track_id).toLowerCase();
        return name.includes(searchTerm);
    });

    filtered.sort((a, b) => {
        if (sortBy === 'track') {
            const nameA = (a.track_data ? a.track_data.trackName : a.track_id).toLowerCase();
            const nameB = (b.track_data ? b.track_data.trackName : b.track_id).toLowerCase();
            return nameA.localeCompare(nameB);
        } else if (sortBy === 'status') {
            return a.status.localeCompare(b.status);
        } else if (sortBy === 'added') {
            return new Date(b.added_at) - new Date(a.added_at);
        }
        return a.id - b.id;
    });

    renderDownloadQueueTable(filtered);
    updateDownloadQueueStats(currentQueueItems);
    document.getElementById('queueFilterCount').textContent = `Showing: ${filtered.length}`;
}

function renderDownloadQueueTable(items) {
    const tbody = document.getElementById('queueBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="empty-msg">No items in download queue.</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(item => {
        const name = item.track_data ? item.track_data.trackName : item.track_id;
        return `
        <tr>
            <td class="checkbox-col"><input type="checkbox" class="queue-checkbox" value="${item.id}"></td>
            <td>${item.id}</td>
            <td><strong>${name}</strong></td>
            <td>${item.quality}</td>
            <td><span class="status-badge status-${item.status}">${item.status}</span></td>
            <td>
                <button class="btn-sm btn-danger" onclick="deleteDownloadQueueItem(${item.id})"><i class="fas fa-trash"></i></button>
                ${(item.status === 'failed' || item.status === 'stopped') ? `<button class="btn-sm btn-warning" onclick="updateDownloadQueueStatus(${item.id}, 'pending')"><i class="fas fa-redo"></i></button>` : ''}
            </td>
            <td>${item.error_message || '—'}</td>
        </tr>
    `}).join('');

    document.querySelectorAll('.queue-checkbox').forEach(cb => {
        cb.addEventListener('change', updateDownloadQueueBulkBar);
    });
}

function updateDownloadQueueStats(items) {
    const stats = { pending: 0, downloading: 0, paused: 0, completed: 0, failed: 0 };
    items.forEach(i => { if (stats[i.status] !== undefined) stats[i.status]++; });
    document.getElementById('statPending').textContent = stats.pending;
    document.getElementById('statDownloading').textContent = stats.downloading;
    document.getElementById('statPaused').textContent = stats.paused;
    document.getElementById('statCompleted').textContent = stats.completed;
    document.getElementById('statFailed').textContent = stats.failed;
    document.getElementById('queueStats').textContent = `${items.length} total`;
}

function initDownloadQueueControls() {
    document.getElementById('queueStatusFilter').addEventListener('change', loadDownloadQueue);
    document.getElementById('queueSearchFilter').addEventListener('input', filterDownloadQueueItems);
    document.getElementById('queueSortBy').addEventListener('change', filterDownloadQueueItems);
    document.getElementById('refreshQueueBtn').addEventListener('click', loadDownloadQueue);
    document.getElementById('clearQueueFilters').addEventListener('click', () => {
        document.getElementById('queueStatusFilter').value = 'all';
        document.getElementById('queueSearchFilter').value = '';
        document.getElementById('queueSortBy').value = 'added';
        loadDownloadQueue();
    });

    document.getElementById('queueSelectAll').addEventListener('change', (e) => {
        document.querySelectorAll('.queue-checkbox').forEach(cb => cb.checked = e.target.checked);
        updateDownloadQueueBulkBar();
    });

    document.getElementById('queueSelectAllBtn').addEventListener('click', () => {
        document.querySelectorAll('.queue-checkbox').forEach(cb => cb.checked = true);
        updateDownloadQueueBulkBar();
    });

    document.getElementById('queueSelectNoneBtn').addEventListener('click', () => {
        document.querySelectorAll('.queue-checkbox').forEach(cb => cb.checked = false);
        updateDownloadQueueBulkBar();
    });

    document.getElementById('clearFailedBtn').addEventListener('click', async () => {
        if (confirm('Clear all failed tasks?')) {
            await apiCall('/queue?status=failed', 'DELETE');
            loadDownloadQueue();
        }
    });

    document.getElementById('retryFailedBtn').addEventListener('click', async () => {
        const failed = currentQueueItems.filter(i => i.status === 'failed');
        for (const item of failed) {
            await apiCall(`/queue?id=${item.id}&status=pending`, 'PUT');
        }
        loadDownloadQueue();
    });

    document.getElementById('queueBulkDeleteBtn').addEventListener('click', async () => {
        const ids = Array.from(document.querySelectorAll('.queue-checkbox:checked')).map(cb => cb.value);
        if (ids.length > 0 && confirm(`Delete ${ids.length} selected items?`)) {
            await apiCall('/queue', 'DELETE', { id: ids });
            loadDownloadQueue();
        }
    });

    document.getElementById('queueBulkStartBtn').addEventListener('click', () => bulkUpdateDownloadQueueStatus('downloading'));
    document.getElementById('queueBulkPauseBtn').addEventListener('click', () => bulkUpdateDownloadQueueStatus('paused'));
    document.getElementById('queueBulkStopBtn').addEventListener('click', () => bulkUpdateDownloadQueueStatus('stopped'));
}

async function bulkUpdateDownloadQueueStatus(status) {
    const ids = Array.from(document.querySelectorAll('.queue-checkbox:checked')).map(cb => cb.value);
    if (ids.length > 0) {
        await apiCall('/queue', 'PUT', { id: ids, status: status });
        loadDownloadQueue();
    }
}

function updateDownloadQueueBulkBar() {
    const count = document.querySelectorAll('.queue-checkbox:checked').length;
    document.getElementById('queueSelectedCount').textContent = count;
    document.getElementById('queueBulkBar').style.display = count > 0 ? 'flex' : 'none';
}

window.deleteDownloadQueueItem = async (id) => {
    await apiCall(`/queue?id=${id}`, 'DELETE');
    loadDownloadQueue();
};

window.updateDownloadQueueStatus = async (id, status) => {
    await apiCall(`/queue?id=${id}&status=${status}`, 'PUT');
    loadDownloadQueue();
};

function initBulkControls() {
    const selectAllBtn = document.getElementById('treeSelectAllBtn');
    const selectNoneBtn = document.getElementById('treeSelectNoneBtn');
    const bulkAddBtn = document.getElementById('treeBulkAddBtn');

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.tree-checkbox').forEach(cb => cb.checked = true);
            updateBulkCount();
        });
    }
    if (selectNoneBtn) {
        selectNoneBtn.addEventListener('click', () => {
            document.querySelectorAll('.tree-checkbox').forEach(cb => cb.checked = false);
            updateBulkCount();
        });
    }

    document.addEventListener('change', (e) => {
        if (e.target.classList.contains('tree-checkbox')) updateBulkCount();
    });

    if (bulkAddBtn) {
        bulkAddBtn.addEventListener('click', async () => {
            const selected = Array.from(document.querySelectorAll('.tree-checkbox:checked'));
            if (selected.length === 0) return;

            bulkAddBtn.disabled = true;
            bulkAddBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';

            for (const cb of selected) {
                const id = cb.getAttribute('data-id');
                const type = cb.getAttribute('data-type');
                if (type === 'track') {
                    await apiCall('/queue', 'POST', { trackId: id });
                } else if (type === 'collection') {
                    const res = await apiCall(`/lookup?id=${id}&entity=song`);
                    if (res.results) {
                        const tracks = res.results.filter(r => r.wrapperType === 'track');
                        for (const trk of tracks) {
                            await apiCall('/queue', 'POST', { trackId: trk.trackId });
                        }
                    }
                }
            }

            showToast(`Added ${selected.length} items to download queue`);
            bulkAddBtn.disabled = false;
            bulkAddBtn.innerHTML = '<i class="fas fa-plus"></i> Import';
            loadDownloadQueue();
        });
    }
}

function updateBulkCount() {
    const count = document.querySelectorAll('.tree-checkbox:checked').length;
    const badge = document.getElementById('treeSelectedCount');
    if (badge) badge.textContent = count;
}

let audioPlayer = null;
let isPlaying = false;

function initAudioPlayer() {
    audioPlayer = new Audio();
    const playBtn = document.getElementById('apPlayBtn');
    const apProgress = document.getElementById('apProgress');
    const apVolume = document.getElementById('apVolume');

    audioPlayer.addEventListener('timeupdate', () => {
        if (audioPlayer.duration) {
            const pct = (audioPlayer.currentTime / audioPlayer.duration) * 1000;
            apProgress.value = Math.min(pct, 1000);
            document.getElementById('apCurrentTime').textContent = formatTime(audioPlayer.currentTime);
        }
    });

    audioPlayer.addEventListener('loadedmetadata', () => {
        document.getElementById('apDuration').textContent = formatTime(audioPlayer.duration);
    });

    audioPlayer.addEventListener('play', () => {
        isPlaying = true;
        playBtn.innerHTML = '<i class="fas fa-pause"></i>';
    });

    audioPlayer.addEventListener('pause', () => {
        isPlaying = false;
        playBtn.innerHTML = '<i class="fas fa-play"></i>';
    });

    if (playBtn) {
        playBtn.addEventListener('click', () => {
            if (!audioPlayer.src) return;
            if (isPlaying) audioPlayer.pause();
            else audioPlayer.play();
        });
    }

    if (apProgress) {
        apProgress.addEventListener('input', () => {
            if (audioPlayer.duration) {
                audioPlayer.currentTime = (apProgress.value / 1000) * audioPlayer.duration;
            }
        });
    }

    if (apVolume) {
        apVolume.addEventListener('input', () => {
            audioPlayer.volume = apVolume.value / 100;
        });
    }
}

function formatTime(seconds) {
    if (!seconds || isNaN(seconds)) return '0:00';
    const m = Math.floor(seconds / 60);
    const s = Math.floor(seconds % 60);
    return `${m}:${s.toString().padStart(2, '0')}`;
}

function playTrack(url, title, artist, artwork) {
    if (!url || url === 'undefined') {
        showToast('No audio preview available', true);
        return;
    }
    audioPlayer.src = url;
    audioPlayer.play();
    document.getElementById('apTitle').textContent = title;
    document.getElementById('apArtist').textContent = artist;
    const apArtwork = document.getElementById('apArtwork');
    if (artwork) {
        apArtwork.style.backgroundImage = `url(${artwork})`;
        apArtwork.style.backgroundSize = 'cover';
        apArtwork.innerHTML = '';
    }
    showToast(`Playing: ${title}`);
}

function initActionButtons() {
    document.addEventListener('click', async (e) => {
        const addBtn = e.target.closest('[data-add-to-queue]');
        if (addBtn) {
            const trackId = addBtn.getAttribute('data-add-to-queue');
            try {
                const res = await apiCall('/queue', 'POST', { trackId });
                if (res.success) {
                    showToast('Added to download queue');
                    loadDownloadQueue();
                }
                else showToast('Error adding to download queue', true);
            } catch (err) {
                showToast('Error adding to download queue', true);
            }
        }
    });
}

function showToast(msg, isError = false) {
    const toast = document.getElementById('toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.style.background = isError ? '#b91c1c' : '#333';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function initMobileControls() {
    const menuToggle = document.getElementById('menuToggle');
    const leftSidebar = document.getElementById('leftSidebar');
    const propsToggle = document.getElementById('propsToggle');
    const rightPanel = document.getElementById('rightPanel');
    const layoutOverlay = document.getElementById('layoutOverlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            leftSidebar.classList.toggle('open-drawer');
            layoutOverlay.classList.toggle('active');
            rightPanel.classList.remove('open-drawer');
        });
    }

    if (propsToggle) {
        propsToggle.addEventListener('click', () => {
            rightPanel.classList.toggle('open-drawer');
            layoutOverlay.classList.toggle('active');
            leftSidebar.classList.remove('open-drawer');
        });
    }

    if (layoutOverlay) {
        layoutOverlay.addEventListener('click', () => {
            leftSidebar.classList.remove('open-drawer');
            rightPanel.classList.remove('open-drawer');
            layoutOverlay.classList.remove('active');
        });
    }
}
