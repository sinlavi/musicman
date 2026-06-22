<?php get_header(); ?>

<div id="browse-tab" class="tab-pane active-pane">
    <?php while ( have_posts() ) : the_post();
        $itunes_data = get_post_meta( get_the_ID(), '_itunes_data', true );
        $itunes_id = get_post_meta( get_the_ID(), '_itunes_id', true );
        $artist_name = isset($itunes_data['artistName']) ? $itunes_data['artistName'] : get_the_title();
    ?>
    <div class="pma-header">
        <h2><i class="fas fa-user"></i> <?php echo esc_html( $artist_name ); ?></h2>
        <button class="btn-sm btn-success" style="margin-left:auto;"><i class="fas fa-download"></i> Add All</button>
    </div>
    <div class="pma-doc-box" style="flex:1; overflow-y:auto;">
        <div class="profile-container">
            <div style="text-align:center; background:#e1e1e1; padding:20px; border:1px solid #ccc; display:flex; align-items:center; justify-content:center; width:100%; max-width:100px;">
                <i class="fas fa-user-tie fa-4x" style="color:#888;"></i>
            </div>
            <div class="profile-meta-grid">
                <div class="meta-block"><label>ID</label><div><?php echo esc_html($itunes_id); ?></div></div>
                <div class="meta-block"><label>Genre</label><div><?php echo esc_html($itunes_data['primaryGenreName'] ?? 'Unknown'); ?></div></div>
                <div class="meta-block"><label>Country</label><div><?php echo esc_html($itunes_data['country'] ?? '—'); ?></div></div>
            </div>
        </div>
    </div>
    <div class="pma-header"><h3><i class="fas fa-boxes"></i> Albums</h3></div>
    <div style="padding:0 10px 10px; overflow-y:auto; flex:1;">
        <p class="empty-msg">Select an album from the left sidebar to view details.</p>
    </div>
    <?php endwhile; ?>
</div>

<?php get_footer(); ?>
