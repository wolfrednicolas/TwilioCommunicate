<?php
namespace Wolfred\Twilio;

class Config
{
	public $config;

	public function __construct(){
		$this->config = [ "accountSid" 	=> getenv("accountsid"),
						  "authToken" 	=> getenv("authtoken"),
						  "subaccount"  => getenv("subaccount")];
	}

}