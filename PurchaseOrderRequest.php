<?php

namespace App\Http\Requests;

class PurchaseOrderRequest extends Request
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
        $rules = [];
        if ($this->method() == 'POST') {
            $rules = [
                'quantity' => 'required|numeric|min:0|digits_between:1,11|purchase_order_validate_quantity',
            ];
        }
        return $rules;
    }

    public function messages()
    {
        $messages = [];
        $messages['purchase_order_validate_quantity'] = 'Quantity of items, for the suppliers should not exceed the quantity of an item';
        return $messages;
    }
}