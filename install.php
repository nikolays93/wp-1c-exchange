<?php
global $wpdb;
$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
$tname = EXCHANGE_MAP;
$map = "CREATE TABLE {$tname} (
  id bigint(20) unsigned NOT NULL auto_increment,
  out_item_id varchar(255) NOT NULL default '',
  item_id bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY out_item_id (out_item_id),
  KEY item_id (item_id)
) {$charset_collate};";

dbDelta( $map );
