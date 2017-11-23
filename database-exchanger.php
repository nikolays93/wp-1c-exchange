<?php

/*
Plugin Name: Импорт продукции организации
Plugin URI:
Description: Импорт товаров (и товарных предложений) и категорий из 1C в WooCoommerce.
Version: 2.0a
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

// $dir = $upload_dir['basedir'] . '/exchange';
namespace CDevelopers\Exchange;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

const DOMAIN = 'database-exchanger';

class Utils
{
    const OPTION = 'exchange';

    private static $initialized;
    private static $settings;
    private function __construct() {}
    private function __clone() {}

    static function activate() {
        add_option( self::OPTION, array(
            'per_once' => 1000,
        ) );
        // require_once EXCHANGE_PLUG_DIR . '/.install.php';
    }
    static function uninstall() {
        delete_option(self::OPTION);
    }

    private static function include_required_classes()
    {
        $class_dir = self::get_plugin_dir('classes');
        $classes = array(
            // __NAMESPACE__ . '\Example_List_Table' => '/wp-list-table.php',
            __NAMESPACE__ . '\WP_Admin_Page'      => $class_dir . '/wp-admin-page.php',
            // __NAMESPACE__ . '\WP_Admin_Forms'     => $class_dir . '/wp-admin-forms.php',
            // __NAMESPACE__ . '\WP_Post_Boxes'      => $class_dir . '/wp-post-boxes.php',
            );

        foreach ($classes as $classname => $path) {
            if( ! class_exists($classname) ) {
                require_once $path;
            }
        }

        // includes
        require_once __DIR__ . '/includes/register-taxanomies.php';
        require_once __DIR__ . '/includes/init.php';
        require_once __DIR__ . '/includes/admin-page.php';
    }

    public static function initialize()
    {
        if( self::$initialized ) {
            return false;
        }

        // load_plugin_textdomain( DOMAIN, false, DOMAIN . '/languages/' );
        self::include_required_classes();

        self::$initialized = true;
    }

    /**
     * Записываем ошибку
     */
    public static function write_debug( $msg, $dir )
    {
        if( ! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG )
            return;

        $dir = str_replace(__DIR__, '', $dir);
        $msg = str_replace(__DIR__, '', $msg);

        $date = new \DateTime();
        $date_str = $date->format(\DateTime::W3C);

        if( $handle = @fopen(__DIR__ . "/debug.log", "a+") ) {
            fwrite($handle, "[{$date_str}] {$msg} ({$dir})\r\n");
            fclose($handle);
        }
        elseif (defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY) {
            echo "Не удается получить доступ к файлу " . __DIR__ . "/debug.log";
            echo "{$msg} ({$dir})";
        }
    }

    /**
     * Загружаем файл если существует
     */
    public static function load_file_if_exists( $file_array, $args = array() )
    {
        $cant_be_loaded = __('The file %s can not be included', DOMAIN);
        if( is_array( $file_array ) ) {
            $result = array();
            foreach ( $file_array as $id => $path ) {
                if ( ! is_readable( $path ) ) {
                    self::write_debug(sprintf($cant_be_loaded, $path), __FILE__);
                    continue;
                }

                $result[] = include_once( $path );
            }
        }
        else {
            if ( ! is_readable( $file_array ) ) {
                self::write_debug(sprintf($cant_be_loaded, $file_array), __FILE__);
                return false;
            }

            $result = include_once( $file_array );
        }

        return $result;
    }

    public static function get_plugin_dir( $path = false )
    {
        $result = __DIR__;

        switch ( $path ) {
            case 'classes': $result .= '/includes/classes'; break;
            case 'settings': $result .= '/includes/settings'; break;
            default: $result .= '/' . $path;
        }

        return $result;
    }

    public static function get_plugin_url( $path = false )
    {
        $result = plugins_url(basename(__DIR__) );

        switch ( $path ) {
            default: $result .= '/' . $path;
        }

        return $result;
    }

    /**
     * Получает настройку из self::$settings или из кэша или из базы данных
     */
    public static function get( $prop_name, $default = false )
    {
        if( ! self::$settings )
            self::$settings = get_option( self::OPTION, array() );

        if( 'all' === $prop_name ) {
            if( is_array(self::$settings) && count(self::$settings) )
                return self::$settings;

            return $default;
        }

        return isset( self::$settings[ $prop_name ] ) ? self::$settings[ $prop_name ] : $default;
    }

    public static function get_settings( $filename, $args = array() )
    {

        return self::load_file_if_exists( self::get_plugin_dir('settings') . '/' . $filename, $args );
    }
}

register_activation_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'activate' ) );
register_uninstall_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'uninstall' ) );
// register_deactivation_hook( __FILE__, array( __NAMESPACE__ . '\Utils', 'deactivate' ) );

add_action( 'plugins_loaded', array( __NAMESPACE__ . '\Utils', 'initialize' ), 9000 );

if( !function_exists( 'sanitize_price' ) ) {
    // sanitizePrice
    function sanitize_price( $string, $delimiter = '.' ) {
        if( ! $string ) {
            return '';
        }

        $arrPriceStr = explode($delimiter, $string);
        $price = 0;
        foreach ($arrPriceStr as $i => $priceStr) {
            if( sizeof($arrPriceStr) !== $i + 1 ){
                $price += (int)preg_replace("/\D/", '', $priceStr);
            }
            else {
                $price += (int)$priceStr / 100;
            }
        }

        return $price;
    }
}

if( ! function_exists( 'sanitize_cyr_url' ) ) {
    // translit
    function sanitize_cyr_url($s){
        $s = strip_tags( (string) $s);
        $s = str_replace(array("\n", "\r"), " ", $s);
        $s = preg_replace("/\s+/", ' ', $s);
        $s = trim($s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
        $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
        $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s);
        $s = str_replace(" ", "-", $s);
        return $s;
    }
}

if( ! function_exists('wp_is_ajax') ) {
    function wp_is_ajax() {
        return (defined('DOING_AJAX') && DOING_AJAX);
    }
}

if( ! function_exists('ajax_answer') ) {
    function ajax_answer( $message, $status = 0, $args = array() ) {
        if( wp_is_ajax() ) {
            $answer = wp_parse_args( $args, array(
                'message' => $message,
                'status' => $status,
                ) );

            echo json_encode( $answer );
            wp_die( '', '', array( 'response' => ($status > 1) ? 200 : 500 ) );
        }

        return $message;
    }
}

if( ! function_exists('ex_check_security') ) {
    function ex_check_security() {
        $EXCHANGE_SECURITY = 'Secret';
        if( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], $EXCHANGE_SECURITY ) ) {
            $err = ajax_answer( 'Ошибка! нарушены правила безопасности' );
            wp_die( $err );
        }

        return true;
    }
}

if( ! function_exists('ex_parse_settings') ) {
    function ex_parse_settings() {
        $settings = wp_parse_args( get_option( EXCHANGE_PAGE ), array(
            'cat_upd'  => '',
            'att_upd'  => '',
            'per_once' => 50
        ) );

        return $settings;
    }
}

// echo "<pre>";
// var_dump( get_post_meta( $_GET['post']) );
// echo "</pre>";
