<?php

namespace App\Http\Requests;

use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $branch = app(BranchContext::class)->current();

        return $branch !== null
            && app(ScopedAuthorizationService::class)->allows($this->user(), 'inventory.adjustments.request', $branch);
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity_after' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'reason_code' => ['required', Rule::in(['surplus', 'shortage', 'damaged', 'loss', 'count_correction'])],
            'reason' => ['required', 'string', 'max:500'],
            'idempotency_key' => ['required', 'string', 'alpha_dash:ascii', 'max:96'],
        ];
    }
}
