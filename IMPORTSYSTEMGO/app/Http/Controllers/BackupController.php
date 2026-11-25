<?php

namespace App\Http\Controllers;

use App\Models\BackupArchive;
use App\Models\DatabaseTriggerAudit;
use App\Services\Authorization\OperationalAreaAccessService;
use App\Services\Backups\BackupAccessService;
use App\Services\Backups\BackupIntegrityService;
use App\Services\Backups\BackupRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function __construct(private readonly BackupAccessService $access) {}

    public function index(Request $request)
    {
        abort_unless($this->access->canView($request->user()), 403);
        $archives = Schema::hasTable('backup_archives')
            ? $this->access->visibleArchives($request->user())->paginate(15)
            : null;
        $triggerAudits = collect();
        if (Schema::hasTable('database_trigger_audits')) {
            $query = DatabaseTriggerAudit::query()->with('branch')->latest('occurred_at');
            if (app(OperationalAreaAccessService::class)->isTerritoriallyBound($request->user())) {
                $branchIds = $this->access->allowedBranchIds($request->user());
                $query->whereIn('branch_id', $branchIds);
            }
            $triggerAudits = $query->limit(30)->get();
        }

        return view('backups.index', [
            'archives' => $archives,
            'triggerAudits' => $triggerAudits,
            'canCreateFull' => $this->access->canCreateFull($request->user()),
            'canCreateTerritorial' => $this->access->canCreateTerritorial($request->user()),
            'schemaReady' => Schema::hasTable('backup_archives') && Schema::hasTable('database_trigger_audits'),
        ]);
    }

    public function createFull(Request $request, BackupRequestService $backups)
    {
        $archive = $backups->requestFull($request->user());

        return redirect()->route('backups.index')->with('status', "Respaldo completo en cola ({$archive->public_id}).");
    }

    public function createTerritorial(Request $request, BackupRequestService $backups)
    {
        $archive = $backups->requestTerritorial($request->user());

        return redirect()->route('backups.index')->with('status', "Exportación territorial en cola ({$archive->public_id}).");
    }

    public function verify(Request $request, BackupArchive $archive, BackupIntegrityService $integrity): RedirectResponse
    {
        abort_unless($this->access->canVerify($request->user()) && $this->access->canDownload($request->user(), $archive), 403);
        $verified = $integrity->verify($archive);
        $archive->forceFill(['last_verified_at' => now(), 'last_verification_ok' => $verified])->save();
        $this->access->audit($request->user(), $verified ? 'backup.verified' : 'backup.verification_failed', $archive, [
            'verified' => $verified,
            'sha256' => $archive->sha256,
        ]);

        return back()->with($verified ? 'status' : 'error', $verified ? 'Integridad ZIP y SHA-256 verificados.' : 'La verificación falló; el archivo no debe utilizarse.');
    }

    public function download(Request $request, BackupArchive $archive, BackupIntegrityService $integrity): BinaryFileResponse
    {
        abort_unless($this->access->canDownload($request->user(), $archive), 403);
        if (! $integrity->verify($archive)) {
            $archive->forceFill(['last_verified_at' => now(), 'last_verification_ok' => false])->save();
            $this->access->audit($request->user(), 'backup.verification_failed', $archive, ['verified' => false]);
            abort(409, 'La integridad del respaldo no pudo verificarse.');
        }
        $archive->forceFill(['last_verified_at' => now(), 'last_verification_ok' => true])->save();
        $this->access->audit($request->user(), 'backup.downloaded', $archive, ['sha256' => $archive->sha256]);
        $path = storage_path('app/private/'.$archive->storage_path);

        return response()->download($path, $archive->public_id.'.zip', ['Cache-Control' => 'private, no-store']);
    }
}
