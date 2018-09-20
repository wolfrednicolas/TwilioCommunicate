<?php
namespace TwilioCommunicate;

class Config
{
	public $config;

	public function __construct(){
		$this->config = [ "accountSid" 	=> getenv("accountsid"),
						  "authToken" 	=> getenv("authtoken"),
						  "subaccount"  => getenv("subaccount")];
	}

}