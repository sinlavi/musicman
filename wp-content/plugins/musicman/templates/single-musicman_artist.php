<?php
get_header();
$post_id = get_the_ID();

function mt_big_image($url) {
    return $url ? preg_replace('/\d+x\d+/', '600x600', $url) : '';
}

$artist_name = get_post_meta($post_id, 'artistName', true);
$artist_id   = get_post_meta($post_id, 'artistId', true);
$genre       = get_post_meta($post_id, 'primaryGenreName', true);
$image_raw   = get_post_meta($post_id, 'artistArtworkUrl', true);
$country     = get_post_meta($post_id, 'country', true);

echo '<article class="musicman-artist-container" style="max-width: 1000px; margin: 30px auto; background: #fff; border-radius: 12px; box-shadow: 0 5px 25px rgba(0,0,0,0.1); overflow: hidden;">';

echo '<div class="artist-header" style="height: 300px; background: #111; position: relative; overflow: hidden; display: flex; align-items: center; justify-content: center;">';
if ($image_raw) {
    echo '<img src="' . esc_url(mt_big_image($image_raw)) . '" style="position: absolute; width: 100%; height: 100%; object-fit: cover; opacity: 0.3; filter: blur(10px);">';
    echo '<img src="' . esc_url(mt_big_image($image_raw)) . '" style="position: relative; z-index: 10; width: 220px; height: 220px; border-radius: 50%; border: 5px solid #fff; box-shadow: 0 10px 30px rgba(0,0,0,0.5); object-fit: cover;">';
}
echo '</div>';

echo '<div style="text-align: center; padding: 30px 20px; border-bottom: 1px solid #eee;">';
echo '<h1 style="margin: 0; font-size: 42px;">' . esc_html($artist_name ?: get_the_title()) . '</h1>';
echo '<p style="color: #666; font-size: 18px; margin-top: 10px;">' . esc_html($genre) . ' Artist' . ($country ? ' from ' . esc_html($country) : '') . '</p>';
echo '</div>';

echo '<div class="artist-content" style="padding: 40px;">';

// Albums
$album_ids = get_posts([
    'post_type'      => 'musicman_collection',
    'posts_per_page' => -1,
    'meta_key'       => '_artist_post_id',
    'meta_value'     => $post_id,
    'fields'         => 'ids',
    'post_status'    => 'publish',
]);

if (!empty($album_ids)) {
    echo '<h2 style="margin-top: 0; margin-bottom: 25px; border-left: 5px solid #2271b1; padding-left: 15px;">Discography</h2>';
    echo '<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 30px;">';
    foreach ($album_ids as $album_post_id) {
        $name = get_post_meta($album_post_id, 'collectionName', true);
        $art  = get_post_meta($album_post_id, 'artworkUrl100', true);
        $year = get_post_meta($album_post_id, 'releaseDate', true);
        echo '<div style="text-align: center;">';
        echo '<a href="' . get_permalink($album_post_id) . '" style="text-decoration: none; color: #333;">';
        echo '<img src="' . esc_url(mt_big_image($art)) . '" style="width: 100%; aspect-ratio: 1; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 12px; transition: transform 0.3s;" onmouseover="this.style.transform=\'scale(1.05)\'" onmouseout="this.style.transform=\'scale(1)\'">';
        echo '<strong style="display: block; font-size: 15px;">' . esc_html($name) . '</strong>';
        if ($year) echo '<span style="color: #888; font-size: 13px;">' . date('Y', strtotime($year)) . '</span>';
        echo '</a>';
        echo '</div>';
    }
    echo '</div>';
}

echo '</div>'; // .artist-content

echo '<div style="padding: 40px; border-top: 1px solid #eee; background: #fafafa;">';
comments_template();
echo '</div>';

echo '</article>';
get_footer();
