<?php

class Exchange_Product
{
    const FILE = 'products.cache';

    public $_sku;
    public $title;
    public $content;
    public $terms = array();
    public $atts = array();

    public $arrMeta = array();

    public static $created = 0;
    public static $updated = 0;

    function __construct( $productData )
    {
        $productData = wp_parse_args( $productData, array(
            'title' => '',
            'content' => '',
            'terms' => array(),
            'atts' => array(),
            '_sku' => '',
            ) );

        $this->_sku =    $productData['_sku'];
        $this->title =   $productData['title'];
        $this->content = $productData['content'];
        $this->terms =   $productData['terms'];
        $this->atts =   $productData['atts'];

        Exchange_Cache::$countProducts++;
    }

    function setMetas( $metas )
    {
        $this->arrMeta = $metas;

        if( isset($metas['_price']) ) {
            $this->arrMeta['_price'] = sanitize_price( $metas['_price'] );
            $this->arrMeta['_regular_price'] = sanitize_price( $metas['_price'] );
        }
    }

    private static function set_terms( $post_id, $product )
    {
        $append = false;
        foreach ($product->terms as $term) {
            $arrTerm = explode('/', $term);
            $term_id = get_term_id_from_external( wp_unslash( end( $arrTerm ) ) );

            if( $term_id ) {
                // error_log( 'Не найдена категория' );
                wp_set_object_terms( $post_id, (int)$term_id, 'product_cat', $append );
            }

            $append = true;
        }
    }

    private static function set_metas( $post_id, $product, $status )
    {
        if( in_array($status, apply_filters('exchange_update_def_meta_status', array('created'))) ) {
            $product->arrMeta = wp_parse_args( $product->arrMeta, array(
                // '_external_sku' => $product->_sku,
                // '_external_id'  => $product->_sku,

                '_price' => '',
                '_regular_price' => '',

                '_sale_price' => '',
                '_sale_price_dates_from' => '',
                '_sale_price_dates_to'   => '',

                'total_sales' => 0,

                '_tax_status' => 'taxable',
                '_tax_class'  => '',

                '_manage_stock' => 'yes',
                '_stock' => 0,
                '_stock_status' => 'outofstock',

                '_visibility' => 'visible',
                '_featured'   => 'no',

                '_backorders' => 'no',
                '_sold_individually' => '',

                '_weight' => '',
                '_length' => '',
                '_width'  => '',
                '_height' => '',

                '_upsell_ids'    => 'a:0:{}',
                '_crosssell_ids' => 'a:0:{}',

                '_purchase_note' => '',
                '_default_attributes' => "a:0:{}",
                '_virtual' => 'no',
                '_downloadable' => 'no',
                '_product_image_gallery' => '',
                '_download_limit' => '-1',
                '_download_expiry' => '-1',
                '_product_version' => '3.0.6',
                ) );
        }

        if( $product->arrMeta['_stock'] >= 1 ) {
            $product->arrMeta['_stock_status'] = 'instock';
        }

        foreach ($product->arrMeta as $meta_key => $meta_value) {
            update_post_meta( $post_id, $meta_key, $meta_value);
        }
    }

    private static function set_atts ( $post_id, $product, $status )
    {
        if( in_array($status, apply_filters( 'exchange_update_att_status', array('updated', 'created') ) )  ){

            /**
             * Получаем аттрибуты, если их нет, задаем пустой массив
             *
             * @attention: $product_attributes has pa_
             *             unlike $atts && updateProductAttribute()
             */
            $product_attributes = ($append) ? get_post_meta( $post_id, '_product_attributes', true ) : array();

            if( !is_array($product_attributes) ) {
                $product_attributes = array();
            }

            foreach ($product->atts as $attr_key => $attr_val) {
                $attribute_name = 'pa_' . htmlspecialchars( stripslashes($attr_key) );

                $term_id = get_item_map_id( $attr_val );
                wp_set_object_terms( $post_id, (int)$term_id, $attribute_name, $append = true );

                $product_attributes[] = array (
                    'name' => $attribute_name,
                    'value' => $att->attribute_value,
                    'position' => 5,
                    'is_visible' => 1,
                    'is_variation' => 1,
                    'is_taxonomy' => 1
                );
            }

            update_post_meta($post_id, '_product_attributes', $product_attributes);
        }
    }

