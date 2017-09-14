<?php

add_action( 'admin_enqueue_scripts', 'ex_page_enqueue_scripts', 10 );

/**
 * Add Script Variables
 *
 * Подключаем нужные скрипты
 * Передаем скрипту requests.js переменную request_settings
 */
function ex_page_enqueue_scripts(){
    if( $screen = get_current_screen() ){
        if( $screen->id != 'woocommerce_page_exchange' )
            return;

        wp_deregister_script('heartbeat');

        wp_enqueue_script( 'products_request', EXCHANGE_PLUG_URL . '/resources/requests.js', 'jquery', '1.0' );
        wp_enqueue_style( 'products_request-css', EXCHANGE_PLUG_URL . '/resources/exchange.css', null, '1.0' );

        wp_localize_script(
            'products_request',
            'request_settings',
            array(
                'nonce'    => wp_create_nonce( EXCHANGE_SECURITY ),
                'products_at_once' => 50,
                'products_count' => Exchange_Cache::$countProducts,
                'cats_count'     => Exchange_Cache::$countCategories,
                )
            );
    }
}

/**
 * Add AJAX Actions
 *
 * Добавляем WordPress hookи для работы с AJAX
 */
add_action( 'wp_ajax_exchange_update_cache', 'ex_update_cache' );
add_action( 'wp_ajax_exchange_insert_terms', array('Exchange_Category', 'initImport') );
add_action( 'wp_ajax_exchange_insert_posts', array('Exchange_Product', 'insertOrUpdate') );

function ex_update_cache() {
    $cache = new Exchange_Cache();
    $cache->setImportFiles();
    $cache->setImportType();
    $cache->updateCache();
}

$page = new WP_Admin_Page();
$page->set_args( EXCHANGE_PAGE, array(
    'parent'      => 'woocommerce',
    'title'       => __('Импорт товаров'),
    'menu'        => __('Импорт товаров'),
    'callback'    => 'ex_settings_page',
    'validate'    => 'ex_settings_validate',
    'permissions' => 'manage_options',
    'tab_sections'=> null,
    'columns'     => 2,
    ) );
$page->add_metabox( 'exchange_import', __('Импорт'), 'ex_settings_sidebar', 'side');
$page->add_metabox( 'exchange_timer', __('Затраченное время'), 'ex_settings_timer', 'side');
$page->add_metabox( 'exchange_settings', __('Настройки'), 'ex_settings_box', 'side');

$page->add_metabox( 'exchange_debug', __('Информация о кэшировании'), 'ex_settings_debug', 'normal');
$page->set_metaboxes();

function ex_settings_page() {
    ex_update_cache();


    echo "<div class='progress'><div class='progress-fill'></div></div>";
    echo "<div id='ajax_action'></div>";
    echo 'Товаров: <span id="product_count">'.Exchange_Cache::$countProducts.'</span><br>';
    echo 'Категорий: <span id="cat_count">'.Exchange_Cache::$countCategories.'</span>';
    echo "<textarea name='logs' id='exchange-logs' cols='30' rows='10'>";
    echo implode(PHP_EOL, Exchange_Cache::$errors) . PHP_EOL . "</textarea>";

  // echo "<pre>";
  // var_dump( unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . Exchange_Category::FILE) ) );
  // var_dump( unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . Exchange_Product::FILE) ) );
  // echo "</pre>";
}

function ex_settings_sidebar() {
  ?>
  <table class="widefat" id="ex-actions">
    <tr>
      <td><input type="checkbox" name="" id="ex_categories"></td>
      <td><label for="ex_categories">Импорт категорий</label></td>
    </tr>
    <tr>
      <td><input type="checkbox" name="" id="ex_attributes"></td>
      <td><label for="ex_attributes">Импорт аттрибутов</label></td>
    </tr>
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

function ex_settings_timer() {
  ?>
  <div id="timer" class='ex-timer'>
    <span class='hours'>0</span>:<span class='minutes'>00</span>:<span class='seconds'>00</span>
  </div>
  <?php
}

function ex_settings_box() {
  $sn = EXCHANGE_PAGE;
  $settings = ex_parse_settings();
  ?>
  <table class="form-table">
    <tr>
      <td>Обновлять категории</td>
      <td>
        <input type="checkbox" value="on" name="<?php echo $sn; ?>[cat_upd]" <?php
          checked($settings['cat_upd'], 'on'); ?>>
      </td>
    </tr>
    <tr>
      <td>Обновлять аттрибуты</td>
      <td>
        <input type="checkbox" value="on" name="<?php echo $sn; ?>[att_upd]" <?php
          checked($settings['att_upd'], 'on'); ?>>
      </td>
    </tr>
    <tr>
      <td>Обработать за запрос</td>
      <td>
        <input type="number" style="width: 60px;" name="<?php
          echo $sn; ?>[per_once]" value="<?php echo $settings['per_once'] ?>">
      </td>
    </tr>
  </table>

  <input type="submit" name="submit" class="button button-primary right" value="Сохранить настройки">
  <div class="clear"></div>
  <?php
}

function ex_settings_debug() {
  $arrProductCount = wp_count_posts('product');
  $productCount = 0;
  foreach ((array) $arrProductCount as $key => $value) {
    $productCount += $value;
  }

  $product_cat_count = wp_count_terms( 'product_cat', array('hide_empty' => false) );

  $attribute_taxonomies = wc_get_attribute_taxonomies();
  $attributes_count = ( is_array($attribute_taxonomies) ) ? sizeof($attribute_taxonomies) : 0;
  ?>
  <table class="widefat striped" id="cache-report">
    <tr>
      <td>Кэшированно товаров</td>
      <td><?php echo Exchange_Cache::$countProducts; ?></td>
    </tr>
    <tr>
      <td>Товаров на сайте</td>
      <td><?php echo $productCount; ?> (из них <?php echo $arrProductCount->trash ?> в корзине)</td>
    </tr>
    <tr>
      <td>Кэшированно категорий</td>
      <td><?php echo Exchange_Cache::$countCategories; ?></td>
    </tr>
    <tr>
      <td>Категорий на сайте</td>
      <td><?php echo $product_cat_count; ?></td>
    </tr>
    <tr>
      <td>Кэшированно аттрбутов</td>
      <td><?php echo Exchange_Cache::$countAttributes; ?></td>
    </tr>
    <tr>
      <td>Аттрбутов на сайте</td>
      <td><?php echo $attributes_count; ?></td>
    </tr>
  </table>
  <p><button type="button" class="button button-primary right">Обновить кэш</button></p>
  <div class="clear"></div>
  <?php
}

function ex_settings_validate( $inputs ) {
  $inputs = array_map_recursive( 'sanitize_text_field', $inputs );
  file_put_contents(__DIR__ . '/debug.log', print_r($inputs, 1) );
  return $inputs;
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

