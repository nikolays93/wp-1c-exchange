<?php

// rename callbacks
// replace all AJAX_ACTION_NAME
// replace all request_settings
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

    $o = get_option( ExchangeAdminPage::SETTINGS_NAME );
    wp_localize_script(
      'products_request',
      'request_settings',
      array(
        'nonce'    => wp_create_nonce( 'any_secret_string' ),
        'products_at_once' => isset($o['update_count']) ? intval($o['update_count']) : 40,
        )
      );
  }
}

/**
 * Products
 */
function add_product_meta($pid, $product, $post_id){
  update_post_meta( $post_id, '_sku', '');
  update_post_meta( $post_id, '_1c_sku', $product['sku']);
  update_post_meta( $post_id, '_1c_id', (string) $pid );

  update_post_meta( $post_id, '_price', $product['offer'][$pid]['regular_price'] );
  update_post_meta( $post_id, '_regular_price', $product['offer'][$pid]['regular_price'] );
  update_post_meta( $post_id, '_sale_price', "" );
  update_post_meta( $post_id, '_sale_price_dates_from', "" );
  update_post_meta( $post_id, '_sale_price_dates_to', "" );
  update_post_meta( $post_id, 'total_sales', '0');

  update_post_meta( $post_id, '_tax_status', 'taxable');
  update_post_meta( $post_id, '_tax_class', '');

  update_post_meta( $post_id, '_manage_stock', "yes" );
  update_post_meta( $post_id, '_stock', $product['offer'][$pid]['stock']);
  update_post_meta( $post_id, '_stock_status', $product['offer'][$pid]['stock'] > 0 ? 'instock' : 'outofstock');

  if( isset($product['offer'][$pid]['stock_wh']) && is_array($product['offer'][$pid]['stock_wh']) )
    update_post_meta( $post_id, '_stock_wh', serialize( $product['offer'][$pid]['stock_wh'] ) );

  update_post_meta( $post_id, '_backorders', "no" );
  update_post_meta( $post_id, '_sold_individually', "" );

  update_post_meta( $post_id, '_weight', "" );
  update_post_meta( $post_id, '_length', "" );
  update_post_meta( $post_id, '_width', "" );
  update_post_meta( $post_id, '_height', "" );

  update_post_meta( $post_id, '_upsell_ids', "a:0:{}" );
  update_post_meta( $post_id, '_crosssell_ids', "a:0:{}" );

  update_post_meta( $post_id, '_purchase_note', "" );

  update_post_meta( $post_id, '_default_attributes', "a:0:{}" );

  update_post_meta( $post_id, '_virtual', 'no');
  update_post_meta( $post_id, '_downloadable', 'no');

  update_post_meta( $post_id, '_product_image_gallery', '');
  update_post_meta( $post_id, '_download_limit', '-1');
  update_post_meta( $post_id, '_download_expiry', '-1');

  update_post_meta( $post_id, '_product_version', '3.0.6');
}
function get_product_terms( $product = false ){
  if(!isset($product['terms']))
    return null;

  $groups = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/groups.map') );
  $post_terms = array();
  if( is_array($product['terms']) ){
    foreach ($product['terms'] as $_1c_term_id) {
      if( isset($groups[$_1c_term_id]) )
        $post_terms[] = (int) $groups[$_1c_term_id];
    }
  }

  if(sizeof($post_terms) < 1)
    return null;

  return array( 'product_cat' => $post_terms );
}

add_action('wp_ajax_insert_posts', 'exchange_insert_posts');
function exchange_insert_posts() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) )
    wp_die('Ошибка! нарушены правила безопасности');

  $to = $_POST['at_once'] * $_POST['counter'];
  $from = $to - $_POST['at_once'];

  $products = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/products.cache') );
  $products_map = unserialize( file_get_contents( CACHE_EXCHANGE_DIR . '/products.map' ) );

  $i = 0;
  $posts_map = array();
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
      'tax_input' => get_product_terms($product),
  		);

    // Edit post
    if( isset($products_map[ $pid ]) ){
      $args['ID'] = $products_map[ $pid ];

      $post_id = wp_update_post($args);
    }
    // Create post
    else {
      $post_id = wp_insert_post( $args );
    }

    if( is_wp_error($post_id) )
      return;

    $posts_map[] = array( $pid => $post_id );

    add_product_meta($pid, $product, $post_id);
  	if( $i >= $to )
  		break;
  }
  // if(sizeof($posts_map) >= 1)
  //   write_to_file($posts_map, CACHE_EXCHANGE_DIR . '/_products.map', 'a');

  wp_die('?');
}

