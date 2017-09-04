<?php

class ImportProducts
{
    protected $import_filename = array('/import0_1.xml');
    protected $offers_filename = array('/offers0_1.xml');

    protected static $importType;

    protected $import;
    protected $offers;

    function __construct( $type = false )
    {
        if( $type )
            self::$importType = $type;
    }

    /**
     * Determine import type
     *
     * Если тип не задан, пытаемся определить самостоятельно
     */
    protected function setImportType(){
        if( ! self::$importType ) {
            $end = explode('.', $this->import_filename[0]);
            $file_extension = end( $end );
            if( $file_extension == 'csv' ) {
                self::$importType = 'csv';
            }
            elseif( $file_extension == 'xml' ) {
                self::$importType = 'commerce2';
            }
        }
    }

    /**
     * Redefine Filenames
     * Используем для ручного ввода
     */
    public function setImportFiles( $path = false )
    {
        if( !is_array($path) ) {
            $this->import_filename = array($path);
        }
        else {
            foreach ($path as $k => $filename) {
                if( $k == 'offers' ) {
                    $this->offers_filename = $filename;
                    continue;
                }

                $this->import_filename[] = $filename;
            }
        }

        $this->setImportType();
    }

    /**
     * Redefine Filenames
     * Используем для поиска в папке
     */
    public function searchImportFiles()
    {
        $this->offers_filename = false;
        $this->import_filename = array();

        $dir = opendir( EXCHANGE_DIR );
        while (($file = readdir($dir)) !== false) {
            if( is_file(EXCHANGE_DIR . '/' . $file) && ! in_array($file, array('.', '..')) ) {
                $this->import_filename[] = '/' . $file;
            }
        }

        $this->setImportType();
    }


    static function loadImportData()
    {
        if( $this->import ) {
            return $this->import;
        }

        foreach( $this->import_filename as $key => $filename ){
            if( is_readable( EXCHANGE_DIR_CACHE . basename($filename) . 'cache' ) ) {
                if( $key !== 'offers' )
                    return unserialize( file_get_contents(EXCHANGE_DIR_CACHE . basename($filename) . 'cache') );
            }
        }

        if( ! self::$import )
            self::$import = new \SimpleXMLElement( file_get_contents(EXCHANGE_DIR . self::IMPORT_FILE) );

        return self::$import;
    }

    /**
     * Добавляет кэш предложений в update_product_cache
     *
     * @return int $o_count - Количество полученных предложений
     */
    protected function update_offers_cache( &$products )
    {
        $o_count = 0;
        $offers = new \SimpleXMLElement( file_get_contents(EXCHANGE_DIR . self::OFFERS_FILE) );

        if( ! $offers )
            return $o_count;

        if( isset($offers->ПакетПредложений) && isset($offers->ПакетПредложений->Предложения) ){
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

                $o_count++;
            }
        }

