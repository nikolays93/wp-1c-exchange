<?php

class Exchange
{
    const SECURITY = 'Secret';

    protected static $type = 'CommerceML2.0';

    public static $countProducts = 0;
    public static $countCategories = 0;

    protected $arrImportFilenames = array('import0_1.xml');
    protected $offersFilename = 'offers0_1.xml';

    public static $errors;
    private static $instance = null;
    public static function get_instance() {
        if ( ! isset( self::$instance ) ) {
            self::$errors = new WP_Error();
            self::$instance = new self;
        }

        return self::$instance;
    }

    static function init(){
        add_action( 'admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'), 10 );
        add_action( 'wp_ajax_exchange_update_cache', array(__CLASS__, 'updateCache') );
        add_action( 'wp_ajax_exchange_insert_terms', array('Exchange_Category', 'insertOrUpdate') );
        add_action( 'wp_ajax_exchange_insert_posts', array('Exchange_Product', 'insertOrUpdate') );
    }

    public static function add_log_notice($code, $message, $data = '') {
        self::$errors->add($code, $message, $data);
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
                self::$type = 'csv';
            }
        }
        else {
            self::$type = $type;
        }
    }

    /**
     * Обновить кэш для дальнейшего импорта
     *
     * @todo recursive (получать полную вложеность категорий для CommerceML)
     */
    public function updateCache()
    {
        $cache = new Exchange_Cache();
        $cache->updateCache();
    }

    /**
     * Add Script Variables
     *
     * Подключаем нужные скрипты
     * Передаем скрипту requests.js переменную request_settings
     *
     * @access private
     */
    static function enqueue_scripts(){
        if( $screen = get_current_screen() ){
            if( $screen->id != 'woocommerce_page_exchange' )
                return;

            wp_enqueue_script( 'products_request', EXCHANGE_PLUG_URL . '/resources/requests.js', 'jquery', '1.0' );
            wp_enqueue_style( 'products_request-css', EXCHANGE_PLUG_URL . '/resources/exchange.css', null, '1.0' );

            wp_localize_script(
                'products_request',
                'request_settings',
                array(
                    'nonce'    => wp_create_nonce( Exchange::SECURITY ),
                    'products_at_once' => 50,
                    'products_count' => self::$countProducts,
                    'cats_count'     => self::$countCategories,
                    )
                );
        }
    }
}
