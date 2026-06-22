<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '400x400', $url) : '';
}

$album_name    = get_post_meta($post_id, 'collectionName', true);
$artist_name   = get_post_meta($post_id, 'artistName', true);
$collection_id = get_post_meta($post_id, 'collectionId', true);
$genre         = get_post_meta($post_id, 'primaryGenreName', true);
$release       = get_post_meta($post_id, 'releaseDate', true);
$artwork_raw   = get_post_meta($post_id, 'artworkUrl100', true);

// --- Tracks: first try the stored _track_ids ---
$track_ids = get_post_meta($post_id, '_track_ids', true);
if (empty($track_ids) || !is_array($track_ids)) {
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
}

echo '<article class="music-collection-single" style="padding: 20px; max-width: 900px; margin: 0 auto;">';
echo '<h1>' . esc_html($album_name) . '</h1>';
echo '<p style="font-size: 1.2em; color: #666;">' . esc_html($artist_name) . '</p>';

if ($artwork_raw) {
    echo '<p><img src="' . esc_url(mt_big_image($artwork_raw)) . '" alt="Artwork" style="width: 300px; border-radius: 8px; border: 1px solid #ccc;"></p>';
}

echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';
if ($genre) echo '<div><strong>Genre:</strong> ' . esc_html($genre) . '</div>';
if ($collection_id) echo '<div><strong>iTunes ID:</strong> ' . esc_html($collection_id) . '</div>';
if ($release) echo '<div><strong>Release:</strong> ' . date('F j, Y', strtotime($release)) . '</div>';
echo '</div>';

if (!empty($track_ids)) {
    echo '<h2 style="margin-top: 30px;">Tracklist</h2>';
    echo '<table class="wp-list-table widefat fixed striped" style="border: 1px solid #eee;">';
    echo '<thead><tr><th style="width: 40px;">#</th><th>Track Name</th><th style="width: 80px;">Time</th></tr></thead>';
    echo '<tbody>';
    foreach ($track_ids as $track_post_id) {
        $track_name  = get_post_meta($track_post_id, 'trackName', true);
        $track_num   = get_post_meta($track_post_id, 'trackNumber', true);
        $duration_ms = get_post_meta($track_post_id, 'trackTimeMillis', true);
        $min = floor($duration_ms / 60000);
        $sec = floor(($duration_ms % 60000) / 1000);
        $dur = $duration_ms ? "$min:" . str_pad($sec, 2, '0', STR_PAD_LEFT) : '—';

        echo '<tr>';
        echo '<td>' . esc_html($track_num) . '</td>';
        echo '<td><a href="' . get_permalink($track_post_id) . '" style="text-decoration: none; font-weight: bold; color: #2271b1;">' . esc_html($track_name) . '</a></td>';
        echo '<td>' . esc_html($dur) . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

comments_template();
echo '</article>';
get_footer();
