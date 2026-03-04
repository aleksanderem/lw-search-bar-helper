<?php
/**
 * Plugin Name: LW Search Bar Helper
 * Description: Eksponuje dane mieszkań (CPT lokal) przez REST API /wp-json/lw/v1/apartments
 * Version: 1.0.1
 * Author: Liska Dev
 * Update URI: https://github.com/aleksanderem/lw-search-bar-helper
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

define('LW_HELPER_VERSION', '1.0.1');
define('LW_HELPER_DIR', plugin_dir_path(__FILE__));
define('LW_HELPER_GITHUB_REPO', 'aleksanderem/lw-search-bar-helper');

require_once LW_HELPER_DIR . 'includes/class-lw-rest-endpoint.php';
require_once LW_HELPER_DIR . 'includes/class-lw-github-updater.php';

// REST API
add_action('rest_api_init', function () {
    $endpoint = new LW_Rest_Endpoint();
    $endpoint->register_routes();
});

// GitHub Updater
new LW_Helper_GitHub_Updater(__FILE__);

// Settings page
add_action('admin_menu', function () {
    add_options_page(
        'LW Helper',
        'LW Helper',
        'manage_options',
        'lw-helper',
        'lw_helper_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('lw_helper', 'lw_investment_name');

    add_settings_section('lw_helper_main', 'Ustawienia', null, 'lw-helper');

    add_settings_field('lw_investment_name', 'Nazwa tej inwestycji', function () {
        $val = get_option('lw_investment_name', '');
        echo '<input type="text" name="lw_investment_name" value="' . esc_attr($val) . '" class="regular-text" placeholder="np. RL2">';
        echo '<p class="description">Nazwa inwestycji na tej stronie (np. RL2 lub RL3). Widoczna w danych z lokalnego REST API.</p>';
    }, 'lw-helper', 'lw_helper_main');
});

function lw_helper_settings_page() {
    ?>
    <div class="wrap">
        <h1>LW Helper</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('lw_helper');
            do_settings_sections('lw-helper');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}
