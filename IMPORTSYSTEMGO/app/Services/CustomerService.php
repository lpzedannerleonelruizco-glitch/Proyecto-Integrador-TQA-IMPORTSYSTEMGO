<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\User;
use App\Services\Authorization\ScopedAuthorizationService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly ScopedAuthorizationService $authorization) {}

    public function save(User $actor, Branch $branch, array $data, ?Customer $customer = null): Customer
    {
        if (! $this->authorization->allows($actor, 'customers.manage', $branch)
            || ($customer && $customer->branch_id !== $branch->id)) {
            throw new AuthorizationException('No puede gestionar clientes en esta sucursal.');
        }

        return DB::transaction(function () use ($actor, $branch, $data, $customer): Customer {
            $documentKey = $this->documentKey($data);
            if ($documentKey && Customer::query()->where('branch_id', $branch->id)->where('document_key', $documentKey)
                ->when($customer, fn ($query) => $query->where('id', '!=', $customer->id))->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['document_number' => 'Ya existe un cliente con ese documento en la sucursal.']);
            }

            $before = $customer?->only(['full_name', 'phone', 'email', 'is_active']);
            $customer ??= new Customer;
            $customer->fill($data);
            $customer->forceFill([
                'branch_id' => $branch->id,
                'document_key' => $documentKey,
                'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : ($customer->exists ? $customer->is_active : true),
                'is_demo' => (bool) config('demo.retail_enabled'),
                'created_by' => $customer->exists ? $customer->created_by : $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            DB::table('audit_logs')->insert([
                'actor_user_id' => $actor->id, 'branch_id' => $branch->id,
                'action' => $before ? 'customer.updated' : 'customer.created',
                'resource_type' => 'customers', 'resource_id' => $customer->id,
                'before_values' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
                'after_values' => json_encode($customer->only(['full_name', 'customer_type', 'document_type', 'is_active']), JSON_THROW_ON_ERROR),
                'occurred_at' => now(),
            ]);

            return $customer->fresh();
        }, 3);
    }

    public function documentKey(array $data): ?string
    {
        if (empty($data['document_type']) || empty($data['document_number'])) {
            return null;
        }

        return mb_strtolower($data['document_type'].':'.trim($data['document_number']).':'.trim($data['document_complement'] ?? ''));
    }
}
