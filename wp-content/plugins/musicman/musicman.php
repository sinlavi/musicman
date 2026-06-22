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
        add_filter( 'template_include', [ $this, 'load_templates' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
        add_action( 'admin_head', [ $this, 'admin_custom_css' ] );
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

    public function admin_scripts( $hook ) {
        if ( strpos( $hook, 'musicman-crawler' ) !== false ) {
            wp_enqueue_style( 'musicman-crawler-style', MUSICMAN_URL . 'assets/crawler-admin.css', [], MUSICMAN_VERSION );
            wp_enqueue_script( 'musicman-crawler-js', MUSICMAN_URL . 'assets/crawler-admin.js', [ 'jquery' ], MUSICMAN_VERSION, true );
            wp_localize_script( 'musicman-crawler-js', 'musicmanCrawler', [
                'root' => esc_url_raw( rest_url() ),
                'nonce' => wp_create_nonce( 'wp_rest' )
            ] );
        }
    }

    public function admin_custom_css() {
        ?>
        <style>
            #toplevel_page_musicman .wp-menu-image img { padding: 0; }
            .status-badge { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
            .status-pending { background: #fff3cd; color: #856404; }
            .status-downloading { background: #cce5ff; color: #004085; }
            .status-completed { background: #d4edda; color: #155724; }
            .status-failed { background: #f8d7da; color: #721c24; }

            /* phpMyAdmin style for list tables */
            .wp-list-table.widefat.fixed.striped { border: 1px solid #c3c4c7; box-shadow: none; }
            .wp-list-table th { background: #f0f0f1; border-bottom: 1px solid #c3c4c7 !important; }
            .wp-list-table td { border-bottom: 1px solid #f0f0f1; }

            /* Legacy Editor Improvements */
            .post-type-musicman_track #postbox-container-2 .postbox,
            .post-type-musicman_artist #postbox-container-2 .postbox,
            .post-type-musicman_collection #postbox-container-2 .postbox {
                border: 1px solid #c3c4c7;
                box-shadow: none;
            }
            .post-type-musicman_track .inside,
            .post-type-musicman_artist .inside,
            .post-type-musicman_collection .inside {
                padding: 12px;
            }
        </style>
        <?php
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
            'Crawler',
            'Crawler',
            'manage_options',
            'musicman-crawler',
            [ $this, 'crawler_page_html' ]
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
		</div>
		<?php
	}

    public function crawler_page_html() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        ?>
        <div class="pmah-layout" style="height: calc(100vh - 32px); margin-left: -20px;">
          <!-- Left Nav -->
          <div class="left-nav" id="leftSidebar">
            <div class="left-header">
              <i class="dashicons dashicons-format-audio"></i>
              <span>Crawler</span>
              <span class="badge" id="treeTotalCount">0 items</span>
            </div>
            <div class="search-panel">
              <input type="text" id="searchTerm" placeholder="Search criteria..." value="Pink Floyd" />
              <div class="search-row">
                <select id="entityType">
                  <option value="musicArtist,album,song">All Types</option>
                  <option value="musicArtist">Artists</option>
                  <option value="album">Albums</option>
                  <option value="song">Tracks</option>
                </select>
              </div>
              <button id="doSearchBtn" class="button button-primary"><i class="dashicons dashicons-search"></i> Query Database</button>
            </div>
            <div class="tree-container" id="treeContainer">
              <div class="empty-msg">Execute query above to construct tree nodes.</div>
            </div>
            <div id="treeBulkBar" class="bulk-bar" style="display:none;">
              <span id="treeSelectedCount">0</span> selected
              <button id="treeBulkAddBtn" class="button button-small">Import</button>
            </div>
          </div>

          <!-- Main Content -->
          <div class="main-content">
            <div class="tabs" style="display: flex; background: #f0f0f1; border-bottom: 1px solid #c3c4c7; padding: 5px 10px 0;">
                <div class="tab active" data-tab="browse" style="padding: 8px 15px; background: #fff; border: 1px solid #c3c4c7; border-bottom: none; cursor: pointer; margin-right: 5px; border-radius: 4px 4px 0 0;">Browse</div>
                <div class="tab" data-tab="queue" style="padding: 8px 15px; background: #e0e0e0; border: 1px solid #c3c4c7; border-bottom: none; cursor: pointer; margin-right: 5px; border-radius: 4px 4px 0 0;">Queue</div>
                <div class="tab" data-tab="stats" style="padding: 8px 15px; background: #e0e0e0; border: 1px solid #c3c4c7; border-bottom: none; cursor: pointer; border-radius: 4px 4px 0 0;">Stats</div>
            </div>

            <div id="browse-pane" class="tab-pane active-pane" style="flex: 1; display: flex; flex-direction: column;">
                <div id="browseWorkspace" style="overflow-y:auto; flex:1; display:flex; flex-direction:column;">
                    <div class="empty-msg" style="margin-top:60px; text-align: center;">
                      No relational database element selected for inspection.<br />Select any row item from the left hierarchy node array to query its details.
                    </div>
                </div>
            </div>

            <div id="queue-pane" class="tab-pane" style="display: none; padding: 20px;">
                <h2>Operations Queue</h2>
                <div id="admin-queue-list">Loading queue...</div>
            </div>

            <div id="stats-pane" class="tab-pane" style="display: none; padding: 20px;">
                <h2>System Statistics</h2>
                <div id="admin-stats-content">Loading stats...</div>
            </div>
          </div>

          <!-- Right Panel -->
          <div class="right-properties" id="rightPanel">
            <div id="rightPanelContent">
                <div class="props-card"><div class="empty-msg">No node selected.</div></div>
            </div>
          </div>
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

    public function load_templates( $template ) {
        $post_type = get_post_type();
        if ( in_array( $post_type, [ 'musicman_track', 'musicman_artist', 'musicman_collection' ] ) ) {
            $plugin_template = MUSICMAN_DIR . 'templates/single-' . $post_type . '.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }
        return $template;
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