    private static function import( $pid, $product )
    {
        $args = array(
            'post_author' => 1,
            'post_title' => $product->title,
            'post_content' => $product->content,
            'post_status' => "publish",
            'post_parent' => '',
            'post_type' => "product",
        );

        if( $post_id = get_item_map_id( $pid ) ) {
            $args['ID'] = $post_id;

            $post_id = wp_update_post($args);
            $status = 'updated';
        }
        else {
            $post_id = wp_insert_post( $args );
            $status = 'created';
        }

        /**
         * @todo  change wp_die to error_log
         */
        if( is_wp_error($_term) ){
            $err = ajax_answer( $category->name .' : '. $_term->get_error_message() );
            wp_die($err);
        }

        if( $status === 'created' ) {
            update_post_meta( $post_id, '_sku', $product->_sku );
            self::$created++;
        }
        else {
            self::$updated++;
        }

        if( $post_id >= 1 ) {
            update_item_map( $pid, $post_id );
            return array('post_id' => $post_id, 'status' => $status );
        }

        return false;
    }

    /**
     * Products
     *
     * Записывает информацию о товарах
     * Записывает в базу сопоставление внешний_ид => post->ID
     *
     * @hook wp_ajax_exchange_insert_posts
     */
    public static function initImport()
    {
        // ex_check_security();

        $products = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . self::FILE) );
        if( ! is_array($products) ) {
            $err = ajax_answer( 'Товары не найдены' );
            wp_die( $err );
        }

        $st = ex_parse_settings();

        $_POST['counter'] = 1;
        $to = $st['per_once'] * $_POST['counter'];
        $from = $to - $st['per_once'];

        $i = 0;
        foreach ($products as $pid => $product) {
            $i++;

            if($i <= $from)
                continue;

            if( $import['post_id'] = self::import($pid, $product) ) {
                self::set_terms( $import['post_id'], $product );
                self::set_metas( $import['post_id'], $product, $import['status'] );
                self::set_atts ( $import['post_id'], $product, $import['status'] );
            }

            if( $i >= $to )
                break;
        }

        if( $count < $st['per_once'] ) {
            ajax_answer('Импорт товаров завершен', 2, array(
                'created' => self::$created,
                'updated' => self::$updated,
            ));
        }
        else {
            ajax_answer('Итерация пройдена', 1, array(
                'created' => self::$created,
                'updated' => self::$updated,
            ));
        }
    }

    // function add_product_meta($pid, $product, $post_id){
    // $def_price = isset($product['offer'][$pid]['regular_price']) ? $product['offer'][$pid]['regular_price'] : false;

    // if( isset($product['offer'][$pid]['regular_price']) )
    //     $metas['_regular_price'] = $product['offer'][$pid]['_regular_price'];

    // if( isset($product['offer'][$pid]['_price']) )
    //   $metas['_price'] = $product['offer'][$pid]['_price'];

    // if( isset($product['offer'][$pid]['_stock']) )
    //     $metas['_stock'] = $product['offer'][$pid]['_stock'];

    // $metas['_stock'] = absint( $metas['_stock'] );

    // if( $metas['_stock'] > 0 )
    //     $metas['_stock_status'] = 'instock';

    // if( isset($product['offer'][$pid]['stock_wh']) && is_array($product['offer'][$pid]['stock_wh']) )
    //     update_post_meta( $post_id, '_stock_wh', serialize( $product['offer'][$pid]['stock_wh'] ) );
    // }
}
// echo "<pre style='margin: 10px 170px;'>";
// var_dump( Exchange_Product::initImport() );
// echo "</pre>";