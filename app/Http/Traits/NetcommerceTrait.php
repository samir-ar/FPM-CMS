<?php

namespace App\Http\Traits;


use App\BillTransaction;
use App\Transaction;
use Carbon\Carbon;

trait NetcommerceTrait
{
    public function getTransactionInstance($id)
    {

        if(request('payment') == 'i-pay')
            $transaction = Transaction::find($id);
        else if(request('payment') == 'bill')
            $transaction = BillTransaction::find($id);

        return $transaction;

    }

    public function getTransactionInstanceByResponse_txtIndex($txtIndex)
    {
        /*
         * Ex: $transaction = Transaction::where('transaction_id', request('txtIndex'))->first();
         */

        $transaction = Transaction::where('transaction_id', request('txtIndex'))->first();

        return $transaction;

    }

    public function getTransactionInstanceByResponse_txtScheduleID($txtScheduleID)
    {
        /*
         * Ex: $transaction = Transaction::where('transaction_id', request('txtScheduleID'))->first();
         */


        $transaction = BillTransaction::where('transaction_id', request('txtScheduleID'))->first();


        return $transaction;
    }



    public function getConfigPath()
    {
        /*
         * This if we want to differentiate multiple config parameters depends on
         * Some Parameter we have
         * example: Having two different payments each with different methods and merchant ids
         *
         */

        if(request('payment') == 'i-pay')
            return 'services.netcommerce.iPay';
        else if(request('payment') == 'bill')
            return 'services.netcommerce.bill';

    }


    public function getPaymentType()
    {
        /*
         * This will return either IPAY or BILL
         * each has different params to be sent to NET_commerce
         */

        if(request('payment') == 'i-pay')
            return 'IPAY';
        else if(request('payment') == 'bill')
            return 'BILL';

    }


    public function getIpayParams($transaction)
    {

        $config_path = $this->getConfigPath();


        $parameters['payment_mode'] = config($config_path.'.mode');
        $parameters['txtAmount'] = $transaction->amount;
        $parameters['txtCurrency'] = $transaction->currency;
        $parameters['txtIndex'] = $transaction->transaction_id;
        $parameters['txtMerchNum'] = config($config_path.'.merchant_nb');
        $parameters['txthttp'] = route('netcommerce.payment.response');

        $signature = $parameters['txtAmount'].
            $parameters['txtCurrency'].
            $parameters['txtIndex'].
            $parameters['txtMerchNum'].
            $parameters['txthttp'].config($config_path.'.merchant_key');

        $parameters['signature'] = $secureHash=hash('sha256',$signature,false);

        $parameters['first_name'] = $transaction->user_name ? : 'not filled';
        $parameters['last_name'] = $transaction->user_name ? : 'not filled';
        $parameters['email'] = $transaction->email ? : 'namirabboud@gmail.com';
        $parameters['mobile'] = $transaction->phone_number ? : '009613123456';
        $parameters['address_line1'] = $transaction->phone_number ? : '009613123456';
        $parameters['city'] = 'City';
        $parameters['country'] = 'Lebanon';

        return $parameters;
    }

