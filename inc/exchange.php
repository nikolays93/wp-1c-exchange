<?php

// rename callbacks
// replace all AJAX_ACTION_NAME
// replace all AJAX_VAR
// change any_secret_string

add_action( 'admin_enqueue_scripts', 'add_ajax_data', 99 );
function add_ajax_data(){
  $screen = get_current_screen();
  if( $screen->id != 'woocommerce_page_exchange' )
    return;

  wp_enqueue_script( 'products_request', NEW_PLUG_URL . '/requests.js', 'jquery', '1.0' );
  wp_enqueue_style( 'products_request-css', NEW_PLUG_URL . '/exchange.css', null, '1.0' );

  $o = get_option(NEW_OPTION);
  wp_localize_script('products_request', 'AJAX_VAR',
    array(
      'url'      => admin_url('admin-ajax.php'),
      'nonce'    => wp_create_nonce( 'any_secret_string' ),
      'products_at_once' => isset($o['update_count']) ? intval($o['update_count']) : 40,
    )
  );
}

/**
 * Products
 */
add_action('wp_ajax_insert_posts', 'exchange_insert_posts');
function exchange_insert_posts() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) )
    wp_die('Ошибка! нарушены правила безопасности');
  
  $to = $_POST['at_once'] * $_POST['counter'];
  $from = $to - $_POST['at_once'];

  $products = unserialize( file_get_contents(EXCHANGE_DIR . '/exchange.cahce') );

  $i = 0;
  foreach ($products as $pid => $product) {
  	$i++;

  	if($i <= $from)
  		continue;

  	echo $i;

  	$post = array(
  		'post_author' => 1,
  		'post_title' => $product['title'],
  		'post_content' => $product['content'],
  		'post_status' => "publish",
  		'post_parent' => '',
  		'post_type' => "product",
  		);
  // Create post
  	$post_id = wp_insert_post( $post );

  	$offer_id = $id;
  	update_post_meta( $post_id, '_sku', '');
  	update_post_meta( $post_id, '_1c_sku', $product['sku']);
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
  	
  	if( $i >= $to )
  		break;
  }
  wp_die();
}

/**
 * Terms
 */
add_action('wp_ajax_insert_terms', 'exchange_insert_terms');
function exchange_insert_terms() {
  if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) )
    wp_die('Ошибка! нарушены правила безопасности');
  
  $to = $_POST['at_once'] * $_POST['counter'];
  $from = $to - $_POST['at_once'];

  $terms = unserialize( file_get_contents(EXCHANGE_DIR . '/groups.cahce') );

  $i = 0;
  foreach ($terms as $tid => $term) {
    $i++;

    if($i <= $from)
      continue;

    echo $i;

    $new_term = wp_insert_term(
      $term['name'], // новый термин
      'product_cat', // категория товара
      array(
        'description'=> '',
        'slug' => $term['slug'],
        )
      );

    if( ! is_wp_error($new_term) && isset($term['parent']) ){
      foreach ($term['parent'] as $ptid => $pterm) {
        wp_insert_term(
        $pterm['name'], // новый термин
        'product_cat', // категория товара
        array(
          'description'=> '',
          'slug' => $pterm['slug'],
          'parent'=> $new_term['term_id'],
          )
        );
      }
    }
    
    if( $i >= $to )
      break;
  }
  wp_die();
}

  // echo "<pre>";
  // var_dump($products);
  // echo "</pre>";
