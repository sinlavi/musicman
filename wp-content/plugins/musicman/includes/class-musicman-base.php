<?php
if (!defined('ABSPATH')) exit;

abstract class MusicMan_Base
{
    /**
     * Search iTunes with full pagination support.
     *
     * @param string $term
     * @param string $entity  'song', 'album', 'musicArtist'
     * @param string $attribute e.g. 'artistTerm' (optional)
     * @return array All results from all pages.
     */
    protected function itunes_search_paginated($term, $entity = 'song', $attribute = '')
    {
        $all_results = [];
        $limit = 200;
        $offset = 0;

        do {
            $args = [
                'term'   => $term,
                'entity' => $entity,
                'limit'  => $limit,
                'offset' => $offset,
            ];
            if ($attribute) {
                $args['attribute'] = $attribute;
            }
            $url = add_query_arg($args, 'https://itunes.apple.com/search');

            $response = wp_remote_get($url, ['timeout' => 20]);
            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                break;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (empty($data['results'])) {
                break;
            }

            $all_results = array_merge($all_results, $data['results']);
            $offset += $limit;

            // If we got fewer than the limit, we're on the last page
            if (count($data['results']) < $limit) {
                break;
            }
        } while (true);

        return $all_results;
    }

    /**
     * iTunes lookup (non‑paginated, returns up to 200 results).
     */
    protected function itunes_lookup($id, $entity = '')
    {
        $args = ['id' => $id];
        if ($entity) {
            $args['entity'] = $entity;
        }
        $url = add_query_arg($args, 'https://itunes.apple.com/lookup');

        $response = wp_remote_get($url, ['timeout' => 15]);
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return [];
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['results'] ?? [];
    }

    /**
     * Create or update a post by a unique meta value.
     */
    public function create_or_update_post($post_type, $data, $meta_mapping, $unique_key)
    {
        $unique_value = $data[$unique_key] ?? '';
        if (empty($unique_value)) {
            return new WP_Error('missing_unique', 'Unique identifier missing.');
        }

        $existing = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_key'       => $unique_key,
            'meta_value'     => $unique_value,
            'fields'         => 'ids',
        ]);

        if (!empty($existing)) {
            $post_id = $existing[0];
        } else {
            $title = $this->generate_post_title($post_type, $data);
            $post_id = wp_insert_post([
                'post_title'  => sanitize_text_field($title),
                'post_type'   => $post_type,
                'post_status' => 'publish',
            ]);
            if (is_wp_error($post_id)) {
                return $post_id;
            }
        }

        foreach ($meta_mapping as $itunes_field => $meta_key) {
            if (isset($data[$itunes_field])) {
                update_post_meta($post_id, $meta_key, sanitize_text_field($data[$itunes_field]));
            }
        }

        return $post_id;
    }

    private function generate_post_title($post_type, $data)
    {
        switch ($post_type) {
            case 'musicman_track':
                return ($data['trackName'] ?? 'Unknown Track') . ' – ' . ($data['artistName'] ?? 'Unknown Artist');
            case 'musicman_artist':
                return $data['artistName'] ?? 'Unknown Artist';
            case 'musicman_collection':
                return $data['collectionName'] ?? 'Unknown Album';
            default:
                return 'Untitled';
        }
    }

    protected function set_featured_image_from_url($post_id, $image_url)
    {
        if (empty($image_url)) return false;
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $attachment_id = media_sideload_image($image_url, $post_id, null, 'id');
        if (!is_wp_error($attachment_id)) {
            return (bool) set_post_thumbnail($post_id, $attachment_id);
        }
        return false;
    }

    protected function get_post_by_meta($post_type, $meta_key, $meta_value)
    {
        $posts = get_posts([
            'post_type'      => $post_type,
            'posts_per_page' => 1,
            'meta_key'       => $meta_key,
            'meta_value'     => $meta_value,
            'fields'         => 'ids',
        ]);
        return !empty($posts) ? $posts[0] : false;
    }

    protected function get_child_track_ids($parent_id, $parent_type)
    {
        $meta_key = ($parent_type === 'musicman_artist') ? '_artist_post_id' : '_collection_post_id';
        return get_posts([
            'post_type'      => 'musicman_track',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'meta_key'       => $meta_key,
            'meta_value'     => $parent_id,
            'fields'         => 'ids',
        ]);
    }

    public function enqueue_assets($post_type) {
        wp_enqueue_script(
            'musicman-admin',
            MUSICMAN_URL . 'assets/admin.js',
            ['jquery'],
            MUSICMAN_VERSION,
            true
        );
        wp_localize_script('musicman-admin', 'mt_admin', [
            'ajaxurl'   => admin_url('admin-ajax.php'),
            'nonce'     => wp_create_nonce('mt_search'),
            'post_id'   => get_the_ID(),
            'post_type' => $post_type,
        ]);
    }

    /* ----- Admin columns helper ----- */
    protected function add_columns_to_list($columns, $custom_columns)
    {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ('title' === $key) {
                foreach ($custom_columns as $col_key => $col_label) {
                    $new[$col_key] = $col_label;
                }
            }
        }
        return $new;
    }

    /* ----- Child comment aggregation ----- */
    protected function setup_child_comment_aggregation($post_type)
    {
        add_action('wp', function () use ($post_type) {
            if (is_singular($post_type)) {
                add_filter('comments_array', [$this, 'merge_child_comments'], 10, 2);
                add_filter('get_comments_number', [$this, 'child_comment_count'], 10, 2);
            }
        });
    }

    public function merge_child_comments($comments, $post_id)
    {
        $track_ids = $this->get_child_tracks_for_post($post_id);
        if (empty($track_ids)) return $comments;

        $child_comments = get_comments([
            'post__in' => $track_ids,
            'status'   => 'approve',
            'orderby'  => 'comment_date_gmt',
            'order'    => 'DESC',
        ]);
        return array_merge($comments, $child_comments);
    }

    public function child_comment_count($count, $post_id)
    {
        $track_ids = $this->get_child_tracks_for_post($post_id);
        if (empty($track_ids)) return $count;

        $child_count = get_comments([
            'post__in' => $track_ids,
            'status'   => 'approve',
            'count'    => true,
        ]);
        return $count + $child_count;
    }

    protected function get_child_tracks_for_post($post_id) { return []; }

    /* ----- Cascade deletion ----- */
    protected function register_delete_children_hook()
    {
        add_action('before_delete_post', [$this, 'delete_children']);
    }

    public function delete_children($post_id)
    {
        if (get_post_type($post_id) !== static::POST_TYPE) return;
        $child_ids = $this->get_child_post_ids($post_id);
        if (empty($child_ids)) return;
        foreach ($child_ids as $child_id) {
            wp_delete_post($child_id, true);
        }
    }

    protected function get_child_post_ids($parent_id) { return []; }

    /* ----- Gutenberg / Comments ----- */
    public function disable_gutenberg($current, $post_type)
    {
        return $post_type === static::POST_TYPE ? false : $current;
    }

    public function enable_comments($status, $post_type)
    {
        return $post_type === static::POST_TYPE ? 'open' : $status;
    }
}
