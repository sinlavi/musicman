/**
 * MusicMan Theme App JS
 */

const API_ROOT = musicmanSettings.root + 'musicman/v1';

document.addEventListener('DOMContentLoaded', () => {
    initSearch();
    if (document.getElementById('queueBody')) {
        loadQueue();
    }
    initAudioPlayer();
    initMobileControls();
    initActionButtons();
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
        const data = await apiCall(`/search?term=${encodeURIComponent(term)}&entity=${entity}`);
        renderTree(data.results);
    } catch (e) {
        container.innerHTML = '<div class="empty-msg">Error performing search.</div>';
    }
}

function renderTree(results) {
    const container = document.getElementById('treeContainer');
    if (!results || results.length === 0) {
        container.innerHTML = '<div class="empty-msg">No results found.</div>';
        return;
    }

    const ul = document.createElement('ul');
    ul.className = 'tree-node';

    results.forEach(item => {
        const li = document.createElement('li');
        const type = item.wrapperType || (item.kind === 'song' ? 'track' : '');
        const id = item.trackId || item.collectionId || item.artistId;
        const name = item.trackName || item.collectionName || item.artistName;

        li.innerHTML = `
            <div class="tree-toggle" data-type="${type}" data-id="${id}">
                <i class="fas ${getIcon(type)}"></i>
                <span class="node-label">${name}</span>
            </div>
        `;

        li.querySelector('.tree-toggle').addEventListener('click', () => {
            if (item.wp_permalink) {
                window.location.href = item.wp_permalink;
            } else {
                console.log('Selected:', type, id);
            }
        });

        ul.appendChild(li);
    });

    container.innerHTML = '';
    container.appendChild(ul);
    document.getElementById('treeTotalCount').textContent = results.length + ' items';
}

function getIcon(type) {
    switch(type) {
        case 'track': return 'fa-music';
        case 'collection': return 'fa-dot-circle';
        case 'artist': return 'fa-user';
        default: return 'fa-file';
    }
}

async function loadQueue() {
    const tbody = document.getElementById('queueBody');
    try {
        const data = await apiCall('/queue');
        if (data.items && data.items.length > 0) {
            renderQueueTable(data.items);
        } else {
            tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">Queue is empty.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-msg">Error loading queue.</td></tr>';
    }
}

function renderQueueTable(items) {
    const tbody = document.getElementById('queueBody');
    tbody.innerHTML = items.map(item => `
        <tr>
            <td>${item.id}</td>
            <td>${item.track_id}</td>
            <td>${item.quality}</td>
            <td><span class="status-badge status-${item.status}">${item.status}</span></td>
            <td>
                <button class="btn-sm btn-danger" onclick="deleteQueueItem(${item.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');

    document.getElementById('queueStats').textContent = `${items.length} items`;
}

window.deleteQueueItem = async (id) => {
    if (confirm('Delete this item?')) {
        await apiCall(`/queue?id=${id}`, 'DELETE');
        loadQueue();
    }
};

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

        const playBtn = e.target.closest('.btn-play[data-itunes-id]');
        if (playBtn) {
            const itunesId = playBtn.getAttribute('data-itunes-id');
            // We need the audio URL. For simplicity, let's fetch mirrors or lookup
            const res = await apiCall(`/lookup?id=${itunesId}`);
            if (res.results && res.results[0]) {
                const item = res.results[0];
                // In a real scenario, we'd get the mirror URL
                // For now, use previewUrl as fallback if no mirror
                const audioUrl = item.previewUrl;
                playTrack(audioUrl, item.trackName, item.artistName, item.artworkUrl100);
            }
        }
    });
}

function showToast(msg, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = msg;
    toast.style.background = isError ? '#b91c1c' : '#333';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
}

function initMobileControls() {
    const menuToggle = document.getElementById('menuToggle');
    const leftSidebar = document.getElementById('leftSidebar');
    const layoutOverlay = document.getElementById('layoutOverlay');

    if (menuToggle) {
        menuToggle.addEventListener('click', () => {
            leftSidebar.classList.toggle('open-drawer');
            layoutOverlay.classList.toggle('active');
        });
    }

    if (layoutOverlay) {
        layoutOverlay.addEventListener('click', () => {
            leftSidebar.classList.remove('open-drawer');
            layoutOverlay.classList.remove('active');
        });
    }
}
