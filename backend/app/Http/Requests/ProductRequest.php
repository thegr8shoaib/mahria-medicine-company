<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'generic_name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'variants' => ['nullable', 'string'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'barcode' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'trade_price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['required', Rule::in(['tablet', 'capsule', 'syrup', 'injection', 'ointment', 'drops', 'pack'])],
            'items_per_pack' => ['nullable', 'integer', 'min:1'],
            'low_stock_alert' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}