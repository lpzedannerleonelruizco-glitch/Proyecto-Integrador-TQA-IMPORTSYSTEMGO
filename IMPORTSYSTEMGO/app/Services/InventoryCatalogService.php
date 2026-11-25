<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\Authorization\GlobalAuthorizationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class InventoryCatalogService
{
    public function __construct(
        private readonly GlobalAuthorizationService $authorization,
    ) {}

    public function saveProduct(User $actor, array $data, ?Product $product = null, ?UploadedFile $image = null): Product
    {
        abort_unless($this->authorization->allows($actor, 'catalog.products.manage'), 403);
        $oldImage = $product?->image_path;

        return DB::transaction(function () use ($actor, $data, $product, $image, $oldImage): Product {
            Category::query()->whereKey($data['category_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            Brand::query()->whereKey($data['brand_id'])->where('is_active', true)->lockForUpdate()->firstOrFail();
            if ($product) {
                $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
                $before = $product->only($this->auditedProductFields());
                $trackingMode = $data['tracking_mode'];
                if ($trackingMode !== ($product->tracking_mode ?? ($product->requires_serial ? 'serial' : 'quantity'))
                    && (InventoryBalance::where('product_id', $product->id)->where('physical_quantity', '>', 0)->exists()
                        || InventoryMovement::where('product_id', $product->id)->exists()
                        || DB::table('sale_items')->where('product_id', $product->id)->exists())) {
                    throw new \DomainException('No se puede cambiar el control de series después de registrar actividad de inventario o ventas.');
                }
            } else {
                $product = new Product;
                $before = null;
            }

            $attributes = Arr::only($data, [
                'sku', 'barcode', 'name', 'category_id', 'brand_id', 'model', 'description',
                'list_price', 'minimum_stock', 'unit_of_measure', 'tracking_mode', 'is_active',
            ]);
            $attributes['requires_serial'] = in_array($data['tracking_mode'], ['serial', 'imei'], true);
            if ($image) {
                $attributes['image_path'] = $image->store('products', 'local');
            }
            $product->fill($attributes);
            $product->save();

            $this->audit(
                $actor,
                $product,
                $before === null ? 'catalog.product.created' : 'catalog.product.updated',
                $before,
                $product->only($this->auditedProductFields()),
            );
            if ($image && $oldImage && $oldImage !== $product->image_path) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($oldImage));
            }

            return $product->fresh(['category', 'brand']);
        }, 3);
    }

    public function setProductActive(User $actor, Product $product, bool $active): Product
    {
        abort_unless($this->authorization->allows($actor, 'catalog.products.manage'), 403);

        return DB::transaction(function () use ($actor, $product, $active): Product {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $before = ['is_active' => $product->is_active];
            if ($product->is_active !== $active) {
                $product->forceFill(['is_active' => $active])->save();
                $this->audit($actor, $product, $active ? 'catalog.product.activated' : 'catalog.product.deactivated', $before, ['is_active' => $active]);
            }

            return $product->fresh();
        }, 3);
    }

    public function saveTaxonomy(User $actor, string $type, array $data, Category|Brand|null $record = null): Category|Brand
    {
        abort_unless($this->authorization->allows($actor, 'catalog.products.manage'), 403);
        $modelClass = match ($type) {
            'categories' => Category::class,
            'brands' => Brand::class,
            default => throw new \InvalidArgumentException('Tipo de catálogo no permitido.'),
        };
        $record ??= new $modelClass;
        if (! $record instanceof $modelClass) {
            throw new \InvalidArgumentException('El registro no pertenece al catálogo indicado.');
        }

        return DB::transaction(function () use ($actor, $type, $data, $record): Category|Brand {
            if ($record->exists) {
                $record = $modelClass::query()->whereKey($record->id)->lockForUpdate()->firstOrFail();
            }
            $before = $record->exists ? $record->only(['code', 'name', 'is_active']) : null;
            $record->fill(Arr::only($data, ['code', 'name', 'is_active']));
            $record->save();
            $this->audit(
                $actor,
                $record,
                $before === null ? "catalog.{$type}.created" : "catalog.{$type}.updated",
                $before,
                $record->only(['code', 'name', 'is_active']),
            );

            return $record->fresh();
        }, 3);
    }

    private function audit(User $actor, Product|Category|Brand $resource, string $action, ?array $before, array $after): void
    {
        DB::table('audit_logs')->insert([
            'actor_user_id' => $actor->id,
            'branch_id' => null,
            'action' => $action,
            'resource_type' => match (true) {
                $resource instanceof Product => 'products',
                $resource instanceof Category => 'categories',
                default => 'brands',
            },
            'resource_id' => $resource->id,
            'before_values' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
            'after_values' => json_encode($after, JSON_THROW_ON_ERROR),
            'occurred_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function auditedProductFields(): array
    {
        return [
            'sku', 'barcode', 'name', 'category_id', 'brand_id', 'model', 'description',
            'list_price', 'minimum_stock', 'unit_of_measure', 'tracking_mode', 'image_path', 'is_active',
        ];
    }
}
