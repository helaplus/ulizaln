<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckTransactionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:transaction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Query transaction status and update subscription';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $trxs = Transaction::query()->where('status',0)->get();

        if(count($trxs) > 0){
            foreach($trxs as $trx){
                $url=$trx->verify_url;
                $response=Http::get($url);
                $content =json_decode($response->body(),true);

                if(!$content){
                    continue;
                }

                if($content['settled']){

                    $check_sub = Subscription::query()->where('phone',$trx->phone)->first();
                    if($check_sub){
                        $check_sub->status = 1;
                        $check_sub->start_date = Carbon::now()->toDateTimeString();
                        $check_sub->end_date = Carbon::now()->addDays(30)->toDateTimeString();
                        $check_sub->save();
                    }else{
                        $subscription = new Subscription();
                        $subscription->phone = $trx->phone;
                        $subscription->status = 1;
                        $subscription->start_date = Carbon::now()->toDateTimeString();
                        $subscription->end_date = Carbon::now()->addDays(30)->toDateTimeString();
                        $subscription->save();
                    }


                    $trx_update = Transaction::query()->find($trx->id);
                    $trx_update->status = 1;
                    $trx_update->save();
                }

            }
        }
    }
}
