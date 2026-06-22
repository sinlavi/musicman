<?php
if (!defined('ABSPATH')) exit;

class MusicMan_Collection extends MusicMan_Base
{
    const POST_TYPE = 'musicman_collection';

    public static $skip_auto_import_tracks = false;

    private $meta_keys = [
        'collectionId', 'collectionName', 'collectionViewUrl',
        'primaryGenreName', 'collectionExplicitness',
        'artistId', 'artistName', 'artistViewUrl',
    ];

    private $media_keys = [
        'artworkUrl30', 'artworkUrl60', 'artworkUrl100',
    ];

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_collection']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'ensure_artist_exists'], 20);
        add_action('save_post_' . self::POST_TYPE, [$this, 'maybe_set_pending_crawl'], 40);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('use_block_editor_for_post_type', '__return_false', 100);
        add_filter('default_comment_status', [$this, 'enable_comments'], 10, 2);

        add_action('admin_menu', function () {
            remove_meta_box('postcustom', self::POST_TYPE, 'normal');
        });

        add_action('wp_ajax_mt_search_itunes_album', [$this, 'ajax_search_album']);
        add_action('wp_ajax_mt_crawl_collection', [$this, 'ajax_crawl_collection']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_footer', [$this, 'render_import_modal']);
        add_filter('single_template', [$this, 'load_single_template']);
        $this->setup_child_comment_aggregation(self::POST_TYPE);
        $this->register_delete_children_hook();
    }

