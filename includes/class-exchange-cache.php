<?php

class Exchange_Cache extends Exchange
{
    const cache_type = 'sql';
    const delimiter = ';';

    protected $strRawImport, $strRawOffers;

    function updateCache()
    {
        $this->compileRawContent();

        $this->updateCategoriesCache();
        $this->updateProductsCache();
    }
    /**
     * Convert files content to one
     *
     * Собрать все файлы в одну переменную.
     * Проверяет на UTF символ, если не находит - конвертирует из CP-1251 в UTF-8.
     */
    protected function compileRawContent()
    {
        if( $this->strRawImport !== null ) {
            return $this->strRawImport;
        }

        $filenames = parent::$arrImportFilenames;
        if( parent::$offersFilename )
            $filenames['offers'] = parent::$offersFilename;

        $i = 0;
        foreach($filenames as $key => $filename){
            if( is_readable(EXCHANGE_DIR . '/' . $filename) ) {
                $fileContent = file_get_contents(EXCHANGE_DIR . '/' . $filename);

                if( ! preg_match('#.#u', $fileContent) ) {
                    $fileContent = iconv('CP1251', 'UTF-8', $fileContent);
                }

                if( $key === 'offers' ) {
                    $this->strRawOffers = $fileContent;
                    continue;
                }

                // Первая строка только у первого файла.
                $str = ($i == 0) ? $fileContent : substr($fileContent, strpos($fileContent, PHP_EOL) );
                $this->strRawImport .= $str . PHP_EOL;
                $i++;
            }
        }
    }

    protected function updateCategoriesCache()
    {
        global $wpdb;

        /**
         * @todo rewrite legacy code
         */
        if( parent::$type === 'CommerceML2.0' ) {
            $import = self::loadImportData();
            foreach( $import->Классификатор->Группы->Группа as $_group ) {
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
        elseif( parent::$type === 'csv' ) {
            $fstrpos = strpos($this->strRawImport, PHP_EOL);

            // th
            $head = trim(substr($this->strRawImport, 0, $fstrpos ));
            $arrHead = explode(PHP_EOL, $head);

            // body
            $raw = explode(PHP_EOL, substr($this->strRawImport, $fstrpos) );

            $categories = array();
            foreach ($raw as $strRaw) {
                if( empty($strRaw) )
                    continue;

                $arrFileStr = explode(self::delimiter, $strRaw);

                /**
                 * @todo recursive child levels
                 */
                $category = new Exchange_Category( $arrFileStr[3] );

                /**
                 * @todo : see! its for TiresWorld Only
                 */
                $category->is_shina = $arrFileStr[16] ? 1 : 0;
                $category->is_disc  = $arrFileStr[17] ? 1 : 0;

                $categories[ $arrFileStr[3] ] = $category;
            }

            Exchange::$countCategories = sizeof($categories);
            if( self::cache_type == 'sql' ) {
                foreach ($categories as $category) {
                    $catData = array(
                        'exid' => $category->name,
                        'parent' => $category->parent,
                        'type' => 'cat',
                        'meta' =>  serialize( array(
                            'is_shina' => $category->is_shina,
                            'is_disc' => $category->is_disc,
                            ) ),
                        );
                    $db = EXCHANGE_CACHE;
                    $exists = $wpdb->get_var(
                        $wpdb->prepare("SELECT id FROM {$db} WHERE `exid` = %s LIMIT 1", $category->name)
                        );

                    if( $exists !== NULL ) {
                        $wpdb->update( $db, $catData, array('exid' => $category->name) );
                    }
                    else {
                        $wpdb->insert( $db, $catData );
                    }
                }
            }
            else {
                if( ! is_dir(EXCHANGE_DIR_CACHE) ) {
                    mkdir(EXCHANGE_DIR_CACHE, 777, true);
                }

                file_put_contents( EXCHANGE_DIR_CACHE . '/' . Exchange_Category::FILE, serialize($categories) );
            }
        }
    }

    protected function updateProductsCache()
    {
        global $wpdb;

        if( parent::$type == 'commerce2' ){
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

                self::$products_count++;
            }

            $p_count += self::update_offers_cache();
        }
        elseif( parent::$type == 'csv' ) {

            $fstrpos = strpos($this->strRawImport, PHP_EOL);

            // th
            $head = trim(substr($this->strRawImport, 0, $fstrpos ));
            $arrHead = explode(PHP_EOL, $head);

            // body
            $raw = explode(PHP_EOL, substr($this->strRawImport, $fstrpos) );

            $products = array();
            foreach ($raw as $strRaw) {
                if( empty($strRaw) )
                    continue;

                $arrFileStr = explode(';', $strRaw);
                if( empty($strRaw) || empty($arrFileStr[0]) ){
                    continue;
                }

                $metas = array(
                    '_price' => $arrFileStr[4],
                    '_regular_price' => $arrFileStr[4],
                    '_stock' => $arrFileStr[5],
                    );

                $atts = array(
                    'manufacturer' => $arrFileStr[6], // proizvoditel
                    'model' => $arrFileStr[7], // model
                    'width' => $arrFileStr[8], // shirina
                    'diametr' => $arrFileStr[9], // diametr
                    'height' => $arrFileStr[10], // vysota
                    'index' => $arrFileStr[11], // indeks
                    'pcd' => $arrFileStr[12], // pcd
                    'flying' => $arrFileStr[13], // vylet
                    'dia' => $arrFileStr[14], // dia
                    'color' => $arrFileStr[15], // tsvet
                    'seasonality' => $arrFileStr[18], // sezon
                    );

                if( self::cache_type == 'sql' ) {
                    $pData = array(
                        'exid' => $arrFileStr[0], // sku
                        'title' => $arrFileStr[1],
                        'content' => $arrFileStr[2],
                        'type' => 'product',
                        'parent' => '',
                        'meta' =>  serialize( $metas ),
                        'terms' => serialize( array($arrFileStr[3]) ),
                        'atts' => serialize( $atts ),
                        );
                    $db = EXCHANGE_CACHE;
                    $exists = $wpdb->get_var(
                        $wpdb->prepare("SELECT exid FROM {$db} WHERE `exid` = %s LIMIT 1", $arrFileStr[0])
                        );

                    if( $exists !== NULL ) {
                        $wpdb->update( $db, $pData, array('exid' => $arrFileStr[0]) );
                    }
                    else {
                        var_dump( $wpdb->insert( $db, $pData ) );
                    }

                    Exchange::$countProducts++;
                }
                else {
                    $_product = new Exchange_Product( array(
                        '_sku'    => $arrFileStr[0],
                        'title'   => $arrFileStr[1],
                        'content' => $arrFileStr[2],
                        'terms'   => array($arrFileStr[3]),
                        ) );

                    $_product->setMetas( $metas );

                    $_product->setAttributes( $atts );

                    $products[ $arrFileStr[0] ] = $_product;

                    if( ! is_dir(EXCHANGE_DIR_CACHE) ) {
                        mkdir(EXCHANGE_DIR_CACHE, 777, true);
                    }

                    file_put_contents( EXCHANGE_DIR_CACHE . '/' . Exchange_Product::FILE, serialize($products) );
                }
            }
        }

        $this->updateAttributesCache();
        $this->updateOffersCache();
    }

    /**
     * Called from @link updateProductsCache
     */
    protected function updateAttributesCache()
    {
    }

    protected function updateOffersCache()
    {
        return;

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
    }
}