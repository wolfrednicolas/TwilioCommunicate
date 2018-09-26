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

    public function get($service, $field, $account_id)
    {
        $result = DB::table('infrastructure_config')
                    ->where('account_id', $account_id)
                    ->where('service', $service)
                    ->where('field', $field)
                    ->first();

                    
        if (!$result){
            //throw new ConfigurationNotFoundException("No configuration values found for {$service}.{$field}");
            return false;
        }
        $decoded = json_decode($result->data, true);
        return $decoded;
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

    public function processData($number){
        $data = Array(
            'friendlyName' => $number->friendlyName,
            'phoneNumber' => $number->phoneNumber,
            'lata' => $number->lata,
            'rateCenter' => $number->rateCenter,
            'latitude' => $number->latitude,
            'longitude' => $number->longitude,
            'region' => $number->region,
            'postalCode' => $number->postalCode,
            'isoCountry' => $number->isoCountry,
            'capabilities' => $number->capabilities,
            'countryCode' => $number->isoCountry
        );

        return $data;
    }

}