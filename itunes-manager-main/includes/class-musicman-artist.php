<?php
if (!defined('ABSPATH')) exit;

class MusicMan_Artist extends MusicMan_Base
{
    const POST_TYPE = 'music_artist';

    public static $skip_auto_import = false;

    private $meta_keys = [
        'artistId', 'artistName', 'artistViewUrl', 'primaryGenreName'
    ];

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_artist']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'maybe_set_pending_crawl'], 40);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
        add_filter('default_comment_status', [$this, 'enable_comments'], 10, 2);

        add_action('admin_menu', function () {
            remove_meta_box('postcustom', self::POST_TYPE, 'normal');
        });

        add_action('wp_ajax_mt_search_itunes_artist', [$this, 'ajax_search_artist']);
        add_action('wp_ajax_mt_crawl_artist', [$this, 'ajax_crawl_artist']);
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
                'name' => 'Artists',
                'singular_name' => 'Artist',
                'add_new_item' => 'Add New Artist',
                'edit_item' => 'Edit Artist',
                'all_items' => 'All Artists',
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-admin-users',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'comments'],
            'show_in_rest' => false,
            'rewrite' => ['slug' => 'artist'],
        ]);
    }

    public function register_meta()
    {
        foreach ($this->meta_keys as $key) {
            register_meta('post', $key, [
                'object_subtype' => self::POST_TYPE,
                'type' => 'string',
                'single' => true,
                'show_in_rest' => false,
            ]);
        }
        register_meta('post', '_album_ids', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'array',
            'single' => true,
            'show_in_rest' => false,
        ]);
        register_meta('post', '_imported', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => false,
        ]);
        register_meta('post', '_pending_crawl', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'boolean',
            'single' => true,
            'show_in_rest' => false,
        ]);
    }

    // -------------------------------------------------------------------------
    // Meta Boxes
    // -------------------------------------------------------------------------
    public function add_meta_boxes()
    {
        add_meta_box('mt_artist_search', 'iTunes Search', [$this, 'render_search'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_artist_meta', 'Artist Metadata', [$this, 'render_meta'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_artist_media', 'Media', [$this, 'render_media'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_artist_import', 'Import Status', [$this, 'render_import_box'], self::POST_TYPE, 'side', 'low');
    }

    public function render_search()
    {
        ?>
        <input type="text" id="mt-artist-search-input" placeholder="Search artist..." style="width:70%">
        <button type="button" id="mt-artist-search-btn" class="button">Search</button>
        <span id="mt-artist-spinner" style="display:none; margin-left:8px;">Loading...</span>
        <div id="mt-artist-results"
             style="margin-top:8px; max-height:200px; overflow:auto; background:#f9f9f9; border:1px solid #ddd; display:none;"></div>
        <?php
    }

    public function render_meta($post)
    {
        wp_nonce_field('mt_save', 'mt_nonce');
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
        foreach ($this->meta_keys as $key) {
            $val = get_post_meta($post->ID, $key, true);
            printf(
                '<div><label>%s</label><input type="text" name="mt_%s" value="%s" style="width:100%%"></div>',
                esc_html($key), esc_attr($key), esc_attr($val)
            );
        }
        echo '</div>';
    }

    public function render_media($post)
    {
        $art_url = get_post_meta($post->ID, 'artistArtworkUrl', true);
        echo '<label>Artist Image URL</label><br>';
        echo '<input type="text" name="mt_artistArtworkUrl" value="' . esc_attr($art_url) . '" style="width:100%">';
    }

    public function render_import_box($post)
    {
        $imported = get_post_meta($post->ID, '_imported', true);
        $pending = get_post_meta($post->ID, '_pending_crawl', true);
        $label = $imported ? 'Re‑import Discography' : 'Import Discography';
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
    public function save_artist($post_id)
    {
        if (!isset($_POST['mt_nonce']) || !wp_verify_nonce($_POST['mt_nonce'], 'mt_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        foreach ($this->meta_keys as $key) {
            if (isset($_POST["mt_$key"])) {
                update_post_meta($post_id, $key, sanitize_text_field($_POST["mt_$key"]));
            }
        }
        if (isset($_POST['mt_artistArtworkUrl'])) {
            update_post_meta($post_id, 'artistArtworkUrl', esc_url_raw($_POST['mt_artistArtworkUrl']));
            $this->set_featured_image_from_url($post_id, $_POST['mt_artistArtworkUrl']);
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
    // AJAX: Full discography crawl
    // -------------------------------------------------------------------------
    public function ajax_crawl_artist()
    {
        check_ajax_referer('mt_search', 'nonce');
        $post_id = (int)$_POST['post_id'];
        if (!$post_id || get_post_type($post_id) !== self::POST_TYPE) wp_die();

        $this->import_albums_and_tracks($post_id);

        delete_post_meta($post_id, '_pending_crawl');
        update_post_meta($post_id, '_imported', '1');

        $album_count = count(get_post_meta($post_id, '_album_ids', true) ?: []);
        wp_send_json_success(['message' => "Imported $album_count albums and their tracks."]);
    }

    // Full import (used by AJAX and manual re‑import)
    public function import_albums_and_tracks($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        // Clean up old children for a fresh crawl
        $old_album_ids = get_post_meta($post_id, '_album_ids', true) ?: [];
        foreach ($old_album_ids as $album_id) {
            wp_delete_post($album_id, true);
        }
        $old_track_ids = $this->get_child_track_ids($post_id, 'music_artist');
        foreach ($old_track_ids as $track_id) {
            wp_delete_post($track_id, true);
        }
        delete_post_meta($post_id, '_album_ids');

        $artist_id = get_post_meta($post_id, 'artistId', true);
        $artist_name = get_post_meta($post_id, 'artistName', true);
        if (!$artist_id || !$artist_name) return;

        // Paginated search for all albums by this artist
        $albums = $this->itunes_search_paginated($artist_name, 'album', 'artistTerm');
        if (empty($albums)) return;

        $track_obj = new MusicMan_Track();
        $album_ids = [];

        foreach ($albums as $album) {
            if (($album['wrapperType'] ?? '') !== 'collection') continue;
            $collectionId = $album['collectionId'] ?? 0;
            if (!$collectionId) continue;

            $coll_post_id = $this->create_or_update_post('music_collection', [
                'collectionId' => $collectionId,
                'collectionName' => $album['collectionName'] ?? '',
                'collectionViewUrl' => $album['collectionViewUrl'] ?? '',
                'artworkUrl100' => $album['artworkUrl100'] ?? '',
                'primaryGenreName' => $album['primaryGenreName'] ?? '',
                'collectionExplicitness' => $album['collectionExplicitness'] ?? '',
                'artistId' => $artist_id,
                'artistName' => $artist_name,
            ], [
                'collectionId' => 'collectionId',
                'collectionName' => 'collectionName',
                'collectionViewUrl' => 'collectionViewUrl',
                'artworkUrl100' => 'artworkUrl100',
                'primaryGenreName' => 'primaryGenreName',
                'collectionExplicitness' => 'collectionExplicitness',
                'artistId' => 'artistId',
                'artistName' => 'artistName',
            ], 'collectionId');

            if (!is_wp_error($coll_post_id)) {
                if ($album['artworkUrl100'] ?? '') {
                    update_post_meta($coll_post_id, 'artworkUrl30', str_replace('100x100', '30x30', $album['artworkUrl100']));
                    update_post_meta($coll_post_id, 'artworkUrl60', str_replace('100x100', '60x60', $album['artworkUrl100']));
                    $this->set_featured_image_from_url($coll_post_id, str_replace('100x100', '400x400', $album['artworkUrl100']));
                }
                update_post_meta($coll_post_id, '_artist_post_id', $post_id);
                $album_ids[] = $coll_post_id;

                // Import tracks for this collection
                $tracks = $this->itunes_lookup($collectionId, 'song');
                foreach ($tracks as $track) {
                    if (($track['wrapperType'] ?? '') !== 'track') continue;
                    $track['artworkUrl30'] = get_post_meta($coll_post_id, 'artworkUrl30', true);
                    $track['artworkUrl60'] = get_post_meta($coll_post_id, 'artworkUrl60', true);
                    $track['artworkUrl100'] = get_post_meta($coll_post_id, 'artworkUrl100', true);

                    $new_track = $track_obj->create_or_update_post('music_track', $track, [
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
                        update_post_meta($new_track, '_artist_post_id', $post_id);
                        update_post_meta($new_track, '_collection_post_id', $coll_post_id);
                        $this->set_featured_image_from_url($new_track, str_replace('100x100', '400x400', get_post_meta($coll_post_id, 'artworkUrl100', true)));
                    }
                }
            }
        }

        update_post_meta($post_id, '_album_ids', $album_ids);
    }

    // -------------------------------------------------------------------------
    // Columns
    // -------------------------------------------------------------------------
    public function columns($columns)
    {
        return $this->add_columns_to_list($columns, [
            'artist_id' => 'Artist ID',
            'genre' => 'Genre',
            'tracks_count' => 'Tracks',
        ]);
    }

    public function column_content($column, $post_id)
    {
        switch ($column) {
            case 'artist_id':
                echo esc_html(get_post_meta($post_id, 'artistId', true));
                break;
            case 'genre':
                echo esc_html(get_post_meta($post_id, 'primaryGenreName', true));
                break;
            case 'tracks_count':
                $tracks = $this->get_child_track_ids($post_id, 'music_artist');
                echo count($tracks);
                break;
        }
    }

    protected function get_child_tracks_for_post($post_id)
    {
        return $this->get_child_track_ids($post_id, 'music_artist');
    }

    protected function get_child_post_ids($parent_id)
    {
        $collection_ids = get_post_meta($parent_id, '_album_ids', true) ?: [];
        $track_ids = $this->get_child_track_ids($parent_id, 'music_artist');
        return array_merge($collection_ids, $track_ids);
    }

    // -------------------------------------------------------------------------
    // AJAX search, modal, scripts
    // -------------------------------------------------------------------------
    public function ajax_search_artist()
    {
        check_ajax_referer('mt_search', 'nonce');
        $term = sanitize_text_field($_POST['term'] ?? '');
        if (!$term) wp_die();

        $results = $this->itunes_search_paginated($term, 'musicArtist');
        if (empty($results)) wp_die('0');

        $html = '<ul style="margin:0;padding:0;">';
        foreach ($results as $artist) {
            $html .= sprintf(
                '<li style="padding:5px;border-bottom:1px solid #eee;cursor:pointer;" data-artist=\'%s\'>%s</li>',
                esc_attr(json_encode($artist)),
                esc_html($artist['artistName'])
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
            $plugin_template = MUSICMAN_DIR . 'templates/single-music_artist.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}