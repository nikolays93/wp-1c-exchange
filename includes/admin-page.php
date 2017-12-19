<?php

namespace CDevelopers\Exchange;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

class Admin_Page
{
    static $instance;

    function __construct()
    {
        if( ! is_admin() )
            return;

        $page = new WP_Admin_Page( Utils::OPTION );
        $page->set_args( array(
            'parent'      => "woocommerce",
            'title'       => __('Импорт товаров', DOMAIN),
            'menu'        => __('Импорт товаров', DOMAIN),
            'callback'    => array($this, 'page_render'),
            // 'validate'    => array($this, 'validate_options'),
            'permissions' => 'manage_options',
            'tab_sections'=> null,
            'columns'     => 2,
            ) );

        if( current_user_can( 'manage_options' ) ) {
            if( ! isset($_SESSION) ) session_start();

            if( ! empty($_GET['ex_update_product']) ||
            ! empty($_GET['ex_update_taxanomy']) ||
            ! empty($_GET['ex_update_relationships']) )
            {
                if( ! $per_once = Utils::get('per_once') ) $per_once = 1000;
                $ex = new Init( array(
                    'offset' => $per_once,
                    'part'   => 1,
                    ) );

                if( ! empty($_GET['ex_update_taxanomy']) ) {
                    $categories = $ex->parse_categories_recursive();
                    $brands = $ex->parse_brands();
                    $warehouses = $ex->parse_warehouses();
                    $ex::update_categories( $categories );
                    $ex::update_warehouses( $warehouses );
                    $ex::update_brands( $brands );

                    $_SESSION['ex_notice_message'] = (object) array(
                        'status' => 'success',
                        'message' => __( 'Taxanomies updated', DOMAIN),
                        );

                    wp_redirect( get_site_url() . remove_query_arg('ex_update_taxanomy') );
                    exit;
                }

                if( ! empty($_GET['ex_update_product']) ) {
                    $products = $ex->parse_products();
                    $ex::update_posts( $products );
                    $ex::update_postmetas( $products );

                    $_SESSION['ex_notice_message'] = (object) array(
                        'status' => 'success',
                        'message' => __( 'Products updated', DOMAIN),
                        );

                    wp_redirect( get_site_url() . remove_query_arg('ex_update_product') );
                    exit;
                }

                if( ! empty($_GET['ex_update_relationships']) ) {
                    $products = $ex->parse_products();
                    // has offset by product count and products
                    $ex::update_cat_relationships( $products );
                    $ex::update_wh_relationships( $products );
                    $ex::update_brand_relationships( $products );

                    $_SESSION['ex_notice_message'] = (object) array(
                        'status' => 'success',
                        'message' => __( 'Relationships updated', DOMAIN),
                        );

                    wp_redirect( get_site_url() . remove_query_arg('ex_update_relationships') );
                    exit;
                }
            }

            if( ! empty($_SESSION['ex_notice_message']) ) {
                $page::add_notice( $_SESSION['ex_notice_message'] );
                unset($_SESSION['ex_notice_message']);
            }
        }

        $page->set_assets( array(__CLASS__, '_assets') );

        // $page->add_metabox( 'metabox0', 'metabox0', array(__CLASS__, 'metabox0_callback'), $position = 'main');
        $page->add_metabox( 'metabox1', __('Запуск импорта'), array(__CLASS__, 'metabox1_callback'), $position = 'side');
        // $page->add_metabox( 'metabox2', 'metabox2', array(__CLASS__, 'metabox2_callback'), $position = 'side');
        $page->set_metaboxes();
    }

    static function _assets()
    {
        $purl = Utils::get_plugin_url('assets/');

        wp_enqueue_style( 'exchange-page', $purl . 'exchange-page.css' );
        wp_enqueue_script( 'exchange-requests', $purl . 'exchange-requests.js' );
    }

    static function init() { self::$instance = new self(); }

    /**
     * Основное содержимое страницы
     *
     * @access
     *     must be public for the WordPress
     */
    function page_render() {
        echo "<div class='progress'><div class='progress-fill'></div></div><div id='ajax_action'></div>";

        /*
        echo sprintf('1. <a href="%s" class="button button-primary">%s</a><br><br>',
            add_query_arg( array('ex_update_taxanomy' => '1') ),
            __( 'Update Taxanomies', DOMAIN ) );

        echo sprintf('2. <a href="%s" class="button button-primary">%s</a><br><br>',
            add_query_arg( array('ex_update_product' => '1') ),
            __( 'Update Products', DOMAIN ) );

        echo sprintf('3. <a href="%s" class="button button-primary">%s</a><br><br>',
            add_query_arg( array('ex_update_relationships' => '1') ),
            __( 'Update relationships', DOMAIN ) );
        */
        ?>
        <!-- <p>В последний раз база обновлялась <span>19.12.17</span></p> -->
        <?php
        if( init::is_import_file_exists() ) {
            echo "<br /><span style='color: green;'>Файл импорта обнаружен</span> (последнее изменение: "
                . date('d.m.Y H:i:s', filemtime( wp_upload_dir()['basedir'] . Init::IMPORT ))
                . ')';
        }
        else {
            echo "<span style='color: red;'>Файл импорта не найден</span>";
        }

        if( init::is_offers_file_exists() ) {
            echo "<br /><span style='color: green;'>Файл предложений обнаружен</span> (последнее изменение: "
                . date('d.m.Y H:i:s', filemtime( wp_upload_dir()['basedir'] . Init::OFFERS ))
                . ')';
        }
        else {
            echo "<span style='color: red;'>Файл предложений не найден</span>";
        }
    }

    static function metabox0_callback()
    {
        echo "main";
    }

    static function metabox1_callback()
    {
        ?>
        <div id="timer" class='ex-timer'>
            <span class='hours'>0</span>:<span class='minutes'>00</span>:<span class='seconds'>00</span>
        </div>

        <p>
            <button type="button" class="button button-danger right" id="stop-exchange">Остановить импорт</button>
            <button type="button" class="button button-primary" id="exchangeit" data-action="start">Начать</button>
        </p>

        <p>
            <small>
                <span style="color: red;">*</span> Пауза не прерывает последний запрос, а останавливает импорт после него.
            </small>
        </p>
        <?php
    }

    static function metabox2_callback()
    {
        echo "side2";
    }
}
add_action( 'init', array(__NAMESPACE__ . '\Admin_Page', 'init'), 10);
