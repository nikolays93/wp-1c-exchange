<?php

class Exchange_Cache
{
    protected $type = 'CommerceML2.0';

    protected $arrImportFilenames = array('import0_1.xml');
    protected $offersFilename = 'offers0_1.xml';

    public static $countProducts = 0;
    public static $countCategories = 0;
    public static $countAttributes = 0;

    protected $strRawImport, $strRawOffers;

    public static $errors = array();

    /**
     * Обновить кэш для дальнейшего импорта
     *
     * @todo recursive (получать полную вложеность категорий для CommerceML)
     */
    function updateCache()
    {
        $this->compileRawContent();

        $this->updateTermsCache();
        $this->updateProductsCache();
    }

    /**
     * Determine import files
     *
     * @param string|array $files имя файла(String) или файлов(Array)
     * Добавьте ключ 'offers' для торговых предложений
     * Не указывайте имена файлов для автоматического определения файла
     * Старайтесь не указывать кирилические имена файлам
     */
    public function setImportFiles( $files = null )
    {
        $this->arrImportFilenames = array();
        if( is_string($files) ) {
            $this->arrImportFilenames = array( $files );
        }
        elseif( is_array($files) ) {
            foreach ($files as $k => $file) {
                if( $k === 'offers' ) {
                    $this->offersFilename = $file;
                    continue;
                }

                $this->arrImportFilenames[] = $file;
            }
        }
        else {
            $this->offersFilename = false;

            if( $dir = opendir( EXCHANGE_DIR ) ) {
                while (($file = readdir($dir)) !== false) {
                    if( is_file(EXCHANGE_DIR . '/' . $file) && ! in_array($file, array('.', '..')) ) {
                        $this->arrImportFilenames[] =  $file;
                    }
                }
            }
        }
    }

    /**
     * Determine import type
     *
     * @param string $type Можно указать тип вручную
     * Если тип не задан, пытаемся определить самостоятельно.
     * Поддерживается 2 типа импорта: csv | CommerceML2.0
     */
    public function setImportType( $type = null )
    {
        /**
         * Проверяем расширение первого файла
         */
        if( ! $type ) {
            $end = explode('.', $this->arrImportFilenames[0]);
            $file_extension = end( $end );
            if( $file_extension == 'csv' ) {
                $this->type = 'csv';
            }
        }
        else {
            $this->type = $type;
        }
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

        $filenames = $this->arrImportFilenames;
        if( $this->offersFilename ) {
            $filenames['offers'] = $this->offersFilename;
        }

        $i = 0;
        foreach($filenames as $key => $filename) {
            if( is_readable(EXCHANGE_DIR . '/' . $filename) ) {
                $fileContent = file_get_contents(EXCHANGE_DIR . '/' . $filename);

                if( ! preg_match('#.#u', $fileContent) ) {
                    $fileContent = iconv('CP1251', 'UTF-8', $fileContent);
                }

                if( $key === 'offers' ) {
                    $this->strRawOffers = $fileContent;
                    continue;
                }

                if( $this->type === 'csv' ) {
                    $strContent = explode(PHP_EOL, $fileContent);
                    foreach ($strContent as $numStr => $content) {
                        if( $numStr == 0 ) {
                            $head = sizeof( explode(';', $content) );
                            continue;
                        }
                        if( ! empty($content) && $head !== sizeof( explode(';', $content) ) ) {
                            $numStr++;
                            self::$errors[] = 'Фальшивый разделитель на строке ' . $numStr . ' (' . $filename . ')';
                        }
                    }
                }
                // Первая строка только у первого файла.
                $str = ($i == 0) ? $fileContent : substr($fileContent, strpos($fileContent, PHP_EOL) );
                $this->strRawImport .= $str . PHP_EOL;
                $i++;
            }
        }
    }

    function updateTermsCache()
    {
        /**
         * @todo rewrite legacy code
         */
        if( $this->type === 'CommerceML2.0' ) {
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
        elseif( $this->type === 'csv' ) {
            $fstrpos = strpos($this->strRawImport, PHP_EOL);

            // th
            $head = trim(substr($this->strRawImport, 0, $fstrpos ));
            $arrHead = explode(';', $head);

            // body
            $raw = explode(PHP_EOL, substr($this->strRawImport, $fstrpos) );

            $categories = array();
            $attributes = array();

            $attsIndex = array(6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 18);
            foreach ($raw as $strRaw) {
                if( empty($strRaw) )
                    continue;

                $arrFileStr = explode(';', $strRaw);

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

                foreach ($attsIndex as $index) {
                    if( $arrFileStr[$index] ) {
                        $attributes[ $arrHead[$index] ][] = trim( $arrFileStr[$index] );
                    }
                }
            }

            foreach ($attributes as $att_key => &$attribute) {
                $attribute = array_unique($attribute);
                self::$countAttributes++;
            }

            self::$countCategories = sizeof($categories);

            if( ! is_dir(EXCHANGE_DIR_CACHE) ) {
                mkdir(EXCHANGE_DIR_CACHE, 777, true);
            }

            file_put_contents( EXCHANGE_DIR_CACHE . '/' . Exchange_Category::FILE, serialize($categories) );
            file_put_contents( EXCHANGE_DIR_CACHE . '/' . Exchange_Attribute::FILE, serialize($attributes) );
        }
    }

    function updateProductsCache()
    {
        if( $this->type == 'commerce2' ){
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
        elseif( $this->type == 'csv' ) {
            $fstrpos = strpos($this->strRawImport, PHP_EOL);

            // th
            // $head = trim(substr($this->strRawImport, 0, $fstrpos ));
            // $arrHead = explode(';', $head);
            // $headSize = sizeof( $arrHead );

            // body
            $raw = explode(PHP_EOL, substr($this->strRawImport, $fstrpos) );

            $products = array();
            foreach ($raw as $strCount => $strRaw) {
                if( empty($strRaw) )
                    continue;

                $arrFileStr = explode(';', $strRaw);

                if( empty($strRaw) || empty($arrFileStr[0]) ){
                    continue;
                }

                $_product = new Exchange_Product( array(
                    '_sku'    => $arrFileStr[0],
                    'title'   => $arrFileStr[1],
                    'content' => $arrFileStr[2],
                    'terms'   => array($arrFileStr[3]),
                    'atts'    => array(
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
                        )
                    ) );

                $_product->setMetas( array(
                    '_price' => $arrFileStr[4],
                    '_regular_price' => $arrFileStr[4],
                    '_stock' => $arrFileStr[5],
                    ) );


                $products[ $arrFileStr[0] ] = $_product;
            }
        }

        if( ! is_dir(EXCHANGE_DIR_CACHE) ) {
            mkdir(EXCHANGE_DIR_CACHE, 777, true);
        }

        file_put_contents( EXCHANGE_DIR_CACHE . '/' . Exchange_Product::FILE, serialize($products) );

        $this->updateOffersCache();
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
