<?php

namespace App\Http\Controllers;

use App\BillTransaction;
use Illuminate\Support\Facades\Validator;
use App\Transaction;
use App\V2\DonationImage;
use Illuminate\Http\Request;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\FormTrait;
use App\Http\Traits\FileTrait;
use Illuminate\Support\Facades\App;

class TransactionController extends Controller
{
    use FormTrait;
    use FileTrait;

    function create()
    {
        App::setLocale('ar');

        $lbp_values = array(5000, 20000, 50000, 100000, 500000);
        $usd_values = array(5, 10, 50, 100, 500);

        $image = DonationImage::first();

        //Show 404 based on the Client request
        //return view('transactions.create', compact('usd_values', 'lbp_values'))->with(['image'=>($image)?$image->image:""]);
        return view('404');
    }

    public function store(Request $request)
    {
        $lbp_values = array(5000, 20000, 50000, 100000, 500000);
        $usd_values = array(5, 10, 50, 100, 500);
;

        if ($request->payment_type == "once")
        {
            $validator = $request->validate([
                'user_id' => 'nullable',
                'user_name' => 'required',
                'phone_number' => 'required',
                'email' => 'required',
                'currency' => 'required|in:USD,LBP',
            ]);

            if ($request->usd_amount == null and  $request->lbp_amount == null){
                return back()->with('error', 'الحقل amount الزامي');
            }

            $transaction = Transaction::create([
                'user_id' => request('user_id'),
                'transaction_id' => 'MOB_'.(int)(microtime(true)*1000),
                'currency' => currency_iso(request('currency')),
                'user_name' => request('user_name'),
                'phone_number' => request('phone_number'),
                'email' => request('email'),
            ]);

            if ($request->currency == "USD"){
                $transaction->amount = $request->usd_amount;
            }elseif ($request->currency == "LBP"){
                $transaction->amount = $request->lbp_amount;
            }
            $transaction->save();

            $redirect_route = route('netcommerce.payment.redirect',$transaction->id).'?payment=i-pay';
            return redirect($redirect_route);

        }
        else if($request->payment_type == "monthly")
        {
            $validator = $request->validate([
                'user_id' => 'nullable',
                'user_name' => 'required',
                'phone_number' => 'required',
                'email' => 'required|email',
                'currency' => 'required|in:USD,LBP',
            ]);

            if ($request->usd_amount == null and  $request->lbp_amount == null){
                return back();
            }

            $transaction = BillTransaction::create([
                'user_id' => request('user_id'),
                'currency' => currency_iso(request('currency')),
                'user_name' => request('user_name'),
                'phone_number' => request('phone_number'),
                'email' => request('email'),

            ]);
            $transaction->recurrent_freq = "monthly";
            $transaction->transaction_id = 'MOB_BILL_'.$transaction->id;

            if ($request->currency == "USD"){
                $transaction->amount = $request->usd_amount;
            }elseif ($request->currency == "LBP"){
                $transaction->amount = $request->lbp_amount;
            }

            $transaction->save();

            $redirect_route = route('netcommerce.payment.redirect',$transaction->id).'?payment=bill';
            return redirect($redirect_route);

        }

        return redirect()->route('transactions.create');
    }


    public function responseUpdateTransaction($success, $message)
    {

        if ($success == 1)
        {
            $message = "Your Donation has completed successfully. Thank you for supporting FPM.";
        }elseif ($success == 0)
        {
            $message = "Your Donation was not successful. Please try again later.";
        }

        return view('transaction.success')->with(['message', $message],['succeeded', $success]);

    }

}
