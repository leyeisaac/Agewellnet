<?php

namespace App\Http\Controllers;

use App\Models\GeneralSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Product;
use App\Models\UserExtra;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function cron()
    {
        $gnl = GeneralSetting::first();
        $gnl->last_cron = Carbon::now()->toDateTimeString();
		$gnl->save();

        if ($gnl->matching_bonus_time == 'daily') {
            $day = Date('H');
            if (strtolower($day) != $gnl->matching_when) {
                return '1';
            }
        }

        if ($gnl->matching_bonus_time == 'weekly') {
            $day = Date('D');
            if (strtolower($day) != $gnl->matching_when) {
                return '2';
            }
        }

        if ($gnl->matching_bonus_time == 'monthly') {
            $day = Date('d');
            if (strtolower($day) != $gnl->matching_when) {
                return '3';
            }
        }

        if (Carbon::now()->toDateString() == Carbon::parse($gnl->last_paid)->toDateString()) {
            /////// bv done for today '------'
            ///////////////////LETS PAY THE BONUS

            $gnl->last_paid = Carbon::now()->toDateString();
            $gnl->save();

            $eligibleUsers = UserExtra::where('bv_left', '>=', $gnl->total_bv)->where('bv_right', '>=', $gnl->total_bv)->get();

            foreach ($eligibleUsers as $uex) {
                $user = $uex->user;
                $weak = $uex->bv_left < $uex->bv_right ? $uex->bv_left : $uex->bv_right;
                $weaker = $weak < $gnl->max_bv ? $weak : $gnl->max_bv;

                $pair = intval($weaker / $gnl->total_bv);

                $bonus = $pair * $gnl->bv_price;

                // add balance to User

                $payment = User::find($uex->user_id);
                $payment->balance += $bonus;
                $payment->save();

                $trx = new Transaction();
                $trx->user_id = $payment->id;
                $trx->amount = $bonus;
                $trx->charge = 0;
                $trx->trx_type = '+';
                $trx->post_balance = $payment->balance;
                $trx->remark = 'binary_commission';
                $trx->trx = getTrx();
                $trx->details = 'Paid ' . $bonus . ' ' . $gnl->cur_text . ' For ' . $pair * $gnl->total_bv . ' BV.';
                $trx->save();

                notify($user, 'matching_bonus', [
                    'amount' => $bonus,
                    'currency' => $gnl->cur_text,
                    'paid_bv' => $pair * $gnl->total_bv,
                    'post_balance' => $payment->balance,
                    'trx' =>  $trx->trx,
                ]);

                $paidbv = $pair * $gnl->total_bv;
                if ($gnl->cary_flash == 0) {
                    $bv['setl'] = $uex->bv_left - $paidbv;
                    $bv['setr'] = $uex->bv_right - $paidbv;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = 0;
                    $bv['lostr'] = 0;
                }
                if ($gnl->cary_flash == 1) {
                    $bv['setl'] = $uex->bv_left - $weak;
                    $bv['setr'] = $uex->bv_right - $weak;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = $weak - $paidbv;
                    $bv['lostr'] = $weak - $paidbv;
                }
                if ($gnl->cary_flash == 2) {
                    $bv['setl'] = 0;
                    $bv['setr'] = 0;
                    $bv['paid'] = $paidbv;
                    $bv['lostl'] = $uex->bv_left - $paidbv;
                    $bv['lostr'] = $uex->bv_right - $paidbv;
                }
                $uex->bv_left = $bv['setl'];
                $uex->bv_right = $bv['setr'];
                $uex->save();


                if ($bv['paid'] != 0) {
                    createBVLog($user->id, 1, $bv['paid'], 'Paid ' . $bonus . ' ' . $gnl->cur_text . ' For ' . $paidbv . ' BV.');
                    createBVLog($user->id, 2, $bv['paid'], 'Paid ' . $bonus . ' ' . $gnl->cur_text . ' For ' . $paidbv . ' BV.');
                }
                if ($bv['lostl'] != 0) {
                    createBVLog($user->id, 1, $bv['lostl'], 'Flush ' . $bv['lostl'] . ' BV after Paid ' . $bonus . ' ' . $gnl->cur_text . ' For ' . $paidbv . ' BV.');
                }
                if ($bv['lostr'] != 0) {
                    createBVLog($user->id, 2, $bv['lostr'], 'Flush ' . $bv['lostr'] . ' BV after Paid ' . $bonus . ' ' . $gnl->cur_text . ' For ' . $paidbv . ' BV.');
                }
            }
            return '---';
        }
    }

    public function monthlyReward()
    {

        $rd = Order::where('created_at', '<', Carbon::now()->subDays(30)->toDateString())-> first();
        $rd->last_paid = Carbon::now()->toDateTimeString();
		$rd->save();

       /*  $rd->reward_time = Date('monthly');
            $day = Date('d');
             strtolower($day); */


        if (Carbon::now()->toDateString() == Carbon::parse($rd->last_paid)->toDateString()) {
            if (User::where('product_id', 'status', 1)-> strtotime($date) < strtotime('-30 days'));
        //$this->validate($request, ['product_id' => 'required|integer']);
       // $monthly_buy = User::where('product_id', '>=', 1)->where('status', 1, 'product_id', ->subDays(30))->firstOrFail();

        /* $gnl = GeneralSetting::first();
        $gnl->last_cron = Carbon::now()->toDateTimeString();
		$gnl->save(); */



      //  if ($current == Carbon::parse($gnl->last_paid)->toDateString()) {
            /////// bv done for today '------'
            ///////////////////LETS PAY THE BONUS
            $rd->last_paid = Carbon::now()->toDateString();
            $rd->save();

            $qualifiedUsers = UserExtra::where('bv_left', '>=', '16000' && 'bv_right', '>=', '15000') || where ('bv_left', '>=', '15000' && 'bv_right', '>=', '16000') ->get();

            foreach ($qualifiedUsers as $uex) {
                $user = $uex->user;
                $left = $uex->bv_left ;
                $right = $uex->bv_right;

                $month_reward = $left + $right;

                $qualified_monthly = User::find($uex->user_id);
                $qualified_monthly -> monthly_reward+=$month_reward;
                $qualified_monthly->save();

                $trx = new Transaction();
                $trx->user_id = $qualified_monthly ->id;
                $trx->amount = '1250';
                $trx->charge = 0;
                $trx->trx_type = '+';
                $trx->post_balance = $qualified_monthly->monthly_reward;
                $trx->remark = 'monthly_reward';
                $trx->trx = getTrx();
                $trx->details = 'Paid ' . $month_reward . ' ' . ' For ' . $qualified_monthly;
                $trx->save();

                notify($user, 'monthly_bonus', [
                    'reward' => $monthly_reward,
                    'currency' => $gnl->cur_text,
                    'amount' => "1250",
                   'post_reward' => $qualified_monthly->monthly_reward,
                    'trx' =>  $trx->trx,

                ]);
            }

        return '---';
    }
    }

    public function interTripReward()
    {


       $rd = User::first();
        $rd->last_paid = Carbon::now()->toDateTimeString();
		$rd->save();

/*
        if ($gnl->monthly_bonus_time == 'monthly') {
            $day = Date('d');
            if (strtolower($day) != $gnl->reward_monthly_when) {
                return '1';
            }
        } */
              //SET MONTHLY PRODUCT STATUS HERE       IF()


              if (Carbon::now()->toDateString() == Carbon::parse($rd->last_paid)->toDateString()) {
                /////// bv done for today '------'
                ///////////////////LETS PAY THE BONUS
                $rd->last_paid = Carbon::now()->toDateString();
                $rd->save();

                $qualifiedUsers = UserExtra::where('bv_left', '>=', '25,000' && 'bv_right', '>=', '20,000' ) || where('bv_left', '>=', '20,000' && 'bv_right', '>=', '25,000') ->get();

                foreach ($qualifiedUsers as $uex) {
                    $user = $uex->user;
                    $left = $uex->bv_left ;
                    $right = $uex->bv_right;

                    $internation_reward = $left + $right;

                    $qualified_international = User::find($uex->user_id);
                    $qualified_international -> intern_trip += $internation_reward;
                    $qualified_international ->save();

                    $trx = new Transaction();
                    $trx->user_id = $qualified_international ->id;
                    $trx->amount = '12500';
                    $trx->charge = 0;
                    $trx->trx_type = '+';
                    $trx->post_balance = $qualified_international->intern_trip;
                    $trx->remark = 'internation_reward';
                    $trx->trx = getTrx();
                    $trx->details = 'Paid ' . $internation_reward . ' ' . ' For ' . $qualified_international;
                    $trx->save();


                    notify($user, 'international_bonus', [
                        'reward' => $international_reward,
                        'currency' => $gnl->cur_text,
                        'amount' => "12500",
                        'post_balance' => $qualified_international->intern_trip,
                        'trx' =>  $trx->trx,
                             ]);
                    }
                }
                    return '---';
    }

    public function carReward()
    {
        $rd = User::first();
        $rd->last_paid = Carbon::now()->toDateTimeString();
		$rd->save();

        if (Carbon::now()->toDateString() == Carbon::parse($gnl->last_paid)->toDateString()) {
            /////// bv done for today '------'
            ///////////////////LETS PAY THE BONUS
            $rd->last_paid = Carbon::now()->toDateString();
            $rd->save();

            $qualifiedUsers = UserExtra::where('bv_left', '>=', '100000' && 'bv_right', '>=', '80000') || where( 'bv_left', '>=', '80000' && 'bv_right', '>=', '100000') ->get();

            foreach ($qualifiedUsers as $uex) {
                $user = $uex->user;
                $left = $uex->bv_left ;
                $right = $uex->bv_right;

                $car_reward = $left + $right;

                $qualified_car = User::find($uex->user_id);
                $qualified_car->car += $car_reward;
                $qualified_car->save();

                $trx = new Transaction();
                $trx->user_id = $qualified_car ->id;
                $trx->amount = '12500';
                $trx->charge = 0;
                $trx->trx_type = '+';
                $trx->post_balance = $qualified_car->car;
                $trx->remark = 'car_reward';
                $trx->trx = getTrx();
                $trx->details = 'Paid ' . $car_reward . ' ' . ' For ' . $qualified_car;
                $trx->save();

                notify($user, 'car_bonus', [
                    'reward' => $car_reward,
                    'currency' => $gnl->cur_text,
                    'amount' => "12500",
                   'post_balance' => $qualified_car->car,
                    'trx' =>  $trx->trx,

                ]);
            }
        }
        return '---';
    }

    public function housingReward()
    {
        $rd = User::first();
        $rd->last_paid = Carbon::now()->toDateTimeString();
		$rd->save();

        if (Carbon::now()->toDateString() == Carbon::parse($gnl->last_paid)->toDateString()) {
            /////// bv done for today '------'
            ///////////////////LETS PAY THE BONUS
            $rd->last_paid = Carbon::now()->toDateString();
            $rd->save();

            $qualifiedUsers = UserExtra::where('bv_left', '>=', '600000' && 'bv_right', '>=', '500,000' ) || where('bv_left', '>=', '500000' &&'bv_right', '>=', '600,000') ->get();

            foreach ($qualifiedUsers as $uex) {
                $user = $uex->user;
                $left = $uex->bv_left ;
                $right = $uex->bv_right;

                $house_reward = $left + $right;

                $qualified_housing = User::find($uex->user_id);
                $qualified_housing -> housing_reward += $house_reward;
                $qualified_housing ->save();


                $trx = new Transaction();
                $trx->user_id = $qualified_housing ->id;
                $trx->amount = '25000';
                $trx->charge = 0;
                $trx->trx_type = '+';
                $trx->post_balance = $house_reward->housing_reward;
                $trx->remark = 'housing_reward';
                $trx->trx = getTrx();
                $trx->details = 'Paid ' . $housing_reward . ' ' . ' For ' . $qualified_housing;
                $trx->save();

                notify($user, 'car_bonus', [
                    'reward' => $housing_reward,
                    'currency' => $gnl->cur_text,
                    'amount' => "25000",
                    'post_balance' => $qualified_housing->housing_reward ,
                    'trx' =>  $trx->trx,

                ]);
            }
        }
        return '---';
    }

    public function globalReward()
    {
        $rd = User::first();
        $rd->last_paid = Carbon::now()->toDateTimeString();
		$rd->save();

        if (Carbon::now()->toDateString() == Carbon::parse($gnl->last_paid)->toDateString()) {
            /////// bv done for today '------'
            ///////////////////LETS PAY THE BONUS
            $rd->last_paid = Carbon::now()->toDateString();
            $rd->save();

            $qualifiedUsers = UserExtra::where('bv_left', '>=', '2000000' && 'bv_right', '>=', '1500000 ' ) || where('bv_left', '>=', '1500000' && 'bv_right', '>=', '2000000 ') ->get();
          //  $total_product_sold = Product:: sum('price');

            foreach ($qualifiedUsers as $uex) {
                $user = $uex->user;
                $left = $uex->bv_left ;
                $right = $uex->bv_right;

                $glob_reward = $left + $right;

              //  $reward = intval($total_product_sold * '0.01');

                $qualified_global = User::find($uex->user_id);
                $qualified_global -> global_reward+= $glob_reward;
                $qualified_global ->save();


                $trx = new Transaction();
                $trx->user_id = $qualified_global ->id;
                $trx->amount = $glob_reward;
                $trx->charge = 0;
                $trx->trx_type = '+';
                $trx->post_balance = $qualified_global->global_reward;
                $trx->remark = 'housing_reward';
                $trx->trx = getTrx();
                $trx->details = 'Paid ' . $glob_reward . ' ' . ' For ' . $qualified_global;
                $trx->save();


                notify($user, 'car_bonus', [
                    'reward' => $global_reward,
                   // 'currency' => $gnl->cur_text,
                   // 'amount' => "Global Reward",
                    'post_balance' => $qualified_global->global_reward,
                    'trx' =>  $trx->trx,

                ]);
            }
        }
        return '---';
    }

    public function stockistcom()
    {
        //if (Carbon::now()->toDateString() == Carbon::parse($gnl->last_paid)->toDateString()) {
            $current = Carbon::now();
     //   $this->validate($request, ['product_id' => 'required|integer']);
        $monthly_sale = User::where( 'role' ,'=', 'stockist' , $request->product_id)->where('total_invest', $current->subDays(30))->firstOrFail();

        foreach ($monthly_sale as $prod) {

         //check the plan for the stockist
           // if (User::where ('plan_id',))
            $user = $prod->stockist;
            /* $left = $uex->bv_left ;
            $right = $uex->bv_right; */

            $stock_com = $prod->total_invest * 0.02;

          //  $reward = intval($total_product_sold * '0.01');

            $qualified_stock = User::find($prod->user_id);
            $qualified_stock -> stockist_com += $stock_com;
            $qualified_stock ->save();


            $trx = new Transaction();
            $trx->user_id = $qualified_stock ->id;
            $trx->amount = $stock_com;
            $trx->charge = 0;
            $trx->trx_type = '+';
            $trx->post_balance = $qualified_stock->stockist_com;
            $trx->remark = 'stockist_com';
            $trx->trx = getTrx();
            $trx->details = 'Paid ' . $stock_com . ' ' . ' For ' . $qualified_stock;
            $trx->save();


            notify($user, 'stockist_com', [
                'amount' => $stock_com,
                'currency' => $gnl->cur_text,
             //  'amount' => "Global Reward",
                'post_balance' => $qualified_stockl->stockist_com,
                'trx' =>  $trx->trx,

            ]);
        }

    return '---';
    }
}
