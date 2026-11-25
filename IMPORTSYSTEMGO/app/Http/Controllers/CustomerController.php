<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\Authorization\BranchContext;
use App\Services\Authorization\ScopedAuthorizationService;
use App\Services\CustomerService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(private readonly BranchContext $context, private readonly ScopedAuthorizationService $authorization) {}

    public function index(Request $request)
    {
        $branch = $this->branch($request, 'customers.view');
        $canViewCredits = $this->authorization->allows($request->user(), 'credits.view', $branch);
        $customersQuery = Customer::query()->whereBelongsTo($branch)
            ->when($request->filled('customer_type'), fn ($query) => $query->where('customer_type', $request->string('customer_type')->toString()))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->when($request->string('q')->trim()->isNotEmpty(), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(fn ($nested) => $nested->where('full_name', 'like', $term)->orWhere('document_number', 'like', $term)->orWhere('phone', 'like', $term));
            })->withCount('sales');
        if ($canViewCredits) {
            $customersQuery->withSum('credits as outstanding_balance', 'outstanding_balance');
        }
        $customers = $customersQuery->orderBy('full_name')->paginate(15)->withQueryString();

        return view('customers.index', compact('branch', 'customers', 'canViewCredits'));
    }

    public function create(Request $request)
    {
        $branch = $this->branch($request, 'customers.manage');

        return view('customers.form', ['branch' => $branch, 'customer' => new Customer]);
    }

    public function store(CustomerRequest $request, CustomerService $service)
    {
        $customer = $service->save($request->user(), $this->branch($request, 'customers.manage'), $request->validated());

        return redirect()->route('customers.show', $customer)->with('status', 'Cliente registrado correctamente.');
    }

    public function quickStore(CustomerRequest $request, CustomerService $service)
    {
        $customer = $service->save($request->user(), $this->branch($request, 'customers.manage'), $request->validated());

        return response()->json(['customer' => [
            'id' => $customer->id,
            'label' => $customer->full_name.($customer->document_number ? ' · '.$customer->document_number : ''),
        ]], 201);
    }

    public function show(Request $request, Customer $customer)
    {
        $branch = $this->branch($request, 'customers.view');
        abort_unless($customer->branch_id === $branch->id, 404);
        $canViewCredits = $this->authorization->allows($request->user(), 'credits.view', $branch);
        $customer->load(['sales' => fn ($query) => $query->with('salesperson')->latest('sold_at')]);
        if ($canViewCredits) {
            $customer->load('credits.installments');
        }
        $stats = [
            'purchases' => $customer->sales->count(),
            'sold' => (string) $customer->sales->sum('total'),
            'last_purchase' => $customer->sales->max('sold_at'),
            'outstanding' => $canViewCredits ? (string) $customer->credits->sum('outstanding_balance') : null,
        ];

        return view('customers.show', compact('branch', 'customer', 'stats', 'canViewCredits'));
    }

    public function edit(Request $request, Customer $customer)
    {
        $branch = $this->branch($request, 'customers.manage');
        abort_unless($customer->branch_id === $branch->id, 404);

        return view('customers.form', compact('branch', 'customer'));
    }

    public function update(CustomerRequest $request, Customer $customer, CustomerService $service)
    {
        $service->save($request->user(), $this->branch($request, 'customers.manage'), $request->validated(), $customer);

        return redirect()->route('customers.show', $customer)->with('status', 'Cliente actualizado correctamente.');
    }

    private function branch(Request $request, string $permission)
    {
        $branch = $this->context->current();
        abort_unless($branch && $this->authorization->allows($request->user(), $permission, $branch), 403);

        return $branch;
    }
}
