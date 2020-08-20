<?php


namespace App\Http\Helpers;


use \stdClass;
use App\voiceLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as twilioClient;

class VoiceCallHelper
{
    private $gateway = null;

    function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    public function makeCall($number, $message) {

        try {

            if(!$this->gateway){
                throw new Exception('voiceCall gateway not defined!');
            }
            // list is here AppHelper::VOICE_GATEWAY_LIST
            if($this->gateway->gateway == 1){
                return $this->makeCallViaSmsGatewayHub($number, $message);
            } else {
                // log voice call to file
                Log::channel('voiceLog')->info("Send new voice call to ".$number." for audio path is:\"".$message."\"");
                return true;
            }

        }
        catch (Exception $e) {
            //write error log
            Log::channel('voiceLog')->error($e->getMessage());
        }


        return true;

    }

    private function makeCallViaSmsGatewayHub($numbers, $message) {
        if(!is_array($numbers)){
            throw new Exception("Mobile number(s) must be an array");
        }
        try {
            $voiceCallGateway = $this->gateway;
            
            $curl = curl_init();
            $user = !empty($voiceCallGateway->user) ? $voiceCallGateway->user : '';
            $password = !empty($voiceCallGateway->password) ? $voiceCallGateway->password : '';
            $authorization = base64_encode($user.':'.$password);

            $messageBody['messages'] = ['from'=>$voiceCallGateway->sender_id,'to'=>$numbers,'audioFileUrl'=>$message];

            curl_setopt_array($curl, array(
             CURLOPT_URL => $voiceCallGateway->api_url, 
             CURLOPT_RETURNTRANSFER => true, 
             CURLOPT_ENCODING => "", 
             CURLOPT_MAXREDIRS => 10, 
             CURLOPT_TIMEOUT => 30, 
             CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1, 
             CURLOPT_CUSTOMREQUEST => "POST", 
             CURLOPT_SSL_VERIFYHOST => false,
             CURLOPT_SSL_VERIFYPEER => true,
              CURLOPT_POSTFIELDS => json_encode($messageBody),
              CURLOPT_HTTPHEADER => array(
                "accept: application/json",
                "authorization: Basic $authorization",
                "content-type: application/json"
              ),
            ));
             
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $response = (!empty($response)) ? json_decode($response) : [];
            curl_close($curl);
            if ($err) {
                Log::channel('voiceLog')->warning($status.". url=".$uri);
            } else {
                if(!empty($response)) {
                    $response = (!empty($response->messages)) ? $response->messages : '';
                   
                    foreach($response as $messages){
                        $to = (!empty($messages->to)) ? $messages->to : ''; 
                        $status = (!empty($messages->status->name)) ? $messages->status->name : ''; 
                        $description = (!empty($messages->status->description)) ? $messages->status->description : ''; 
                        $detail = json_encode($messages);
                        $log = $this->logVoiceToDB($to, $status, $description,$detail);
                    }
                }
                // Loop through each number and store it as a new row
                
            }

            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function logVoiceToDB($to, $status, $description, $detail){
        
        return voiceLog::create([
            'sender_id' => $this->gateway->sender_id,
            'to' => $to,
            'status' => $status,
            'description'=> $description,
            'detail'=>$detail,
            'message_id'=>$this->gateway->massage_id
        ]);

    }

    private function parseXmlResponse($response) {
        try {
            $responseXml = simplexml_load_string($response);
            if ($responseXml instanceof \SimpleXMLElement) {
                $status = (string)$responseXml->result->status;
                return $status;
            }
        }
        catch (Exception $e)
        {
            throw new Exception($e->getMessage());
        }

    }

}
