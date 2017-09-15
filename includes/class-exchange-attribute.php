<?php

class Exchange_Attribute
{
    const FILE = 'attributes.cache';

    public static $created = 0;
    public static $updated = 0;


    /**
     * Insert Or Update Category Item
     *
     * Записывает категории товаров посредством AJAX
     * @hook wp_ajax_exchange_insert_terms
     */
    static private function import($cat_id, $category, $categories)
    {
    }

    static public function initImport()
    {
        // if( ! isset($_POST['nonce']) || ! wp_verify_nonce( $_POST['nonce'], EXCHANGE_SECURITY ) ) {
        //     $err = ajax_answer( 'Ошибка! нарушены правила безопасности' );
        //     wp_die( $err );
        // }

        $attributes = unserialize( file_get_contents(EXCHANGE_DIR_CACHE . '/' . self::FILE) );
        if( ! is_array($attributes) ) {
            $err = ajax_answer( 'Аттрибуты не найдены' );
            wp_die( $err );
        }

        echo "<pre style='margin: 0 150px;'>";
        var_dump( $attributes );
        echo "</pre>";

        $arrAlready = array();
        foreach ( $attributes as $attr => $child_attrs ) {
            // self::import($attr, $child_attrs);
        }

        ajax_answer('Выгрузка аттрибутов завершена', 2, array(
            'created' => self::$created,
            'updated' => self::$updated,
        ));
    }

    /**
     * @param string $attribute_name Attribute slug without pa_
     */
    // updateProductAttribute
    public static function updateProductAttribute( $attribute_name, $attribute_label = false, $args = false )
    {
        global $wpdb;

        $tablename = $wpdb->prefix . 'woocommerce_attribute_taxonomies';
        // $attribute = $wpdb->get_row(
        //     $wpdb->prepare( "SELECT * FROM {$tablename} WHERE `attribute_name` = %s LIMIT 1;", $attribute_name )
        // );

        $attribute = false;
        $attribute_taxonomies = wc_get_attribute_taxonomies();
        if( !is_array($attribute_taxonomies) ) {
            $attribute_taxonomies = array();
        }

        foreach ($attribute_taxonomies as $_attr) {
            if( $_attr->attribute_name == $attribute_name ){
                $attribute = $_attr;
                break;
            }
        }

        if( $attribute_label ) {
            if( $attribute ){
                $args = array(
                    'attribute_id'      => $attribute->attribute_id,
                    'attribute_name'    => $attribute->attribute_name,
                    'attribute_label'   => $attribute_label,
                    'attribute_type'    => $attribute->attribute_type,
                    'attribute_orderby' => $attribute->attribute_orderby,
                    'attribute_public'  => $attribute->attribute_public,
                    );

                $result = $wpdb->update(
                    $tablename,
                    $args,
                    array( 'attribute_id' => $attribute->attribute_id ),
                    array( '%d', '%s', '%s', '%s', '%s', '%d' ),
                    array( '%d' )
                    );
                $action = 'updated';
            }
            else {
                $args = wp_parse_args( $args, array(
                    'attribute_name' => $attribute_name,
                    'attribute_label' => $attribute_label,
                    'attribute_type' => 'select',
                    'attribute_orderby' => 'menu_order',
                    'attribute_public' => 0,
                    ) );

                $result = $wpdb->insert(
                    $tablename,
                    $args,
                    array( '%s', '%s', '%s', '%s', '%d' )
                    );

                if( ! $result ) {
                    return false;
                }

                $args['attribute_id'] = $wpdb->insert_id;
                $action = 'created';
            }

            $attribute = (object)$args;

            // set_transient( 'wc_attribute_taxonomies', $attribute_taxonomies );
            delete_transient( 'wc_attribute_taxonomies' );
        }

        $result = new stdClass();
        $result->attribute = $attribute;
        $result->action    = $action;
        return $result;
    }

    /**
     * Обновить значение атрибута товара
     * Создать таксаномию(Атрибут), теримн(Значение атрибута) если понадобится
     *
     * @param  absint   $post_id ID записи которой задаем аттрибуты
     * @param  array    $atts    Масив с объектами атрибутов
     * @param  boolean  $append  Добавить значения к сущетвующим если true
     */
    public static function updateProductAttributes( $post_id, $atts, $append = false )
    {

        foreach ($atts as $att) {
            /**
             * @var stdClass $att объект атрибута
             *
             * @prop attribute_name
             * @prop attribute_label
             * @prop attribute_value
             */
            $pa_tax = 'pa_' . htmlspecialchars( stripslashes($att->attribute_name) );
            self::updateProductAttribute( $att->attribute_name, $att->attribute_label );

            $term_id = self::get_item_map_id( $att->attribute_value );
            $insert = ( $term_id ) ? (int)$term_id : $att->attribute_value;

            // if( term_exists( $insert, $pa_tax ) ){
            //     $current_term = wp_update_term( $insert, $pa_tax, array(
            //         'name' => $att->attribute_value,
            //         ) );
            // }
            // else {
            //     $current_term = wp_insert_term( $insert, $pa_tax );
            // }

            // if( is_wp_error($current_term) )
            //     continue;

            // if( ! $term_id ) {
            //     self::update_item_map( $att->attribute_value, (int)$current_term['term_id'] );
            // }

            $test = wp_set_object_terms( $post_id, (int)$current_term['term_id'], $pa_tax, $append );

            $product_attributes[] = array (
              'name' => $pa_tax,
              'value' => $att->attribute_value,
              'position' => 5,
              'is_visible' => 1,
              'is_variation' => 1,
              'is_taxonomy' => 1
              );
        }

        update_post_meta($post_id, '_product_attributes', $product_attributes);
    }
}
