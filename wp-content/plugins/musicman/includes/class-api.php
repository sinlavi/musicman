<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MusicMan_API {
	private $base_url_search = 'https://itunes.apple.com/search';
	private $base_url_lookup = 'https://itunes.apple.com/lookup';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'http_api_curl', [ $this, 'apply_proxy' ], 10, 3 );
	}

	public function register_routes() {
		$version = 'v1';
		$namespace = 'musicman/' . $version;

		register_rest_route( $namespace, '/search', [
			'methods'  => 'GET',
			'callback' => [ $this, 'handle_search' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/lookup', [
			'methods'  => 'GET',
			'callback' => [ $this, 'handle_lookup' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/stats', [
			'methods'  => 'GET',
			'callback' => [ $this, 'handle_stats' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/queue', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_queue' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
			],
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'add_to_queue' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
			],
			[
				'methods'  => 'DELETE',
				'callback' => [ $this, 'delete_from_queue' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
			],
			[
				'methods'  => 'PUT',
				'callback' => [ $this, 'update_queue' ],
				'permission_callback' => [ $this, 'is_logged_in' ],
			]
		] );

		register_rest_route( $namespace, '/mirrors', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_mirrors' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'set_mirror' ],
				'permission_callback' => [ $this, 'is_admin' ],
			]
		] );

		register_rest_route( $namespace, '/lyrics', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_lyrics' ],
				'permission_callback' => '__return_true',
			]
		] );
	}

	public function is_logged_in() {
		return is_user_logged_in();
	}

	public function is_admin() {
		return current_user_can( 'manage_options' );
	}

	private function get_proxy() {
		$proxies = get_option( 'musicman_proxies', '' );
		if ( empty( $proxies ) ) return null;
		$lines = explode( "\n", $proxies );
		$lines = array_filter( array_map( 'trim', $lines ) );
		if ( empty( $lines ) ) return null;
		return $lines[ array_rand( $lines ) ];
	}

    public function apply_proxy( &$handle, $r, $url ) {
        if ( strpos( $url, 'itunes.apple.com' ) === false ) return;
        $proxy = $this->get_proxy();
        if ( $proxy ) {
            curl_setopt( $handle, CURLOPT_PROXY, $proxy );
        }
    }

	private function make_request( $url ) {
		return wp_remote_get( $url, [ 'timeout' => 20 ] );
	}

	public function handle_search( $request ) {
		$params = $request->get_params();
		if (!isset($params['media'])) $params['media'] = 'music';

		$url = add_query_arg( $params, $this->base_url_search );
		$response = $this->make_request( $url );
		if ( is_wp_error( $response ) ) return new WP_Error( 'api_error', 'iTunes Error', [ 'status' => 500 ] );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! empty( $body['results'] ) ) {
			$body['results'] = $this->sync_entities( $body['results'], is_user_logged_in() );
		}

		return rest_ensure_response( $body );
	}

	public function handle_lookup( $request ) {
		$params = $request->get_params();
		$url = add_query_arg( $params, $this->base_url_lookup );
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', 'iTunes Error', [ 'status' => 500 ] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['results'] ) ) {
			$body['results'] = $this->sync_entities( $body['results'], is_user_logged_in() );
		}

		return rest_ensure_response( $body );
	}

	public function handle_stats( $request ) {
		global $wpdb;
		return rest_ensure_response([
			'track_count' => wp_count_posts('musicman_track')->publish,
			'artist_count' => wp_count_posts('musicman_artist')->publish,
			'album_count' => wp_count_posts('musicman_collection')->publish,
			'queue_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}musicman_queue")
		]);
	}

	private function sync_entities( $results, $do_sync = true ) {
		foreach ( $results as $key => $item ) {
			$type = isset( $item['wrapperType'] ) ? $item['wrapperType'] : '';
			$itunes_id = '';
            $id_key = '';
			$post_type = '';
			$title = '';

			if ( $type === 'track' || (isset($item['kind']) && $item['kind'] === 'song') ) {
				$itunes_id = isset($item['trackId']) ? $item['trackId'] : '';
                $id_key = 'trackId';
				$post_type = 'musicman_track';
				$title = (isset($item['trackName']) ? $item['trackName'] : '') . ' – ' . (isset($item['artistName']) ? $item['artistName'] : '');
			} elseif ( $type === 'collection' ) {
				$itunes_id = isset($item['collectionId']) ? $item['collectionId'] : '';
                $id_key = 'collectionId';
				$post_type = 'musicman_collection';
				$title = isset($item['collectionName']) ? $item['collectionName'] : '';
			} elseif ( $type === 'artist' ) {
				$itunes_id = isset($item['artistId']) ? $item['artistId'] : '';
                $id_key = 'artistId';
				$post_type = 'musicman_artist';
				$title = isset($item['artistName']) ? $item['artistName'] : '';
			}

			if ( $post_type && $itunes_id ) {
                if ($do_sync) {
				    $post_id = $this->upsert_entity( $post_type, $itunes_id, $id_key, $title, $item );
                    if ($post_id) {
				        $item['wp_post_id'] = $post_id;
				        $item['wp_permalink'] = get_permalink($post_id);
                    }
                } else {
                    $post = self::get_post_by_itunes_id($post_type, $id_key, $itunes_id);
                    if ($post) {
                        $item['wp_post_id'] = $post->ID;
				        $item['wp_permalink'] = get_permalink($post->ID);
                    }
                }
			}
			$results[$key] = $item;
		}
		return $results;
	}

	public function upsert_entity( $post_type, $itunes_id, $id_key, $title, $data ) {
		$post = self::get_post_by_itunes_id($post_type, $id_key, $itunes_id);

		if ( $post ) {
			$post_id = $post->ID;
		} else {
			$post_id = wp_insert_post( [
				'post_type'   => $post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			] );
			update_post_meta( $post_id, $id_key, $itunes_id );
		}

		if ( $post_id ) {
			foreach ($data as $k => $v) {
                if (!is_array($v)) {
                    update_post_meta($post_id, $k, sanitize_text_field($v));
                }
            }
		}
		return $post_id;
	}

	public function get_queue( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = $wpdb->prefix . 'musicman_queue';
		$status = $request->get_param('status');

		$sql = "SELECT * FROM $table WHERE user_id = %d";
		if ($status && $status !== 'all') $sql .= $wpdb->prepare(" AND status = %s", $status);
		$sql .= " ORDER BY added_at DESC";

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ), ARRAY_A );

		foreach ($items as &$item) {
			$itunes_id = $item['track_id'];
			$post = self::get_post_by_itunes_id('musicman_track', 'trackId', $itunes_id);
			if ($post) {
                $item['track_data'] = [
                    'trackName' => get_post_meta($post->ID, 'trackName', true),
                    'artistName' => get_post_meta($post->ID, 'artistName', true),
                ];
			}
		}
		return rest_ensure_response( [ 'success' => true, 'items' => $items ] );
	}

	public function add_to_queue( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$track_id = $request->get_param('trackId');
		if ( ! $track_id ) return new WP_Error( 'missing_params', 'Missing trackId', [ 'status' => 400 ] );

		$table = $wpdb->prefix . 'musicman_queue';
		$wpdb->insert( $table, [
			'track_id' => $track_id,
			'user_id'  => $user_id,
			'status'   => 'pending',
			'quality'  => $request->get_param('quality') ?: '192',
			'platform' => $request->get_param('platform') ?: 'telegram',
		] );
		return rest_ensure_response( [ 'success' => true, 'id' => $wpdb->insert_id ] );
	}

	public function update_queue( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$id = $request->get_param('id');
		$status = $request->get_param('status');
		$table = $wpdb->prefix . 'musicman_queue';
		if (!$id) return new WP_Error('missing_id', 'Missing id', ['status' => 400]);

		$data = [];
		if ($status) $data['status'] = $status;

		$ids = is_array($id) ? $id : [$id];
		foreach ($ids as $sid) {
            $wpdb->update($table, $data, ['id' => $sid, 'user_id' => $user_id]);
        }

		return rest_ensure_response(['success' => true]);
	}

	public function delete_from_queue( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$id = $request->get_param('id');
        $status = $request->get_param('status');
		$table = $wpdb->prefix . 'musicman_queue';

		if ($id) {
			$ids = is_array($id) ? $id : [$id];
			foreach($ids as $sid) $wpdb->delete( $table, [ 'id' => $sid, 'user_id' => $user_id ] );
		} elseif ($status) {
            $wpdb->delete( $table, [ 'status' => $status, 'user_id' => $user_id ] );
        } else {
			return new WP_Error( 'missing_params', 'Missing id', [ 'status' => 400 ] );
		}
		return rest_ensure_response( [ 'success' => true ] );
	}

	public function get_mirrors( $request ) {
		$type = $request->get_param('entityType');
		$id = $request->get_param('entityId');
		return rest_ensure_response( [ 'success' => true, 'mirrors' => $this->get_mirrors_internal($type, $id) ] );
	}

	private function get_mirrors_internal($type, $id) {
		global $wpdb;
		$table = $wpdb->prefix . 'musicman_mirrors';
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE entity_type = %s AND entity_id = %s", $type, $id ), ARRAY_A );
		$mirrors = [];
		foreach ($rows as $row) {
			$p = $row['platform'];
			if (!isset($mirrors[$p])) $mirrors[$p] = [];
			$mirrors[$p][$row['url_type']] = [ 'url' => $row['mirror_url'], 'quality' => $row['quality'] ];
		}
		return $mirrors;
	}

	public function set_mirror( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'musicman_mirrors';
		$data = [
			'entity_type' => $request->get_param('entityType'),
			'entity_id'   => $request->get_param('entityId'),
			'url_type'    => $request->get_param('urlType'),
			'mirror_url'  => $request->get_param('mirrorUrl'),
			'quality'     => $request->get_param('quality'),
			'platform'    => $request->get_param('platform') ?: 'telegram',
		];
		$wpdb->replace( $table, $data );
		return rest_ensure_response( [ 'success' => true ] );
	}

	public function get_lyrics( $request ) {
		$track_id = $request->get_param('id');
		$post = self::get_post_by_itunes_id('musicman_track', 'trackId', $track_id);
		if (!$post) return new WP_Error('not_found', 'Track not found', ['status' => 404]);

		$plain = get_post_meta($post->ID, 'lyricsPlain', true);
        $synced = get_post_meta($post->ID, 'lyricsSynced', true);

		if (!$plain && !$synced) {
            $artist = get_post_meta($post->ID, 'artistName', true);
            $track = get_post_meta($post->ID, 'trackName', true);
            $album = get_post_meta($post->ID, 'collectionName', true);

			$fetched = $this->fetch_lyrics_from_lrclib($track, $artist, $album);
            $data = json_decode($fetched, true);
			if ($data) {
                $plain = $data['plainLyrics'] ?? '';
                $synced = $data['syncedLyrics'] ?? '';
                update_post_meta($post->ID, 'lyricsPlain', $plain);
                update_post_meta($post->ID, 'lyricsSynced', $synced);
            }
		}
		return rest_ensure_response( [ 'success' => true, 'lyrics' => json_encode([ 'plainLyrics' => $plain, 'syncedLyrics' => $synced ]) ] );
	}

	public static function get_post_by_itunes_id($post_type, $id_key, $itunes_id) {
		$query = new WP_Query( [
			'post_type'  => $post_type,
			'meta_query' => [ [ 'key' => $id_key, 'value' => $itunes_id ] ],
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );
		return $query->have_posts() ? $query->posts[0] : null;
	}

	private function fetch_lyrics_from_lrclib($track, $artist, $album) {
		$url = 'https://lrclib.net/api/get?' . http_build_query([ 'track_name' => $track, 'artist_name' => $artist, 'album_name' => $album ]);
		$response = $this->make_request($url);
		if (is_wp_error($response)) return null;
		return wp_remote_retrieve_body($response);
	}
}
new MusicMan_API();
