<?php
foreach(array(
  array('MODULE_PAYMENT_INVOICE_TEXT_TITLE','Invoice'),
  array('MODULE_PAYMENT_INVOICE_TEXT_DESCRIPTION','Invoice Payment'),
  array('MODULE_PAYMENT_INVOICE_LOGIN_TITLE','Login'),
  array('MODULE_PAYMENT_INVOICE_API_KEY_TITLE','API KEY')
) as $v) define($v[0],$v[isset($v[1])?1:0]);
define('MODULE_PAYMENT_INVOICE_STATUS_TITLE', 'Статус модуля Invoice');
define('MODULE_PAYMENT_INVOICE_ORDER_STATUS_ID_TITLE', 'Статус заказа после оплаты');
define('MODULE_PAYMENT_INVOICE_ORDER_STATUS_DESC', '');
define('MODULE_PAYMENT_INVOICE_DEFAULT_TERMINAL_NAME_TITLE', 'Имя терминала');
define('MODULE_PAYMENT_INVOICE_DEFAULT_TERMINAL_NAME_DESC', 'Имя терминала по умолчанию');
