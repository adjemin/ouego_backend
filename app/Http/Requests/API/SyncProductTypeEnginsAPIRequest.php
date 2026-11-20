<?php

namespace App\Http\Requests\API;

use Illuminate\Validation\Rule;
use InfyOm\Generator\Request\APIRequest;

class SyncProductTypeEnginsAPIRequest extends APIRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'type_engin_ids' => ['present', 'array'],
            'type_engin_ids.*' => ['integer', 'distinct', Rule::exists('type_engins', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages()
    {
        return [
            'type_engin_ids.present' => 'Le champ type_engin_ids est obligatoire',
            'type_engin_ids.array' => 'Le champ type_engin_ids doit être une liste',
            'type_engin_ids.*.exists' => "Type d'engin introuvable",
        ];
    }
}
