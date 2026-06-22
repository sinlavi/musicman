<?php get_header(); ?>

<div id="browse-tab" class="tab-pane active-pane">
    <?php while ( have_posts() ) : the_post();
        $itunes_data = get_post_meta( get_the_ID(), '_itunes_data', true );
        $itunes_id = get_post_meta( get_the_ID(), '_itunes_id', true );
        $collection_name = isset($itunes_data['collectionName']) ? $itunes_data['collectionName'] : get_the_title();
        $artwork = isset($itunes_data['artworkUrl100']) ? $itunes_data['artworkUrl100'] : '';
    ?>
    <div class="pma-header">
        <h2><i class="fas fa-dot-circle"></i> <?php echo esc_html( $collection_name ); ?></h2>
        <button class="btn-sm btn-success" style="margin-left:auto;"><i class="fas fa-download"></i> Add All</button>
    </div>
    <div class="pma-doc-box" style="flex:1; overflow-y:auto;">
        <div class="profile-container">
            <div><img src="<?php echo esc_url($artwork); ?>" style="width:100px; border:1px solid #ccc; padding:2px; background:#fff; border-radius:2px;" alt="Artwork"></div>
            <div class="profile-meta-grid">
                <div class="meta-block"><label>ID</label><div><?php echo esc_html($itunes_id); ?></div></div>
                <div class="meta-block"><label>Artist</label><div><?php echo esc_html($itunes_data['artistName'] ?? 'Unknown'); ?></div></div>
                <div class="meta-block"><label>Tracks</label><div><?php echo esc_html($itunes_data['trackCount'] ?? 0); ?></div></div>
            </div>
        </div>
    </div>
    <div class="pma-header"><h3><i class="fas fa-list-ol"></i> Tracks</h3></div>
    <div id="album-tracks-list" style="padding:10px;">
        <div class="empty-msg"><i class="fas fa-spinner fa-pulse"></i> Loading tracks...</div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const list = document.getElementById('album-tracks-list');
            try {
                const res = await apiCall('/lookup?id=<?php echo $itunes_id; ?>&entity=song');
                if (res.results) {
                    const tracks = res.results.filter(r => r.wrapperType === 'track' || r.kind === 'song');
                    if (tracks.length === 0) {
                        list.innerHTML = '<p class="empty-msg">No tracks found.</p>';
                        return;
                    }
                    list.innerHTML = `
                        <table class="data-table">
                            <thead><tr><th>#</th><th>Track</th><th>Time</th><th>Action</th></tr></thead>
                            <tbody>
                                ${tracks.map(trk => `
                                    <tr>
                                        <td>${trk.trackNumber}</td>
                                        <td><a href="${trk.wp_permalink || '#'}">${trk.trackName}</a></td>
                                        <td>${Math.floor(trk.trackTimeMillis/60000)}:${str_pad(Math.floor((trk.trackTimeMillis%60000)/1000), 2, '0', 'STR_PAD_LEFT')}</td>
                                        <td>
                                            <button class="btn-sm btn-play" data-itunes-id="${trk.trackId}"><i class="fas fa-play"></i></button>
                                            <button class="btn-sm btn-success" data-add-to-queue="${trk.trackId}"><i class="fas fa-download"></i></button>
                                        </td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    `;
                }
            } catch (e) {
                list.innerHTML = '<p class="empty-msg">Error loading tracks.</p>';
            }
        });
        function str_pad(n, width, z) {
          z = z || '0';
          n = n + '';
          return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
        }
    </script>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
