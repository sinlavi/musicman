<?php
/**
 * Plugin Name: MusicMan
 * Description: Multi-User iTunes API Proxy & Download Manager for WordPress.
 * Version: 1.0.0
 * Author: Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MusicMan {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', [ $this, 'register_post_types' ] );
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		$this->includes();
	}

	private function includes() {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-api.php';
	}

	public function register_post_types() {
		$types = [
			'musicman_artist'     => [ 'label' => 'Artists', 'singular' => 'Artist' ],
			'musicman_collection' => [ 'label' => 'Collections', 'singular' => 'Collection' ],
			'musicman_track'      => [ 'label' => 'Tracks', 'singular' => 'Track' ],
		];

		foreach ( $types as $type => $info ) {
			register_post_type( $type, [
				'labels'      => [
					'name'          => $info['label'],
					'singular_name' => $info['singular'],
				],
				'public'      => true,
				'has_archive' => true,
				'show_in_rest' => true,
				'supports'    => [ 'title', 'editor', 'thumbnail', 'custom-fields' ],
				'menu_icon'   => 'dashicons-format-audio',
			] );
		}
	}

	public function add_admin_menu() {
		add_options_page(
			'MusicMan Settings',
			'MusicMan',
			'manage_options',
			'musicman',
			[ $this, 'settings_page_html' ]
		);
	}

	public function settings_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) return;

		if ( isset( $_POST['musicman_save_settings'] ) ) {
			update_option( 'musicman_proxies', $_POST['musicman_proxies'] );
			echo '<div class="updated"><p>Settings saved.</p></div>';
		}

		$proxies = get_option( 'musicman_proxies', '' );
		?>
		<div class="wrap">
			<h1>MusicMan Settings</h1>
			<form method="post">
				<table class="form-table">
					<tr>
						<th scope="row">Proxies (one per line)</th>
						<td>
							<textarea name="musicman_proxies" rows="10" cols="50" class="large-text"><?php echo esc_textarea( $proxies ); ?></textarea>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="musicman_save_settings" class="button button-primary" value="Save Settings">
				</p>
			</form>
		</div>
		<?php
	}

	public function activate() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		$table_mirrors = $wpdb->prefix . 'musicman_mirrors';
		$sql_mirrors = "CREATE TABLE $table_mirrors (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			entity_type varchar(50) NOT NULL,
			entity_id varchar(255) NOT NULL,
			url_type varchar(50) NOT NULL,
			mirror_url text NOT NULL,
			quality varchar(10),
			platform varchar(50) NOT NULL DEFAULT 'telegram',
			updated_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY unique_mirror (entity_type, entity_id, url_type, quality, platform)
		) $charset_collate;";

		$table_queue = $wpdb->prefix . 'musicman_queue';
		$sql_queue = "CREATE TABLE $table_queue (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			track_id varchar(255) NOT NULL,
			user_id bigint(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			file_path text,
			quality varchar(10),
			platform varchar(50) DEFAULT 'telegram',
			added_at datetime DEFAULT CURRENT_TIMESTAMP,
			started_at datetime,
			completed_at datetime,
			error_message text,
			retry_count int DEFAULT 0,
			priority int DEFAULT 0,
			PRIMARY KEY  (id),
			KEY status (status),
			KEY user_id (user_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_mirrors );
		dbDelta( $sql_queue );
	}
}

MusicMan::get_instance();
