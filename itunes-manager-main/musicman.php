<?php
/**
 * Plugin Name: MusicMan
 * Version:     1.8.0
 * Description: Manage music tracks, artists and collections with iTunes API integration.
 */

if (!defined('ABSPATH')) exit;

define('MUSICMAN_VERSION', '1.8.0');
define('MUSICMAN_DIR', plugin_dir_path(__FILE__));
define('MUSICMAN_URL', plugin_dir_url(__FILE__));

require_once MUSICMAN_DIR . 'includes/class-musicman-base.php';
require_once MUSICMAN_DIR . 'includes/class-musicman-track.php';
require_once MUSICMAN_DIR . 'includes/class-musicman-artist.php';
require_once MUSICMAN_DIR . 'includes/class-musicman-collection.php';
add_filter('template_include', function ($template) {
    $post_type = get_post_type();
    if (in_array($post_type, ['music_track', 'music_artist', 'music_collection'])) {
        $plugin_template = MUSICMAN_DIR . 'templates/single-' . $post_type . '.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});
add_action('plugins_loaded', function () {
    new MusicMan_Track();
    new MusicMan_Artist();
    new MusicMan_Collection();
});