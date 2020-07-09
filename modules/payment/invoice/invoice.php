<?php
defined('_VALID_OS') or die('Direct Access to this location is not allowed.');

require "InvoiceSDK/RestClient.php";
require "InvoiceSDK/common/SETTINGS.php";
require "InvoiceSDK/common/ORDER.php";
require "InvoiceSDK/CREATE_TERMINAL.php";
require "InvoiceSDK/CREATE_PAYMENT.php";

class invoice
{
    public $order;
    public $code = 'invoice';
    public $title = 'Invoice';
    public $description = 'Invoice Payment Module';
    public $icon = 'icon.png';
    public $icon_small = 'icon_small.png';
    public $enabled = true;

    public $api_key;
    public $login;
    public $default_terminal_name;
    public $order_status;

    public function __construct(){
        global $order;
        $this->order = &$order;

        $this->api_key = MODULE_PAYMENT_INVOICE_API_KEY;
        $this->login = MODULE_PAYMENT_INVOICE_LOGIN;
        $this->default_terminal_name = MODULE_PAYMENT_DEFAULT_TERMINAL_NAME;
    }

    function update_status() {
        global $order;

        return true;
    }

    function javascript_validation()
    {
        return false;
    }

    function selection()
    {
        if (isset($_SESSION[$this->name]))
        {
            $order_id = substr($_SESSION[$this->name], strpos($_SESSION[$this->name], '-')+1);
            $check_query = os_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');
            if (os_db_num_rows($check_query) < 1)
            {
                os_db_query('DELETE FROM '.TABLE_ORDERS.' WHERE orders_id = "'.(int)$order_id.'"');
                os_db_query('DELETE FROM '.TABLE_ORDERS_TOTAL.' WHERE orders_id = "'.(int)$order_id.'"');
                os_db_query('DELETE FROM '.TABLE_ORDERS_STATUS_HISTORY.' WHERE orders_id = "'.(int)$order_id.'"');
                os_db_query('DELETE FROM '.TABLE_ORDERS_PRODUCTS.' WHERE orders_id = "'.(int)$order_id.'"');
                os_db_query('DELETE FROM '.TABLE_ORDERS_PRODUCTS_ATTRIBUTES.' WHERE orders_id = "'.(int)$order_id.'"');
                os_db_query('DELETE FROM '.TABLE_ORDERS_PRODUCTS_DOWNLOAD.' WHERE orders_id = "'.(int)$order_id.'"');
                unset($_SESSION[$this->name]);
            }
        }
        if (os_not_null($this->icon)) $icon = os_image(http_path('payment').$this->code.'/'.$this->icon, $this->title);
        return array(
            'id' => $this->code,
            'icon' => $this->icon,
            'module' => $this->title,
            'title'=>$this->title
        );
    }

    function pre_confirmation_check()
    {
        global $cartID, $cart;
        if (empty($_SESSION['cart']->cartID)) $_SESSION['cartID'] = $_SESSION['cart']->cartID = $_SESSION['cart']->generate_cart_id();
        if (!isset($_SESSION['cartID'])) $_SESSION['cartID'] = $_SESSION['cart']->generate_cart_id();
    }

