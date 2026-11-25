<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Services\Authorization\ScopedAuthorizationService;
use App\Services\OrganizationService;
use App\Support\OrganizationResources;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request, OrganizationService $service, string $resource)
    {
        $definition = OrganizationResources::definition($resource);
        $records = $service->query($request->user(), $resource)->orderBy('id')->paginate(20);
        $canManage = app(ScopedAuthorizationService::class)->allows($request->user(), "organization.{$resource}.manage");

        return view('organization.index', compact('resource', 'definition', 'records', 'canManage'));
    }

    public function create(Request $request, OrganizationService $service, string $resource)
    {
        $service->query($request->user(), $resource, 'manage');
        $definition = OrganizationResources::definition($resource);
        $record = new $definition['model'];

        return view('organization.form', compact('resource', 'definition', 'record'));
    }

    public function edit(Request $request, OrganizationService $service, string $resource, int $record)
    {
        $definition = OrganizationResources::definition($resource);
        $record = $service->query($request->user(), $resource, 'manage')->findOrFail($record);

        return view('organization.form', compact('resource', 'definition', 'record'));
    }

    public function store(OrganizationRequest $request, OrganizationService $service, string $resource)
    {
        $service->save($request->user(), $resource, $request->validated(), null);

        return redirect()->route('organization.index', $resource)->with('status', 'Registro creado y auditado.');
    }

    public function update(OrganizationRequest $request, OrganizationService $service, string $resource, int $record)
    {
        $service->save($request->user(), $resource, $request->validated(), $record);

        return redirect()->route('organization.index', $resource)->with('status', 'Cambios guardados y auditados.');
    }
}
