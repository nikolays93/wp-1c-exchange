<?php
global $wpdb;
$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";
$empty = 'NOT NULL default \'\'';
$empty_textfield = 'varchar(255) ' . $empty;

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

$tname = EXCHANGE_CACHE;
$cache = "CREATE TABLE {$tname} (
  idx bigint(20) unsigned NOT NULL auto_increment,
  id bigint(20) unsigned NOT NULL default '0',
  exid {$empty_textfield},
  title {$empty_textfield},
  content {$empty_textfield},
  parent bigint(20) unsigned NOT NULL default '0',
  type {$empty_textfield},
  meta {$empty_textfield},
  terms {$empty_textfield},
  atts {$empty_textfield},
  PRIMARY KEY (idx),
  KEY id (id),
  KEY exid (exid)
) {$charset_collate};";

dbDelta( $cache );