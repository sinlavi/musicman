<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function musicman_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
}
add_action( 'after_setup_theme', 'musicman_theme_setup' );

function musicman_theme_scripts() {
    wp_enqueue_style( 'musicman-google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600&display=swap' );
    wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css' );
    wp_enqueue_style( 'musicman-style', get_stylesheet_uri() );

    wp_enqueue_script( 'musicman-app', get_template_directory_uri() . '/js/app.js', [], '1.0.0', true );
    wp_localize_script( 'musicman-app', 'musicmanSettings', [
        'root' => esc_url_raw( rest_url() ),
        'nonce' => wp_create_nonce( 'wp_rest' ),
        'is_logged_in' => is_user_logged_in(),
        'current_user' => wp_get_current_user(),
    ] );
}
add_action( 'wp_enqueue_scripts', 'musicman_theme_scripts' );
