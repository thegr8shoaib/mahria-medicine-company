<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.batch_number' => ['required', 'string', 'max:50'],
            'items.*.expiry_date' => $this->route('purchase')
                ? ['required', 'date']
                : ['required', 'date', 'after:today'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.sale_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.items_per_pack' => ['nullable', 'integer', 'min:0'],
        ];
    }
}