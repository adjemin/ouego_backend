<?php

namespace App\Http\Requests;

use App\Models\TypeEngin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTypeEnginRequest extends FormRequest
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
        $rules = TypeEngin::$rules;
        
        return $rules;
    }
}
