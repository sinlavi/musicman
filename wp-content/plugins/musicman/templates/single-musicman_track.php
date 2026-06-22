<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '400x400', $url) : '';
}

$track_name  = get_post_meta($post_id, 'trackName', true);
$artist_name = get_post_meta($post_id, 'artistName', true);
$album_name  = get_post_meta($post_id, 'collectionName', true);
$artwork_raw = get_post_meta($post_id, 'artworkUrl100', true);
$duration_ms = get_post_meta($post_id, 'trackTimeMillis', true);
$genre       = get_post_meta($post_id, 'primaryGenreName', true);
$track_id    = get_post_meta($post_id, 'trackId', true);

echo '<article class="music-track-single" style="padding: 20px; max-width: 800px; margin: 0 auto;">';
echo '<h1>' . esc_html($track_name) . '</h1>';
echo '<p style="color: #666; font-size: 1.2em;">' . esc_html($artist_name) . ' – ' . esc_html($album_name) . '</p>';

if ($artwork_raw) {
    echo '<p><img src="' . esc_url(mt_big_image($artwork_raw)) . '" alt="Artwork" style="width: 300px; border-radius: 8px; border: 1px solid #ccc;"></p>';
}

echo '<div style="background: #f9f9f9; padding: 15px; border-radius: 4px; margin-top: 20px;">';
if ($genre) echo '<p><strong>Genre:</strong> ' . esc_html($genre) . '</p>';
if ($track_id) echo '<p><strong>iTunes ID:</strong> ' . esc_html($track_id) . '</p>';
if ($duration_ms) {
    $min = floor($duration_ms / 60000);
    $sec = floor(($duration_ms % 60000) / 1000);
    echo '<p><strong>Duration:</strong> ' . $min . ':' . str_pad($sec, 2, '0', STR_PAD_LEFT) . '</p>';
}
echo '</div>';

$lyrics_plain = get_post_meta($post_id, 'lyricsPlain', true);
if ($lyrics_plain) {
    echo '<h2 style="margin-top: 30px;">Lyrics</h2>';
    echo '<div style="white-space: pre-wrap; font-family: monospace; background: #fff; border: 1px solid #eee; padding: 15px;">' . esc_html($lyrics_plain) . '</div>';
}

comments_template();
echo '</article>';
get_footer();
