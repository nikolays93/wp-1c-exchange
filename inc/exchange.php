<?php

// rename callbacks
// replace all AJAX_ACTION_NAME
// change any_secret_string

/**
 * Add Script Variables
 *
 * Подключаем нужные скрипты и
 * Передаем скрипту requests.js переменную request_settings
 */
add_action( 'admin_enqueue_scripts', 'add_ajax_data', 99 );
function add_ajax_data(){
  /**
   * screen->id изменяется если изменить положение сслыки в меню.
   */
  if( $screen = get_current_screen() ){
    if( $screen->id != 'woocommerce_page_exchange' )
      return;

    wp_enqueue_script( 'products_request', EXCHANGE_PLUG_URL . '/requests.js', 'jquery', '1.0' );
    wp_enqueue_style( 'products_request-css', EXCHANGE_PLUG_URL . '/exchange.css', null, '1.0' );

    $import = new ImportProducts();
    $import->searchImportFiles();

    wp_localize_script(
      'products_request',
      'request_settings',
      array(
        'nonce'    => wp_create_nonce( 'any_secret_string' ),
        'products_at_once' => 40,
        'products_count' => $import->getProductsCount(),
        'cats_count'     => $import->getСategoriesСount(),
        )
      );
  }
}

/**
 * Products
 *
 * Записывает информацию о товарах
 * Записывает в базу сопоставление внешний_ид => post->ID
 */
function add_product_meta($pid, $product, $post_id){
    $def_price = isset($product['offer'][$pid]['regular_price']) ? $product['offer'][$pid]['regular_price'] : false;
    $metas = wp_parse_args( $product['metas'], array(
        '_sku' => '',                  // :)
        '_1c_sku' => $product['_sku'], // :)
        '_1c_id' => (string) $pid,

        '_price' => $def_price,
        '_regular_price' => $def_price,

        '_sale_price' => '',
        '_sale_price_dates_from' => '',
        '_sale_price_dates_to'   => '',

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

    if( isset($product['offer'][$pid]['regular_price']) )
        $metas['_regular_price'] = $product['offer'][$pid]['_regular_price'];

    if( isset($product['offer'][$pid]['_price']) )
      $metas['_price'] = $product['offer'][$pid]['_price'];

    if( isset($product['offer'][$pid]['_stock']) )
        $metas['_stock'] = $product['offer'][$pid]['_stock'];

    $metas['_stock'] = absint( $metas['_stock'] );

    if( $metas['_stock'] > 0 )
        $metas['_stock_status'] = 'instock';

    if( isset($product['offer'][$pid]['stock_wh']) && is_array($product['offer'][$pid]['stock_wh']) )
        update_post_meta( $post_id, '_stock_wh', serialize( $product['offer'][$pid]['stock_wh'] ) );

    foreach ($metas as $meta_key => $meta_value) {
        if( $meta_value !== false )
          update_post_meta( $post_id, $meta_key, $meta_value);
    }
}

add_action('wp_ajax_exchange_insert_posts', 'exchange_insert_posts');
function exchange_insert_posts() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) ) {
    wp_die('Ошибка! нарушены правила безопасности');
  }

  $products = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/products.cache') );
  if( !is_array($products) )
    wp_die();

  $to = $_POST['at_once'] * $_POST['counter'];
  $from = $to - $_POST['at_once'];

  $i = 0;
  foreach ($products as $pid => $product) {
    $i++;

    if($i <= $from)
        continue;

    $args = array(
        'post_author' => 1,
        'post_title' => $product['title'],
        'post_content' => $product['content'],
        'post_status' => "publish",
        'post_parent' => '',
        'post_type' => "product",
        'tax_input' => ExchangeUtils::get_product_terms_from_map($product),
        );

    if( $post_id = ExchangeUtils::get_item_map_id( $pid ) ) {
      // Edit post
      $args['ID'] = $post_id;

      $post_id = wp_update_post($args);
    }
    else {
      // Create post
      $post_id = wp_insert_post( $args );
    }

    if( is_wp_error($post_id) )
      return;

    ExchangeUtils::update_item_map( $pid, $post_id );

    add_product_meta($pid, $product, $post_id);
    if( $i >= $to )
        break;
  }

  wp_die();
}

