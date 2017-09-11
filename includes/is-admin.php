<?php

define('EXCHANGE_PAGE', 'exchange');

$page = new WP_Admin_Page();
$page->set_args( EXCHANGE_PAGE, array(
  'parent'      => 'woocommerce',
  'title'       => __('Импорт товаров'),
  'menu'        => __('Импорт товаров'),
  'callback'    => 'ex_settings_page',
  //'validate'    => 'ex_settings_validate',
  'permissions' => 'manage_options',
  'tab_sections'=> null,
  'columns'     => 2,
  ) );

$page->add_metabox( 'exchange_box', __('Выгрузить'), 'ex_settings_sidebar', 'side');
$page->set_metaboxes();

function ex_settings_page() {
  echo "<div class='progress'><div class='progress-fill'></div></div>";
  echo "<div id='ajax_action'></div>";

  echo 'Товаров: <span id="product_count">'.Exchange::$countProducts.'</span><br>';
  echo 'Категорий: <span id="cat_count">'.Exchange::$countCategories.'</span>';

  // echo "<textarea name='logs' id='logs' cols='30' rows='10' style='width:100%;resize:vertical;margin:20px 0;'></textarea>";
  // echo "<pre>";
  // var_dump( unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . Exchange_Category::FILE) ) );
  // var_dump( unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . Exchange_Product::FILE) ) );
  // echo "</pre>";
}

function ex_settings_sidebar() {
  ?>
  <p><button type='button' class='button button-primary' id='load-categories'>Загрузить категории</button></p>
  <p><button type='button' class='button button-primary' id='load-products'>Загрузить товар</button></p>
  <?php
}

function ex_settings_validate( $inputs ) {

}

/**
 * Добавляем поля в WooCoomerce Product Metabox (После ввода цены товара)
 */
// $wc_fields = new \WCProductSettings();
// $wc_fields->add_field( array(
//   'type'        => 'text',
//   'id'          => '_1c_sku',
//   'label'       => 'Артикул 1C',
//   ) );

// $wc_fields->add_field( array(
//   'type'        => 'text',
//   'id'          => '_stock_wh',
//   'label'       => 'Наличие на складах',
//   'description' => 'Роботизированная строка КоличествоНаСкладе',
//   ) );

// $wc_fields->set_fields();