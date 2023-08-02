<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\WhatsappLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            $message = 'Hello there, please subscribe to continue';
        }

            if(isset($details['messages'][0]['interactive']['button_reply'])){

                //button reply action:
                $response = $this->processButtonReply($details);
                if($response !=null){
                    $this->sendResponse($contact,'text',$response);
                }
                return response()->json(['message'=>"success"],200);
            }
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
        return $response;
    }

    public function checkSub($phone){
        $sub = Subscription::query()->where('phone',$phone)->first();
        if($sub){
            if(Carbon::parse($sub->end_date)->greaterThanOrEqualTo(Carbon::now()) ){
                return true;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function sub(){

    }
}
