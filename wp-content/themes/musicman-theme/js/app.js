/**
 * MusicMan Theme App JS - Full Implementation
 */

const API_ROOT = musicmanSettings.root + 'musicman/v1';

document.addEventListener('DOMContentLoaded', () => {
    initSearch();
    if (document.getElementById('queueBody')) {
        loadQueue();
        setInterval(loadQueue, 5000);
    }
    initAudioPlayer();
    initMobileControls();
    initActionButtons();
    initBulkControls();

    // If on a single page, load metadata into right panel
    if (document.body.classList.contains('single')) {
        // We can extract ID from body classes or global var if needed
    }
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

function updateRightPanel(item) {
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
                <i class="fas fa-download"></i> Add to Queue
            </button>
        `;
    }

    html += `</div>`;
    container.innerHTML = html;
    document.getElementById('rightPanel').classList.add('open-drawer');
}

async function loadQueue() {
    const tbody = document.getElementById('queueBody');
    if (!tbody) return;
    const filter = document.getElementById('queueStatusFilter').value;
    try {
        const data = await apiCall(`/queue?status=${filter}`);
        if (data.items) {
            renderQueueTable(data.items);
            updateQueueStats(data.items);
        }
    } catch (e) {}
}

function renderQueueTable(items) {
    const tbody = document.getElementById('queueBody');
    if (items.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">No items in queue.</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(item => {
        const name = item.track_data ? item.track_data.trackName : item.track_id;
        return `
        <tr>
            <td>${item.id}</td>
            <td><strong>${name}</strong></td>
            <td>${item.quality}</td>
            <td><span class="status-badge status-${item.status}">${item.status}</span></td>
            <td>
                <button class="btn-sm btn-danger" onclick="deleteQueueItem(${item.id})"><i class="fas fa-trash"></i></button>
                ${item.status === 'failed' ? `<button class="btn-sm btn-warning" onclick="updateQueueStatus(${item.id}, 'pending')"><i class="fas fa-redo"></i></button>` : ''}
            </td>
        </tr>
    `}).join('');
}

function updateQueueStats(items) {
    const stats = { pending: 0, downloading: 0, completed: 0 };
    items.forEach(i => { if (stats[i.status] !== undefined) stats[i.status]++; });
    document.getElementById('statPending').textContent = stats.pending;
    document.getElementById('statDownloading').textContent = stats.downloading;
    document.getElementById('statCompleted').textContent = stats.completed;
    document.getElementById('queueStats').textContent = `${items.length} total`;
}

window.deleteQueueItem = async (id) => {
    await apiCall(`/queue?id=${id}`, 'DELETE');
    loadQueue();
};

window.updateQueueStatus = async (id, status) => {
    await apiCall(`/queue?id=${id}&status=${status}`, 'PUT');
    loadQueue();
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
            const selected = document.querySelectorAll('.tree-checkbox:checked');
            if (selected.length === 0) return;

            bulkAddBtn.disabled = true;
            bulkAddBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i>';

            for (const cb of selected) {
                const id = cb.getAttribute('data-id');
                const type = cb.getAttribute('data-type');
                if (type === 'track') {
                    await apiCall('/queue', 'POST', { trackId: id });
                } else if (type === 'collection') {
                    // Fetch tracks and add them
                    const res = await apiCall(`/lookup?id=${id}&entity=song`);
                    if (res.results) {
                        const tracks = res.results.filter(r => r.wrapperType === 'track');
                        for (const trk of tracks) {
                            await apiCall('/queue', 'POST', { trackId: trk.trackId });
                        }
                    }
                }
            }

            showToast(`Added ${selected.length} items to queue`);
            bulkAddBtn.disabled = false;
            bulkAddBtn.innerHTML = '<i class="fas fa-plus"></i> Import';
        });
    }
}

function updateBulkCount() {
    const count = document.querySelectorAll('.tree-checkbox:checked').length;
    document.getElementById('treeSelectedCount').textContent = count;
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
                if (res.success) showToast('Added to queue');
                else showToast('Error adding to queue', true);
            } catch (err) {
                showToast('Error adding to queue', true);
            }
        }

        const addAlbumBtn = e.target.closest('[data-add-to-queue-album]');
        if (addAlbumBtn) {
            const id = addAlbumBtn.getAttribute('data-add-to-queue-album');
            const res = await apiCall(`/lookup?id=${id}&entity=song`);
            if (res.results) {
                const tracks = res.results.filter(r => r.wrapperType === 'track');
                for (const trk of tracks) {
                    await apiCall('/queue', 'POST', { trackId: trk.trackId });
                }
                showToast(`Added ${tracks.length} tracks to queue`);
            }
        }

        const playBtn = e.target.closest('.btn-play[data-itunes-id]');
        if (playBtn) {
            const itunesId = playBtn.getAttribute('data-itunes-id');
            const res = await apiCall(`/lookup?id=${itunesId}`);
            if (res.results && res.results[0]) {
                const item = res.results[0];
                const audioUrl = item.previewUrl;
                playTrack(audioUrl, item.trackName, item.artistName, item.artworkUrl100);
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