    // -------------------------------------------------------------------------
    // Post Type & Meta
    // -------------------------------------------------------------------------
    public function register_post_type()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Collections',
                'singular_name' => 'Collection',
                'add_new_item' => 'Add New Collection',
                'edit_item' => 'Edit Collection',
                'all_items' => 'All Collections',
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-album',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'comments'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'collection'],
        ]);
    }

    public function register_meta()
    {
        foreach (array_merge($this->meta_keys, $this->media_keys) as $key) {
            register_meta('post', $key, [
                'object_subtype' => self::POST_TYPE,
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ]);
        }
        register_meta('post', '_track_ids', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'array',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_tracks_imported', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_imported', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_pending_crawl', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => true,
        ]);
    }

    // -------------------------------------------------------------------------
    // Meta Boxes
    // -------------------------------------------------------------------------
    public function add_meta_boxes()
    {
        add_meta_box('mt_album_search', 'iTunes Search', [$this, 'render_search'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_album_meta', 'Collection Metadata', [$this, 'render_meta'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_album_media', 'Artwork URLs', [$this, 'render_media'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_album_import', 'Import Status', [$this, 'render_import_box'], self::POST_TYPE, 'side', 'low');
    }

    public function render_search()
    {
        ?>
        <input type="text" id="mt-album-search-input" placeholder="Search album..." style="width:70%">
        <button type="button" id="mt-album-search-btn" class="button">Search</button>
        <span id="mt-album-spinner" style="display:none; margin-left:8px;">Loading...</span>
        <div id="mt-album-results"
             style="margin-top:8px; max-height:200px; overflow:auto; background:#f9f9f9; border:1px solid #ddd; display:none;"></div>
        <?php
    }

    public function render_meta($post)
    {
        wp_nonce_field('mt_save', 'mt_nonce');
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';

        $skip_key = 'artistName';
        foreach ($this->meta_keys as $key) {
            if ($key === $skip_key) continue;
            $val = get_post_meta($post->ID, $key, true);
            printf(
                '<div><label>%s</label><input type="text" name="mt_%s" value="%s" style="width:100%%"></div>',
                esc_html($key), esc_attr($key), esc_attr($val)
            );
        }

        // Artist name with link
        $artist_name = get_post_meta($post->ID, 'artistName', true);
        $artist_post_id = get_post_meta($post->ID, '_artist_post_id', true);
        echo '<div><label>artistName';
        if ($artist_post_id) {
            echo ' <a href="' . esc_url(get_edit_post_link($artist_post_id)) . '" target="_blank">(View Artist)</a>';
        }
        echo '</label><input type="text" name="mt_artistName" value="' . esc_attr($artist_name) . '" style="width:100%"></div>';

        echo '</div>';
    }

    public function render_media($post)
    {
        echo '<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">';
        foreach ($this->media_keys as $key) {
            $val = get_post_meta($post->ID, $key, true);
            printf(
                '<div><label>%s</label><input type="text" name="mt_%s" value="%s" style="width:100%%"></div>',
                esc_html($key), esc_attr($key), esc_attr($val)
            );
        }
        echo '</div>';
    }

    public function render_import_box($post)
    {
        $imported = get_post_meta($post->ID, '_imported', true);
        $pending = get_post_meta($post->ID, '_pending_crawl', true);
        $label = $imported ? 'Re‑import Tracks' : 'Import Tracks';
        $disabled = $pending ? 'disabled' : '';
        ?>
        <p>Status: <strong id="mt-import-status">
                <?php echo $imported ? 'Imported' : ($pending ? 'Pending…' : 'Not imported'); ?>
            </strong></p>
        <button type="button" id="mt-trigger-import" class="button">
            <?php echo $label; ?>
        </button>
        <?php
    }

    // -------------------------------------------------------------------------
    // Save
    // -------------------------------------------------------------------------
    public function save_collection($post_id)
    {
        if (!isset($_POST['mt_nonce']) || !wp_verify_nonce($_POST['mt_nonce'], 'mt_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach ($this->meta_keys as $key) {
            if (isset($_POST["mt_$key"])) {
                update_post_meta($post_id, $key, sanitize_text_field($_POST["mt_$key"]));
            }
        }
        foreach ($this->media_keys as $key) {
            if (isset($_POST["mt_$key"])) {
                update_post_meta($post_id, $key, esc_url_raw($_POST["mt_$key"]));
            }
        }

        $url100 = get_post_meta($post_id, 'artworkUrl100', true);
        if ($url100) {
            update_post_meta($post_id, 'artworkUrl30', str_replace('100x100', '30x30', $url100));
            update_post_meta($post_id, 'artworkUrl60', str_replace('100x100', '60x60', $url100));
            $this->set_featured_image_from_url($post_id, str_replace('100x100', '400x400', $url100));
        }
    }

    /**
     * Ensure artist exists (without importing its discography).
     */
    public function ensure_artist_exists($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        $artist_name = get_post_meta($post_id, 'artistName', true);
        $artist_id = get_post_meta($post_id, 'artistId', true);
        if (!$artist_id || !$artist_name) return;

        $existing = $this->get_post_by_meta('musicman_artist', 'artistId', $artist_id);
        if (!$existing) {
            MusicMan_Artist::$skip_auto_import = true;
            $art_post = $this->create_or_update_post('musicman_artist', [
                'artistId' => $artist_id,
                'artistName' => $artist_name,
                'artistViewUrl' => get_post_meta($post_id, 'artistViewUrl', true),
                'primaryGenreName' => get_post_meta($post_id, 'primaryGenreName', true),
            ], [
                'artistId' => 'artistId',
                'artistName' => 'artistName',
                'artistViewUrl' => 'artistViewUrl',
                'primaryGenreName' => 'primaryGenreName',
            ], 'artistId');
            MusicMan_Artist::$skip_auto_import = false;
            update_post_meta($post_id, '_artist_post_id', $art_post);
        } else {
            update_post_meta($post_id, '_artist_post_id', $existing);
        }
    }

    public function maybe_set_pending_crawl($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (get_post_meta($post_id, '_imported', true)) return;

        update_post_meta($post_id, '_pending_crawl', '1');
    }

    // -------------------------------------------------------------------------
    // AJAX: Crawl tracks for a collection
    // -------------------------------------------------------------------------
    public function ajax_crawl_collection()
    {
        check_ajax_referer('mt_search', 'nonce');
        $post_id = (int)$_POST['post_id'];
        if (!$post_id || get_post_type($post_id) !== self::POST_TYPE) wp_die();

        $this->import_tracks($post_id);   // will run even if _tracks_imported is set (we clear it first)

        delete_post_meta($post_id, '_pending_crawl');
        update_post_meta($post_id, '_imported', '1');

        $count = count(get_post_meta($post_id, '_track_ids', true) ?: []);
        wp_send_json_success(['message' => "Imported $count tracks."]);
    }

    // Full track import (used by crawl and by manual re‑import)
    public function import_tracks($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Clear existing track list so we can re‑import
        $existing_tracks = get_post_meta($post_id, '_track_ids', true) ?: [];
        foreach ($existing_tracks as $track_id) {
            wp_delete_post($track_id, true);
        }
        delete_post_meta($post_id, '_track_ids');
        delete_post_meta($post_id, '_tracks_imported');

        $collection_id = get_post_meta($post_id, 'collectionId', true);
        if (!$collection_id) return;

        // Use lookup (returns up to 200 tracks) – sufficient for most albums
        $tracks = $this->itunes_lookup($collection_id, 'song');
        if (empty($tracks)) return;

        // Ensure artwork for collection
        $art100 = get_post_meta($post_id, 'artworkUrl100', true);
        if (!$art100) {
            foreach ($tracks as $t) {
                if (!empty($t['artworkUrl100'])) {
                    $art100 = $t['artworkUrl100'];
                    break;
                }
            }
        }
        if ($art100) {
            update_post_meta($post_id, 'artworkUrl100', $art100);
            update_post_meta($post_id, 'artworkUrl30', str_replace('100x100', '30x30', $art100));
            update_post_meta($post_id, 'artworkUrl60', str_replace('100x100', '60x60', $art100));
            $this->set_featured_image_from_url($post_id, str_replace('100x100', '400x400', $art100));
        }

        // Ensure artist exists (already done, but re‑check)
        $this->ensure_artist_exists($post_id);

        $track_obj = new MusicMan_Track();
        $track_ids = [];

        foreach ($tracks as $track) {
            if (($track['wrapperType'] ?? '') !== 'track') continue;

            $track['artworkUrl30'] = get_post_meta($post_id, 'artworkUrl30', true);
            $track['artworkUrl60'] = get_post_meta($post_id, 'artworkUrl60', true);
            $track['artworkUrl100'] = get_post_meta($post_id, 'artworkUrl100', true);

            $new_track = $track_obj->create_or_update_post('musicman_track', $track, [
                'trackId' => 'trackId',
                'trackName' => 'trackName',
                'artistId' => 'artistId',
                'artistName' => 'artistName',
                'collectionId' => 'collectionId',
                'collectionName' => 'collectionName',
                'trackViewUrl' => 'trackViewUrl',
                'trackExplicitness' => 'trackExplicitness',
                'trackTimeMillis' => 'trackTimeMillis',
                'trackNumber' => 'trackNumber',
                'discNumber' => 'discNumber',
                'releaseDate' => 'releaseDate',
                'country' => 'country',
                'primaryGenreName' => 'primaryGenreName',
                'wrapperType' => 'wrapperType',
                'kind' => 'kind',
                'collectionCensoredName' => 'collectionCensoredName',
                'trackCensoredName' => 'trackCensoredName',
                'artistViewUrl' => 'artistViewUrl',
                'collectionViewUrl' => 'collectionViewUrl',
                'collectionExplicitness' => 'collectionExplicitness',
                'discCount' => 'discCount',
                'trackCount' => 'trackCount',
                'isStreamable' => 'isStreamable',
                'collectionArtistId' => 'collectionArtistId',
                'collectionArtistName' => 'collectionArtistName',
                'collectionArtistViewUrl' => 'collectionArtistViewUrl',
                'artworkUrl30' => 'artworkUrl30',
                'artworkUrl60' => 'artworkUrl60',
                'artworkUrl100' => 'artworkUrl100',
                'previewUrl' => 'previewUrl',
            ], 'trackId');

            if (!is_wp_error($new_track)) {
                update_post_meta($new_track, '_collection_post_id', $post_id);
                update_post_meta($new_track, '_artist_post_id', get_post_meta($post_id, '_artist_post_id', true));
                $this->set_featured_image_from_url($new_track, str_replace('100x100', '400x400', get_post_meta($post_id, 'artworkUrl100', true)));
                $track_ids[] = $new_track;
            }
        }

        update_post_meta($post_id, '_track_ids', $track_ids);
        update_post_meta($post_id, '_tracks_imported', '1');
    }

    // -------------------------------------------------------------------------
    // Columns
    // -------------------------------------------------------------------------
    public function columns($columns)
    {
        return $this->add_columns_to_list($columns, [
            'collection_id' => 'Collection ID',
            'artist' => 'Artist',
            'artwork' => 'Artwork',
            'tracks_count' => 'Tracks',
        ]);
    }

    public function column_content($column, $post_id)
    {
        switch ($column) {
            case 'collection_id':
                echo esc_html(get_post_meta($post_id, 'collectionId', true));
                break;
            case 'artist':
                $artist_name = get_post_meta($post_id, 'artistName', true);
                $artist_post = get_post_meta($post_id, '_artist_post_id', true);
                if ($artist_post && get_post_status($artist_post)) {
                    $edit_url = get_edit_post_link($artist_post);
                    echo '<a href="' . esc_url($edit_url) . '">' . esc_html($artist_name) . '</a>';
                } else {
                    echo esc_html($artist_name);
                }
                break;
            case 'artwork':
                $url = get_post_meta($post_id, 'artworkUrl60', true);
                if ($url) echo '<img src="' . esc_url($url) . '" width="60" height="60">';
                break;
            case 'tracks_count':
                $tracks = get_post_meta($post_id, '_track_ids', true);
                echo is_array($tracks) ? count($tracks) : 0;
                break;
        }
    }

    protected function get_child_tracks_for_post($post_id)
    {
        return get_post_meta($post_id, '_track_ids', true) ?: [];
    }

    protected function get_child_post_ids($parent_id)
    {
        return get_post_meta($parent_id, '_track_ids', true) ?: [];
    }

    // -------------------------------------------------------------------------
    // AJAX search, modal, scripts
    // -------------------------------------------------------------------------
    public function ajax_search_album()
    {
        check_ajax_referer('mt_search', 'nonce');
        $term = sanitize_text_field($_POST['term'] ?? '');
        if (!$term) wp_die();

        $results = $this->itunes_search_paginated($term, 'album');
        if (empty($results)) wp_die('0');

        $html = '<ul style="margin:0;padding:0;">';
        foreach ($results as $album) {
            $html .= sprintf(
                '<li style="padding:5px;border-bottom:1px solid #eee;cursor:pointer;" data-album=\'%s\'>%s – %s</li>',
                esc_attr(json_encode($album)),
                esc_html($album['collectionName']),
                esc_html($album['artistName'])
            );
        }
        $html .= '</ul>';
        echo $html;
        wp_die();
    }

    public function enqueue_admin_scripts($hook)
    {
        global $post;
        if (!in_array($hook, ['post-new.php', 'post.php'])) return;
        if (get_post_type($post) !== self::POST_TYPE) return;

        $this->enqueue_assets(self::POST_TYPE);
    }

    public function render_import_modal()
    {
        $screen = get_current_screen();
        if (!$screen || $screen->post_type !== self::POST_TYPE) return;
        ?>
        <div id="mt-import-modal" style="display:none;">
            <div class="mt-modal-overlay"></div>
            <div class="mt-modal-content">
                <h2>Importing…</h2>
                <p id="mt-import-message">Please wait while the import runs.</p>
                <div class="spinner is-active" style="float:none;margin:10px auto;"></div>
            </div>
        </div>
        <style>
            .mt-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                z-index: 100000;
            }

            .mt-modal-content {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #fff;
                padding: 20px;
                z-index: 100001;
                border-radius: 4px;
                text-align: center;
            }
        </style>
        <?php
    }

    public function load_single_template($template)
    {
        if (is_singular(self::POST_TYPE)) {
            $plugin_template = MUSICMAN_DIR . 'templates/single-musicman_collection.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}
