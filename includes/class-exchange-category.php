<?php
define('SHINA_ID', 17);
define('DISC_ID', 16);

class Exchange_Category extends Cached_Item
{
    const FILE = 'categories.cache';
    // @todo: for tire world
    public $is_shina;
    public $is_disc;

    public $name, $parent;

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

        Exchange::$countCategories++;
    }

    /**
     * Insert Or Update Category Item
     *
     * Записывает категории товаров посредством AJAX
     * @hook wp_ajax_exchange_insert_posts
     *
     * @access private
     */
    static function insertOrUpdate( $count = 50 )
    {
        if( ! wp_verify_nonce( $_POST['nonce'], Exchange::SECURITY ) ) {
            wp_die('Ошибка! нарушены правила безопасности');
        }

        $categories = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . self::FILE) );
        if( ! is_array($categories) ){
            wp_die('Категории не найдены');
        }

        $to = $_POST['at_once'] * $_POST['counter'];
        $from = $to - $_POST['at_once'];

        $i = 0;
        foreach ($categories as $tid => $self) {
            $tid = $self->name;
            $i++;

            if($i <= $from)
                continue;

            self::insertOrUpdateHandle($tid, $self, false );

            if( $i >= $to )
                break;
        }

        wp_die(1);
    }

    static function insertOrUpdateHandle( $id, $self, $parent = false )
    {
        $args = array(
            'description'=> '',
            //'slug' => $self['slug'],
            'parent' => $parent ? $parent : null,
            );

        /**
         * @todo: see! it's for TiresWorld only
         */
        if( $self->is_shina ) $args['parent'] = SHINA_ID;
        if( $self->is_disc )  $args['parent'] = DISC_ID;

        if( $term_id = Exchange_Utils::get_item_map_id( $id ) ){
            $args['name'] = $self->name;
            $_term = wp_update_term( $term_id, 'product_cat', $args );
            $status = 'updated';
        }
        else {
            $_term = wp_insert_term( $self->name, 'product_cat', $args);
            $status = 'created';
        }

        if( is_wp_error($_term) ){
            $err = array_shift( $_term->errors );
            echo $self->name . ':' . $err[0] . PHP_EOL;
            return;
        }

        echo $_term['term_id'] . ':' . $status . PHP_EOL;
        $_term_id = (int) $_term['term_id'];

        Exchange_Utils::update_item_map( $id, $_term_id );
        // update_term_meta( $_term_id, '_1c_term_id', (string) $id );

        // if( isset($self['parent']) ){
        //     foreach ($self['parent'] as $child_id => $child_data ) {
        //         // Тоже самое проделвыаем с дочерними терминами
        //         insertOrUpdateHandle($child_id, $child_data, $_term_id );
        //     }
        // }
    }
}