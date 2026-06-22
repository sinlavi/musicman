<?php get_header(); ?>

<div id="browse-tab" class="tab-pane active-pane">
    <?php while ( have_posts() ) : the_post();
        $itunes_data = get_post_meta( get_the_ID(), '_itunes_data', true );
        $itunes_id = get_post_meta( get_the_ID(), '_itunes_id', true );
        $lyrics = get_post_meta( get_the_ID(), '_lyrics', true );
        $track_name = isset($itunes_data['trackName']) ? $itunes_data['trackName'] : get_the_title();
        $artist_name = isset($itunes_data['artistName']) ? $itunes_data['artistName'] : 'Unknown Artist';
        $artwork = isset($itunes_data['artworkUrl100']) ? $itunes_data['artworkUrl100'] : '';
    ?>
    <div class="pma-header">
        <h2><i class="fas fa-music"></i> <?php echo esc_html( $track_name ); ?></h2>
        <div style="display:flex; gap:4px; margin-left:auto;">
            <button class="btn-sm btn-play" data-itunes-id="<?php echo esc_attr($itunes_id); ?>"><i class="fas fa-play"></i> Play</button>
            <button class="btn-sm btn-success" data-add-to-queue="<?php echo esc_attr($itunes_id); ?>"><i class="fas fa-download"></i> Add</button>
        </div>
    </div>
    <div class="pma-doc-box" style="flex:1; overflow-y:auto;">
        <div class="profile-container">
            <div><img src="<?php echo esc_url($artwork); ?>" style="width:100px; border:1px solid #ccc; padding:2px; background:#fff; border-radius:2px;" alt="Artwork"></div>
            <div class="profile-meta-grid">
                <div class="meta-block"><label>ID</label><div><?php echo esc_html($itunes_id); ?></div></div>
                <div class="meta-block"><label>Artist</label><div><?php echo esc_html($artist_name); ?></div></div>
                <div class="meta-block"><label>Album</label><div><?php echo esc_html($itunes_data['collectionName'] ?? 'Single'); ?></div></div>
                <div class="meta-block"><label>Duration</label><div><?php
                    $ms = $itunes_data['trackTimeMillis'] ?? 0;
                    echo floor($ms/60000) . ':' . str_pad(floor(($ms%60000)/1000), 2, '0', STR_PAD_LEFT);
                ?></div></div>
                <div class="meta-block" style="grid-column:1/-1;"><label>Mirrors</label><div id="mirrors-container"></div></div>
                <div class="meta-block" style="grid-column:1/-1;"><label>Lyrics</label><div id="lyrics-container"></div></div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const mirrorsCont = document.getElementById('mirrors-container');
            const lyricsCont = document.getElementById('lyrics-container');

            try {
                const res = await apiCall('/mirrors?entityType=track&entityId=<?php echo $itunes_id; ?>');
                if (res.success && res.mirrors) {
                    let html = '<div class="mirror-box">';
                    for (const [plat, mirrors] of Object.entries(res.mirrors)) {
                        html += `<div class="mirror-item"><span class="mirror-platform">${plat}</span>`;
                        for (const [type, data] of Object.entries(mirrors)) {
                            html += `<a href="${data.url}" target="_blank" class="mirror-link">${type} ${data.quality || ''}</a>`;
                        }
                        html += '</div>';
                    }
                    html += '</div>';
                    mirrorsCont.innerHTML = html || 'No mirrors set.';
                }

                const lyRes = await apiCall('/lyrics?id=<?php echo $itunes_id; ?>');
                if (lyRes.success && lyRes.lyrics) {
                    const lyrics = lyRes.lyrics;
                    if (lyrics.syncedLyrics) {
                        lyricsCont.innerHTML = `<div class="lyrics-synced">${lyrics.syncedLyrics.split('\n').join('<br>')}</div>`;
                    } else if (lyrics.plainLyrics) {
                        lyricsCont.innerHTML = `<div class="lyrics-box">${lyrics.plainLyrics}</div>`;
                    }
                } else {
                    lyricsCont.innerHTML = 'Lyrics not found.';
                }
            } catch (e) {}
        });
    </script>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
