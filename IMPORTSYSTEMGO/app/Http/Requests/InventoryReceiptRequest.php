<?php

namespace App\Http\Requests;

use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = app(BranchContext::class)->current();

        return $branch !== null
            && app(ScopedAuthorizationService::class)->allows($this->user(), 'inventory.stock.manage', $branch);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
            'unit_cost' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'reference' => ['required', 'string', 'alpha_dash:ascii', 'max:80'],
            'observations' => ['nullable', 'string', 'max:500'],
            'identifiers_text' => ['nullable', 'string', 'max:200000'],
        ];
    }
}
