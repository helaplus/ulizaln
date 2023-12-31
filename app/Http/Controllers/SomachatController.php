<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\WhatsappLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Storage;

class SomachatController extends Controller
{

    public function receiver(Request $request){
        $WhatsappLog = new WhatsappLog();
        $WhatsappLog->details = json_encode($request->all());
        $WhatsappLog->save();
        $details = $request->all();
        if(empty($details)){
            $details = $request->json()->all();
        }


        $contact = $details['contacts'][0]['wa_id'];

        //check sub
        if(!$this->checkSub($contact)){
            //Generate invoice
            $media = $this->generateQrcodeInvoice($contact);
            $message = 'Hello there, please subscribe to continue. Pay using lightning payments. A lightning invoice has been generated for you. Copy the payment request string below to your lightning wallet and complete the payment.'.PHP_EOL.PHP_EOL.$media['pr'];

            $res = $this->sendResponse($contact,'text',$message);

            // $res = $this->sendMediaMessage($contact,'image',$media['qr-code'],$message);
            // Log::info(json_encode($res));
            exit;
        }

            // if(isset($details['messages'][0]['interactive']['button_reply'])){

            //     //button reply action:
            //     $response = $this->processButtonReply($details);
            //     if($response !=null){
            //         $this->sendResponse($contact,'text',$response);
            //     }
            //     return response()->json(['message'=>"success"],200);
            // }
            if(isset($details['messages'][0]['interactive']['list_reply'])){
                $message = $details['messages'][0]['interactive']['list_reply']['id'];
            }else{
                $message = $details['messages'][0]['text']['body'];
            }
            if(!$message){
                $message = "We could not understand your question. Kindly reply with your prompt";
                $this->sendResponse($contact,'text',$message);
                return response()->json(['message'=>"success"],200);
            }
            $message = strtolower($message);
            $message = $this->getResponseFromChatGPT($message);


            $WhatsappLog = new WhatsappLog();
            $WhatsappLog->details = $message;
            $WhatsappLog->save();

            $this->sendResponse($contact,'text',$message);
            return response()->json(['message'=>"success"],200);
    }

    public function processButtonReply($details){
        $id = $details['messages'][0]['interactive']['button_reply']['id'];
        $exploded = explode("#",$id);
        switch ($exploded[0]) {
            case 'Somachat':
                //Somachat
                $phone = $details['contacts'][0]['wa_id'];
                $message = $this->getResponseFromChatGPT("What should I learn today?");
                break;
        }

        return $message;

    }

    public function getResponseFromChatGPT($message){

        if($message == 'soma' || $message=='somachat'){
            $message = "Welcome, you can go ahead and ask anything";
            return $message;
        }

        $client = \OpenAI::client(env('OPENAI_API_KEY'));
        $result = $client->completions()->create([
            'model' => 'text-davinci-003',
//            'model' => 'gpt-3.5-turbo',
            'prompt' => "Reply in brief: ".$message,
            'temperature' => 0.9,
            'max_tokens' => 250,
        ]);
        $WhatsappLog = new WhatsappLog();
        $WhatsappLog->details = json_encode($result);
        $WhatsappLog->save();
        $response = ltrim($result['choices'][0]['text'], $characters = " \n\r\t\v\x00");
        return trim($response);
    }

    public function sendResponse($to,$type,$message){

        $data = array();
        $data['messaging_product'] = "whatsapp";
        $data['recipient_type'] = "individual";
        $data['to'] = $to;
        $data['type'] = $type;
        $data['text'] = [
            'body' =>$message
        ];

        $apiURL = env('META_ENDPOINT');
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $token = env('META_BEARER_TOKEN');
        $response = Http::withToken($token)->withHeaders($headers)->post($apiURL, $data);
        // $response = Http::withHeaders($headers)->post($apiURL, $data);
        return $response;
    }

    public function checkSub($phone){
        $sub = Subscription::query()->where('phone',$phone)->first();
        if($sub){
            if(Carbon::parse($sub->end_date)->greaterThanOrEqualTo(Carbon::now()) ){
                Log::info('TRUE');
                return true;
            }else{
                Log::info('FALSE');
                return false;
            }
        }else{
            Log::info('FALSE');
            return false;
        }
    }

    public function sub(){

    }
    public function generateQrcodeInvoice($phone){
        $payment_request=self::generatePaymentRequest($phone);
        if(!$payment_request){
            return false;
        }

        $qr='qr'.rand(11111,1111111).'.png';
        QrCode::format('png')->generate($payment_request,storage_path($qr));
        // $res = $this->uploadMedia(storage_path($qr));

        $trx = Transaction::query()->where('payment_request',$payment_request)->first();
        if($trx){
            $trx->wa_media_id = storage_path($qr);
            $trx->save();
        }

        return [
            'qr-code' => storage_path($qr),
            'pr' => $payment_request
        ];
    }

    public function generatePaymentRequest($phone) {
        $url=config('app.lightinging_url');
        $response=Http::get($url);
        $content =json_decode($response->body(),true);
        if(!$content){
            return false;
        }

        $verify_url = $content['verify'];
        $pr = $content['pr'];

        //log trx
        $trx = new Transaction();
        $trx->payment_request = $pr;
        $trx->phone = $phone;
        $trx->verify_url = $verify_url;
        $trx->amount_in_milisats = 1000;
        $trx->save();

        return $pr;
    }

    public function sendMediaMessage($to,$type,$media,$message){
        $data = array();
        $data['recipient_type'] = "individual";
        $data['to'] = $to;
        $data['type'] = $type;
        $data['interactive'] = [
            'type'=> "button",
            'header'=> [
                'type'=>'image',
                'image'=> [
                    'id' =>$media,
                ]
            ],
            'body'=>[
                'text'=> $message
            ],
            'footer'=> [
                'text'=>'Powered by Helaplus.com'
            ]
        ];

        $data ['messaging_product'] = 'whatsapp';
        $apiURL = env('META_ENDPOINT');
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $token = env('META_BEARER_TOKEN');
        $response = Http::withToken($token)->withHeaders($headers)->post($apiURL, $data);
        return $response;
    }

    public function uploadMedia($image_path){
        $headers = [
            'Content-Type' => 'application/json',
        ];

        // $data_img = file_get_contents($image_path);
        $data = array();
        $data['messaging_product'] = "whatsapp";
        $data['type'] = 'image/png';
        $data['file'] = $image_path;

        $apiURL = env('META_MEDIA_ENDPOINT');
        $token = env('META_BEARER_TOKEN');
        $response = Http::withToken($token)->withHeaders($headers)->post($apiURL, $data);

        $WhatsappLog = new WhatsappLog();
        $WhatsappLog->details = json_encode($response);
        $WhatsappLog->save();

        return $response;
    }
}
