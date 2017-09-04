<?php

/**
 * Добавляем поля в WooCoomerce Product Metabox (После ввода цены товара)
 */
$wc_fields = new \WCProductSettings();
$wc_fields->add_field( array(
  'type'        => 'text',
  'id'          => '_1c_sku',
  'label'       => 'Артикул 1C',
  ) );

$wc_fields->add_field( array(
  'type'        => 'text',
  'id'          => '_stock_wh',
  'label'       => 'Наличие на складах',
  'description' => 'Роботизированная строка КоличествоНаСкладе',
  ) );

$wc_fields->set_fields();