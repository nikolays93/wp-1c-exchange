<?php

class Exchange_Category
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
            wp_die('00:Ошибка! нарушены правила безопасности');
        }

        $categories = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . self::FILE) );
        if( ! is_array($categories) ){
            wp_die('00:Категории не найдены');
        }

        $alreadyUpdated = array();
        foreach ($categories as $id => $self) {
            if( in_array($self->name, $alreadyUpdated) )
                continue;

            $args = array(
                'description'=> '',
                // 'parent' => $parent ? $parent : null,
                );

            /**
             * @todo: see! it's for TiresWorld only
             */
            if( $self->is_shina ) $args['parent'] = SHINA_ID;
            if( $self->is_disc )  $args['parent'] = DISC_ID;

            if( isset($self->parent) && $self->parent ){
                // echo $self->name, $self->parent, Exchange_Utils::get_item_map_id( $self->parent ) . PHP_EOL;
                if( $parent_id = Exchange_Utils::get_item_map_id( $self->parent ) ) {
                    $args['parent'] = (int) $parent_id;
                }
                else {
                    // $id ==? $self->parent
                    $parent = $categories[ $self->parent ];

                    $pargs = array();
                    // @todo: see! it's for TiresWorld only
                    if( $parent->is_shina ) $pargs['parent'] = SHINA_ID;
                    if( $parent->is_disc )  $pargs['parent'] = DISC_ID;

                    $pargs['name'] = $parent->name;
                    $pargs['description'] = '';

                    $term = self::insertOrUpdateHandle($parent->name, $pargs, $parent );
                    $args['parent'] = (int) $term['term_id'];
                    $alreadyUpdated[] = $parent->name;
                }
            }

            self::insertOrUpdateHandle( $id, $args, $self );
        }

        wp_die('10:Импорт категорий завершен!');
    }

    static function insertOrUpdateHandle( $id, $args, $self ){
        if( $term_id = Exchange_Utils::get_item_map_id( $id ) && !isset( $args['name'] ) ){
            $status = 'updated';
            $args['name'] = $self->name;
            $_term = wp_update_term( $term_id, 'product_cat', $args );
        }
        else {
            $status = 'created';
            $_term = wp_insert_term( $self->name, 'product_cat', $args );
        }

        // if( defined('EXCHANGE_DEBUG') && EXCHANGE_DEBUG ) {
        //     echo $self->name . " need " . $status . '. has parent : ' . $self->parent . PHP_EOL;
        //     print_r($args);
        //     echo PHP_EOL;
        //     return array('term_id' => 'PARENT!');
        // }

        if( is_wp_error($_term) ){
            $err = array_shift($_term->errors);
            echo '0:' .$self->name . ':' . $err[0] . PHP_EOL;
            return array('term_id' => 0);
        }

        // echo '1:' .$_term['term_id'] . ':' . $status . PHP_EOL;
        // echo $id, (int)$_term['term_id'], PHP_EOL;
        Exchange_Utils::update_item_map( $id, (int)$_term['term_id'] );

        return $_term;
    }
}