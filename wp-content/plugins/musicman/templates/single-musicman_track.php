<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '600x600', $url) : '';
}

$track_name  = get_post_meta($post_id, 'trackName', true);
$artist_name = get_post_meta($post_id, 'artistName', true);
$album_name  = get_post_meta($post_id, 'collectionName', true);
$artwork_raw = get_post_meta($post_id, 'artworkUrl100', true);
$duration_ms = get_post_meta($post_id, 'trackTimeMillis', true);
$genre       = get_post_meta($post_id, 'primaryGenreName', true);
$track_id    = get_post_meta($post_id, 'trackId', true);
$release     = get_post_meta($post_id, 'releaseDate', true);

echo '<article class="musicman-track-container" style="max-width: 900px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden;">';

// Header with background artwork
echo '<div class="track-header" style="position: relative; height: 350px; background: #222; overflow: hidden; display: flex; align-items: flex-end;">';
if ($artwork_raw) {
    echo '<img src="' . esc_url(mt_big_image($artwork_raw)) . '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.4; filter: blur(20px);">';
}
echo '<div style="position: relative; z-index: 10; padding: 40px; display: flex; align-items: center; gap: 30px; width: 100%; background: linear-gradient(transparent, rgba(0,0,0,0.8));">';
if ($artwork_raw) {
    echo '<img src="' . esc_url(mt_big_image($artwork_raw)) . '" style="width: 200px; height: 200px; border-radius: 8px; box-shadow: 0 8px 30px rgba(0,0,0,0.5);">';
}
echo '<div style="color: #fff;">';
echo '<h1 style="margin: 0; font-size: 36px;">' . esc_html($track_name ?: get_the_title()) . '</h1>';
echo '<p style="margin: 5px 0 0; font-size: 20px; opacity: 0.9;">' . esc_html($artist_name) . '</p>';
echo '<p style="margin: 5px 0 0; font-size: 16px; opacity: 0.7;">' . esc_html($album_name) . '</p>';
echo '</div>';
echo '</div></div>';

echo '<div class="track-content" style="padding: 40px; display: grid; grid-template-columns: 2fr 1fr; gap: 40px;">';

echo '<div>';
$lyrics_plain = get_post_meta($post_id, 'lyricsPlain', true);
if ($lyrics_plain) {
    echo '<h3 style="margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 10px;">Lyrics</h3>';
    echo '<div style="white-space: pre-wrap; font-size: 16px; line-height: 1.8; color: #444;">' . esc_html($lyrics_plain) . '</div>';
} else {
    echo '<p style="color: #999; font-style: italic;">No lyrics available for this track.</p>';
}
echo '</div>';

echo '<aside>';
echo '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">';
echo '<h4 style="margin-top: 0;">Track Details</h4>';
echo '<ul style="list-style: none; padding: 0; font-size: 14px;">';
if ($genre) echo '<li style="margin-bottom: 10px;"><strong>Genre:</strong> ' . esc_html($genre) . '</li>';
if ($track_id) echo '<li style="margin-bottom: 10px;"><strong>iTunes ID:</strong> ' . esc_html($track_id) . '</li>';
if ($duration_ms) {
    $min = floor($duration_ms / 60000);
    $sec = floor(($duration_ms % 60000) / 1000);
    echo '<li style="margin-bottom: 10px;"><strong>Duration:</strong> ' . $min . ':' . str_pad($sec, 2, '0', STR_PAD_LEFT) . '</li>';
}
if ($release) echo '<li style="margin-bottom: 10px;"><strong>Released:</strong> ' . date('Y-m-d', strtotime($release)) . '</li>';
echo '</ul>';
echo '<button class="button button-primary" style="width: 100%; padding: 10px;" data-add-to-queue="' . $track_id . '"><i class="fas fa-download"></i> Add to Download Queue</button>';
echo '</div>';
echo '</aside>';

echo '</div>'; // .track-content

echo '<div style="padding: 40px; border-top: 1px solid #eee; background: #fafafa;">';
comments_template();
echo '</div>';

echo '</article>';
get_footer();
