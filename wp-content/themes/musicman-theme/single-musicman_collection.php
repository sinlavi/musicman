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
    <div style="padding:0 10px 10px; overflow-y:auto; flex:1;">
        <p class="empty-msg">Select a track from the left sidebar to view details.</p>
    </div>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
