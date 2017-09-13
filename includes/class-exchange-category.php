<?php

class Exchange_Category
{
    const FILE = 'categories.cache';
    // @todo: for tire world
    public $is_shina;
    public $is_disc;

    public $name, $parent;

    protected static $arrAlready = array();
    public static $created = 0;
    public static $updated = 0;

    function __construct( $strName = null )
    {
        $this->parent = 0;
        $searchParent = explode('/', $strName);
        if( isset($searchParent[1]) ){
            $this->name = $searchParent[1];
            $this->parent = $searchParent[0];
        }
        else {
            $this->name = $strName;
        }
    }

    /**
     * Insert Or Update Category Item
     *
     * Записывает категории товаров посредством AJAX
     * @hook wp_ajax_exchange_insert_terms
     */
    static private function import($cat_id, $category, $categories)
    {
        if( in_array($cat_id, self::$arrAlready) ) {
            return false;
        }

        $args = array();
        // @var $category->parent = parent cat id
        if( $category->parent ) {
            // категории назначен не существуующий в кэше родитель
            if( ! isset( $categories[ $category->parent ] ) ) {
                return false;
            }

            self::$arrAlready[] = self::import( $category->parent, $categories[ $category->parent ], $categories );
        }


        // @todo: see! it's for TiresWorld only
        if( $category->is_shina ) $args['parent'] = SHINA_ID;
        if( $category->is_disc )  $args['parent'] = DISC_ID;

        $args['parent'] = 0;
        if( $category->parent ) {
            $parent_id = get_term_id_from_external( $category->parent );
            $args['parent'] = (int) $parent_id;
        }

        $args['name'] = $category->name;

        if( $term_id = get_term_id_from_external( $cat_id ) ){
            $status = 'updated';
            $_term = wp_update_term( $term_id, 'product_cat', $args );
        }
        else {
            $status = 'created';
            $_term = wp_insert_term( $category->name, 'product_cat', $args );
        }

        if( is_wp_error($_term) ){
            $err = ajax_answer( $category->name .' : '. $_term->get_error_message() );
            wp_die($err);
            return '';
        }

        if( $status === 'created' ) {
            update_term_meta( $_term['term_id'], 'external_id', $cat_id );
            self::$created++;
        }
        else {
            self::$updated++;
        }

        return $cat_id;
    }

    static public function initImport()
    {
        if( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], EXCHANGE_SECURITY ) ) {
            $err = ajax_answer( 'Ошибка! нарушены правила безопасности' );
            wp_die( $err );
        }

        $categories = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . self::FILE) );
        if( ! is_array($categories) ) {
            $err = ajax_answer( 'Категории не найдены' );
            wp_die( $err );
        }

        $arrAlready = array();
        foreach ( $categories as $cat_id => $category ) {
            self::import($cat_id, $category, $categories);
        }

        ajax_answer('Выгрузка категорий завершена', 2, array(
            'created' => self::$created,
            'updated' => self::$updated,
        ));
    }
}
