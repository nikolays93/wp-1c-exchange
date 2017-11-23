<?php

namespace CDevelopers\Exchange;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

class Admin_Page
{
    function __construct()
    {
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

        $page->set_assets( array($this, '_assets') );

        $page->add_metabox( 'metabox1', __('Импорт'), array($this, 'metabox1_callback'), $position = 'side');
        $page->add_metabox( 'metabox2', __('Затраченное время'), array($this, 'metabox2_callback'), $position = 'side');
        $page->add_metabox( 'metabox3', __('Настройки'), array($this, 'metabox3_callback'), $position = 'side');

        // $page->add_metabox( 'exchange_debug', __('Информация о кэшировании'), array($this, 'ex_settings_debug'), 'normal');
        $page->set_metaboxes();
    }

    function _assets()
    {
        wp_deregister_script('heartbeat');

        $url = Utils::get_plugin_url();
        // wp_enqueue_script( 'products_request', $url . '/assets/requests.js', 'jquery', '1.0' );
        wp_enqueue_style( 'products_request-css', $url . '/assets/exchange.css', null, '1.0' );

        // wp_localize_script(
        //     'products_request',
        //     'request_settings',
        //     array(
        //         'nonce'    => wp_create_nonce( EXCHANGE_SECURITY ),
        //         'products_at_once' => 50,
        //         'products_count' => Exchange_Cache::$countProducts,
        //         'cats_count'     => Exchange_Cache::$countCategories,
        //         )
        //     );
        // wp_enqueue_style();
        // wp_enqueue_script();
    }

    /**
     * Основное содержимое страницы
     *
     * @access
     *     must be public for the WordPress
     */
    function page_render() {
        ?>
        <div class='progress'><div class='progress-fill'></div></div>
        <div id='ajax_action'></div>

        <strong>Обновлено:</strong>
        <div>Складов и брэндов: <span>0</span></div>
        <div>Категорий: <span>0</span></div>
        <div>Товаров: <span>0</span></div>

        <textarea name='logs' id='exchange-logs' cols='30' rows='10'></textarea>
        <?php
    }

    /**
     * Тело метабокса вызваное функций $this->add_metabox
     *
     * @access
     *     must be public for the WordPress
     */
    function metabox1_callback() {
        // print_r( Utils::get( 'all' ) );
        ?>
        <table class="widefat" id="ex-actions">
            <tr>
                <td><input type="checkbox" name="" id="ex_taxes"></td>
                <td><label for="ex_taxes">Импорт складов и брэндов</label></td>
            </tr>
            <tr>
                <td><input type="checkbox" name="" id="ex_categories"></td>
                <td><label for="ex_categories">Импорт категорий</label></td>
            </tr>
            <!--
            <tr>
                <td><input type="checkbox" name="" id="ex_attributes"></td>
                <td><label for="ex_attributes">Импорт аттрибутов</label></td>
            </tr> -->
            <tr>
                <td><input type="checkbox" name="" id="ex_products" checked="true"></td>
                <td><label for="ex_products">Импорт товаров</label></td>
            </tr>
        </table>
        <p>
            <button type="button" class="button button-danger right" id="stop-exchange">Остановить импорт</button>
            <button type="button" class="button button-primary" id="exchangeit" data-action="start">Начать</button>
        </p>
        <p>
            <small>
                <span style="color: red;">*</span> Пауза не прерывает последний запрос, а останавливает импорт после него.
            </small>
        </p>

        <!-- <p><button type='button' class='button button-primary' id='load-categories'>Загрузить категории</button></p> -->
        <!-- <p><button type='button' class='button button-primary' id='load-products'>Загрузить товар</button></p> -->
        <?php
    }

    function metabox2_callback() {
        ?>
        <div id="timer" class='ex-timer'>
            <span class='hours'>0</span>:<span class='minutes'>00</span>:<span class='seconds'>00</span>
        </div>
        <?php
    }

    function metabox3_callback() {
        if( ! $per_once = Utils::get('per_once') ) $per_once = 0; ?>
        <table class="form-table">
            <!--
            <tr>
                <td>Обновлять категории</td>
                <td>
                    <input type="checkbox" value="on" name="[cat_upd]">
                </td>
            </tr>
            <tr>
                <td>Обновлять аттрибуты</td>
                <td>
                    <input type="checkbox" value="on" name="[att_upd]">
                </td>
            </tr> -->
            <tr>
                <td>Обработать за запрос</td>
                <td>
                    <?php
                    echo '<input type="number" style="width: 60px;" name="'.
                        Utils::OPTION.'[per_once]" value="'.$per_once.'">';
                    ?>
                </td>
            </tr>
        </table>

        <input type="submit" name="submit" class="button button-primary right" value="Сохранить настройки">
        <div class="clear"></div>
        <?php
    }
}
new Admin_Page();


// if( ! $per_once = Utils::get('per_once') ) $per_once = 0;
//             $ex = new Init( array(
//                 'offset' => $per_once,
//                 'part'   => 1,
//             ) );

//                     echo "<pre>";

        // no has offset
        // $categories = $ex->parse_categories_recursive();
        // $brands = $ex->parse_brands();
        // $warehouses = $ex->parse_warehouses();
        // $ex::update_categories( $categories );
        // $ex::update_warehouses( $warehouses );
        // $ex::update_brands( $brands );

        // $products = $ex->parse_products();
        // var_dump( count($products) );
        // $ex::update_posts( $products );
        // $ex::update_postmetas( $products );

        // has offset by product count and products
        // $ex::update_cat_relationships( $products );
        // $ex::update_wh_relationships( $products );
        // $ex::update_brand_relationships( $products );

        // echo "</pre>";