/**
 * Terms
 *
 * Записывает категории товаров
 * И создает карту терминов ( 1c_ID => term_id )
 * Как вся карта записана, исправляем и перезаписываем ее.
 */

/**
 * Insert hierarchical terms
 */
function recursive_wp_term( $id, $data, $parent = false, $old_terms_map, &$new_terms_map ){
  $args = array(
    'description'=> '',
    'slug' => $data['slug'],
    'parent' => $parent ? $parent : null,
    );

  if( array_key_exists($id, $old_terms_map) ){
    $args['name'] = $data['name'];
    wp_update_term( $old_terms_map[$id], 'product_cat', $args );
  }
  else {
    $_term = wp_insert_term($data['name'], 'product_cat', $args);
  }

  if( is_wp_error($_term) || ! isset($_term['term_id']) )
    return;

  $_term_id = (int) $_term['term_id'];
  $new_terms_map[] = array( (string) $id => $_term_id );

  update_term_meta( $_term_id, '_1c_term_id', (string) $id );

  if( isset($data['parent']) ){
    foreach ($data['parent'] as $child_id => $child_data ) {
      // Тоже самое проделвыаем с дочерними терминами
      recursive_wp_term($child_id, $child_data, $_term_id, $old_terms_map, $new_terms_map );
    }
  }
}

add_action('wp_ajax_insert_terms', 'exchange_insert_terms');
function exchange_insert_terms() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) )
    wp_die('Ошибка! нарушены правила безопасности');
  
  // Обработать Начиная с 
  $from = $to - $_POST['at_once'];
  // До
  $to = $_POST['at_once'] * $_POST['counter'];
  // Масив из файла
  $terms = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/groups.cache') );

  // Загрузить карту и пропсукать либо обрабатывать уже сущесвтующие данные
  $old_terms_map = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/groups.map') );
  if( !is_array( $old_terms_map ) || sizeof($old_terms_map) < 1 )
    $old_terms_map = array();

  $i = 0;
  // Переменная для новой карты
  $new_terms_map = array();
  foreach ($terms as $tid => $term) {
    $i++;

    if($i <= $from)
      continue;

    recursive_wp_term($tid, $term, false, $old_terms_map, $new_terms_map );

    if( $i >= $to )
      break;
  }

  // Записать карту в конец файла
  if( sizeof($new_terms_map) >= 1 )
    write_to_file($new_terms_map, CACHE_EXCHANGE_DIR . '/_groups.map', 'a');

  wp_die();
}

/**
 * Поправить записанные map'ы
 */
function fix_map( $new_filename, $old_filename ){

  $new_path = CACHE_EXCHANGE_DIR . '/' . $new_filename;
  $new_data = file($new_path);

  $old_path = CACHE_EXCHANGE_DIR . '/' . $old_filename;
  $old_data = file_get_contents($old_path);
  $data = (!empty($old_data)) ? unserialize( $old_data ) : array();

  /**
   * Если новых данных не найдено удаляем пустой файл
   */
  if( !is_array($new_data) || sizeof($new_data) < 1 ){
    unlink( $new_path );
    return;
  }

  // Каждую строку
  foreach ($new_data as $data_str) {
    // Преобразовать в масив
    foreach ( unserialize($data_str) as $str ) {
      // Вынимаем информацию из масива
      foreach ( $str as $str_1c_id => $str_id ) {
        // дозаписываем уже в готовый масив
        $data[$str_1c_id] = $str_id;
      }
    }
  }
  
  /**
   * Перезаписываем новый состыкованый масив в файл
   */
  write_to_file($result, $old_path, 'w' );
  // Устаревшую полученую информацию удаляем
  unlink( CACHE_EXCHANGE_DIR . '/' . $from );
}

add_action('wp_ajax_fix_product_map', 'exchange_fix_product_map');
function exchange_fix_product_map(){
  fix_map( '_products.map', 'products.map' );
}

add_action('wp_ajax_fix_term_map', 'exchange_fix_term_map');
function exchange_fix_term_map() {
  fix_map( '_groups.map', 'groups.map' );
}