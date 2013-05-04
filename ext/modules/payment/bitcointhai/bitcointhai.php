<?php
// Bitcoin.in.th IPN module

chdir('../../../..');
include('includes/application_top.php');

include_once(DIR_WS_CLASSES.'bitcointhai.php');

$api = new bitcointhaiAPI;

$data = $_POST;

if($ipn = $api->verifyIPN($data)){
	$query = tep_db_query("SELECT orders_id, orders_status FROM ".TABLE_ORDERS." WHERE orders_id='".tep_db_input($data['reference_id'])."'");
	if($rec = tep_db_fetch_array($query)){
		$order_status = ($data['success'] == 1 ? MODULE_PAYMENT_BITCOINTHAI_CONFIRMED_STATUS_ID : $rec['orders_status']);
		if($order_status == 0){
			$order_status = DEFAULT_ORDERS_STATUS_ID;
		}
		
		tep_db_query("UPDATE ".TABLE_ORDERS." SET orders_status='".$order_status."' WHERE orders_id='".$rec['orders_id']."'");
		
		$sql_data_array = array('orders_id' => $rec['orders_id'],
							    'orders_status_id' => $order_status,
							    'date_added' => 'now()',
							    'customer_notified' => '0',
							    'comments' => '[Bitcoin IPN: '.$data['message'].']');
		
		tep_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	}
}else{
	header("HTTP/1.0 403 Forbidden");
	echo 'IPN Failed';
}
?>