    public function getBillParameters($transaction)
    {
        $config_path = $this->getConfigPath();


        $parameters['txtFirstName'] = $transaction->user_name;
        $parameters['txtLastName'] = $transaction->user_name;
        $parameters['txtEmail'] = $transaction->email;
        $parameters['txtPhone'] = $transaction->phone_number;
        $parameters['txtMobile'] = $transaction->phone_number;
        $parameters['txtCountry'] = $transaction->phone_number;
        $parameters['txtCountry'] = 'Lebanon';
        $parameters['txtCity'] = 'City';
        $parameters['txtAddress'] = $transaction->phone_number;
        $parameters['txtMerchNum'] = config($config_path.'.merchant_nb');


        /*
         * txtMerchReq Values
         * add_sch : register a new schedule
         * rac_sch: re-activate a schedule
         * dac_sch: de-activate a schedule
         * del_sch: delete a schedule
         * upd_sch: update a schedule
         * upd_cc: update a credit card
         *
         */

        $parameters['txtMerchReq'] = 'add_sch';



        $parameters['txthttp'] = route('netcommerce.payment.response');
        $parameters['txtScheduleID'] = $transaction->transaction_id;



        /*
         * txt_ScheduleStatus Values
         * 1 : schedule is active
         * 2 : schedule is not active
         *
         */

        $parameters['Flag_ScheduleStatus'] = 1;



        $parameters['txtRecurrentAmount'] = $transaction->amount;


        /*
         * txtRecurrentFreq Values
         * monthly, quarterly, yearly, bi-yearly
         *
         */

        $parameters['txtRecurrentFreq'] = $transaction->recurrent_freq;




        $parameters['txtCurrency'] = $transaction->currency;



        /*
         * Flag_IsPaymentEnds Values
         * 1: schedule has an end
         * 0: schedule is endless.
         *
         */

        $parameters['Flag_IsPaymentEnds'] = 0;


        $parameters['txtNumInstallments'] = '';


        $parameters['txtStartPaymentDate'] = Carbon::today()->addMonth()->format('dmY');
        $parameters['txtEndPaymentDate'] = '';

        $parameters['Flag_IsInstantPayment'] = 1;
        $parameters['txtInstantAmount'] = $transaction->amount;
        $parameters['txtInstantDescr'] = ' not set';
        $parameters['Flag_BypassCardCheck'] = 1; //setting this to 0 will make a transaction with 1$ to check the card
        $parameters['next_payment_amount'] = $transaction->amount;
        $parameters['next_payment_date'] = Carbon::today()->addMonth()->format('dmY');
        //$parameters['txtNumAut'] = '';
        //$parameters['RespCode'] = '';



        if($parameters['txtMerchReq'] == 'add_sch'){
            //sha256(txtMerchNum&txtMerchReq&txtScheduleID&txthttp&Flag_ScheduleStatus&tx
            //tFirstName&txtLastName&txtsEmail&txtPhone&txtMobile&txtCountry&txtCity&txtAdd
            //ress&txtRecurrentAmount&txtRecurrentFreq&txtCurrency&Flag_IsPaymentEnds&txtN
            //umInstallments&txtStartPaymentDate&txtEndPaymentDate&Flag_IsInstantPayment&t
            //xtInstantAmount&txtInstantDescr&sha256_key)

            $signature = $parameters['txtMerchNum'].
                $parameters['txtMerchReq'].$parameters['txtScheduleID'].$parameters['txthttp'].
                $parameters['Flag_ScheduleStatus'].$parameters['txtFirstName'].$parameters['txtLastName'].
                $parameters['txtEmail'].$parameters['txtPhone'].$parameters['txtMobile'].
                $parameters['txtCountry'].$parameters['txtCity'].$parameters['txtAddress'].
                $parameters['txtRecurrentAmount'].$parameters['txtRecurrentFreq'].$parameters['txtCurrency'].
                $parameters['Flag_IsPaymentEnds'].$parameters['txtNumInstallments'].$parameters['txtStartPaymentDate'].
                $parameters['txtEndPaymentDate'].$parameters['Flag_IsInstantPayment'].$parameters['txtInstantAmount'].
                $parameters['txtInstantDescr'].config($config_path.'.merchant_key');
        }


        $parameters['signature'] = $secureHash=hash('sha256',$signature,false);


        //dd($parameters);
        return $parameters;

    }


    public function responseUpdateTransaction($transaction)
    {


        $data = request()->all();

        $succeeded = isset($data['RespCode']) ? (request('RespCode') == '00' ? true : false) : (request('RespVal') == 1 ? true : false);

        //update the transaction
        $transaction->resp_code = isset($data['RespVal']) ? request('RespVal') : request('RespCode');
        $transaction->resp_msg	= $succeeded ? 'Your Donation has completed successfully. Thank you for supporting FPM.' : request('RespMsg').', Transaction id: '.$transaction->id.', Amount: '.$transaction->amount.', Currency: '.iso_to_string($transaction->currency);
        $transaction->auth_nb	= request('txtNumAut');
        $transaction->amount	= request('txtAmount');
        $transaction->sub_msg	= request('sub');
        $transaction->completed = true;
        $transaction->success	= $succeeded;
        $transaction->save();

        return $transaction;
    }


    public function responseAdditionalUpdates($transaction)
    {
        //do something with the update transaction
    }
}
?>
