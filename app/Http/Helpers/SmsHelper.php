<?php


namespace App\Http\Helpers;


use \stdClass;
use App\smsLog;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as twilioClient;

class SmsHelper
{
    private $gateway = null;

    function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    public function sendSms($number, $message) {

        try {

            if(!$this->gateway){
                throw new Exception('SMS gateway not defined!');
            }
            // list is here AppHelper::SMS_GATEWAY_LIST
            if($this->gateway->gateway == 1){
                return $this->sendSmsViaBulkSmsRoute($number, $message);
            }
            elseif ($this->gateway->gateway == 2) {
                return $this->sendSmsViaItSolutionbd($number, $message);
            }
            elseif ($this->gateway->gateway == 3) {
                return $this->sendSmsViaZamanIt($number, $message);
            }
            elseif ($this->gateway->gateway == 4) {
                return $this->sendSmsViaMimSms($number, $message);
            }
            elseif ($this->gateway->gateway == 5) {
                return $this->sendSmsViaTwilio($number, $message);
            }
            elseif ($this->gateway->gateway == 6) {
                return $this->sendSmsViaBulkSmsRoute($number, $message);
            }
            elseif ($this->gateway->gateway == 7) {
                return $this->sendSmsViaMsg91($number, $message);
            }
            elseif ($this->gateway->gateway == 8) {
                return $this->sendSmsViaText160($number, $message);
            }
            else {
                // log sms to file
                Log::channel('smsLog')->info("Send new sms to ".$number." and message is:\"".$message."\"");
                return true;
            }

        }
        catch (Exception $e) {
            //write error log
            Log::channel('smsLog')->error($e->getMessage());
        }


        return true;

    }

    private function sendSmsViaBulkSmsRoute($number, $message) {
        try {

            $client = new Client();
            $uri = $this->gateway->api_url."?api_key=".$this->gateway->user."&type=text&contacts=".$number."&senderid=".$this->gateway->sender_id."&msg=".urlencode($message);
            $response = $client->get($uri);
            $status = json_decode($response->getBody());

            $isSuccess = false;
            switch ($status) {
                case "1002":
                    $msg = "Sender Id/Masking Not Found";
                    break;
                case "1003":
                    $msg = "API Not Found";
                    break;
                case "1004":
                    $msg = "SPAM Detected";
                    break;
                case "1005":
                    $msg = "Internal Error";
                    break;
                case "1006":
                    $msg = "Internal Error";
                    break;
                case "1007":
                    $msg = "Balance Insufficient";
                    break;
                case "1008":
                    $msg = "Message is empty";
                    break;
                case "1009":
                    $msg = "Message Type Not Set";
                    break;
                case "1010":
                    $msg = "Invalid User & Password";
                    break;
                case "1011":
                    $msg = "Invalid User Id";
                    break;
                default:
                    $msg = 'SMS SEND';
                    $isSuccess = true;
                    break;
            }

            if($isSuccess) {

                $log = $this->logSmsToDB($number, $message, $msg);
            }
            else{
                Log::channel('smsLog')->warning($msg.". url=".$uri);
            }

            return true;

        } catch (RequestException $e) {
            throw new Exception($e->getMessage());
        }


    }

