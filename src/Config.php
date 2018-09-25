<?php
namespace Wolfred\Twilio;

use DB;

class Config
{
	public $config;

	public function __construct(){
		$this->config = [ "accountSid" 	=> getenv("ACCOUNTSID"),
						  "authToken" 	=> getenv("AUTHTOKEN"),
						  'env' 		=> getenv("APP_ENV"),
						  'name' 		=> getenv("APP_NAME"),
						  'url' 		=> getenv("APP_URL")
						];
	}

	public function setInfrastructureConfig($service, $field, $data, $account_id=NULL)
	{
		if (is_array($data))
            $data = json_encode($data);

        $query = [
            'account_id' => $account_id,
            'service'    => $service,
            'field'      => $field,
            'data'       => $data,
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s")
        ];

        DB::table('infrastructure_config')
            ->where('account_id', $account_id)
            ->where('service', $service)
            ->delete();

        DB::table('infrastructure_config')->insert($query);
        
        return true;

	}

}