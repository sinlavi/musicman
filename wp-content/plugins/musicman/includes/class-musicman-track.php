<?php
if (!defined('ABSPATH')) exit;

class MusicMan_Track extends MusicMan_Base
{
    const POST_TYPE = 'musicman_track';

    private $meta_keys = [
        'trackId', 'trackName', 'artistId', 'artistName', 'collectionId',
        'collectionName', 'trackViewUrl', 'trackExplicitness',
        'trackTimeMillis', 'trackNumber', 'discNumber', 'releaseDate',
        'country', 'primaryGenreName',
        'wrapperType', 'kind', 'updatedAt', 'collectionCensoredName',
        'trackCensoredName', 'artistViewUrl', 'collectionViewUrl',
        'collectionExplicitness', 'discCount',
        'trackCount', 'isStreamable',
        'collectionArtistId', 'collectionArtistName', 'collectionArtistViewUrl',
    ];

    private $media_keys = [
        'artworkUrl30', 'artworkUrl60', 'artworkUrl100',
        'previewUrl',
        'audioUrl128', 'audioUrl192', 'audioUrl320'
    ];

    private $lyric_keys = [
        'lyricsPlain', 'lyricsSynced', 'lyricsFetched'
    ];

    public function __construct()
    {
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_meta']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_track']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'auto_set_title_tags_and_thumbnail'], 20);
        add_action('save_post_' . self::POST_TYPE, [$this, 'create_artist_and_collection'], 30);
        add_action('save_post_' . self::POST_TYPE, [$this, 'maybe_set_pending_crawl'], 40);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'column_content'], 10, 2);
        add_filter('use_block_editor_for_post_type', [$this, 'disable_gutenberg'], 10, 2);
        add_filter('default_comment_status', [$this, 'enable_comments'], 10, 2);

        add_action('admin_menu', function () {
            remove_meta_box('postcustom', self::POST_TYPE, 'normal');
        });

        add_action('wp_ajax_mt_search_itunes_track', [$this, 'ajax_search_track']);
        add_action('wp_ajax_mt_fetch_lyrics', [$this, 'ajax_fetch_lyrics']);
        add_action('wp_ajax_mt_toggle_like', [$this, 'ajax_toggle_like']);
        add_action('wp_ajax_mt_crawl_track', [$this, 'ajax_crawl_track']);

        add_action('wp', [$this, 'count_view']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('admin_footer', [$this, 'render_import_modal']);
        add_filter('single_template', [$this, 'load_single_template']);
    }

    // -------------------------------------------------------------------------
    // Post Type & Meta
    // -------------------------------------------------------------------------
    public function register_post_type()
    {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'Tracks',
                'singular_name' => 'Track',
                'add_new_item' => 'Add New Track',
                'edit_item' => 'Edit Track',
                'all_items' => 'All Tracks',
            ],
            'public' => true,
            'has_archive' => true,
            'menu_icon' => 'dashicons-format-audio',
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields', 'comments'],
            'show_in_rest' => true,
            'rewrite' => ['slug' => 'track'],
        ]);
    }

    public function register_meta()
    {
        $all = array_merge($this->meta_keys, $this->media_keys, $this->lyric_keys);
        foreach ($all as $key) {
            register_meta('post', $key, [
                'object_subtype' => self::POST_TYPE,
                'type' => 'string',
                'single' => true,
                'show_in_rest' => true,
            ]);
        }
        register_meta('post', '_mt_views', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_mt_likes', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_artist_post_id', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'integer',
            'single' => true,
            'show_in_rest' => true,
        ]);
        register_meta('post', '_collection_post_id', [
            'object_subtype' => self::POST_TYPE,
            'type' => 'integer',
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
        add_meta_box('mt_search', 'iTunes Search', [$this, 'render_search'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_media', 'Media Files', [$this, 'render_media'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_meta', 'Track Metadata', [$this, 'render_meta'], self::POST_TYPE, 'normal', 'high');
        add_meta_box('mt_lyrics', 'Lyrics', [$this, 'render_lyrics'], self::POST_TYPE, 'normal', 'low');
        add_meta_box('mt_stats', 'Stats', [$this, 'render_stats'], self::POST_TYPE, 'side', 'default');
        add_meta_box('mt_import', 'Import Status', [$this, 'render_import_box'], self::POST_TYPE, 'side', 'low');
    }

    public function render_search()
    {
        ?>
        <input type="text" id="mt-search-input" placeholder="Search track, artist, album or ID..." style="width:70%">
        <button type="button" id="mt-search-btn" class="button">Search</button>
        <span id="mt-search-spinner" style="display:none; margin-left:8px;">Loading...</span>
        <div id="mt-search-results"
             style="margin-top:8px; max-height:200px; overflow:auto; background:#f9f9f9; border:1px solid #ddd; display:none;"></div>
        <?php
    }

    public function render_media($post)
    {
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';
        foreach ($this->media_keys as $key) {
            $val = get_post_meta($post->ID, $key, true);
            printf(
                '<div><label style="font-weight:600;">%s</label><br><input type="text" name="mt_%s" value="%s" style="width:100%%"></div>',
                esc_html($key), esc_attr($key), esc_attr($val)
            );
        }
        echo '</div>';
    }

    public function render_meta($post)
    {
        wp_nonce_field('mt_save', 'mt_nonce');
        echo '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">';

        $skip_keys = ['artistName', 'collectionName']; // we'll handle these manually
        foreach ($this->meta_keys as $key) {
            if (in_array($key, $skip_keys)) continue;
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
            $edit_url = get_edit_post_link($artist_post_id);
            echo ' <a href="' . esc_url($edit_url) . '" target="_blank">(View Artist)</a>';
        }
        echo '</label><input type="text" name="mt_artistName" value="' . esc_attr($artist_name) . '" style="width:100%"></div>';

        // Collection name with link
        $coll_name = get_post_meta($post->ID, 'collectionName', true);
        $coll_post_id = get_post_meta($post->ID, '_collection_post_id', true);
        echo '<div><label>collectionName';
        if ($coll_post_id) {
            $edit_url = get_edit_post_link($coll_post_id);
            echo ' <a href="' . esc_url($edit_url) . '" target="_blank">(View Album)</a>';
        }
        echo '</label><input type="text" name="mt_collectionName" value="' . esc_attr($coll_name) . '" style="width:100%"></div>';

        echo '</div><input type="hidden" name="mt_post_id" value="' . (int)$post->ID . '">';
    }

    public function render_lyrics($post)
    {
        $plain = get_post_meta($post->ID, 'lyricsPlain', true);
        $synced = get_post_meta($post->ID, 'lyricsSynced', true);
        $fetched = get_post_meta($post->ID, 'lyricsFetched', true);
        ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
            <div>
                <label>Plain Lyrics</label>
                <textarea name="mt_lyricsPlain" rows="8"
                          style="width:100%"><?php echo esc_textarea($plain); ?></textarea>
            </div>
            <div>
                <label>Synced Lyrics</label>
                <textarea name="mt_lyricsSynced" rows="8"
                          style="width:100%"><?php echo esc_textarea($synced); ?></textarea>
            </div>
        </div>
        <p>
            <button type="button" id="mt-fetch-lyrics-btn" class="button">Fetch from LRCLIB</button>
            <span id="mt-lyrics-status"
                  style="margin-left:12px;"><?php echo $fetched ? 'Last fetched: ' . esc_html($fetched) : ''; ?></span>
            <input type="hidden" name="mt_lyricsFetched" value="<?php echo esc_attr($fetched); ?>">
        </p>
        <?php
    }

    public function render_stats($post)
    {
        $views = (int)get_post_meta($post->ID, '_mt_views', true);
        $likes = (int)get_post_meta($post->ID, '_mt_likes', true);
        $comments = (int)get_comments_number($post->ID);
        echo "<p><strong>Views:</strong> $views</p>";
        echo "<p><strong>Likes:</strong> $likes <button type='button' id='mt-like-toggle' data-post='{$post->ID}' class='button'>+1 Like</button></p>";
        echo "<p><strong>Comments:</strong> $comments</p>";
    }

    public function render_import_box($post)
    {
        $imported = get_post_meta($post->ID, '_imported', true);
        $pending = get_post_meta($post->ID, '_pending_crawl', true);
        $label = $imported ? 'Re‑import Artist & Album' : 'Import Artist & Album';
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
    public function save_track($post_id)
    {
        if (!isset($_POST['mt_nonce']) || !wp_verify_nonce($_POST['mt_nonce'], 'mt_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $all = array_merge($this->meta_keys, $this->media_keys, $this->lyric_keys);
        foreach ($all as $key) {
            if (isset($_POST["mt_$key"])) {
                update_post_meta($post_id, $key, sanitize_text_field($_POST["mt_$key"]));
            }
        }
        if (isset($_POST['mt_lyricsPlain'])) {
            update_post_meta($post_id, 'lyricsPlain', wp_kses_post($_POST['mt_lyricsPlain']));
        }
        if (isset($_POST['mt_lyricsSynced'])) {
            update_post_meta($post_id, 'lyricsSynced', wp_kses_post($_POST['mt_lyricsSynced']));
        }
        if (isset($_POST['mt_lyricsFetched'])) {
            update_post_meta($post_id, 'lyricsFetched', sanitize_text_field($_POST['mt_lyricsFetched']));
        }
    }

    // Auto title, tags, thumbnail
    public function auto_set_title_tags_and_thumbnail($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $track = get_post_meta($post_id, 'trackName', true);
        $artist = get_post_meta($post_id, 'artistName', true);
        if (empty(get_the_title($post_id)) && $track && $artist) {
            wp_update_post(['ID' => $post_id, 'post_title' => "$track – $artist"]);
        }

        $genre = get_post_meta($post_id, 'primaryGenreName', true);
        if ($genre) {
            wp_set_post_tags($post_id, $genre, true);
        }

        if (!has_post_thumbnail($post_id)) {
            $collection_id = get_post_meta($post_id, '_collection_post_id', true);
            if ($collection_id) {
                $coll_art = get_post_meta($collection_id, 'artworkUrl100', true);
                if ($coll_art) {
                    $this->set_featured_image_from_url($post_id, str_replace('100x100', '400x400', $coll_art));
                    return;
                }
            }
            $track_art = get_post_meta($post_id, 'artworkUrl60', true);
            if ($track_art) {
                $this->set_featured_image_from_url($post_id, str_replace('60x60', '400x400', $track_art));
            }
        }
    }

    // Create artist & collection (slim, no sub‑import)
    public function create_artist_and_collection($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;

        $artist_id = get_post_meta($post_id, 'artistId', true);
        $artist_name = get_post_meta($post_id, 'artistName', true);
        $artist_url = get_post_meta($post_id, 'artistViewUrl', true);
        $genre = get_post_meta($post_id, 'primaryGenreName', true);

        if ($artist_id && $artist_name) {
            $existing = $this->get_post_by_meta('musicman_artist', 'artistId', $artist_id);
            if (!$existing) {
                MusicMan_Artist::$skip_auto_import = true;
                $art_post_id = $this->create_or_update_post('musicman_artist', [
                    'artistId' => $artist_id,
                    'artistName' => $artist_name,
                    'artistViewUrl' => $artist_url,
                    'primaryGenreName' => $genre,
                ], [
                    'artistId' => 'artistId',
                    'artistName' => 'artistName',
                    'artistViewUrl' => 'artistViewUrl',
                    'primaryGenreName' => 'primaryGenreName',
                ], 'artistId');
                MusicMan_Artist::$skip_auto_import = false;
                update_post_meta($post_id, '_artist_post_id', $art_post_id);
            } else {
                update_post_meta($post_id, '_artist_post_id', $existing);
            }
        }

        $coll_id = get_post_meta($post_id, 'collectionId', true);
        $coll_name = get_post_meta($post_id, 'collectionName', true);
        $coll_url = get_post_meta($post_id, 'collectionViewUrl', true);
        $coll_art100 = get_post_meta($post_id, 'artworkUrl100', true);
        $coll_explicit = get_post_meta($post_id, 'collectionExplicitness', true);

        if ($coll_id && $coll_name) {
            $existing_coll = $this->get_post_by_meta('musicman_collection', 'collectionId', $coll_id);
            if (!$existing_coll) {
                MusicMan_Collection::$skip_auto_import_tracks = true;
                $coll_post_id = $this->create_or_update_post('musicman_collection', [
                    'collectionId' => $coll_id,
                    'collectionName' => $coll_name,
                    'collectionViewUrl' => $coll_url,
                    'artworkUrl100' => $coll_art100,
                    'primaryGenreName' => $genre,
                    'collectionExplicitness' => $coll_explicit,
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
                MusicMan_Collection::$skip_auto_import_tracks = false;

                if (!is_wp_error($coll_post_id)) {
                    if ($coll_art100) {
                        update_post_meta($coll_post_id, 'artworkUrl30', str_replace('100x100', '30x30', $coll_art100));
                        update_post_meta($coll_post_id, 'artworkUrl60', str_replace('100x100', '60x60', $coll_art100));
                        $this->set_featured_image_from_url($coll_post_id, str_replace('100x100', '400x400', $coll_art100));
                    }
                    update_post_meta($post_id, '_collection_post_id', $coll_post_id);
                }
            } else {
                $coll_post_id = $existing_coll;
                update_post_meta($post_id, '_collection_post_id', $coll_post_id);
            }

            $this->copy_collection_artwork_to_track($post_id, $coll_post_id);
        }
    }

    private function copy_collection_artwork_to_track($track_id, $collection_id)
    {
        foreach (['artworkUrl30', 'artworkUrl60', 'artworkUrl100'] as $field) {
            $value = get_post_meta($collection_id, $field, true);
            if ($value) {
                update_post_meta($track_id, $field, $value);
            }
        }
    }

    /**
     * Set _pending_crawl if this is a new track (no _imported yet).
     */
    public function maybe_set_pending_crawl($post_id)
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (wp_is_post_revision($post_id)) return;
        if (get_post_meta($post_id, '_imported', true)) return;

        update_post_meta($post_id, '_pending_crawl', '1');
    }

    // -------------------------------------------------------------------------
    // AJAX: Crawl (complete the import process)
    // -------------------------------------------------------------------------
    public function ajax_crawl_track()
    {
        check_ajax_referer('mt_search', 'nonce');
        $post_id = (int)$_POST['post_id'];
        if (!$post_id || get_post_type($post_id) !== self::POST_TYPE) wp_die();

        // The slim creation already ran on save; we only need to mark as imported
        delete_post_meta($post_id, '_pending_crawl');
        update_post_meta($post_id, '_imported', '1');

        // Optionally re‑fetch lyrics, set featured image again, etc.
        $this->auto_set_title_tags_and_thumbnail($post_id);

        wp_send_json_success(['message' => 'Track import completed.']);
    }

    // -------------------------------------------------------------------------
    // Columns
    // -------------------------------------------------------------------------
    public function columns($columns)
    {
        return $this->add_columns_to_list($columns, [
            'track_id' => 'Track ID',
            'artist' => 'Artist',
            'album' => 'Album',       // new
            'artwork' => 'Artwork',
            'views' => 'Views',
            'likes' => 'Likes',
        ]);
    }

    public function column_content($column, $post_id)
    {
        switch ($column) {
            case 'track_id':
                echo esc_html(get_post_meta($post_id, 'trackId', true));
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
            case 'album':
                $album_name = get_post_meta($post_id, 'collectionName', true);
                $album_post = get_post_meta($post_id, '_collection_post_id', true);
                if ($album_post && get_post_status($album_post)) {
                    $edit_url = get_edit_post_link($album_post);
                    echo '<a href="' . esc_url($edit_url) . '">' . esc_html($album_name) . '</a>';
                } else {
                    echo esc_html($album_name);
                }
                break;
            case 'artwork':
                $url = get_post_meta($post_id, 'artworkUrl60', true);
                if ($url) echo '<img src="' . esc_url($url) . '" width="60" height="60">';
                break;
            case 'views':
                echo (int)get_post_meta($post_id, '_mt_views', true);
                break;
            case 'likes':
                echo (int)get_post_meta($post_id, '_mt_likes', true);
                break;
        }
    }
    // -------------------------------------------------------------------------
    // Other AJAX (search, lyrics, like)
    // -------------------------------------------------------------------------
    public function ajax_search_track()
    {
        check_ajax_referer('mt_search', 'nonce');
        $term = sanitize_text_field($_POST['term'] ?? '');
        if (!$term) wp_die();

        $results = $this->itunes_search_paginated($term, 'song');
        if (empty($results)) wp_die('0');

        $html = '<ul style="margin:0;padding:0;list-style:none;">';
        foreach ($results as $track) {
            $html .= sprintf(
                '<li style="padding:5px;border-bottom:1px solid #eee;cursor:pointer;" data-track=\'%s\'>%s – %s (%s)</li>',
                esc_attr(json_encode($track)),
                esc_html($track['trackName']),
                esc_html($track['artistName']),
                esc_html($track['collectionName'] ?? '')
            );
        }
        $html .= '</ul>';
        echo $html;
        wp_die();
    }

    public function ajax_fetch_lyrics()
    {
        check_ajax_referer('mt_search', 'nonce');
        $post_id = (int)$_POST['post_id'];
        if (!$post_id) wp_die();

        $artist = get_post_meta($post_id, 'artistName', true);
        $track = get_post_meta($post_id, 'trackName', true);
        $album = get_post_meta($post_id, 'collectionName', true);
        if (!$artist || !$track) wp_die('0');

        $urls = [];
        if ($album) {
            $urls[] = 'https://lrclib.net/api/get?artist_name=' . urlencode($artist) . '&track_name=' . urlencode($track) . '&album_name=' . urlencode($album);
        }
        $urls[] = 'https://lrclib.net/api/get?artist_name=' . urlencode($artist) . '&track_name=' . urlencode($track);

        $found = false;
        foreach ($urls as $url) {
            $resp = wp_remote_get($url, ['timeout' => 10]);
            if (is_wp_error($resp)) continue;
            $body = json_decode(wp_remote_retrieve_body($resp), true);
            if (!empty($body) && (isset($body['plainLyrics']) || isset($body['syncedLyrics']))) {
                $found = $body;
                break;
            }
        }
        if (!$found) wp_die('0');

        $plain = $found['plainLyrics'] ?? '';
        $synced = $found['syncedLyrics'] ?? '';
        update_post_meta($post_id, 'lyricsPlain', $plain);
        update_post_meta($post_id, 'lyricsSynced', $synced);
        update_post_meta($post_id, 'lyricsFetched', current_time('mysql'));

        echo json_encode(['plain' => $plain, 'synced' => $synced]);
        wp_die();
    }

    public function ajax_toggle_like()
    {
        check_ajax_referer('mt_search', 'nonce');
        $post_id = (int)$_POST['post_id'];
        if (!$post_id) wp_die();

        $likes = (int)get_post_meta($post_id, '_mt_likes', true) + 1;
        update_post_meta($post_id, '_mt_likes', $likes);
        echo json_encode(['likes' => $likes]);
        wp_die();
    }

    public function count_view()
    {
        if (is_singular(self::POST_TYPE)) {
            $id = get_queried_object_id();
            $v = (int)get_post_meta($id, '_mt_views', true) + 1;
            update_post_meta($id, '_mt_views', $v);
        }
    }

    public function enqueue_admin_scripts($hook)
    {
        global $post;
        if (!in_array($hook, ['post-new.php', 'post.php'])) return;
        if (get_post_type($post) !== self::POST_TYPE) return;

        $this->enqueue_assets(self::POST_TYPE);
    }

    /**
     * Render the modal HTML (shared across all post types).
     */
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
            $plugin_template = MUSICMAN_DIR . 'templates/single-musicman_track.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}