    private function sendSmsViaMsg91($numbers, $message) {
        if(!is_array($numbers)){
            throw new Exception("Mobile number(s) must be an array");
        }
        try {
            $smsGateway = $this->gateway;
            
            $client = new Client();
            // $sms_gateway_url = $smsGateway->api_url.'?route=4&sender='.urlencode($smsGateway->sender_id).'&mobiles='.implode(",", $numbers).'&authkey='.urlencode($smsGateway->user).'&message='.urlencode($message).'&country=91';

            $smsObject = new stdClass();
            $smsObject->message = $message;
            $smsObject->to = $numbers;
            $response = $client->post(
                $smsGateway->api_url,
                array(
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'authkey' => $smsGateway->user
                    ],
                    'json' => array(
                        'sender' => $smsGateway->sender_id,
                        'route' => '4',
                        'country' => '91',
                        "sms" => [$smsObject],
                    ),
                )
            );
            $status =$response->getStatusCode();
//            $contents =  $response->getBody()->getContents();
            if($status == 200 || $status == "200") {
                // Loop through each number and store it as a new row
                foreach($numbers as $number){
                    $log = $this->logSmsToDB($number, $message, "SMS SENT");
                }
            }
            else{
                Log::channel('smsLog')->warning($status.". url=".$uri);
            }

            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function sendSmsViaText160($numbers, $message) {
        if(!is_array($numbers)){
            throw new Exception("Mobile number(s) must be an array");
        }
        try {
            $client = new Client();
            $uri = $this->gateway->api_url."?authkey=".urlencode($this->gateway->user)."&sender=".urlencode($this->gateway->sender_id)."&message=".urlencode($message)."&country=91&route=4&mobiles=".implode(",", $phoneNumbers);
            $response = $client->get($uri);
            $status = $response->getBody()->getContents();
            if($status == 200 || $status == "200"){
                // Loop through each number and store it as a new row
                foreach($numbers as $number){
                    $log = $this->logSmsToDB($number, $message, "SMS SENT");
                }
            }
            else{
                Log::channel('smsLog')->warning($status.". url=".$uri);
            }

            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function sendSmsViaItSolutionbd($number, $message) {
        try {
            $client = new Client();
            $uri = $this->gateway->api_url."?user=".urlencode($this->gateway->user)."&password=".urlencode($this->gateway->password)."&sender=".urlencode($this->gateway->sender_id)."&SMSText=".urlencode($message)."&GSM=".$number."&type=longSMS";
            $response = $client->get($uri);
            $status = $response->getBody()->getContents();
            if($status !="-5" && $status !="5")
            {
                $log = $this->logSmsToDB($number, $message, "SMS SEND");
            }
            else{
                Log::channel('smsLog')->warning($status.". url=".$uri);
            }

            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function sendSmsViaZamanIt($number, $message) {
        try {

            $client = new Client();
            $uri = $this->gateway->api_url."?user=".urlencode($this->gateway->user)."&password=".urlencode($this->gateway->password)."&sender=".urlencode($this->gateway->sender_id)."&SMSText=".urlencode($message)."&GSM=".$number."&type=longSMS";
            $response = $client->get($uri);
            $status = $this->parseXmlResponse($response->getBody()->getContents());

            if($status !="-5" && $status !="5")
            {
                $log = $this->logSmsToDB($number, $message, "SMS SEND");
            }
            else{
                Log::channel('smsLog')->warning($status.". url=".$uri);
            }

            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function sendSmsViaMimSms($number, $message) {
        try {
            $client = new Client();
            $uri = $this->gateway->api_url."?user=".urlencode($this->gateway->user)."&password=".urlencode($this->gateway->password)."&sender=".urlencode($this->gateway->sender_id)."&SMSText=".urlencode($message)."&GSM=".$number."&type=longSMS";
            $response = $client->get($uri);
            $status = $response->getBody()->getContents();
            if($status !="-5" && $status !="5")
            {
                $log = $this->logSmsToDB($number, $message, "SMS SEND");
            }
            else{
                Log::channel('smsLog')->warning($status.". url=".$uri);
            }

            return true;

        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    private function sendSmsViaTwilio($number, $message) {

        if (!preg_match("/^\+/i", $number)){
            $number = "+".$number;
        }

        $sid    = $this->gateway->user;
        $token  = $this->gateway->password;
        $twilio = new twilioClient($sid, $token);
        $data = [
            "body" => $message
        ];

        if(strlen($this->gateway->sender_id)){
            $data["from"] = $this->gateway->sender_id ;
        }

        $response = $twilio->messages->create($number, $data);
        if(!$response->errorCode){
            $log = $this->logSmsToDB($number, $message, "SMS SEND");
        }
        else{
            Log::channel('smsLog')->warning($response->errorMessage.". url=twilio api");
        }
        return true;
    }

    private function logSmsToDB($to, $message, $status){

        return smsLog::create([
            'sender_id' => $this->gateway->sender_id,
            'to' => $to,
            'message' => $message,
            'status' => $status,
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
