<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '400x400', $url) : '';
}

$album_name    = get_post_meta($post_id, 'collectionName', true);
$artist_name   = get_post_meta($post_id, 'artistName', true);
$artist_post   = get_post_meta($post_id, '_artist_post_id', true);
$collection_id = get_post_meta($post_id, 'collectionId', true);
$genre         = get_post_meta($post_id, 'primaryGenreName', true);
$release       = get_post_meta($post_id, 'releaseDate', true);
$artwork_raw   = get_post_meta($post_id, 'artworkUrl100', true);
$explicit      = get_post_meta($post_id, 'collectionExplicitness', true);
$comments_count = get_comments_number($post_id);

// --- Tracks: first try the stored _track_ids ---
$track_ids = get_post_meta($post_id, '_track_ids', true);
if (empty($track_ids) || !is_array($track_ids)) {
    // Fallback: query all tracks that belong to this collection
    $track_ids = get_posts([
        'post_type'      => 'music_track',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'fields'         => 'ids',
        'meta_query'     => [
            [
                'key'   => '_collection_post_id',
                'value' => $post_id,
            ],
        ],
        'orderby'        => 'meta_value_num',
        'meta_key'       => 'trackNumber',   // this works because meta_key is outside meta_query
        'order'          => 'ASC',
    ]);
}

echo '<article class="music-collection-single">';
echo '<h1>' . esc_html($album_name) . '</h1>';

if ($artwork_raw) {
    echo '<p><img src="' . esc_url(mt_big_image($artwork_raw)) . '" alt="' . esc_attr($album_name) . '" style="max-width:300px;"></p>';
}

if ($artist_name) {
    echo '<p><strong>Artist:</strong> ';
    if ($artist_post && get_post_status($artist_post)) {
        echo '<a href="' . get_permalink($artist_post) . '">' . esc_html($artist_name) . '</a>';
    } else {
        echo esc_html($artist_name);
    }
    echo '</p>';
}
if ($genre)     echo '<p><strong>Genre:</strong> ' . esc_html($genre) . '</p>';
if ($release)   echo '<p><strong>Released:</strong> ' . esc_html($release) . '</p>';
if ($explicit)  echo '<p><strong>Explicit:</strong> ' . esc_html($explicit) . '</p>';
if ($collection_id) echo '<p><strong>iTunes ID:</strong> ' . esc_html($collection_id) . '</p>';

$collection_view = get_post_meta($post_id, 'collectionViewUrl', true);
if ($collection_view) {
    echo '<p><a href="' . esc_url($collection_view) . '" target="_blank">View on iTunes</a></p>';
}

echo '<p><strong>Tracks:</strong> ' . count($track_ids) . ' | <strong>Comments:</strong> ' . $comments_count . '</p>';

if (!empty($track_ids)) {
    echo '<h2>Tracklist</h2>';
    echo '<ol class="collection-tracks">';
    foreach ($track_ids as $track_post_id) {
        $track_name   = get_post_meta($track_post_id, 'trackName', true);
        $track_art    = get_post_meta($track_post_id, 'artworkUrl60', true);
        $track_number = get_post_meta($track_post_id, 'trackNumber', true);
        $duration_ms  = get_post_meta($track_post_id, 'trackTimeMillis', true);
        $min = floor($duration_ms / 60000);
        $sec = floor(($duration_ms % 60000) / 1000);
        $dur = $duration_ms ? "$min:" . str_pad($sec, 2, '0', STR_PAD_LEFT) : '';
        echo '<li style="margin:10px 0; display:flex; align-items:center;">';
        if ($track_art) echo '<img src="' . esc_url(mt_big_image($track_art)) . '" width="60" height="60" style="margin-right:10px;">';
        echo '<span><a href="' . get_permalink($track_post_id) . '">' . esc_html($track_name) . '</a>';
        if ($track_number) echo ' (' . esc_html($track_number) . ')';
        if ($dur) echo ' <small>[' . esc_html($dur) . ']</small>';
        echo '</span>';
        echo '</li>';
    }
    echo '</ol>';
}

comments_template();
echo '</article>';
get_footer();