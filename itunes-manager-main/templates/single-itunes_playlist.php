<?php
/**
 * Template for single iTunes Playlist
 */
get_header();

$track_ids = get_post_meta(get_the_ID(), '_itunes_playlist_tracks', true);
if ( ! is_array($track_ids) ) $track_ids = array();
$total_tracks = count($track_ids);

// Get track posts
$tracks = array();
foreach ( $track_ids as $track_id ) {
    $post_id = itunes_get_post_id_by_meta('track', $track_id);
    if ( $post_id ) {
        $tracks[] = get_post($post_id);
    }
}
?>

    <div class="itunes-music-platform">
        <div class="container">
            <!-- Playlist Header -->
            <div class="playlist-header">
                <div class="playlist-artwork">
                    <div class="playlist-icon">📋</div>
                </div>

                <div class="playlist-info">
                    <div class="playlist-type">Playlist</div>
                    <h1 class="playlist-title"><?php the_title(); ?></h1>
                    <div class="playlist-meta">
                        <span class="meta-item">🎵 <?php echo $total_tracks; ?> tracks</span>
                        <span class="meta-item">⏱ <?php echo itunes_format_duration(itunes_get_total_duration($tracks)); ?></span>
                    </div>

                    <?php if ( get_the_content() ) : ?>
                        <div class="playlist-description"><?php the_content(); ?></div>
                    <?php endif; ?>

                    <div class="playlist-actions">
                        <button class="action-btn play-all" data-track-ids="<?php echo esc_attr(implode(',', $track_ids)); ?>">
                            ▶ Play All
                        </button>
                        <button class="action-btn download-all" data-track-ids="<?php echo esc_attr(implode(',', $track_ids)); ?>">
                            ⬇ Download All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Track List -->
            <section class="playlist-tracks">
                <h2>Tracks</h2>
                <?php if ( $tracks ) : ?>
                    <div class="track-list">
                        <?php foreach ( $tracks as $index => $track ) :
                            $track_id = get_post_meta($track->ID, '_itunes_trackId', true);
                            $duration = get_post_meta($track->ID, '_itunes_trackTimeMillis', true);
                            $artist_name = get_post_meta($track->ID, '_itunes_artistName', true);
                            $track_status = get_post_meta($track->ID, '_itunes_download_status', true);
                            $explicit = get_post_meta($track->ID, '_itunes_trackExplicitness', true);
                            ?>
                            <div class="track-list-item">
                                <span class="track-number"><?php echo $index + 1; ?></span>
                                <div class="track-list-info">
                                    <h4>
                                        <a href="<?php echo get_permalink($track->ID); ?>">
                                            <?php echo esc_html($track->post_title); ?>
                                        </a>
                                        <?php if ( $explicit === 'explicit' ) : ?>
                                            <span class="badge explicit">E</span>
                                        <?php endif; ?>
                                        <?php if ( $track_status === 'completed' ) : ?>
                                            <span class="badge downloaded">✓</span>
                                        <?php endif; ?>
                                    </h4>
                                    <span class="track-artist-name"><?php echo esc_html($artist_name); ?></span>
                                    <?php if ( $duration ) : ?>
                                        <span class="track-duration"><?php echo itunes_format_duration($duration); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="track-actions">
                                    <button class="play-track-btn" data-track-id="<?php echo esc_attr($track_id); ?>">
                                        ▶ Play
                                    </button>
                                    <button class="remove-track-btn" data-track-id="<?php echo esc_attr($track_id); ?>" data-post-id="<?php echo get_the_ID(); ?>">
                                        ✕
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No tracks in this playlist.</p>
                <?php endif; ?>
            </section>
        </div>
    </div>

    <style>
        .playlist-header {
            display: flex;
            gap: 30px;
            padding: 20px 0 30px;
            border-bottom: 2px solid #eee;
            margin-bottom: 30px;
        }

        .playlist-artwork {
            flex: 0 0 200px;
        }

        .playlist-icon {
            width: 200px;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 80px;
            color: #fff;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }

        .playlist-info {
            flex: 1;
        }

        .playlist-type {
            font-size: 12px;
            text-transform: uppercase;
            color: #888;
            font-weight: 600;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }

        .playlist-title {
            font-size: 34px;
            margin: 0 0 10px;
            font-weight: 700;
        }

        .playlist-meta {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .playlist-meta .meta-item {
            color: #666;
            font-size: 14px;
        }

        .playlist-description {
            color: #666;
            margin-bottom: 15px;
            line-height: 1.6;
        }

        .playlist-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .playlist-actions .action-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .playlist-actions .play-all {
            background: #0073aa;
            color: #fff;
        }

        .playlist-actions .play-all:hover {
            background: #005a87;
        }

        .playlist-actions .download-all {
            background: #28a745;
            color: #fff;
        }

        .playlist-actions .download-all:hover {
            background: #218838;
        }

        .playlist-tracks {
            margin: 40px 0;
        }

        .playlist-tracks h2 {
            font-size: 28px;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .track-artist-name {
            font-size: 13px;
            color: #888;
            margin-left: 5px;
        }

        .remove-track-btn {
            padding: 4px 8px;
            background: #dc3545;
            color: #fff;
            border: none;
            border-radius: 3px;
            cursor: pointer;
            font-size: 12px;
            transition: background 0.3s;
        }

        .remove-track-btn:hover {
            background: #c82333;
        }

        @media (max-width: 768px) {
            .playlist-header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .playlist-artwork {
                flex: 0 0 160px;
            }

            .playlist-icon {
                width: 160px;
                height: 160px;
                font-size: 60px;
            }

            .playlist-title {
                font-size: 26px;
            }

            .playlist-meta {
                justify-content: center;
            }

            .playlist-actions {
                justify-content: center;
            }
        }
    </style>

<?php get_footer(); ?>