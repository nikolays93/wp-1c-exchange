<?php
global $wpdb;
$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";
$empty = 'NOT NULL default \'\'';

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
$tname = EXCHANGE_MAP;
$map = "CREATE TABLE {$tname} (
  id bigint(20) unsigned NOT NULL auto_increment,
  exid varchar(64) {$empty},
  item_id bigint(20) unsigned NOT NULL default '0',
  PRIMARY KEY (id),
  KEY exid (exid),
  KEY item_id (item_id)
) {$charset_collate};";

dbDelta( $map );

$tname = EXCHANGE_CACHE;
$cache = "CREATE TABLE {$tname} (
  idx bigint(20) unsigned NOT NULL auto_increment,
  id bigint(20) unsigned NOT NULL default '0',
  exid varchar(64) {$empty},
  title text {$empty},
  content longtext {$empty},
  parent text NULL,
  type varchar(20) {$empty},
  meta varchar(255) NULL,
  terms varchar(255) NULL,
  atts varchar(255) NULL,
  PRIMARY KEY (idx),
  KEY id (id),
  KEY exid (exid)
) {$charset_collate};";

dbDelta( $cache );