/**
 * Terms
 *
 * Записывает категории товаров
 */

define('SHINA_ID', 15);
define('DISC_ID', 16);


/**
 * Insert hierarchical terms
 */
function recursive_add_wp_term( $id, $data, $parent = false ) {
  /**
   * @todo: see! it's for TiresWorld only
   */
  $args = array(
    'description'=> '',
    //'slug' => $data['slug'],
    'parent' => $parent ? $parent : null,
    );

  if( $data['is_shina'] )
    $args['parent'] = SHINA_ID;

  if( $data['is_disc'] )
    $args['parent'] = DISC_ID;

  if( $term_id = ExchangeUtils::get_item_map_id( $id ) ){
    $args['name'] = $data['name'];
    wp_update_term( $term_id, 'product_cat', $args );
  }
  else {
    $_term = wp_insert_term($data['name'], 'product_cat', $args);
  }

  if( is_wp_error($_term) || ! isset($_term['term_id']) )
    return;

  $_term_id = (int) $_term['term_id'];

  ExchangeUtils::update_item_map( $id, $_term_id );
  update_term_meta( $_term_id, '_1c_term_id', (string) $id );

  if( isset($data['parent']) ){
    foreach ($data['parent'] as $child_id => $child_data ) {
      // Тоже самое проделвыаем с дочерними терминами
      recursive_add_wp_term($child_id, $child_data, $_term_id );
    }
  }
}

add_action('wp_ajax_exchange_insert_terms', 'exchange_insert_terms');
function exchange_insert_terms() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) ) {
    wp_die('Ошибка! нарушены правила безопасности');
  }

  $terms = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/categories.cache') );
  if( ! is_array($terms) ){
    wp_die();
  }

  // Обработать Начиная с..
  $from = $to - $_POST['at_once'];
  $to = $_POST['at_once'] * $_POST['counter'];

    $i = 0;
    foreach ($terms as $tid => $term) {
        // @todo: TiresWorld
        $tid = $term['name'];
        $i++;

        if($i <= $from)
            continue;

        recursive_add_wp_term($tid, $term, false );

        if( $i >= $to )
            break;
    }

  wp_die();
}

/**
 * Insert WooCoommerce Attributes
 */
add_action('wp_ajax_exchange_insert_atts', 'exchange_insert_atts');
function exchange_insert_atts() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) ) {
    wp_die('Ошибка! нарушены правила безопасности');
  }

  $products = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/products.cache') );
  if( !is_array($products) )
    wp_die();

  $to = $_POST['at_once'] * $_POST['counter'];
  $from = $to - $_POST['at_once'];

  $i = 0;
  foreach ($products as $pid => $product) {
    $i++;

    if($i <= $from)
        continue;

    /**
     * Update Attributes
     *
     * $product['attributes'] = array(
     *   'manufacturer' => $arrFileStr[6], // proizvoditel
     *   'model' => $arrFileStr[7], // model
     *   'width' => $arrFileStr[8], // shirina
     *   'diametr' => $arrFileStr[9], // diametr
     *   'height' => $arrFileStr[10], // vysota
     *   'index' => $arrFileStr[11], // indeks
     *   'PCD' => $arrFileStr[12], // pcd
     *   'flying' => $arrFileStr[13], // vylet
     *   'DIA' => $arrFileStr[14], // dia
     *   'color' => $arrFileStr[15], // tsvet
     *   'seasonality' => $arrFileStr[18], // sezon
     * );
     */

    $attributes = array();
    foreach ($product['attributes'] as $attr_key => $attr_val) {
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

    $id = ExchangeUtils::get_item_map_id( $product['_sku'] );
    ExchangeUtils::updateProductAttributes($id, $attributes);

    if( $i >= $to )
        break;
  }

  wp_die();
}