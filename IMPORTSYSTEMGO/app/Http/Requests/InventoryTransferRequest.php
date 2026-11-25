<?php

namespace App\Http\Requests;

use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class InventoryTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = app(BranchContext::class)->current();

        return $branch !== null
            && app(ScopedAuthorizationService::class)->allows($this->user(), 'inventory.transfers.request', $branch);
    }

    public function rules(): array
    {
        return [
            'source_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'destination_warehouse_id' => ['required', 'integer', 'different:source_warehouse_id', 'exists:warehouses,id'],
            'idempotency_key' => ['required', 'string', 'alpha_dash:ascii', 'max:96'],
            'reason' => ['required', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999.99'],
        ];
    }
}
