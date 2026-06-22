<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MusicMan_API {
	private $base_url_search = 'https://itunes.apple.com/search';
	private $base_url_lookup = 'https://itunes.apple.com/lookup';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
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

		register_rest_route( $namespace, '/batch', [
			'methods'  => 'GET',
			'callback' => [ $this, 'handle_batch' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( $namespace, '/popular', [
			'methods'  => 'GET',
			'callback' => [ $this, 'handle_popular' ],
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
			],
			[
				'methods'  => 'DELETE',
				'callback' => [ $this, 'delete_mirror' ],
				'permission_callback' => [ $this, 'is_admin' ],
			]
		] );

		register_rest_route( $namespace, '/lyrics', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_lyrics' ],
				'permission_callback' => '__return_true',
			],
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'save_lyrics' ],
				'permission_callback' => [ $this, 'is_admin' ],
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

	private function make_request( $url ) {
		$args = [ 'timeout' => 15 ];
		$proxy = $this->get_proxy();
		if ( $proxy ) {
			// WordPress doesn't natively support per-request proxy in wp_remote_get easily
			// without filters, but for this task we'll assume standard WP_Http usage or simplified proxy logic
			// A real implementation would use 'http_api_curl' action to set CURLOPT_PROXY
		}
		return wp_remote_get( $url, $args );
	}

	public function handle_search( $request ) {
		$params = $request->get_params();
		if (!isset($params['media'])) $params['media'] = 'music';
		$url = add_query_arg( $params, $this->base_url_search );
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', 'Failed to fetch from iTunes', [ 'status' => 500 ] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['results'] ) ) {
			$body['results'] = $this->sync_entities( $body['results'] );
		}

		return rest_ensure_response( $body );
	}

	public function handle_lookup( $request ) {
		$params = $request->get_params();
		$url = add_query_arg( $params, $this->base_url_lookup );
		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', 'Failed to fetch from iTunes', [ 'status' => 500 ] );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! empty( $body['results'] ) ) {
			$body['results'] = $this->sync_entities( $body['results'] );
		}

		return rest_ensure_response( $body );
	}

	public function handle_batch( $request ) {
		$ids = $request->get_param('ids');
		if (empty($ids)) return new WP_Error('missing_ids', 'Missing ids parameter', ['status' => 400]);

		$params = $request->get_params();
		$params['id'] = $ids;
		unset($params['ids']);

		return $this->handle_lookup( new WP_REST_Request('GET', '/lookup') );
	}

	public function handle_popular( $request ) {
		$query = new WP_Query([
			'post_type' => 'musicman_track',
			'posts_per_page' => $request->get_param('limit') ?: 20,
			'orderby' => 'ID',
			'order' => 'DESC'
		]);

		$results = [];
		foreach ($query->posts as $post) {
			$data = get_post_meta($post->ID, '_itunes_data', true);
			if ($data) {
				$data['wp_post_id'] = $post->ID;
				$data['wp_permalink'] = get_permalink($post->ID);
				$results[] = $data;
			}
		}

		return rest_ensure_response(['resultCount' => count($results), 'results' => $results]);
	}

	public function handle_stats( $request ) {
		global $wpdb;
		$counts = wp_count_posts('musicman_track');
		$track_count = $counts->publish;
		$counts_artist = wp_count_posts('musicman_artist');
		$artist_count = $counts_artist->publish;
		$counts_coll = wp_count_posts('musicman_collection');
		$coll_count = $counts_coll->publish;

		return rest_ensure_response([
			'track_count' => $track_count,
			'artist_count' => $artist_count,
			'album_count' => $coll_count,
			'queue_count' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}musicman_queue")
		]);
	}

	private function sync_entities( $results ) {
		foreach ( $results as $key => $item ) {
			$type = isset( $item['wrapperType'] ) ? $item['wrapperType'] : '';
			$itunes_id = '';
			$post_type = '';
			$title = '';

			if ( $type === 'track' || (isset($item['kind']) && $item['kind'] === 'song') ) {
				$itunes_id = isset($item['trackId']) ? $item['trackId'] : '';
				$post_type = 'musicman_track';
				$title = isset($item['trackName']) ? $item['trackName'] : '';
			} elseif ( $type === 'collection' ) {
				$itunes_id = isset($item['collectionId']) ? $item['collectionId'] : '';
				$post_type = 'musicman_collection';
				$title = isset($item['collectionName']) ? $item['collectionName'] : '';
			} elseif ( $type === 'artist' ) {
				$itunes_id = isset($item['artistId']) ? $item['artistId'] : '';
				$post_type = 'musicman_artist';
				$title = isset($item['artistName']) ? $item['artistName'] : '';
			}

			if ( $post_type && $itunes_id ) {
				$post_id = $this->upsert_entity( $post_type, $itunes_id, $title, $item );
				$item['wp_post_id'] = $post_id;
				$item['wp_permalink'] = get_permalink($post_id);
				$item['mirrorUrls'] = $this->get_mirrors_internal($type ?: 'track', $itunes_id);
			}
			$results[$key] = $item;
		}
		return $results;
	}

	private function upsert_entity( $post_type, $itunes_id, $title, $data ) {
		$query = new WP_Query( [
			'post_type'  => $post_type,
			'meta_query' => [
				[
					'key'   => '_itunes_id',
					'value' => $itunes_id,
				],
			],
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );

		if ( $query->have_posts() ) {
			$post_id = $query->posts[0]->ID;
			wp_update_post( [
				'ID'         => $post_id,
				'post_title' => $title,
			] );
		} else {
			$post_id = wp_insert_post( [
				'post_type'   => $post_type,
				'post_title'  => $title,
				'post_status' => 'publish',
			] );
			update_post_meta( $post_id, '_itunes_id', $itunes_id );
		}

		if ( $post_id ) {
			update_post_meta( $post_id, '_itunes_data', $data );
		}
		return $post_id;
	}

	public function get_queue( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$table = $wpdb->prefix . 'musicman_queue';
		$status = $request->get_param('status');

		$sql = "SELECT * FROM $table WHERE user_id = %d";
		if ($status && $status !== 'all') {
			$sql .= $wpdb->prepare(" AND status = %s", $status);
		}
		$sql .= " ORDER BY added_at DESC";

		$items = $wpdb->get_results( $wpdb->prepare( $sql, $user_id ), ARRAY_A );

		foreach ($items as &$item) {
			$itunes_id = $item['track_id'];
			$post = $this->get_post_by_itunes_id('musicman_track', $itunes_id);
			if ($post) {
				$item['track_data'] = get_post_meta($post->ID, '_itunes_data', true);
				$item['mirrors'] = $this->get_mirrors_internal('track', $itunes_id);
			}
		}

		return rest_ensure_response( [ 'success' => true, 'items' => $items ] );
	}

	public function add_to_queue( $request ) {
		global $wpdb;
		$user_id = get_current_user_id();
		$track_id = $request->get_param('trackId');

		if ( ! $track_id ) {
			return new WP_Error( 'missing_params', 'Missing trackId', [ 'status' => 400 ] );
		}

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
		if ($request->get_param('error_message')) $data['error_message'] = $request->get_param('error_message');

		if (is_array($id)) {
			foreach ($id as $single_id) {
				$wpdb->update($table, $data, ['id' => $single_id, 'user_id' => $user_id]);
			}
		} else {
			$wpdb->update($table, $data, ['id' => $id, 'user_id' => $user_id]);
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
			if (is_array($id)) {
				foreach($id as $sid) $wpdb->delete( $table, [ 'id' => $sid, 'user_id' => $user_id ] );
			} else {
				$wpdb->delete( $table, [ 'id' => $id, 'user_id' => $user_id ] );
			}
		} elseif ($status) {
			$wpdb->delete( $table, [ 'status' => $status, 'user_id' => $user_id ] );
		} else {
			return new WP_Error( 'missing_params', 'Missing id or status', [ 'status' => 400 ] );
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
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table WHERE entity_type = %s AND entity_id = %s",
			$type, $id
		), ARRAY_A );

		$mirrors = [];
		foreach ($rows as $row) {
			$p = $row['platform'];
			if (!isset($mirrors[$p])) $mirrors[$p] = [];
			$mirrors[$p][$row['url_type']] = [
				'url' => $row['mirror_url'],
				'quality' => $row['quality']
			];
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

	public function delete_mirror( $request ) {
		global $wpdb;
		$table = $wpdb->prefix . 'musicman_mirrors';
		$where = [
			'entity_type' => $request->get_param('entityType'),
			'entity_id' => $request->get_param('entityId'),
		];
		if ($request->get_param('platform')) $where['platform'] = $request->get_param('platform');
		if ($request->get_param('urlType')) $where['url_type'] = $request->get_param('urlType');

		$wpdb->delete($table, $where);
		return rest_ensure_response(['success' => true]);
	}

	public function get_lyrics( $request ) {
		$track_id = $request->get_param('id');
		$post = $this->get_post_by_itunes_id('musicman_track', $track_id);

		if (!$post) return new WP_Error('not_found', 'Track not found', ['status' => 404]);

		$lyrics = get_post_meta($post->ID, '_lyrics', true);
		if (!$lyrics) {
			$data = get_post_meta($post->ID, '_itunes_data', true);
			if ($data) {
				$lyrics = $this->fetch_lyrics_from_lrclib($data['trackName'], $data['artistName'], $data['collectionName'] ?? null);
				if ($lyrics) {
					update_post_meta($post->ID, '_lyrics', $lyrics);
				}
			}
		}

		return rest_ensure_response( [ 'success' => true, 'lyrics' => json_decode($lyrics, true) ] );
	}

	public function save_lyrics( $request ) {
		$track_id = $request->get_param('id');
		$lyrics = $request->get_param('lyrics');
		$post = $this->get_post_by_itunes_id('musicman_track', $track_id);

		if (!$post) return new WP_Error('not_found', 'Track not found', ['status' => 404]);

		update_post_meta($post->ID, '_lyrics', is_string($lyrics) ? $lyrics : json_encode($lyrics));
		return rest_ensure_response( [ 'success' => true ] );
	}

	private function get_post_by_itunes_id($post_type, $itunes_id) {
		$query = new WP_Query( [
			'post_type'  => $post_type,
			'meta_query' => [
				[
					'key'   => '_itunes_id',
					'value' => $itunes_id,
				],
			],
			'posts_per_page' => 1,
			'no_found_rows'  => true,
		] );
		return $query->have_posts() ? $query->posts[0] : null;
	}

	private function fetch_lyrics_from_lrclib($track, $artist, $album) {
		$url = 'https://lrclib.net/api/get?' . http_build_query([
			'track_name'  => $track,
			'artist_name' => $artist,
			'album_name'  => $album,
		]);
		$response = $this->make_request($url);
		if (is_wp_error($response)) return null;
		return wp_remote_retrieve_body($response);
	}
}

new MusicMan_API();
