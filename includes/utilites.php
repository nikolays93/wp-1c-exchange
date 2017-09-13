<?php

if( !function_exists( 'get_term_id_from_external' ) ) {
    function get_term_id_from_external( $external ) {
        global $wpdb;

        $q = "SELECT term_id FROM {$wpdb->termmeta} WHERE `meta_value` = %s LIMIT 1;";
        $result = $wpdb->get_var( $wpdb->prepare( $q , $external ) );

        return $result;
    }
}

if( !function_exists( 'sanitize_price' ) ) {
    // sanitizePrice
    function sanitize_price( $string, $delimiter = '.' ) {
        if( ! $string ) {
            return '';
        }

        $arrPriceStr = explode($delimiter, $string);
        $price = 0;
        foreach ($arrPriceStr as $i => $priceStr) {
            if( sizeof($arrPriceStr) !== $i + 1 ){
                $price += (int)preg_replace("/\D/", '', $priceStr);
            }
            else {
                $price += (int)$priceStr / 100;
            }
        }

        return $price;
    }
}

if( ! function_exists( 'sanitize_cyr_url' ) ) {
    // translit
    function sanitize_cyr_url($s){
        $s = strip_tags( (string) $s);
        $s = str_replace(array("\n", "\r"), " ", $s);
        $s = preg_replace("/\s+/", ' ', $s);
        $s = trim($s);
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
        $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
        $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s);
        $s = str_replace(" ", "-", $s);
        return $s;
    }
}

if( ! function_exists('get_item_map_id') ) {
    function get_item_map_id( $out ) {
        global $wpdb;

        $tablename = EXCHANGE_MAP;
        $product = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$tablename} WHERE `out_item_id` = %s LIMIT 1;", $out )
            );

        return isset($product->item_id) ? (int)$product->item_id : false;
    }
}

if( ! function_exists('create_item_map') ) {
    function create_item_map( $out, $id ) {
        global $wpdb;

        if( get_item_map_id( $out ) ) {
            return 0;
        }

        $result = $wpdb->insert(
            EXCHANGE_MAP,
            array( 'out_item_id' => $out, 'item_id' => $id ),
            array( '%s', '%d' )
            );

        return $result;
    }
}

if( ! function_exists('update_item_map') ) {
    function update_item_map( $out, $id ) {
        global $wpdb;

        if( ! $result = create_item_map($out, $id) ) {
            $action = 'updated';
            $result = $wpdb->update(
                EXCHANGE_MAP,
                array( 'out_item_id' => $out, 'item_id' => $id ),
                array( 'out_item_id' => $out ),
                array( '%s', '%d' ),
                array( '%s' )
                );
        }
        else {
            $action = 'created';
        }

        return array($result, $action);
    }
}

if( ! function_exists('wp_is_ajax') ) {
    function wp_is_ajax() {
        return (defined('DOING_AJAX') && DOING_AJAX);
    }
}

if( ! function_exists('ajax_answer') ) {
    function ajax_answer( $message, $status = 0, $args = array() ) {
        if( wp_is_ajax() ) {
            $answer = wp_parse_args( $args, array(
                'message' => $message,
                'status' => $status,
                'count' => 0,
                ) );

            echo json_encode( $answer, $message, array( 'response' => ($status > 1) ? 200 : 500 ) );

            wp_die();
        }
    }
}
