<?php

class Exchange_Product extends Cached_Item
{
    const FILE = 'products.cache';

    public $_sku;
    public $title;
    public $content;
    public $terms = array();

    public $arrMeta = array();
    public $arrAttributes = array();

    function __construct( $productData )
    {
        $productData = wp_parse_args( $productData, array(
            'title' => '',
            'content' => '',
            'terms' => array(),
            '_sku' => '',
            ) );

        $this->_sku =    $productData['_sku'];
        $this->title =   $productData['title'];
        $this->content = $productData['content'];
        $this->terms =   $productData['terms'];

        Exchange::$countProducts++;
    }

    function setMetas( $metas )
    {
        $metas = wp_parse_args( $metas, array(
            //'_sku' => '',
            '_1c_sku' => $this->_sku,
            '_1c_id'  => $this->_sku,

            '_price' => '',
            '_regular_price' => '',

            '_sale_price' => '',
            '_sale_price_dates_from' => '',
            '_sale_price_dates_to'   => '',

            '_stock' => 0,

            'total_sales' => 0,

            '_tax_status' => 'taxable',
            '_tax_class'  => '',

            '_manage_stock' => 'yes',
            '_stock' => 0,
            '_stock_status' => 'outofstock',

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

        $this->arrMeta = $metas;
        $this->arrMeta['_price'] =         Exchange_Utils::sanitizePrice( $metas['_price'] );
        $this->arrMeta['_regular_price'] = Exchange_Utils::sanitizePrice( $metas['_regular_price'] );
        $this->arrMeta['_sale_price'] =    Exchange_Utils::sanitizePrice( $metas['_sale_price'] );
        if( $metas['_stock'] >= 1 ) $this->arrMeta['_stock_status'] = 'instock';
    }

    function setAttributes( $arrAttributes )
    {
        $this->arrAttributes = $arrAttributes;
    }

    /**
     * Products
     *
     * Записывает информацию о товарах
     * Записывает в базу сопоставление внешний_ид => post->ID
     *
     * @hook wp_ajax_exchange_insert_posts
     * @access private
     */
    static function insertOrUpdate( $count = 50 )
    {
        if( ! wp_verify_nonce( $_POST['nonce'], Exchange::SECURITY ) ) {
            wp_die('Ошибка! нарушены правила безопасности');
        }

        $products = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . self::FILE) );
        if( !is_array($products) )
            wp_die();

        $to = $_POST['at_once'] * $_POST['counter'];
        $from = $to - $_POST['at_once'];

        $i = 0;
        foreach ($products as $pid => $self) {
            $i++;

            if($i <= $from)
                continue;

            $args = array(
                'post_author' => 1,
                'post_title' => $self->title,
                'post_content' => $self->content,
                'post_status' => "publish",
                'post_parent' => '',
                'post_type' => "product",
                /**
                 * @todo  rewrite get_product_terms_from_map
                 */
                 // 'tax_input' => Exchange_Utils::get_product_terms_from_map($product),
                );

            if( $post_id = Exchange_Utils::get_item_map_id( $pid ) ) {
                // Update post
                $args['ID'] = $post_id;

                $post_id = wp_update_post($args);
                $status = 'updated';
            }
            else {
                // Create post
                $post_id = wp_insert_post( $args );
                $status = 'created';
            }

            if( is_wp_error($post_id) )
              return;

            Exchange_Utils::update_item_map( $pid, $post_id );

            /**
             * Update Metas
             */
            foreach ($self->arrMeta as $meta_key => $meta_value) {
                update_post_meta( $post_id, $meta_key, $meta_value);
            }

            /**
             * Update Attributes
             */
            if( in_array($status, apply_filters( 'exchange_update_att_status', array('updated', 'created') ) )  ){
                $attributes = array();
                foreach ($self->arrAttributes as $attr_key => $attr_val) {
                    $attr = new \stdClass();
                    switch ($attr_key) {
                        case 'manufacturer': $attr->attribute_label = 'Производитель'; break;
                        case 'model': $attr->attribute_label = 'Модель'; break;
                        case 'width': $attr->attribute_label = 'Ширина'; break;
                        case 'diametr': $attr->attribute_label = 'Диаметр'; break;
                        case 'height': $attr->attribute_label = 'Высота'; break;
                        case 'index': $attr->attribute_label = 'Индекс'; break;
                        case 'pcd': $attr->attribute_label = 'PCD'; break;
                        case 'flying': $attr->attribute_label = 'Вылет'; break;
                        case 'dia': $attr->attribute_label = 'DIA'; break;
                        case 'color': $attr->attribute_label = 'Цвет'; break;
                        case 'seasonality': $attr->attribute_label = 'Сезонность'; break;
                    }

                    if( !isset($attr->attribute_label) )
                        continue;

                    $attr->attribute_name = $attr_key;
                    $attr->attribute_value = $attr_val;
                    $attributes[] = $attr;
                }

                Exchange_Utils::updateProductAttributes($post_id, $attributes);
            }

            if( $i >= $to )
                break;
        }

        wp_die();
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