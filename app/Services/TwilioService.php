<?php

namespace App\Services;

use Twilio\Rest\Client;

class TwilioService
{
    protected $client;
    protected $verifySid;
    protected $twilioNumber;

    public function __construct()
    {
        $sid = config('services.twilio.sid');
        $token = config('services.twilio.auth_token');
        $this->client = new Client($sid, $token);
        $this->verifySid = config('services.twilio.verify_sid');
        $this->twilioNumber = config('services.twilio.phone_number');
    }

    public function sendSms($to, $message)
    {
        try {
            // $verification = $this->client->verify->v2->services($this->verifySid)
            //     ->verifications
            //     ->create('+'.$to, 'sms'); // 'sms' or 'call' for voice OTP

            // return $verification->status;

            $this->client->messages->create('+'.$to, [
                'from' => $this->twilioNumber,
                'body' => $message,
            ]);

            return 'SMS sent successfully';
        } catch (\Exception $e) {
            return 'Error: ' . $e->getMessage();
        }
    }
}
