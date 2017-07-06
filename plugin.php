<?php
/*
Plugin Name: 1c exchange
Plugin URI: 
Description: 
Version: 1.1b
Author: NikolayS93
Author URI: https://vk.com/nikolays_93
Author EMAIL: nikolayS93@ya.ru
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/
namespace PLUGIN_NAME;

if ( ! defined( 'ABSPATH' ) )
  exit; // disable direct access

$upload_dir = wp_upload_dir();

define('NEW_PLUG_URL', plugins_url( basename(__DIR__) ) );
define('NEW_PLUG_DIR', plugin_dir_path( __FILE__ ) );
define('EXCHANGE_DIR', $upload_dir['basedir'] . '/1c_exchange');
define('CACHE_EXCHANGE_DIR', EXCHANGE_DIR . '/cache' );
define('NEW_OPTION', 'exchange');

if(is_admin()){
  require_once NEW_PLUG_DIR . '/inc/class-wp-admin-page-render.php';
  require_once NEW_PLUG_DIR . '/inc/class-wp-form-render.php';
  require_once NEW_PLUG_DIR . '/inc/class-wc-product-settings.php';
  
  require_once NEW_PLUG_DIR . '/inc/exchange.php';


  add_filter( NEW_OPTION . '_columns', function(){return 2;} );

  $page = new WPAdminPageRender( NEW_OPTION,
  array(
    'parent' => 'woocommerce',
    'title' => __('Загрузка товаров 1C'),
    'menu' => __('Загрузка товаров 1C'),
    ),
  'PLUGIN_NAME\_render_page',
  NEW_OPTION,
  'PLUGIN_NAME\_validate_plugin'
  );

  $page->add_metabox( 'exchange_box', __('Выгрузить'), 'PLUGIN_NAME\qq', 'side');
  $page->set_metaboxes();


  $wc_fields = new \WCProductSettings();
  $wc_fields->add_field( array(
    'type'        => 'text',
    'id'          => '_1c_sku',
    'label'       => 'Артикул 1C',
    //'description' => 'Этот товар будет показываться в блоке популярных товаров',
    ) );

  $wc_fields->add_field( array(
    'type'        => 'text',
    'id'          => '_stock_wh',
    'label'       => 'Наличие на складах',
    'description' => 'Роботизированная строка КоличествоНаСкладе',
    ) );

  

  $wc_fields->set_fields();
}

/**
 * Admin Page
 */
function qq(){

  // echo "<p><button type='button' class='button'>Определить inputs</button></p>";
  // echo "<p><button type='button' class='button'>Определить offers</button></p>";

  echo "<p> &nbsp; </p>";

  echo "<p><button type='button' class='button button-primary' id='load-categories'>Загрузить категории</button></p>";

  // echo "<p> &nbsp; </p>";

  echo "<p><button type='button' class='button button-primary' id='load-products'>Загрузить товар</button></p>";
}

