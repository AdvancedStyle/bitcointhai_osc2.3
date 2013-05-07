<?php
/*
Bitcoin.in.th API class
*/
class bitcointhaiAPI
{
	var $access_id, $access_key;
	var $api_url = 'http://api.bitcoin.in.th';
	var $order_id;
	var $error;
	public function init($api_id, $api_key){
		if(strlen($api_id) < 12 || strlen($api_key) < 12){
			return false;
		}
		$this->access_id = $api_id;
		$this->access_key = $api_key;
		return true;
	}
	
	public function validate($amount, $currency){
		$params = $this->authParam();
		$params['amount'] = $amount;
		$params['currency'] = $currency;
		if($data = $this->apiFetch('validate',$params)){
			$this->error = $data->errors;
			return $data->success;
		}
	}
	
	public function checkorder($order_id, $reference_id=''){
		$params = $this->authParam();
		$params['order_id'] = $order_id;
		if($reference_id != ''){
			$params['reference_id'] = $reference_id;
		}
		if($data = $this->apiFetch('checkorder',$params)){
			return $data;
		}
		return false;
	}
	
	public function sendReference($order_id, $reference_id){
		$params = $this->authParam();
		$params['order_id'] = $order_id;
		$params['reference_id'] = $reference_id;
		if($data = $this->apiFetch('savereference',$params)){
			return $data;
		}
	}
	
	public function paybox($data){
		$params = $this->authParam();
		$params['amount'] = $data['amount'];
		$params['currency'] = $data['currency'];
		$params['ipn'] = $data['ipn'];
		$params['order_id'] = (int)$this->order_id;
		if($data = $this->apiFetch('paybox',$params)){
			$this->order_id = $data->order_id;
			return $data;
		}
		return false;
	}
	
	public function countDown($expire,$selector, $text = 'You must send the bitcoins within the next %s Minutes %s Seconds',$expiremsg = 'Bitcoin payment time has expired, please refresh the page to get a new address'){
		return '<p class="bitcoincountdown">'.sprintf($text,'<span id="btcmins">'.$expire.'</span>','<span id="btcsecs">0</span>').'</p><script src="https://ajax.googleapis.com/ajax/libs/jquery/1.9.1/jquery.min.js"></script>
		<script>
			if(typeof bitcointhaitimer == \'undefined\'){
			var bitcointhaitimer = '.(60*$expire).';
			jQuery.noConflict();
			jQuery(function($){
				function btccountDown(){
					bitcointhaitimer -= 1;
					var minutes = Math.floor(bitcointhaitimer / 60);
					var seconds = bitcointhaitimer - minutes * 60;
					$("#btcmins").text(minutes);
					$("#btcsecs").text(seconds);
					if(bitcointhaitimer <= 0){
						$("#btcmins").closest("'.$selector.'").after("<p>'.$expiremsg.'</p>").remove();
					}else{
						setTimeout(btccountDown,1000);
					}
				}
				setTimeout(btccountDown,1000);
			});
			}
		</script>';
	}
	
	public function verifyIPN($data){
		if(!empty($data)){
			$params = array('verify' => $data['verify'],
							'order_id' => $data['order_id']);
			if($data = $this->apiFetch('verifyipn',$params)){
				return $data->success;
			}
		}
		return false;
	}
	
	private function apiFetch($action,$params){
		if($ch = curl_init ()){
			curl_setopt ( $ch, CURLOPT_URL, $this->api_url.'?action='.$action);
			curl_setopt ( $ch, CURLOPT_FOLLOWLOCATION, true );
			curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
			curl_setopt ( $ch, CURLOPT_CONNECTTIMEOUT, 5 );
			curl_setopt ($ch, CURLOPT_POST,count($params));
			curl_setopt ($ch, CURLOPT_POSTFIELDS,$params);
			
			$str = curl_exec ( $ch );
			curl_close ( $ch );
			if($data = json_decode($str)){
				return $data;
			}
		}
		return false;
	}
	
	private function authParam(){
		return array('api_id' => $this->access_id, 'api_key' => $this->access_key);
	}
}
?>