<?php 
namespace Wolfred\Twilio;

use Twilio\Rest\Client;
use Twilio\Exceptions\TwilioException;
use URL;

class Communicate
{
    protected $config;

    public function __construct(){
        $config = new Config();
        $this->config = $config->config;
        $this->conf = $config;
    }

    public function hasInstance($bool = true)  
    {  
        return $bool;  
    }

    public function createSubaccount($account_id, $FCM=NULL, $APNS=NULL)
    {
        $name = "{$this->config['name']}_{$this->config['env']}_{$account_id}";
        $twilio = new Client($this->config['accountSid'], $this->config['authToken']);
        $sub_account = $twilio->api->v2010->accounts
                              ->create(array("friendlyName" => $name));
        $client = new Client($sub_account->sid, $sub_account->authToken);

        $new_key = $client->newKeys
                 ->create(array('friendlyName' => $account_id));

        $array = [
            'sid'   => $sub_account->sid,
            'token' => $sub_account->authToken,
            'api_key' => $new_key->sid,
            'api_secret' => $new_key->secret
        ];

        //credentials for firebase
        if($FCM !== NULL){
           \Log::info('entro fcm');
           /* $FCM_credential = $client->notify->v1->credentials->create("fcm",
                                          array(
                                              "friendlyName" => "FCMCredentialFor{$account->id}",
                                              "secret" => getenv('FCM_SECRET')
                                         ) 
                                 );     
            $array_merge = [
                'FCM_push_credential' => $FCM_credential->sid
            ];
            $array = array_merge($array,$array_merge);*/
        }

        //APNS for IOS notification, se deberia configurar los certificados
        if($APNS !== NULL){
            \Log::info('entro APNS');
            /*$env = getenv('APP_ENV');
            $credentials = \Config::get('services.APNS.'.$env);
            $APN_credential = $client->notify->v1->credentials
                                    ->create("apn", // type
                                             array(
                                                 "certificate" => $credentials['certificate'],
                                                 "friendlyName" => "APNCredentialFor{$account->id}",
                                                 "privateKey" => $credentials['privateKey'],
                                                 "sandbox" => (boolean) $credentials['sandbox']
                                             )
                                    );
            $array_merge = [
                'APN_push_credential' => $APN_credential->sid
            ];
            $array = array_merge($array,$array_merge);  */  
        }

        $application = $client->api->applications->create('Main', [
            'VoiceUrl'             => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/initial",
            'VoiceMethod'          => 'POST',

            'VoiceFallbackUrl'     => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/initial",
            'VoiceFallbackMethod'  => 'POST',

            'StatusCallback'       => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/status",
            'StatusCallbackMethod' => 'POST',

            'SmsUrl'               => "{$this->config['url']}/infrastructure/tracking/phone/incoming?account_id={$account_id}&message_type=sms",
            'SmsMethod'            => 'POST',

            'SmsFallbackUrl'       => "{$this->config['url']}/infrastructure/tracking/phone/incoming?account_id={$account_id}&message_type=sms",
            'SmsFallbackMethod'    => 'POST'
        ]);

        $voip_car = $client->applications->create('VoIP', [
            'VoiceUrl'             => "{$this->config['url']}/twilio-mobile/voice/{account_id}",
            'VoiceMethod'          => 'POST'
        ]);
        
        $array_merge = [
            'application_sid' => $application->sid,
            'voip_sid' => $voip_car->sid
        ];
        $array = array_merge($array,$array_merge);  
        

        $this->conf->setInfrastructureConfig('twilio', 'authentication', $array, $account_id);
    }

