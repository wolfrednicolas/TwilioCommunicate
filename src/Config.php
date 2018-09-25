<?php
namespace Wolfred\Twilio;

use DB;
use Twilio\Rest\Client;

class Config
{
    public $config;

    public function __construct(){
        $this->config = [ "accountSid"  => getenv("ACCOUNTSID"),
                          "authToken"   => getenv("AUTHTOKEN"),
                          'env'         => getenv("APP_ENV"),
                          'name'        => getenv("APP_NAME"),
                          'url'         => getenv("APP_URL")
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

    public function getTwilioClient($account_id)
    {
        $phone_data = DB::table('infrastructure_config')
                            ->where('account_id', '=', $account_id)
                            ->where('service', 'twilio')
                            ->first();

        $data = json_decode($phone_data->data);

        $TWILIO_ACCOUNT_SID = $data->sid;
        $TWILIO_AUTH_TOKEN = $data->token;
    
        $client = new Client($TWILIO_ACCOUNT_SID, $TWILIO_AUTH_TOKEN);

        return $client;
    }

}