    function confirmation() {
        global $cartID, $cart_id, $customer_id, $languages_id, $order, $order_total_modules;
        if (isset($_SESSION['cartID'])) {
            $insert_order = false;
            if (isset($_SESSION[$this->name])) {
                $order_id = substr($_SESSION[$this->name], strpos($_SESSION[$this->name], '-'));
                $curr_check = os_db_query("select currency from " . TABLE_ORDERS . " where orders_id = '" . (int)$order_id . "'");
                $curr = os_db_fetch_array($curr_check);
                if (
                    ($curr['currency'] != $order->info['currency'])
                    ||
                    ($cartID != substr($cart_id, 0, strlen($cartID)))
                ){
                    $check_query = os_db_query('select orders_id from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '" limit 1');
                    if (os_db_num_rows($check_query) < 1) {
                        os_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
                        os_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
                        os_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
                        os_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
                        os_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
                        os_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
                    }
                    $insert_order = true;
                }
            } else $insert_order = true;
            if ($insert_order == true) {
                $order_totals = array();
                if (is_array($order_total_modules->modules)) {
                    reset($order_total_modules->modules);
                    while (list(, $value) = each($order_total_modules->modules)) {
                        $class = substr($value, 0, strrpos($value, '.'));
                        if($GLOBALS[$class]->enabled)
                            for($i=0, $n=sizeof($GLOBALS[$class]->output); $i<$n; $i++)
                                if
                                (
                                    os_not_null($GLOBALS[$class]->output[$i]['title'])
                                    &&
                                    os_not_null($GLOBALS[$class]->output[$i]['text'])
                                ) $order_totals[] = array(
                                    'code' => $GLOBALS[$class]->code,
                                    'title' => $GLOBALS[$class]->output[$i]['title'],
                                    'text' => $GLOBALS[$class]->output[$i]['text'],
                                    'value' => $GLOBALS[$class]->output[$i]['value'],
                                );
                    }
                }
                if ($_SESSION['customers_status']['customers_status_ot_discount_flag'] == 1)
                    $discount = $_SESSION['customers_status']['customers_status_ot_discount'];
                else $discount = '0.00';
                if ($_SERVER["HTTP_X_FORWARDED_FOR"])
                    $customers_ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
                else $customers_ip = $_SERVER["REMOTE_ADDR"];
                $sql_data_array = array(
                    'customers_id' => $_SESSION['customer_id'],
                    'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                    'customers_cid' => $order->customer['csID'],
                    'customers_vat_id' => $_SESSION['customer_vat_id'],
                    'customers_company' => $order->customer['company'],
                    'customers_status' => $_SESSION['customers_status']['customers_status_id'],
                    'customers_status_name' => $_SESSION['customers_status']['customers_status_name'],
                    'customers_status_image' => $_SESSION['customers_status']['customers_status_image'],
                    'customers_status_discount' => $discount,
                    'customers_street_address' => $order->customer['street_address'],
                    'customers_suburb' => $order->customer['suburb'],
                    'customers_city' => $order->customer['city'],
                    'customers_postcode' => $order->customer['postcode'],
                    'customers_state' => $order->customer['state'],
                    'customers_country' => $order->customer['country']['title'],
                    'customers_telephone' => $order->customer['telephone'],
                    'customers_email_address' => $order->customer['email_address'],
                    'customers_address_format_id' => $order->customer['format_id'],
                    'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                    'delivery_company' => $order->delivery['company'],
                    'delivery_street_address' => $order->delivery['street_address'],
                    'delivery_suburb' => $order->delivery['suburb'],
                    'delivery_city' => $order->delivery['city'],
                    'delivery_postcode' => $order->delivery['postcode'],
                    'delivery_state' => $order->delivery['state'],
                    'delivery_country' => $order->delivery['country']['title'],
                    'delivery_address_format_id' => $order->delivery['format_id'],
                    'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'billing_company' => $order->billing['company'],
                    'billing_street_address' => $order->billing['street_address'],
                    'billing_suburb' => $order->billing['suburb'],
                    'billing_city' => $order->billing['city'],
                    'billing_postcode' => $order->billing['postcode'],
                    'billing_state' => $order->billing['state'],
                    'billing_country' => $order->billing['country']['title'],
                    'billing_address_format_id' => $order->billing['format_id'],
                    'payment_method' => $order->info['payment_method'],
                    'payment_class' => $order->info['payment_class'],
                    'shipping_method' => $order->info['shipping_method'],
                    'shipping_class' => $order->info['shipping_class'],
                    'language' => $_SESSION['language'],
                    'customers_ip' => $customers_ip,
                    'orig_reference' => $order->customer['orig_reference'],
                    'login_reference' => $order->customer['login_reference'],
                    'cc_type' => $order->info['cc_type'],
                    'cc_owner' => $order->info['cc_owner'],
                    'cc_number' => $order->info['cc_number'],
                    'cc_expires' => $order->info['cc_expires'],
                    'date_purchased' => 'now()',
                    'orders_status' => $order->info['order_status'],
                    'currency' => $order->info['currency'],
                    'currency_value' => $order->info['currency_value']
                );
                os_db_perform(TABLE_ORDERS, $sql_data_array);
                $insert_id = os_db_insert_id();
                for ($i=0, $n=sizeof($order_totals); $i<$n; $i++) {
                    $sql_data_array = array(
                        'orders_id' => $insert_id,
                        'title' => $order_totals[$i]['title'],
                        'text' => $order_totals[$i]['text'],
                        'value' => $order_totals[$i]['value'],
                        'class' => $order_totals[$i]['code'],
                        'sort_order' => $order_totals[$i]['sort_order']
                    );
                    os_db_perform(TABLE_ORDERS_TOTAL, $sql_data_array);
                }
                for ($i=0, $n=sizeof($order->products); $i<$n; $i++) {
                    $sql_data_array = array(
                        'orders_id' => $insert_id,
                        'products_id' => os_get_prid($order->products[$i]['id']),
                        'products_model' => $order->products[$i]['model'],
                        'products_name' => $order->products[$i]['name'],
                        'products_price' => $order->products[$i]['price'],
                        'final_price' => $order->products[$i]['final_price'],
                        'products_tax' => $order->products[$i]['tax'],
                        'products_quantity' => $order->products[$i]['qty']
                    );
                    os_db_perform(TABLE_ORDERS_PRODUCTS, $sql_data_array);
                    $order_products_id = os_db_insert_id();
                    $attributes_exist = '0';
                    if (isset($order->products[$i]['attributes'])) {
                        $attributes_exist = '1';
                        for ($j=0, $n2=sizeof($order->products[$i]['attributes']); $j<$n2; $j++) {
                            if (DOWNLOAD_ENABLED == 'true') {
                                $attributes_query = "SELECT
                  popt.products_options_name,poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                left join " . TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD . " pad
                  on pa.products_attributes_id=pad.products_attributes_id
                where
                  pa.products_id = '" . $order->products[$i]['id'] . "'
                  and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                  and pa.options_id = popt.products_options_id
                  and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                  and pa.options_values_id = poval.products_options_values_id
                  and popt.language_id = '" . $_SESSION['languages_id'] . "'
                  and poval.language_id = '" . $_SESSION['languages_id'] . "'";
                                $attributes = os_db_query($attributes_query);
                            } else {
                                $attributes = os_db_query("SELECT
                  popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix
                from " . TABLE_PRODUCTS_OPTIONS . " popt, " . TABLE_PRODUCTS_OPTIONS_VALUES . " poval, " . TABLE_PRODUCTS_ATTRIBUTES . " pa
                where
                  pa.products_id = '" . $order->products[$i]['id'] . "'
                  and pa.options_id = '" . $order->products[$i]['attributes'][$j]['option_id'] . "'
                  and pa.options_id = popt.products_options_id
                  and pa.options_values_id = '" . $order->products[$i]['attributes'][$j]['value_id'] . "'
                  and pa.options_values_id = poval.products_options_values_id
                  and popt.language_id = '" . $_SESSION['languages_id'] . "'
                  and poval.language_id = '" . $_SESSION['languages_id'] . "'");
                            }
                            os_db_query("UPDATE ".TABLE_PRODUCTS_ATTRIBUTES." set
                attributes_stock=attributes_stock - '".$order->products[$i]['qty']."'
              where
                products_id='".$order->products[$i]['id']."'
                and options_values_id='".$order->products[$i]['attributes'][$j]['value_id']."'
                and options_id='".$order->products[$i]['attributes'][$j]['option_id']."'");
                            $attributes_values = os_db_fetch_array($attributes);
                            $sql_data_array = array(
                                'orders_id' => $insert_id,
                                'orders_products_id' => $order_products_id,
                                'products_options' => $attributes_values['products_options_name'],
                                'products_options_values' => $attributes_values['products_options_values_name'],
                                'options_values_price' => $attributes_values['options_values_price'],
                                'price_prefix' => $attributes_values['price_prefix']
                            );
                            os_db_perform(TABLE_ORDERS_PRODUCTS_ATTRIBUTES, $sql_data_array);
                            if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && os_not_null($attributes_values['products_attributes_filename'])) {
                                $sql_data_array = array(
                                    'orders_id' => $insert_id,
                                    'orders_products_id' => $order_products_id,
                                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                    'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                    'download_count' => $attributes_values['products_attributes_maxcount']
                                );
                                os_db_perform(TABLE_ORDERS_PRODUCTS_DOWNLOAD, $sql_data_array);
                            }
                        }
                    }
                }
                $cart_ik_id = $cartID . '-' . $insert_id;
                $_SESSION[$this->name] = $cart_ik_id;
            }
        }
        return array('title' => $this->description);
    }

    public function process_button(){
        global $currencies, $currency, $osPrice,$insert_id;
        $last_order_id = os_db_query("SELECT MAX(orders_id) AS max FROM " . TABLE_ORDERS);
        $id = os_db_fetch_array($last_order_id);
        $id = $id['max']++;
        $amount = number_format($this->order->info['total'], 2, '.', '');
        $url = htmlspecialchars($_SERVER['SERVER_NAME']);

        $tid = $this->getTerminal();

        $invoice_order = new INVOICE_ORDER($amount);
        $invoice_order->id = $id;

        $settings = new SETTINGS($tid);
        $settings->success_url = $url;

        $request = new CREATE_PAYMENT($invoice_order, $settings, array());
        $response = (new RestClient($this->login, $this->api_key))->CreatePayment($request);

        if($response == null or isset($response->error)) return '<h1>Payment error</h1>';

        $payment_url = $response->payment_url;

        $arg = array(
            'payment_url' => $payment_url,
        );
        $formdata = $arg;

        foreach ($formdata as $field => $value){
            $this->form .= os_draw_hidden_field($field,$value);
        }

        $sql_data_array = array(
            'orders_status' => 2,
        );

        os_db_perform(DB_PREFIX.'orders', $sql_data_array, 'update', "orders_id='".(int)$id."'");
        $_SESSION['invoice_order_id'] = $id;

        return $this->form;
    }

    public function before_process()
    {
        return false;
    }
    public function after_process()
    {
        $order_id = $_SESSION['invoice_order_id']+1;
        os_db_query('delete from ' . TABLE_ORDERS . ' where orders_id = "' . (int)$order_id . '"');
        os_db_query('delete from ' . TABLE_ORDERS_TOTAL . ' where orders_id = "' . (int)$order_id . '"');
        os_db_query('delete from ' . TABLE_ORDERS_STATUS_HISTORY . ' where orders_id = "' . (int)$order_id . '"');
        os_db_query('delete from ' . TABLE_ORDERS_PRODUCTS . ' where orders_id = "' . (int)$order_id . '"');
        os_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_ATTRIBUTES . ' where orders_id = "' . (int)$order_id . '"');
        os_db_query('delete from ' . TABLE_ORDERS_PRODUCTS_DOWNLOAD . ' where orders_id = "' . (int)$order_id . '"');
    }
    public function output_error()
    {
        return false;
    }
    public function check()
    {
        if (!isset($this->_check))
        {
            $check_query = os_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_INVOICE_STATUS'");
            $this->_check = os_db_num_rows($check_query);
        }
        return $this->_check;
    }
    public function install()
    {
        os_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_INVOICE_API_KEY', '', '6', '4', now())");
        os_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_INVOICE_LOGIN', '', '6', '4', now())");
        os_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, date_added) values ('MODULE_PAYMENT_INVOICE_DEFAULT_TERMINAL_NAME', '', '6', '4', now())");
        os_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) values ('MODULE_PAYMENT_INVOICE_STATUS', 'True', '6', '1', 'os_cfg_select_option(array(\'True\', \'False\'), ', now())");
        os_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, use_function, date_added) values ('MODULE_PAYMENT_INVOICE_ORDER_STATUS_ID', '0', '6', '0', 'os_cfg_pull_down_order_statuses(', 'os_get_order_status_name', now())");
    }
    public function remove()
    {
        os_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }
    public function keys()
    {
        return array(
            'MODULE_PAYMENT_INVOICE_STATUS',
            'MODULE_PAYMENT_INVOICE_LOGIN',
            'MODULE_PAYMENT_INVOICE_DEFAULT_TERMINAL_NAME',
            'MODULE_PAYMENT_INVOICE_API_KEY',
            'MODULE_PAYMENT_INVOICE_ORDER_STATUS_ID'
        );
    }

    public function getTerminal() {
        if(!file_exists('invoice_tid')) file_put_contents('invoice_tid', '');
        $tid = file_get_contents('invoice_tid');

        if($tid == null and empty($tid)) {
            $request = new CREATE_TERMINAL($this->default_terminal_name);
            $response = (new RestClient($this->login, $this->api_key))->CreateTerminal($request);

            if($response == null or empty($response->error)) throw new Exception('Terminal error');

            $tid = $response->id;

            file_put_contents('invoice_tid', $tid);
        }

        return $tid;
    }
}