    public function checkPhone($account_id, $phone)
    {
        $client=$this->conf->getTwilioClient($account_id);
        try {
            $number = $client->lookups->phoneNumbers($phone)->fetch();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function findAvailable($account_id, $area_code)
    {
        //area code or country iso
        $client=$this->conf->getTwilioClient($account_id);
        try {
            $length = strlen($area_code);
            if($length>3){
                return false;
            }
            if($length == 2){ # search by a country ISO code
                try {
                    $numbers = $client->availablePhoneNumbers($area_code)->local->read();
                    $numbersData = Array();

                    foreach($numbers as $number){
                        $data = $this->conf->processData($number);
                        array_push($numbersData, $data);
                    }
                    return $numbersData;
                }catch (\RestException $e) { // Search for Mobile number if any Local found
                    try{
                        $numbers = $client->availablePhoneNumbers($area_code)->mobile->read();
                        $numbersData = Array();

                        foreach($numbers as $number){
                            $data = $this->conf->processData($number);
                            array_push($numbersData, $data);
                        }

                        return $numbersData;
                    }catch (\RestException $e) {
                        #$country = DB::table('address_countries')->where('iso',$area_code)->first();
                        #throw new PhoneNumberSearchException("No phone numbers found for ".$country->name);
                        return "no Phone numbers availables for this country";
                    }

                }
            }else { # numerical area code
                if($area_code == "787"){ #Puerto rico
                    $numbers = $client->availablePhoneNumbers('PR')->local->read();
                    #var_dump($numbers); die();
                }
                else{ # search for US local number
                    $numbers = $client->availablePhoneNumbers('US')->local->read(
                        array("areaCode" => $area_code)
                    );

                    if (count($numbers) < 1){ # No US number, search in Canada
                        $numbers = $client->availablePhoneNumbers('CA')->local->read(
                            array("areaCode" => $area_code)
                        );
                    }
                } 

                $numbersData = Array();

                foreach($numbers as $number){
                    $data = $this->conf->processData($number);
                    array_push($numbersData, $data);
                }

                return $numbersData;
            }
        
        } catch (\RestException $e) {
            \Log::info('error de mensaje inesperado', (array)$e->getMessage());
            \Log::info('error de codigo inesperado', (array)$e->getCode());
            return "";
        }
    }

    public function findNumbersByFeatures($account_id, $iso_country, $options)
    {
        //iso country
        try{
            $client=$this->conf->getTwilioClient($account_id);
            $numbers = $client->availablePhoneNumbers($iso_country)->local->read(
                $options
            );
            $numbersData = Array();
            foreach($numbers as $number){
                $data = $this->conf->processData($number);
                array_push($numbersData, $data);
            }
        }catch (\RestException $e) {
            $numbersData =[];
        }
        return $numbersData;
    }

    public function findTollFreeNumbers($account_id, $iso_country, $optional = [])
    {
        //iso country
        try{
            $client=$this->conf->getTwilioClient($account_id);
            $numbers = $client->availablePhoneNumbers($iso_country)->tollFree->read(

            );
            $numbersData = Array();
            foreach($numbers as $number){
                $data = $this->conf->processData($number);
                array_push($numbersData, $data);
            }
        }catch (\RestException $e) {
            $numbersData =[];
        }
        return $numbersData;
    }

    public function createPhoneNumber($account_id, $number, $voip)
    {
       $client = $this->conf->getTwilioClient($account_id);
        try {

            //TODO: don't use route here
            $params = [
                'PhoneNumber'          => $number,

                'VoiceUrl'             => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/initial",
                'VoiceMethod'          => 'POST',

                'VoiceFallbackUrl'     => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/initial",
                'VoiceFallbackMethod'  => 'POST',

                'StatusCallback'       => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/status",
                'StatusCallbackMethod' => 'POST',

                'SmsUrl'               =>"{$this->config['url']}/infrastructure/tracking/phone/incoming?account_id={$account_id}&message_type=sms",
                'SmsMethod'            => 'POST',

                'SmsFallbackUrl'       => "{$this->config['url']}/infrastructure/tracking/phone/incoming?account_id={$account_id}&message_type=sms",
                'SmsFallbackMethod'    => 'POST'
            ];
         
            $number = $client->incomingPhoneNumbers->create($params);

            if($voip === true){
                $data = $this->conf->get("twilio", "authentication", $account_id);
                try {
                    $client->incomingPhoneNumbers($number->sid)
                        ->update([
                            'VoiceApplicationSid' => $data['voip_sid']
                        ]);
                    
                } catch (\RestException $e) {
                    return "Error inesperado al convertir en VOIP";
                }
            }
            return $number->sid;

        } catch (\RestException $e) {
            return "Error inesperado al crear el numero";
        }
    }

    public function deleteNumber($account_id, $number_sid)
    {
        try {
            $client = $this->conf->getTwilioClient($account_id);
            $client->incomingPhoneNumbers($number_sid)->delete();
            return true;
        } catch (\RestException $e) {
            return "Error inesperado al borrar el numero";
        }
    }

    public function setAsVoipReceiver($account_id, $number_sid)
    {
        $client = $this->conf->getTwilioClient($account_id);
        $data = $this->conf->get("twilio", "authentication", $account_id);
        try {
            $client->incomingPhoneNumbers($number_sid)
                ->update([
                    'VoiceApplicationSid' => $data['voip_sid']
                ]);
            return true;
        } catch (\RestException $e) {
            return "Error inesperado al convertir en VOIP";
        }
    }

    public function UnSetAsVoipReceiverAndSetForwarder($account_id, $number_sid){

       $client = $this->conf->getTwilioClient($account_id);

       try {

            $params = [
                'VoiceUrl'             => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/initial",
                'VoiceMethod'          => 'POST',

                'VoiceFallbackUrl'     => "{$this->config['url']}/{$account_id}/infrastructure/phone/calls/initial",
                'VoiceFallbackMethod'  => 'POST',
                'VoiceApplicationSid' => null,
            ];

            $client->incomingPhoneNumbers($number_sid)
                ->update($params);
            return true;
        } catch (\RestException $e) {
            return "Error inesperado al quitar el numero como VOIP";
        }
    }

    public function UnSetAsVoipReceiverAndSetVoicemail($account_id, $number_sid, $url){

       $client = $this->conf->getTwilioClient($account_id); 
       try {
            $params = [
                'VoiceApplicationSid' => null,
                'voiceFallbackUrl' => $url,
                'voiceUrl' => $url,
                'VoiceMethod'          => 'POST',
            ];

            $client->incomingPhoneNumbers($number_sid)
                ->update($params);
            
        } catch (RestException $e) {
            return "Error inesperado al setear como voicemail";
        }
    }

    public function getConfig(){
        return $this->config;
    }

    public function listNumbers($account_id){
        $client = $this->conf->getTwilioClient($account_id); 
        #$account = $twilio->api->v2010->accounts($this->config['subaccount'])->fetch();
        $incomingPhoneNumbers = $client->incomingPhoneNumbers->read();

        $numbers = [];
        foreach ($incomingPhoneNumbers as $number) {
            $numbers[] = (object) [ 'phoneNumber' => $number->phoneNumber,
                     'sid'         => $number->sid ];
        }

        return $numbers;
    }

    public function logCalls($account_id, $optional)
    {
        $client = $this->conf->getTwilioClient($account_id);
        $logArray = Array();
        foreach ($client->calls->read($optional) as $call) {
            $data = $this->conf->getLogData($call);
            array_push($logArray, $data);
        }
        return $logArray;
    }

    public function twilioLookUp($account_id, $phone, $optional)
    {
        $client = $this->conf->getTwilioClient($account_id);
        $phone_number = $client->lookups->v1->phoneNumbers($phone)
                                    ->fetch($optional);
        return $phone_number;
    }

    public function changeStatusSubAccount($account_id, $status)
    {
        \Log::info('eto es status', (array)$status);
        $data = $this->conf->get("twilio", "authentication", $account_id);

        $twilio = new Client($this->config['accountSid'], $this->config['authToken']);

        $account = $twilio->api->v2010->accounts($data['sid'])
                              ->update(array("status" => $status));
        return $account;
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