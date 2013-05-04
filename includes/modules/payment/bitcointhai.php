<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2003 osCommerce

  Released under the GNU General Public License
*/

  class bitcointhai {
    var $code, $title, $description, $enabled, $api, $ipn;

// class constructor
    function bitcointhai() {
      global $order;

      $this->code = 'bitcointhai';
      $this->title = MODULE_PAYMENT_BITCOINTHAI_TEXT_TITLE;
      $this->description = MODULE_PAYMENT_BITCOINTHAI_TEXT_DESCRIPTION;
      $this->sort_order = MODULE_PAYMENT_BITCOINTHAI_SORT_ORDER;
      $this->enabled = ((MODULE_PAYMENT_BITCOINTHAI_STATUS == 'True') ? true : false);

      if ((int)MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID > 0) {
        $this->order_status = MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID;
      }

      if (is_object($order)) $this->update_status();
    }

// class methods
    function update_status() {
      global $order;

      if ( ($this->enabled == true) && ((int)MODULE_PAYMENT_BITCOINTHAI_ZONE > 0) ) {
        $check_flag = false;
        $check_query = tep_db_query("select zone_id from " . TABLE_ZONES_TO_GEO_ZONES . " where geo_zone_id = '" . MODULE_PAYMENT_BITCOINTHAI_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
        while ($check = tep_db_fetch_array($check_query)) {
          if ($check['zone_id'] < 1) {
            $check_flag = true;
            break;
          } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
            $check_flag = true;
            break;
          }
        }
		
        if ($check_flag == false) {
          $this->enabled = false;
        }
      }
	  
		include_once(DIR_WS_CLASSES.'bitcointhai.php');
		
		$this->api = new bitcointhaiAPI;
		if(!$this->api->init(MODULE_PAYMENT_BITCOINTHAI_API_ID, MODULE_PAYMENT_BITCOINTHAI_API_KEY)){
			$this->enabled = false;
		}elseif(!$this->api->validate($order->info['total'],$order->info['currency'])){
			$this->enabled = false;
		}


// disable the module if the order only contains virtual products
      if ($this->enabled == true) {
        if ($order->content_type == 'virtual') {
          $this->enabled = false;
        }
      }
    }

    function javascript_validation() {
      return false;
    }

    function selection() {
      return array('id' => $this->code,
                   'module' => $this->title);
    }

    function pre_confirmation_check() {
      return false;
    }

    function confirmation() {
		global $order;
		$this->api->order_id = $_SESSION['bitcoin_order_id'];
		$data = array('amount' => $order->info['total'],
					  'currency' => $order->info['currency'],
					  'ipn' => tep_href_link('ext/modules/payment/bitcointhai/bitcointhai.php', '', 'SSL', false, false));
		if(!$paybox = $this->api->paybox($data)){
			return array('title' => MODULE_PAYMENT_BITCOINTHAI_TEXT_ERROR);
		}
		$_SESSION['bitcoin_order_id'] = $this->api->order_id;
		$btc_url = 'bitcoin:'.$paybox->address.'?amount='.$paybox->btc_amount.'&label='.urlencode(STORE_NAME);
		
		$fields = array(array('title' => MODULE_PAYMENT_BITCOINTHAI_TEXT_ADDRESS,'field' => '<a href="'.$btc_url.'">'.$paybox->address.'</a>'),
					    array('title' => MODULE_PAYMENT_BITCOINTHAI_TEXT_AMOUNT,'field' => $paybox->btc_amount.' BTC'));
		
      return array('title' => '<input type="hidden" name="order_id" value="'.$paybox->order_id.'"><div style="float:left; margin:10px;"><a href="'.$btc_url.'"><img src="data:image/png;base64,'.$paybox->qr_data.'" width="200" alt="Send to '.$paybox->address.'" border="0"></a></div><p>'.sprintf(MODULE_PAYMENT_BITCOINTHAI_TEXT_PAYMSG,$paybox->btc_amount,$paybox->address).'</p><p>'.MODULE_PAYMENT_BITCOINTHAI_TEXT_AFTERPAY.'</p>'.$this->api->countDown($paybox->expire,'table',MODULE_PAYMENT_BITCOINTHAI_TEXT_COUNTDOWN,MODULE_PAYMENT_BITCOINTHAI_TEXT_COUNTDOWN_EXP),'fields'=> $fields);
    }

    function process_button() {
      return false;
    }

    function before_process() {
      $result = $this->api->checkorder($_POST['order_id']);
	  if(!$result || $result->error != ''){
		  if(!$result){
			  $e = MODULE_PAYMENT_BITCOINTHAI_TEXT_ERROR;
		  }else{
			  $e = $result->error;
			  if(isset($result->order_id)){
				  $_SESSION['bitcoin_order_id'] = $result->order_id;
			  }
		  }
		  tep_redirect(str_replace('amp;','',tep_href_link(FILENAME_CHECKOUT_PAYMENT, 'payment_error=' . $this->code . '&error=' . urlencode($e), 'SSL')));
	  }
	  return true;
    }

    function after_process() {
		global $insert_id, $order;
		unset($_SESSION['bitcoin_order_id']);
		$this->api->sendReference($_POST['order_id'], $insert_id);
		
		  $sql_data_array = array('orders_id' => $insert_id, 
								  'orders_status_id' => $order->info['order_status'], 
								  'date_added' => 'now()', 
								  'customer_notified' => false,
								  'comments' => '[Bitcoin: Payment awaiting confirmation]');
		  tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
      return false;
    }

    function get_error() {
      return array('title' => MODULE_PAYMENT_BITCOINTHAI_TITLE_ERROR,
                     'error' => urldecode($_GET['error']));
    }

    function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from " . TABLE_CONFIGURATION . " where configuration_key = 'MODULE_PAYMENT_BITCOINTHAI_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install() {
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added) values ('Enable Cash On Delivery Module', 'MODULE_PAYMENT_BITCOINTHAI_STATUS', 'True', 'Do you want to accept Bitcoin payments?', '6', '1', 'tep_cfg_select_option(array(\'True\', \'False\'), ', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added) values ('Payment Zone', 'MODULE_PAYMENT_BITCOINTHAI_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '2', 'tep_get_zone_class_title', 'tep_cfg_pull_down_zone_classes(', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Access ID', 'MODULE_PAYMENT_BITCOINTHAI_API_ID', '0', 'API Access ID from http://bitcoin.in.th/merchants/', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('API Access KEY', 'MODULE_PAYMENT_BITCOINTHAI_API_KEY', '0', 'API Access Key from http://bitcoin.in.th/merchants/', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added) values ('Sort order of display.', 'MODULE_PAYMENT_BITCOINTHAI_SORT_ORDER', '0', 'Sort order of display. Lowest is displayed first.', '6', '0', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('Before Confirmation Order Status', 'MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID', '0', 'Set the status of orders made with this payment module to this value', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
      tep_db_query("insert into " . TABLE_CONFIGURATION . " (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added) values ('After Confirmation Order Status', 'MODULE_PAYMENT_BITCOINTHAI_CONFIRMED_STATUS_ID', '0', 'Set the status of orders after payment confirmation is received', '6', '0', 'tep_cfg_pull_down_order_statuses(', 'tep_get_order_status_name', now())");
   }

    function remove() {
      tep_db_query("delete from " . TABLE_CONFIGURATION . " where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      return array('MODULE_PAYMENT_BITCOINTHAI_STATUS', 'MODULE_PAYMENT_BITCOINTHAI_ZONE', 'MODULE_PAYMENT_BITCOINTHAI_API_ID','MODULE_PAYMENT_BITCOINTHAI_API_KEY','MODULE_PAYMENT_BITCOINTHAI_ORDER_STATUS_ID', 'MODULE_PAYMENT_BITCOINTHAI_SORT_ORDER','MODULE_PAYMENT_BITCOINTHAI_CONFIRMED_STATUS_ID');
    }
  }
?>
