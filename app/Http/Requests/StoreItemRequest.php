<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                => ['required', 'string', 'max:255'],
            'sku'                 => ['required', 'string', 'max:100', 'unique:items,sku'],
            'quantity'            => ['required', 'integer', 'min:0'],
            'price'               => ['required', 'numeric', 'min:0'],
            'min_stock_threshold' => ['required', 'integer', 'min:0'],
            'category_id'         => ['required', 'integer', 'exists:categories,id'],
            'status'              => ['sometimes', 'in:in stock,low stock,ordered,discontinued'],
        ];
    }
}
