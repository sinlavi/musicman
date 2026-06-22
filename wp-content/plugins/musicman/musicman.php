<?php
/**
 * Plugin Name: MusicMan
 * Description: Multi-User iTunes API Proxy & Download Manager for WordPress.
 * Version: 1.8.0
 * Author: Jules
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MUSICMAN_VERSION', '1.8.0' );
define( 'MUSICMAN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MUSICMAN_URL', plugin_dir_url( __FILE__ ) );

class MusicMan {
	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->includes();
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		register_activation_hook( __FILE__, [ $this, 'activate' ] );

		add_action( 'plugins_loaded', [ $this, 'init_components' ] );
	}

	private function includes() {
		require_once MUSICMAN_DIR . 'includes/class-api.php';
		require_once MUSICMAN_DIR . 'includes/class-musicman-base.php';
		require_once MUSICMAN_DIR . 'includes/class-musicman-track.php';
		require_once MUSICMAN_DIR . 'includes/class-musicman-artist.php';
		require_once MUSICMAN_DIR . 'includes/class-musicman-collection.php';
	}

	public function init_components() {
		new MusicMan_Track();
		new MusicMan_Artist();
		new MusicMan_Collection();
	}

	public function add_admin_menu() {
		add_menu_page(
			'MusicMan',
			'MusicMan',
			'manage_options',
			'musicman',
			[ $this, 'dashboard_page_html' ],
			'dashicons-format-audio'
		);

		add_submenu_page(
			'musicman',
			'Settings',
			'Settings',
			'manage_options',
			'musicman-settings',
			[ $this, 'settings_page_html' ]
		);
	}

	public function dashboard_page_html() {
		if ( ! current_user_can( 'manage_options' ) ) return;
        global $wpdb;

        $stats = [
            'tracks' => wp_count_posts('musicman_track')->publish,
            'artists' => wp_count_posts('musicman_artist')->publish,
            'albums' => wp_count_posts('musicman_collection')->publish,
            'queue' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}musicman_queue"),
            'mirrors' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}musicman_mirrors")
        ];
		?>
		<div class="wrap">
			<h1>MusicMan Dashboard</h1>

            <div class="welcome-panel" style="padding: 20px;">
                <div class="welcome-panel-column-container">
                    <div class="welcome-panel-column">
                        <h3>System Stats</h3>
                        <ul>
                            <li><span class="dashicons dashicons-format-audio"></span> <strong><?php echo $stats['tracks']; ?></strong> Tracks</li>
                            <li><span class="dashicons dashicons-admin-users"></span> <strong><?php echo $stats['artists']; ?></strong> Artists</li>
                            <li><span class="dashicons dashicons-album"></span> <strong><?php echo $stats['albums']; ?></strong> Albums</li>
                        </ul>
                    </div>
                    <div class="welcome-panel-column">
                        <h3>Active Operations</h3>
                        <ul>
                            <li><span class="dashicons dashicons-download"></span> <strong><?php echo $stats['queue']; ?></strong> Items in Queue</li>
                            <li><span class="dashicons dashicons-admin-links"></span> <strong><?php echo $stats['mirrors']; ?></strong> Mirror Links</li>
                        </ul>
                    </div>
                </div>
            </div>

            <h2 style="margin-top: 20px;">Recent Queue Activity</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Track ID</th>
                        <th>User</th>
                        <th>Status</th>
                        <th>Quality</th>
                        <th>Added At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $queue_items = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}musicman_queue ORDER BY added_at DESC LIMIT 10");
                    if ($queue_items) {
                        foreach ($queue_items as $item) {
                            $user = get_userdata($item->user_id);
                            echo "<tr>
                                <td>{$item->id}</td>
                                <td>{$item->track_id}</td>
                                <td>" . ($user ? $user->display_name : 'Unknown') . "</td>
                                <td><span class='status-badge status-{$item->status}'>{$item->status}</span></td>
                                <td>{$item->quality}</td>
                                <td>{$item->added_at}</td>
                            </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='6'>No active queue items found.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>

            <style>
                .status-badge {
                    padding: 2px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .status-pending { background: #fff3cd; color: #856404; }
                .status-downloading { background: #cce5ff; color: #004085; }
                .status-completed { background: #d4edda; color: #155724; }
                .status-failed { background: #f8d7da; color: #721c24; }
            </style>
		</div>
		<?php
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