        return $o_count;
    }

    /**
     * Обновить кэш товаров
     * @todo add exception on mkdir
     * @return int $p_count - Количество полученных товаров
     */
    protected function update_product_cache()
    {
        $p_count = 0;
        if( self::$importType == 'commerce2' ){
            $import = self::loadImportData();
            /**
             * Записываем товары (для кэша)
             */
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

            $p_count += self::update_offers_cache();
        }
        elseif( self::$importType == 'csv' ) {
            $products = array();
            foreach ($this->import_filename as $filename) {
                $fileContent = file_get_contents(EXCHANGE_DIR . $filename);
                if( ! preg_match('#.#u', $fileContent) ){
                    $fileContent = iconv('CP1251', 'UTF-8', $fileContent);
                }

                $file = explode(PHP_EOL, $fileContent);
                $head = $file[0];
                unset($file[0]);

                foreach ($file as $fileStr) {
                    $arrFileStr = explode(';', $fileStr);
                    if( empty($fileStr) || empty($arrFileStr[0]) ){
                        continue;
                    }

                    $_sku = $arrFileStr[0];
                    $price = ExchangeUtils::sanitizePrice( $arrFileStr[4] );

                    $products[$_sku] = array(
                        'title' => $arrFileStr[1],
                        'content' => $arrFileStr[2],
                        'terms' => array($arrFileStr[3]),
                        );
                    $products[$_sku]['metas'] = array(
                        '_sku' => $_sku,
                        '_price' => $price,
                        '_regular_price' => $price,
                        '_stock' => $arrFileStr[5],
                        );
                    $products[$_sku]['attributes'] = array(
                        'manufacturer' => $arrFileStr[6], // proizvoditel
                        'model' => $arrFileStr[7], // model
                        'width' => $arrFileStr[8], // shirina
                        'diametr' => $arrFileStr[9], // diametr
                        'height' => $arrFileStr[10], // vysota
                        'index' => $arrFileStr[11], // indeks
                        'PCD' => $arrFileStr[12], // pcd
                        'flying' => $arrFileStr[13], // vylet
                        'DIA' => $arrFileStr[14], // dia
                        'color' => $arrFileStr[15], // tsvet
                        // is_shina
                        // is_disc
                        'seasonality' => $arrFileStr[18], // sezon
                        );

                    // $term_taxonomy_ids = wp_set_object_terms( get_the_ID(), 'ATTRIBUTE_VALUE', 'pa_ATTRIBUTE', true );
                    // $thedata = Array(
                    //      'pa_ATTRIBUTE'=>Array(
                    //            'name'=>'pa_ATTRIBUTE',
                    //            'value'=>'ATTRIBUTE_VALUE',
                    //            'is_visible' => '1',
                    //            'is_variation' => '1',
                    //            'is_taxonomy' => '1'
                    //      )
                    // );
                    // update_post_meta( get_the_ID(),'_product_attributes',$thedata);

                    $p_count++;
                }
            }
        }

        if( ! is_dir(EXCHANGE_DIR_CACHE) ) {
            mkdir(EXCHANGE_DIR_CACHE, null, true);
        }

        file_put_contents( EXCHANGE_DIR_CACHE . '/products.cache', serialize($products));

        return true;
        return $p_count;
    }

    /**
     * Обновить кэш категорий
     * @todo recursive (получать полную вложеность категорий)
     *
     * @return int $p_count - Количество полученных категорий
     */
    protected function update_categories_cache()
    {
        $terms = array();
        $t_count = 0;
        if( self::$importType == 'commerce2' ) {
            $import = self::loadImportData();
            foreach( $import->Классификатор->Группы->Группа as $_group ){
                $gid =   (string) $_group->Ид;
                $gname = preg_replace("/(^[0-9\/|\-_.]+. )/", "", (string) $_group->Наименование);

                $terms[$gid] = array(
                    'name' => $gname,
                    'slug' => translit($gname),
                    );

                $t_count++;
            // @todo recursive
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
        }
        elseif( self::$importType == 'csv' ) {
            foreach ($this->import_filename as $filename) {
                $fileContent = file_get_contents(EXCHANGE_DIR . $filename);
                if( ! preg_match('#.#u', $fileContent) ){
                    $fileContent = iconv('CP1251', 'UTF-8', $fileContent);
                }

                $file = explode(PHP_EOL, $fileContent);
                $head = $file[0];
                unset($file[0]);

                foreach ($file as $fileStr) {
                    if( empty($fileStr) )
                        continue;

                    $arrFileStr = explode(';', $fileStr);

                    /**
                     * @todo : see! its for TiresWorld Only
                     */
                    $terms[] = array(
                        'name' => $arrFileStr[3],
                        'is_shina' => $arrFileStr[16],
                        'is_disc'  => $arrFileStr[17],
                        );
                    $t_count++;
                }
            }
        }
        $terms = array_unique($terms);

        if( ! is_dir(EXCHANGE_DIR_CACHE) ) {
            mkdir(EXCHANGE_DIR_CACHE, 770, true);
        }

        file_put_contents( EXCHANGE_DIR_CACHE . '/categories.cache', serialize($terms) );
        return $t_count;
    }

    function getProductsCount()
    {
        $products_cache = EXCHANGE_DIR_CACHE . '/products.cache';

        if( ! is_readable( $products_cache ) ) {
            if( !self::update_product_cache() )
                 return; // Не удалось записать/получить кэш
        }

        $products = unserialize( file_get_contents( $products_cache ) );
        return count($products);
    }

    function getСategoriesСount()
    {
        $categories_cache = EXCHANGE_DIR_CACHE . '/categories.cache';

        if( ! is_readable( $categories_cache ) ) {
            if( !self::update_categories_cache() )
                return; // Не удалось записать/получить кэш
        }

        $terms = unserialize( file_get_contents( $categories_cache ) );
        return count($terms);
    }
}