<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '600x600', $url) : '';
}

$album_name    = get_post_meta($post_id, 'collectionName', true);
$artist_name   = get_post_meta($post_id, 'artistName', true);
$collection_id = get_post_meta($post_id, 'collectionId', true);
$genre         = get_post_meta($post_id, 'primaryGenreName', true);
$release       = get_post_meta($post_id, 'releaseDate', true);
$artwork_raw   = get_post_meta($post_id, 'artworkUrl100', true);

echo '<article class="musicman-collection-container" style="max-width: 900px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden;">';

echo '<div class="album-hero" style="display: flex; gap: 40px; padding: 40px; background: linear-gradient(135deg, #f6f7f7 0%, #e9ecef 100%); align-items: center;">';
if ($artwork_raw) {
    echo '<img src="' . esc_url(mt_big_image($artwork_raw)) . '" style="width: 250px; height: 250px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">';
}
echo '<div>';
echo '<span style="display: inline-block; padding: 4px 12px; background: #2271b1; color: #fff; border-radius: 20px; font-size: 12px; font-weight: bold; text-transform: uppercase; margin-bottom: 15px;">Collection</span>';
echo '<h1 style="margin: 0; font-size: 38px;">' . esc_html($album_name ?: get_the_title()) . '</h1>';
echo '<h2 style="margin: 10px 0 0; font-size: 22px; font-weight: normal; color: #555;">by ' . esc_html($artist_name) . '</h2>';
echo '<p style="margin-top: 20px; color: #666; font-size: 14px;">' . esc_html($genre) . ' · ' . ($release ? date('Y', strtotime($release)) : '') . '</p>';
echo '</div></div>';

echo '<div class="album-content" style="padding: 40px;">';

// Tracks
$track_ids = get_posts([
    'post_type'      => 'musicman_track',
    'posts_per_page' => -1,
    'meta_key'       => '_collection_post_id',
    'meta_value'     => $post_id,
    'fields'         => 'ids',
    'post_status'    => 'publish',
    'orderby'        => 'meta_value_num',
    'meta_key'       => 'trackNumber',
    'order'          => 'ASC',
]);

if (!empty($track_ids)) {
    echo '<h3 style="margin-top: 0; margin-bottom: 20px;">Tracklist</h3>';
    echo '<div style="border: 1px solid #eee; border-radius: 8px; overflow: hidden;">';
    echo '<table style="width: 100%; border-collapse: collapse; background: #fff;">';
    echo '<thead style="background: #f8f9fa; border-bottom: 1px solid #eee;"><tr><th style="padding: 15px; text-align: left; width: 50px;">#</th><th style="padding: 15px; text-align: left;">Track</th><th style="padding: 15px; text-align: right; width: 100px;">Duration</th></tr></thead>';
    echo '<tbody>';
    foreach ($track_ids as $track_post_id) {
        $name = get_post_meta($track_post_id, 'trackName', true);
        $num  = get_post_meta($track_post_id, 'trackNumber', true);
        $ms   = get_post_meta($track_post_id, 'trackTimeMillis', true);
        $min  = floor($ms / 60000);
        $sec  = floor(($ms % 60000) / 1000);
        $dur  = $ms ? "$min:" . str_pad($sec, 2, '0', STR_PAD_LEFT) : '—';

        echo '<tr style="border-bottom: 1px solid #eee;" onmouseover="this.style.background=\'#fdfdfd\'" onmouseout="this.style.background=\'none\'">';
        echo '<td style="padding: 15px; color: #999;">' . esc_html($num) . '</td>';
        echo '<td style="padding: 15px;"><a href="' . get_permalink($track_post_id) . '" style="text-decoration: none; color: #2271b1; font-weight: bold;">' . esc_html($name) . '</a></td>';
        echo '<td style="padding: 15px; text-align: right; color: #666; font-family: monospace;">' . esc_html($dur) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table></div>';
}

echo '</div>'; // .album-content

echo '<div style="padding: 40px; border-top: 1px solid #eee; background: #fafafa;">';
comments_template();
echo '</div>';

echo '</article>';
get_footer();
