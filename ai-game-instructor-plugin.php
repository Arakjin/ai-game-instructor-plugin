<?php
/**
 * Plugin Name: AI Game Instructor
 * Plugin URI: https://example.com/ai-game-instructor
 * Description: A WordPress plugin that adds a game companion assistant via shortcode.
 * Version: 0.1.0
 * Author: AI Game Instructor
 * Text Domain: ai-game-instructor
 * Requires at least: 6.0
 * Tested up to: 6.8
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AI_GAME_INSTRUCTOR_PLUGIN_FILE')) {
    define('AI_GAME_INSTRUCTOR_PLUGIN_FILE', __FILE__);
}

if (!defined('AI_GAME_INSTRUCTOR_PLUGIN_PATH')) {
    define('AI_GAME_INSTRUCTOR_PLUGIN_PATH', plugin_dir_path(__FILE__));
}

if (!defined('AI_GAME_INSTRUCTOR_PLUGIN_URL')) {
    define('AI_GAME_INSTRUCTOR_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once AI_GAME_INSTRUCTOR_PLUGIN_PATH . 'includes/class-ai-game-instructor-plugin.php';

register_activation_hook(__FILE__, array('AI_Game_Instructor_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('AI_Game_Instructor_Plugin', 'deactivate'));

add_action('plugins_loaded', function () {
    AI_Game_Instructor_Plugin::instance();
});
