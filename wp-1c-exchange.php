<?php
/*
Plugin Name: 1c exchange
Plugin URI:
Description:
Version: 1.2.1b
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

/**
 * @todo : добавить выбор экспортируемых файлов
 * @todo : перейти на классы
 * @todo : добавить выбор обновляемых атрибутов
 */

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

if( ! is_admin() )
  return;

global $wpdb;

define('EXCHANGE_PLUG_DIR', rtrim( plugin_dir_path( __FILE__ ), '/' ) );
define('EXCHANGE_PLUG_URL', rtrim( plugins_url( basename(__DIR__) ), '/' ) );

$upload_dir = wp_upload_dir();
define('EXCHANGE_DIR', $upload_dir['basedir'] . '/exchange' );
define('EXCHANGE_DIR_CACHE', EXCHANGE_DIR . '/_cache' );

define('EXCHANGE_MAP', $wpdb->get_blog_prefix() . 'exchenged_items_map');

function load_exchange_plugin() {
  require_once EXCHANGE_PLUG_DIR . '/inc/class/wp-admin-page-render.php';
  require_once EXCHANGE_PLUG_DIR . '/inc/class/wp-form-render.php';
  require_once EXCHANGE_PLUG_DIR . '/inc/class/wc-product-settings.php';

  require_once EXCHANGE_PLUG_DIR . '/inc/utilites.php';
  require_once EXCHANGE_PLUG_DIR . '/inc/product-fields.php';
  require_once EXCHANGE_PLUG_DIR . '/inc/import.php';
  require_once EXCHANGE_PLUG_DIR . '/inc/admin-page.php';
  require_once EXCHANGE_PLUG_DIR . '/inc/exchange.php';
}
add_action('plugins_loaded', 'load_exchange_plugin');

register_activation_hook( __FILE__, 'install_exchange_plugin');
function install_exchange_plugin() {
  require_once EXCHANGE_PLUG_DIR . '/install.php';
}

register_deactivation_hook( __FILE__, 'uninstall_exchange_plugin');
function uninstall_exchange_plugin() {
  require_once EXCHANGE_PLUG_DIR . '/uninstall.php';
}




// var_dump( update_product_map_item('value_test', 18) );
