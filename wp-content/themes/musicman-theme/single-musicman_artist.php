<?php get_header(); ?>

<div id="browse-tab" class="tab-pane active-pane">
    <?php while ( have_posts() ) : the_post();
        $id = get_the_ID();
        $itunes_id = get_post_meta( $id, 'artistId', true );
        $artist_name = get_post_meta($id, 'artistName', true) ?: get_the_title();
        $views = (int)get_post_meta($id, '_mt_views', true);
        $genre = get_post_meta($id, 'primaryGenreName', true);
        $country = get_post_meta($id, 'country', true);
        $link = get_post_meta($id, 'artistViewUrl', true);
    ?>
    <div class="pma-header">
        <h2><i class="fas fa-user"></i> <?php echo esc_html( $artist_name ); ?></h2>
        <div style="margin-left:auto; display:flex; align-items:center; gap:10px;">
            <span class="stat-item"><i class="fas fa-eye"></i> <?php echo $views; ?></span>
            <button class="btn-sm btn-success" id="crawl-artist-btn"><i class="fas fa-sync"></i> Crawl Albums</button>
        </div>
    </div>
    <div class="pma-doc-box" style="flex:1; overflow-y:auto;">
        <div class="profile-container">
            <div style="text-align:center;">
                <?php if (has_post_thumbnail()): the_post_thumbnail('medium', ['style' => 'width:120px; height:auto; border-radius:4px;']); else: ?>
                    <div style="background:#e1e1e1; padding:20px; border:1px solid #ccc; border-radius:4px;"><i class="fas fa-user-tie fa-4x" style="color:#888;"></i></div>
                <?php endif; ?>
            </div>
            <div class="profile-meta-grid">
                <div class="meta-block"><label>iTunes ID</label><div><?php echo esc_html($itunes_id); ?></div></div>
                <div class="meta-block"><label>Genre</label><div><?php echo esc_html($genre ?? 'Unknown'); ?></div></div>
                <div class="meta-block"><label>Country</label><div><?php echo esc_html($country ?? '—'); ?></div></div>
                <div class="meta-block"><label>Link</label><div><a href="<?php echo esc_url($link ?? '#'); ?>" target="_blank">View on iTunes</a></div></div>
            </div>
        </div>
    </div>
    <div class="pma-header"><h3><i class="fas fa-boxes"></i> Albums</h3></div>
    <div id="artist-albums-list" style="padding:10px;">
        <div class="empty-msg"><i class="fas fa-spinner fa-pulse"></i> Loading albums...</div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', async () => {
            const list = document.getElementById('artist-albums-list');
            const crawlBtn = document.getElementById('crawl-artist-btn');

            const loadAlbums = async () => {
                try {
                    const res = await apiCall('/lookup?id=<?php echo $itunes_id; ?>&entity=album');
                    if (res.results) {
                        const albums = res.results.filter(r => r.wrapperType === 'collection');
                        if (albums.length === 0) {
                            list.innerHTML = '<p class="empty-msg">No albums found.</p>';
                            return;
                        }
                        list.innerHTML = `
                            <table class="data-table">
                                <thead><tr><th>Album</th><th>Year</th><th>Tracks</th><th>Action</th></tr></thead>
                                <tbody>
                                    ${albums.map(alb => `
                                        <tr>
                                            <td>
                                                <div style="display:flex; align-items:center; gap:8px;">
                                                    <img src="${alb.artworkUrl60}" style="width:30px; border-radius:2px;">
                                                    <a href="${alb.wp_permalink || '#'}">${alb.collectionName}</a>
                                                </div>
                                            </td>
                                            <td>${alb.releaseDate ? new Date(alb.releaseDate).getFullYear() : 'N/A'}</td>
                                            <td>${alb.trackCount}</td>
                                            <td><button class="btn-sm btn-success" data-add-to-queue-album="${alb.collectionId}"><i class="fas fa-download"></i> Add All</button></td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>
                        `;
                    }
                } catch (e) {
                    list.innerHTML = '<p class="empty-msg">Error loading albums.</p>';
                }
            };

            crawlBtn.addEventListener('click', async () => {
                crawlBtn.disabled = true;
                crawlBtn.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Crawling...';
                await loadAlbums();
                crawlBtn.disabled = false;
                crawlBtn.innerHTML = '<i class="fas fa-sync"></i> Crawl Albums';
                showToast('Artist crawl complete');
            });

            loadAlbums();
        });
    </script>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
