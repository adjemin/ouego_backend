<?php

namespace App\Http\Requests;

use App\Models\OrderDelivery;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderDeliveryRequest extends FormRequest
{
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
        return OrderDelivery::$rules;
    }
}
