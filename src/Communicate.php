<?php 
namespace TwilioCommunicate;

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;

class Communicate
{
	protected $config;

	public function __construct(){
		$r = new Config();
		$this->config = $r->config;
	}

	public function hasInstance($bool = true)  
	{  
		return $bool;  
	}

	public function getConfig(){
		return $this->config;
	}

	public function getNumbers(){
		$twilio  = new Client($this->config['accountSid'], $this->config['authToken']);
		$account = $twilio->api->v2010->accounts($this->config['subaccount'])->fetch();
		$incomingPhoneNumbers = $account->incomingPhoneNumbers->read();

		$n = [];
		foreach ($incomingPhoneNumbers as $number) {
			$n[] = (object) [ 'phoneNumber' => $number->phoneNumber,
				     'sid'		   => $number->sid ];
		}

		return $n;
	}

	public function sendMessage($from, $to, $message)
	{
		try 
		{
			$twilio  = new Client($this->config['accountSid'], $this->config['authToken']);
			$account = $twilio->api->v2010->accounts($this->config['subaccount'])->fetch();
			$message = $account->messages->create($to, ["body"=>$message, "from"=>$from]);
			return $message;
		} catch(TwilioException $e) {
			return $e->getMessage();
		}
	}

} 