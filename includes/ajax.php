<?php

namespace CDevelopers\Exchange;

add_action('wp_ajax_exchange_taxanomies', __NAMESPACE__ . '\ex_ajax_update_taxanomy');
function ex_ajax_update_taxanomy() {
    // if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) ){
    //     echo 'Ошибка! нарушены правила безопасности';
    //     wp_die();
    // }
    $ex = new Init( array(
        'offset' => Utils::get('offset'),
        'part'   => 1,
    ) );

    $categories = $ex->parse_categories_recursive();
    $brands = $ex->parse_brands();
    $warehouses = $warehouses = $ex->parse_warehouses();

    $ex::update_categories( $categories );
    // $ex::update_warehouses( $warehouses );
    $ex::set_warehouses_relations( $warehouses );
    foreach ($warehouses as $xml_wh => $wh) {
        if( isset($wh['contact']) ) {
            set_theme_mod( $wh['contact'] . '_company_name', $wh['name'] );
        }
    }
    $ex::update_brands( $brands );

    echo json_encode( array('retry' => 0) );
    wp_die();
}

add_action('wp_ajax_exchange_products', __NAMESPACE__ . '\ex_ajax_update_product');
function ex_ajax_update_product() {
    // if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) ){
    //     echo 'Ошибка! нарушены правила безопасности';
    //     wp_die();
    // }

    $ex = new Init( array(
        'offset' => Utils::get('offset'),
        'part'   => $_POST['part'],
    ) );

    $products = $ex->parse_products();
    if( $products ) {
        $ex::update_posts( $products );
        $ex::update_postmetas( $products );
        echo json_encode( array('retry' => 1) );
    }
    else {
        echo json_encode( array('retry' => 0) );
    }

    wp_die();
}

add_action('wp_ajax_exchange_relationships', __NAMESPACE__ . '\ex_ajax_update_relationships');
function ex_ajax_update_relationships() {
    // if( ! wp_verify_nonce( $_POST['nonce'], 'any_secret_string' ) ){
    //     echo 'Ошибка! нарушены правила безопасности';
    //     wp_die();
    // }

    $ex = new Init( array(
        'offset' => Utils::get('offset'),
        'part'   => $_POST['part'],
    ) );

    $products = $ex->parse_products();
    if( $products ) {
        $ex::update_cat_relationships( $products );
        // $ex::update_wh_relationships( $products ); // not needed?
        $ex::update_brand_relationships( $products );
        echo json_encode( array('retry' => 1) );
    }
    else {
        update_option( 'last_exchange', time() );
        echo json_encode( array('retry' => 0) );
    }

    wp_die();
}
