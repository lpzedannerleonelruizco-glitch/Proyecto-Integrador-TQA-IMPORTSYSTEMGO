<?php

namespace App\Http\Requests;

use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InventoryProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(GlobalAuthorizationService::class)
            ->allows($this->user(), 'catalog.products.manage');
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        return [
            'sku' => ['required', 'string', 'max:48', Rule::unique('products', 'sku')->ignore($productId)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('products', 'barcode')->ignore($productId)],
            'name' => ['required', 'string', 'max:160'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'model' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'list_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'minimum_stock' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999.99'],
            'unit_of_measure' => ['required', 'string', 'max:24'],
            'tracking_mode' => ['required', Rule::in(['quantity', 'serial', 'imei'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'is_active' => ['required', 'boolean'],
            'acquisition_cost' => ['prohibited'],
            'requires_serial' => ['prohibited'],
            'is_demo' => ['prohibited'],
        ];
    }
}
