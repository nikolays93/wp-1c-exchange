<?php

class Exchange_Product
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

            if( $post_id > 0 ) {
                $arrTerm = explode('/', $self->terms[0]);
                $term_id = get_term_id_from_external(str_replace('\\', '', end( $arrTerm )));

                wp_set_object_terms( $post_id, (int)$term_id, 'product_cat', false );
            }

            Exchange_Utils::update_item_map( $pid, $post_id );

            /**
             * Update Metas
             */
            if( in_array($status, apply_filters( 'exchange_update_def_meta_status', array('updated', 'created') ) ) ) {
                $self->arrMeta = wp_parse_args( $self->arrMeta, array(
                    '_sku' => $self->_sku,
                    // '_external_sku' => $self->_sku,
                    // '_external_id'  => $self->_sku,

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

                    /**
                     * for 2.5.2 version
                     */
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

            if( $self->arrMeta['_stock'] >= 1 ) {
                $self->arrMeta['_stock_status'] = 'instock';
            }

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

                self::updateProductAttributes($post_id, $attributes);
            }

            if( $i >= $to )
                break;
        }

        wp_die();
    }

    /**
     * @param string $attribute_name Attribute slug without pa_
     */
    // updateWooAtt
    public static function updateProductAttribute( $attribute_name, $attribute_label = false, $args = false )
    {
        global $wpdb;

        $tablename = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        // $attribute = $wpdb->get_row(
        //     $wpdb->prepare( "SELECT * FROM {$tablename} WHERE `attribute_name` = %s LIMIT 1;", $attribute_name )
        // );

        $attribute = false;
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if( !is_array($attribute_taxonomies) ) {
            $attribute_taxonomies = array();
        }

        foreach ($attribute_taxonomies as $_attr) {
            if( $_attr->attribute_name == $attribute_name ){
                $attribute = $_attr;
                break;
            }
        }

        if( $attribute_label ) {
            if( $attribute ){
                $args = array(
                    'attribute_id'      => $attribute->attribute_id,
                    'attribute_name'    => $attribute->attribute_name,
                    'attribute_label'   => $attribute_label,
                    'attribute_type'    => $attribute->attribute_type,
                    'attribute_orderby' => $attribute->attribute_orderby,
                    'attribute_public'  => $attribute->attribute_public,
                    );

                $result = $wpdb->update(
                    $tablename,
                    $args,
                    array( 'attribute_id' => $attribute->attribute_id ),
                    array( '%d', '%s', '%s', '%s', '%s', '%d' ),
                    array( '%d' )
                    );
                $action = 'updated';
            }
            else {
                $args = wp_parse_args( $args, array(
                    'attribute_name' => $attribute_name,
                    'attribute_label' => $attribute_label,
                    'attribute_type' => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public' => 0,
                    ) );

                $result = $wpdb->insert(
                    $tablename,
                    $args,
                    array( '%s', '%s', '%s', '%s', '%d' )
                    );

                if( ! $result ) {
                    return false;
                }

                $args['attribute_id'] = $wpdb->insert_id;
                $action = 'created';
            }

            $attribute = (object)$args;

            // set_transient( 'wc_attribute_taxonomies', $attribute_taxonomies );
            delete_transient( 'wc_attribute_taxonomies' );
        }

        $result = new stdClass();
        $result->attribute = $attribute;
        $result->action    = $action;
        return $result;
    }

    /**
     * Обновить значение атрибута товара
     * Создать таксаномию(Атрибут), теримн(Значение атрибута) если понадобится
     *
     * @param  absint   $post_id ID записи которой задаем аттрибуты
     * @param  array    $atts    Масив с объектами атрибутов
     * @param  boolean  $append  Добавить значения к сущетвующим если true
     */
    public static function updateProductAttributes( $post_id, $atts, $append = false )
    {
        /**
         * Получаем аттрибуты, если их нет, задаем пустой массив
         *
         * @attention: $product_attributes has pa_
         *             unlike $atts && updateWooAtt()
         */
        $product_attributes = ($append) ? get_post_meta( $post_id, '_product_attributes', true ) : array();

        if( !is_array($product_attributes) ) {
            $product_attributes = array();
        }

        foreach ($atts as $att) {
            /**
             * @var stdClass $att объект атрибута
             *
             * @prop attribute_name
             * @prop attribute_label
             * @prop attribute_value
             */
            $pa_tax = 'pa_' . htmlspecialchars( stripslashes($att->attribute_name) );
            self::updateWooAtt( $att->attribute_name, $att->attribute_label );

            $term_id = self::get_item_map_id( $att->attribute_value );
            $insert = ( $term_id ) ? (int)$term_id : $att->attribute_value;

            if( term_exists( $insert, $pa_tax ) ){
                $current_term = wp_update_term( $insert, $pa_tax, array(
                    'name' => $att->attribute_value,
                    ) );
            }
            else {
                $current_term = wp_insert_term( $insert, $pa_tax );
            }

            if( is_wp_error($current_term) )
                continue;

            if( ! $term_id ) {
                self::update_item_map( $att->attribute_value, (int)$current_term['term_id'] );
            }

            $test = wp_set_object_terms( $post_id, (int)$current_term['term_id'], $pa_tax, $append );

            $product_attributes[] = array (
              'name' => $pa_tax,
              'value' => $att->attribute_value,
              'position' => 5,
              'is_visible' => 1,
              'is_variation' => 1,
              'is_taxonomy' => 1
              );
        }

        update_post_meta($post_id, '_product_attributes', $product_attributes);
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
