<?php

namespace App\Http\Requests\Api;

use App\Http\Traits\ResponseTrait;
use Illuminate\Foundation\Http\FormRequest;

class ContentRequest extends FormRequest
{
    use ResponseTrait;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'channel_id' => 'required|exists:channel_owners,id',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        return $this->api_error_response('missing_parameters', 101,implode(', ', $validator->messages()->all()));
    }

}
