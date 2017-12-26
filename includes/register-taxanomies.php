<?php

namespace CDevelopers\Exchange;

// add_action('init', __NAMESPACE__ . '\ex_register_warehouse');
add_action('init', __NAMESPACE__ . '\ex_register_brand');
// function ex_register_warehouse() {
//     register_taxonomy('warehouse', array('product'), array(
//         'label'                 => 'Склады', // определяется параметром $labels->name
//         'labels'                => array(
//             'name'              => 'Склады',
//             'singular_name'     => 'Склад',
//             'search_items'      => 'Search warehouse',
//             'all_items'         => 'All warehouse',
//             'view_item '        => 'View warehouse',
//             'parent_item'       => 'Parent warehouse',
//             'parent_item_colon' => 'Parent warehouse:',
//             'edit_item'         => 'Edit warehouse',
//             'update_item'       => 'Update warehouse',
//             'add_new_item'      => 'Add New warehouse',
//             'new_item_name'     => 'New warehouse Name',
//             'menu_name'         => 'Склады',
//         ),
//         'description'           => '', // описание таксономии
//         'public'                => true,
//         // 'publicly_queryable'    => null, // равен аргументу public
//         // 'show_in_nav_menus'     => true, // равен аргументу public
//         // 'show_ui'               => true, // равен аргументу public
//         // 'show_tagcloud'         => true, // равен аргументу show_ui
//     ) );
// }

function ex_register_brand() {
    register_taxonomy('brand', array('product'), array(
        'label'                 => 'Производители', // определяется параметром $labels->name
        'labels'                => array(
            'name'              => 'Производители',
            'singular_name'     => 'Производитель',
            'search_items'      => 'Search brands',
            'all_items'         => 'All brands',
            'view_item '        => 'View brands',
            'parent_item'       => 'Parent brands',
            'parent_item_colon' => 'Parent brands:',
            'edit_item'         => 'Edit brands',
            'update_item'       => 'Update brands',
            'add_new_item'      => 'Add New brands',
            'new_item_name'     => 'New brands Name',
            'menu_name'         => 'Производители',
        ),
        'description'           => '', // описание таксономии
        'public'                => true,
        // 'publicly_queryable'    => null, // равен аргументу public
        // 'show_in_nav_menus'     => true, // равен аргументу public
        // 'show_ui'               => true, // равен аргументу public
        // 'show_tagcloud'         => true, // равен аргументу show_ui
    ) );
}