<?php

class ExchangeAdminPage
{
    const SETTINGS_NAME = 'exchange';

    function __construct()
    {
      add_filter( self::SETTINGS_NAME . '_columns', function(){return 2;} );

      $page = new WPAdminPageRender(
        self::SETTINGS_NAME,
        array(
          'parent' => 'woocommerce',
          'title' => __('Импорт товаров из 1C'),
          'menu' => __('Импорт товаров из 1C'),
          ),
        array(__CLASS__, 'render'),
        self::SETTINGS_NAME,
        array(__CLASS__, 'validate')
        );

      $page->add_metabox( 'exchange_box', __('Выгрузить'), array(__CLASS__, 'sidebar'), 'side');
      $page->set_metaboxes();
    }

    // public for WP
    static function render() {
      echo "<div class='progress'><div class='progress-fill'></div></div>";
      echo "<div id='ajax_action'></div>";

      echo 'Товаров: <span id="product_count"></span><br>';
      echo 'Категорий: <span id="cat_count"></span>';
    }

    /**
     * Валидация параметров отправленных формой настроек.
     *
     * Public for WordPress
     */
    static function validate( $inputs ) {
      /**
       * Если надо обновить кэш, удаляем старый
       */
      if( isset($inputs['update_cache']) && $inputs['update_cache'] ) {
        unlink(EXCHANGE_DIR_CACHE . '/products.cache');
        unlink(EXCHANGE_DIR_CACHE . '/groups.cache');

        unset($inputs['update_cache']);
      }

      return $inputs;
    }

    // public for WP
    static function sidebar() {
      ?>
      <p><button type='button' class='button button-primary' id='load-categories'>Загрузить категории</button></p>
      <p><button type='button' class='button button-primary' id='load-products'>Загрузить товар</button></p>
      <p><button type='button' class='button button-primary' id='load-atts'>Загрузить аттрибуты</button></p>
      <?php

      // var_dump( wc_get_attribute_taxonomies() );

      $data = array(
        // array(
        //   'id'      => 'update_count',
        //   'type'    => 'number',
        //   'label'   => 'Обработать за 1 запрос',
        //   'desc'    => '',
        //   'default' => '40',
        //   ),
        array(
          'id'      => 'update_cache',
          'type'    => 'checkbox',
          'label'   => 'Обновить кэш',
          'value'   => 'on',
          ),
        );

      // WPForm::render(
      //   $data,
      //   WPForm::active(self::SETTINGS_NAME, false, true),
      //   true,
      //   array('clear_value' => false, 'admin_page' => true)
      //   );

      // submit_button();
    }

    // function SideTermOptions(){
//   $update_term_data = array(
//     array(
//       'id'      => 'update_term][name',
//       'type'    => 'checkbox',
//       'label'   => 'Наименование',
//       'value'   => 'on',
//       ),
//     array(
//       'id'      => 'update_term][slug',
//       'type'    => 'checkbox',
//       'label'   => 'Slug',
//       'value'   => 'on',
//       ),
//     );

//   WPForm::render(
//     $update_term_data,
//     '', //WPForm::active(NEW_OPTION, false, true),
//     true,
//     array('clear_value' => false, 'admin_page' => true )
//     );
// }
// function SideProductOptions(){
  // 'sku'     => (string) $_product->Артикул,
  // 'title'   => (string) $_product->Наименование,
  // 'value'   => (string) $_product->БазоваяЕдиница[0]->attributes()['НаименованиеПолное'],
  // 'content' => (string) $_product->Описание,
  //       'brand'   => (string) $_product->Изготовитель->Наименование, // $_product->Изготовитель->Ид
  //       'terms'
// }

/**
 * Callback админ страницы
 */
function _render_page(){
  echo "<div class='progress'><div class='progress-fill'></div></div>";
  echo "<div id='ajax_action'></div>";

  // $file = file(CACHE_EXCHANGE_DIR . '/groups.map');
  // echo "<pre>";
  // foreach ($file as $str) {
  //   var_dump( unserialize($str) );
  // }
  // echo "</pre>";

  $products = array();
  $terms = array();
  $p_count = 0;
  $t_count = 0;

  /**
   * Если кэши найдены загружаем их
   */
  // if(  && is_readable( CACHE_EXCHANGE_DIR . '/groups.cache' ) ){
  //   $products = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/products.cache') );
  //   $p_count = count($products);

  //   $terms = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/groups.cache') );
  //   $t_count = count($terms);
  // }
  // // Если нет, получаем информацию из файлов
  // else {
  // }
  /**
   * При загрузке страницы: Если кэш не найден, читаются файлы import0_1.xml и offers0_1.xml
   * И записываются как кэш в файлы groups.cache и products.cache (Товары и предложения)
   */
  // echo '<p>Найдено товаров: <input type="text" readonly="true" value="'.$p_count.'" id="p_count"></p>';
  // echo '<p>Найдено категорий: <input type="text" readonly="true" value="'.$t_count.'" id="t_count"></p>';

  // if( is_wp_debug() ){
  //   echo "<pre style='height: 200px; overflow-y: scroll;border: 2px solid;'>";
  //   print_r($terms);
  //   var_dump( unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/groups.map') ) );
  //   echo "</pre>";

  //   echo "<pre style='height: 500px; overflow-y: scroll;border: 2px solid;'>";
  //   print_r($products);
  //   var_dump( unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/products.map') ) );
  //   echo "</pre>";
  // }
}
}



new ExchangeAdminPage();