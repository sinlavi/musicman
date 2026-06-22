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
        'post_type'      => 'music_collection',
        'posts_per_page' => -1,
        'meta_key'       => '_artist_post_id',
        'meta_value'     => $post_id,
        'fields'         => 'ids',
        'post_status'    => 'publish',
    ]);
} else {
    $album_ids = array_filter($album_ids, function($id) {
        return get_post_status($id) === 'publish';
    });
}

// Tracks linked directly to this artist
$track_ids = get_posts([
    'post_type'      => 'music_track',
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

echo '<article class="music-artist-single">';
echo '<h1>' . esc_html($artist_name) . '</h1>';

// Artist image (400x400)
if ($image_raw) {
    echo '<p><img src="' . esc_url(mt_big_image($image_raw)) . '" alt="' . esc_attr($artist_name) . '" style="max-width:300px;"></p>';
}

if ($genre) echo '<p><strong>Genre:</strong> ' . esc_html($genre) . '</p>';
if ($artist_id) echo '<p><strong>iTunes ID:</strong> ' . esc_html($artist_id) . '</p>';

$artist_view = get_post_meta($post_id, 'artistViewUrl', true);
if ($artist_view) {
    echo '<p><a href="' . esc_url($artist_view) . '" target="_blank">View on iTunes</a></p>';
}

echo '<p><strong>Albums:</strong> ' . $album_count . ' | <strong>Tracks:</strong> ' . $track_count . ' | <strong>Comments:</strong> ' . $comments_count . '</p>';

// Albums
if (!empty($album_ids)) {
    echo '<h2>Albums</h2>';
    echo '<div class="albums-list" style="display:flex; flex-wrap:wrap;">';
    foreach ($album_ids as $album_post_id) {
        $album_name = get_post_meta($album_post_id, 'collectionName', true);
        $album_art  = get_post_meta($album_post_id, 'artworkUrl60', true);
        echo '<div style="margin:10px; text-align:center; width:140px;">';
        echo '<a href="' . get_permalink($album_post_id) . '">';
        if ($album_art) {
            echo '<img src="' . esc_url(mt_big_image($album_art)) . '" width="120" height="120" style="display:block; margin:0 auto;"><br>';
        }
        echo esc_html($album_name) . '</a>';
        echo '</div>';
    }
    echo '</div>';
}

// Tracks
if (!empty($track_ids)) {
    echo '<h2>Tracks</h2>';
    echo '<ul class="tracks-list">';
    foreach ($track_ids as $track_post_id) {
        $track_name  = get_post_meta($track_post_id, 'trackName', true);
        $track_art   = get_post_meta($track_post_id, 'artworkUrl60', true);
        $album_name  = get_post_meta($track_post_id, 'collectionName', true);
        $duration_ms = get_post_meta($track_post_id, 'trackTimeMillis', true);
        $min = floor($duration_ms / 60000);
        $sec = floor(($duration_ms % 60000) / 1000);
        $dur = $duration_ms ? "$min:" . str_pad($sec, 2, '0', STR_PAD_LEFT) : '';
        echo '<li style="margin:10px 0; display:flex; align-items:center;">';
        if ($track_art) echo '<img src="' . esc_url(mt_big_image($track_art)) . '" width="60" height="60" style="margin-right:10px;">';
        echo '<span><a href="' . get_permalink($track_post_id) . '">' . esc_html($track_name) . '</a>';
        if ($album_name) echo ' – <small>' . esc_html($album_name) . '</small>';
        if ($dur) echo ' <small>(' . esc_html($dur) . ')</small>';
        echo '</span>';
        echo '</li>';
    }
    echo '</ul>';
}

comments_template();
echo '</article>';
get_footer();