<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $itemId = $this->route('item');

        return [
            'name'                => ['sometimes', 'string', 'max:255'],
            'sku'                 => ['sometimes', 'string', 'max:100', Rule::unique('items', 'sku')->ignore($itemId)],
            'quantity'            => ['sometimes', 'integer', 'min:0'],
            'price'               => ['sometimes', 'numeric', 'min:0'],
            'min_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'category_id'         => ['sometimes', 'integer', 'exists:categories,id'],
            'status'              => ['sometimes', 'in:in stock,low stock,ordered,discontinued'],
        ];
    }
}
