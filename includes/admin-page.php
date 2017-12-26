<?php

namespace CDevelopers\Exchange;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

class Admin_Page
{
    static $instance;
    private $resourses;

    function __construct()
    {
        if( ! is_admin() )
            return;

        $this->check_actions();

        $ex = new Init();
        $this->resourses = array(
            'offset'       => ($offset = Utils::get('offset')) ? $offset : 1000,
            'import_size'  => $ex->get_import_size(),
            'import_mtime' => filemtime( wp_upload_dir()['basedir'] . Init::IMPORT ),
            'offers_size'  => $ex->get_offers_size(),
            'offers_mtime' => filemtime( wp_upload_dir()['basedir'] . Init::OFFERS ),
            'categories'   => $ex->parse_categories_recursive(),
            'brands'       => $ex->parse_brands(),
            'warehouses'   => $ex->parse_warehouses(),
        );

        $this->resourses['categories_size'] = count($this->resourses['categories']);
        $this->resourses['brands_size']     = count($this->resourses['brands']);
        $this->resourses['warehouses_size'] = count($this->resourses['warehouses']);

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

        // $page->add_metabox( 'metabox0', 'metabox0', array(__CLASS__, 'metabox0_callback'), $position = 'main');
        $page->add_metabox( 'metabox1', __('Запуск импорта'), array(__CLASS__, 'metabox1_callback'), $position = 'side');
        $page->add_metabox( 'metabox2', __('Настройки'), array(__CLASS__, 'metabox2_callback'), $position = 'side');
        $page->set_metaboxes();
    }

    function _assets()
    {
        $purl = Utils::get_plugin_url('assets/');

        wp_enqueue_style( 'exchange-page', $purl . 'exchange-page.css' );
        wp_enqueue_script(  'exchange-requests', $purl . 'exchange-requests.js' );
        wp_localize_script( 'exchange-requests', 'resourses', array(
            'offset'          => $this->resourses['offset'],
            'import_size'     => $this->resourses['import_size'],
            'offers_size'     => $this->resourses['offers_size'],
            'categories_size' => $this->resourses['categories_size'],
            'brands_size'     => $this->resourses['brands_size'],
            'warehouses_size' => $this->resourses['warehouses_size'],
        ) );
    }

    static function init() {
        self::$instance = new self(); }

    function check_actions()
    {
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
    }

    /**
     * Основное содержимое страницы
     *
     * @access
     *     must be public for the WordPress
     */
    function page_render() {
        echo "
        <div class='progress'><div class='progress-fill'></div></div>
        <div id='ajax_action' style='text-align: center;'></div>";

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

        if( $prs = $this->resourses['import_size'] ) {
            echo '<br><span style="color: green;">Найдено товаров:</span> ' . $prs;
            echo ' (Последнее изменение: ' . date('d.m.Y H:i:s', $this->resourses['import_mtime']) . ')';
        }
        else {
            echo "<br /><span style='color: red;'>Файл импорта поврежден или не обнаружен</span>";
        }

        if( $ofs = $this->resourses['offers_size'] ) {
            echo '<br><span style="color: green;">Найдено предложений:</span> ' . $ofs;
            echo ' (Последнее изменение: ' . date('d.m.Y H:i:s', $this->resourses['offers_mtime']) . ')';
        }
        else {
            echo "<br /><span style='color: red;'>Файл предложений поврежден или не обнаружен</span>";
        }

        echo '<br>Найдено категорий: ' . $this->resourses['categories_size'];
        echo '<br>Найдено производителей: ' . $this->resourses['brands_size'];
        echo '<br>Найдено складов: ' . $this->resourses['warehouses_size'];
        echo " (Из них ".Init::set_warehouses_relations( $this->resourses['warehouses'] )." будет обновлено)";
    }

    static function metabox1_callback()
    {
        ?>
        <div id="timer" class='ex-timer'>
            <span class='hours'>0</span>:<span class='minutes'>00</span>:<span class='seconds'>00</span>
        </div>

        <p>
            <button type="button" class="button button-danger right" id="stop-exchange">Прервать импорт</button>
            <button type="button" class="button button-primary" id="exchangeit" data-action="start">Начать</button>
        </p>

        <p>
            <small>
                <span style="color: red;">*</span> Если прервать импорт, возобновить его не получится.
            </small>
        </p>
        <?php
    }

    static function metabox2_callback()
    {
        $data = array(
            array(
                'id'      => 'offset',
                'type'    => 'number',
                'label'   => 'Обработать за раз<br>',
                'default' => '1000',
                // 'desc'  => 'This is example text field',
            ),
        );

        $form = new WP_Admin_Forms( $data, $is_table = false, $args = array(
            // Defaults:
            // 'admin_page'  => true,
            // 'item_wrap'   => array('<p>', '</p>'),
            // 'form_wrap'   => array('', ''),
            // 'label_tag'   => 'th',
            // 'hide_desc'   => false,
        ) );
        echo $form->render();
        submit_button( 'Сохранить', 'primary', 'save_changes' );
    }
}
add_action( 'init', array(__NAMESPACE__ . '\Admin_Page', 'init'), 10);
