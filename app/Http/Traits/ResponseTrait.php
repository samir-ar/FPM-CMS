<?php
namespace App\Http\Traits;

use Illuminate\Support\Facades\Lang;

trait ResponseTrait
{
    public function api_error_response($debugger = NULL, $code = 1, $message = NULL)
    {
        $error_data['error'] = array();
        $error_data['error']['message']     = utf8_decode(utf8_encode(Lang::get($message)));
        $error_data['error']['code']        = $code;
        $error_data['error']['debugger']    = $debugger;

        return response()->json($error_data)->setStatusCode(400);
    }
}