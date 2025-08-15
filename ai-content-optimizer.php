<?php
/**
 * Plugin Name: AI Content Optimizer
 * Description: Adds a meta box to posts and products to optimize title and description using AI.
 * Version: 1.1
 * Author: SeekerAI
 */
defined('ABSPATH') or die('No script kiddies please!');

define('AICO_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AICO_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once AICO_PLUGIN_DIR . 'includes/class-settings-page.php';
require_once AICO_PLUGIN_DIR . 'includes/class-openrouter-api.php';
require_once AICO_PLUGIN_DIR . 'includes/metabox-editor.php';
require_once AICO_PLUGIN_DIR . 'templates/prompt-templates.php';

add_action('init', function () {
    if (!post_type_exists('product')) return;
    register_meta('post', '_ai_brand', ['show_in_rest' => true]);
    register_meta('post', '_ai_keywords', ['show_in_rest' => true]);
    register_meta('post', '_ai_features', ['show_in_rest' => true]);
    register_meta('post', '_ai_scenarios', ['show_in_rest' => true]);
});