function translit($s) {
  $s = (string) $s; // преобразуем в строковое значение
  $s = strip_tags($s); // убираем HTML-теги
  $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
  $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
  $s = trim($s); // убираем пробелы в начале и конце строки
  $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
  $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
  $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s); // очищаем строку от недопустимых символов
  $s = str_replace(" ", "-", $s); // заменяем пробелы знаком минус
  return $s; // возвращаем результат
}

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

  if( is_readable( CACHE_EXCHANGE_DIR . '/products.cache' ) && is_readable( CACHE_EXCHANGE_DIR . '/groups.cache' ) ){
    $products = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/products.cache') );
    $p_count = count($products);

    $terms = unserialize( file_get_contents(CACHE_EXCHANGE_DIR . '/groups.cache') );
    $t_count = count($terms);
  }
  else {
    $import = new \SimpleXMLElement( file_get_contents(EXCHANGE_DIR . '/import0_1.xml') );
    foreach ( $import->Каталог->Товары->Товар as $_product ) {
      $id = (string) $_product->Ид;

      $products[$id] = array(
        'sku'     => (string) $_product->Артикул,
        'title'   => (string) $_product->Наименование,
        'value'   => (string) $_product->БазоваяЕдиница[0]->attributes()['НаименованиеПолное'],
        'content' => (string) $_product->Описание,
        'brand'   => (string) $_product->Изготовитель->Наименование, // $_product->Изготовитель->Ид
        );

      $groups = array();
      foreach ($_product->Группы as $group) {
        $groups[] = (string) $group->Ид;
      }
      $products[$id]['terms'] = $groups;

      $p_count++;
    }

    /**
     * Add Groups cache
     *
     * @todo recursive
     */
    foreach( $import->Классификатор->Группы->Группа as $_group ){
      $gid =   (string) $_group->Ид;
      $gname = preg_replace("/(^[0-9\/|\-_.]+. )/", "", (string) $_group->Наименование);

      $terms[$gid] = array(
        'name' => $gname,
        'slug' => translit($gname),
        );

      $t_count++;
      if( isset($_group->Группы->Группа) ){
        foreach ($_group->Группы->Группа as $_parent_group) {
          $pgid = (string) $_parent_group->Ид;
          $gname = preg_replace("/(^[0-9\/|\-_.]+. )/", "", (string) $_parent_group->Наименование );

          $terms[$gid]['parent'][$pgid] = array(
            'name' => preg_replace("/(^[0-9\/|\-_.]+. )/", "", $gname ),
            'slug' => translit($gname),
            'parent' => $gid,
            );

          $t_count++;
        }
      }
    }

    file_put_contents( CACHE_EXCHANGE_DIR . '/groups.cache', serialize($terms) );
    

    $offers = new \SimpleXMLElement( file_get_contents(EXCHANGE_DIR . '/offers0_1.xml') );
    foreach ($offers->ПакетПредложений->Предложения->Предложение as $offer) {
      $id = (string) $offer->Ид;

      $qtys = array();
      foreach ($offer->Склад as $attr) {
        $qtys[] = intval($attr->attributes()['КоличествоНаСкладе']);
      }

      $offer_id = $id;
      $products[$id]['offer'][$offer_id] = array(
        'sku'           => (string) $offer->Артикул,
        'title'         => (string) $offer->Наименование,
        'value'         => (string) $offer->БазоваяЕдиница[0]->attributes()['НаименованиеПолное'],
        'regular_price' => (int)    $offer->Цены->Цена->ЦенаЗаЕдиницу,
        'currency'      => (string) $offer->Цены->Цена->Валюта,
        'stock'         => (int)    $offer->Количество,
        'stock_wh'      => $qtys,
        );

      // $p_count++;
    }

    file_put_contents(CACHE_EXCHANGE_DIR . '/products.cache', serialize($products));
  }

  echo '<p>Найдено товаров: <input type="text" readonly="true" value="'.$p_count.'" id="p_count"></p>';
  echo '<p>Найдено категорий: <input type="text" readonly="true" value="'.$t_count.'" id="t_count"></p>';

  // echo "<pre style='height: 200px; overflow-y: scroll;'>";
  // print_r($terms);
  // echo "</pre>";

  // echo "<pre style='height: 500px; overflow-y: scroll;'>";
  // print_r($products);
  // echo "</pre>";

  $data = array(
    array(
      'id'      => 'update_count',
      'type'    => 'number',
      'label'   => 'Обработать за 1 запрос',
      'desc'    => '',
      'default' => '40',
      ),
    array(
      'id'      => 'update_cache',
      'type'    => 'checkbox',
      'label'   => 'Обновить кэш',
      'value'   => 'on',
      ),
    );

  WPForm::render(
    apply_filters( 'PLUGIN_NAME\dt_admin_options', $data ),
    WPForm::active(NEW_OPTION, false, true),
    true,
    array('clear_value' => false)
    );
  submit_button();
}

function _validate_plugin( $inputs ){
    // $inputs = array_map_recursive( 'sanitize_text_field', $inputs );
    // $inputs = array_filter_recursive($inputs);
    if(!is_array($inputs))
      return false;

    $inputs['update_count'] = isset($inputs['update_count']) ? intval($inputs['update_count']) : 40;
    if( isset($inputs['update_cache']) && $inputs['update_cache'] ){
      unlink(CACHE_EXCHANGE_DIR . '/products.cache');
      unlink(CACHE_EXCHANGE_DIR . '/groups.cache');

      unset($inputs['update_cache']);
    }

    // file_put_contents(__DIR__.'/valid.log', print_r($inputs, 1));

    return $inputs;
}