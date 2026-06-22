<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '400x400', $url) : '';
}

$artist_name = get_post_meta($post_id, 'artistName', true);
$artist_id   = get_post_meta($post_id, 'artistId', true);
$genre       = get_post_meta($post_id, 'primaryGenreName', true);
$image_raw   = get_post_meta($post_id, 'artistArtworkUrl', true);
$comments_count = get_comments_number($post_id);

// Albums – try stored _album_ids first, fallback to direct query
$album_ids = get_post_meta($post_id, '_album_ids', true);
if (empty($album_ids) || !is_array($album_ids)) {
    $album_ids = get_posts([
        'post_type'      => 'musicman_collection',
        'posts_per_page' => -1,
        'meta_key'       => '_artist_post_id',
        'meta_value'     => $post_id,
        'fields'         => 'ids',
        'post_status'    => 'publish',
    ]);
}

// Tracks linked directly to this artist
$track_ids = get_posts([
    'post_type'      => 'musicman_track',
    'posts_per_page' => -1,
    'meta_key'       => '_artist_post_id',
    'meta_value'     => $post_id,
    'fields'         => 'ids',
    'post_status'    => 'publish',
    'orderby'        => 'title',
    'order'          => 'ASC',
]);

$album_count = count($album_ids);
$track_count = count($track_ids);

echo '<article class="music-artist-single" style="padding: 20px; max-width: 1000px; margin: 0 auto;">';
echo '<h1>' . esc_html($artist_name) . '</h1>';

if ($image_raw) {
    echo '<p><img src="' . esc_url(mt_big_image($image_raw)) . '" alt="' . esc_attr($artist_name) . '" style="max-width:300px; border-radius: 8px; border: 1px solid #ccc;"></p>';
}

echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 20px;">';
if ($genre) echo '<p><strong>Genre:</strong> ' . esc_html($genre) . '</p>';
if ($artist_id) echo '<p><strong>iTunes ID:</strong> ' . esc_html($artist_id) . '</p>';
echo '</div>';

// Albums
if (!empty($album_ids)) {
    echo '<h2 style="margin-top: 30px;">Albums</h2>';
    echo '<div class="albums-list" style="display:flex; flex-wrap:wrap; gap: 20px;">';
    foreach ($album_ids as $album_post_id) {
        $album_name = get_post_meta($album_post_id, 'collectionName', true);
        $album_art  = get_post_meta($album_post_id, 'artworkUrl100', true);
        echo '<div style="text-align:center; width:150px;">';
        echo '<a href="' . get_permalink($album_post_id) . '" style="text-decoration: none; color: #222;">';
        if ($album_art) {
            echo '<img src="' . esc_url(mt_big_image($album_art)) . '" width="150" height="150" style="display:block; border-radius: 4px; border: 1px solid #eee; margin-bottom: 5px;">';
        }
        echo '<strong style="display: block; font-size: 13px;">' . esc_html($album_name) . '</strong></a>';
        echo '</div>';
    }
    echo '</div>';
}

// Tracks
if (!empty($track_ids)) {
    echo '<h2 style="margin-top: 30px;">All Tracks</h2>';
    echo '<ul class="tracks-list" style="list-style: none; padding: 0;">';
    foreach ($track_ids as $track_post_id) {
        $track_name  = get_post_meta($track_post_id, 'trackName', true);
        $track_art   = get_post_meta($track_post_id, 'artworkUrl60', true);
        $album_name  = get_post_meta($track_post_id, 'collectionName', true);
        $duration_ms = get_post_meta($track_post_id, 'trackTimeMillis', true);
        $min = floor($duration_ms / 60000);
        $sec = floor(($duration_ms % 60000) / 1000);
        $dur = $duration_ms ? "$min:" . str_pad($sec, 2, '0', STR_PAD_LEFT) : '';
        echo '<li style="padding: 10px; border-bottom: 1px solid #eee; display:flex; align-items:center;">';
        if ($track_art) echo '<img src="' . esc_url($track_art) . '" width="40" height="40" style="margin-right:15px; border-radius: 2px;">';
        echo '<span><a href="' . get_permalink($track_post_id) . '" style="text-decoration: none; font-weight: bold; color: #2271b1;">' . esc_html($track_name) . '</a>';
        if ($album_name) echo ' <span style="color: #666; font-size: 11px;">in ' . esc_html($album_name) . '</span>';
        if ($dur) echo ' <small style="color: #999; margin-left: 10px;">' . esc_html($dur) . '</small>';
        echo '</span>';
        echo '</li>';
    }
    echo '</ul>';
}

comments_template();
echo '</article>';
get_footer();
