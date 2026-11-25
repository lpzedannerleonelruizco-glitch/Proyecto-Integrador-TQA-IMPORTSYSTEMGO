<?php

namespace Tests\Feature;

use App\Jobs\GenerateBackupArchive;
use App\Models\BackupArchive;
use App\Models\Branch;
use App\Models\User;
use App\Services\Authorization\BranchContext;
use App\Services\Backups\BackupAccessService;
use App\Services\Backups\BackupArchiveBuilder;
use App\Services\Backups\BackupIntegrityService;
use Database\Seeders\AuthorizationSeeder;
use Database\Seeders\DemoRetailSeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class BackupWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'demo.retail_enabled' => true,
            'demo.database' => $this->app->make('db')->connection()->getDatabaseName(),
        ]);
        $this->seed(AuthorizationSeeder::class);
        $this->seed(DemoRetailSeeder::class);
        $this->seed(OperationalDemoSeeder::class);
    }

    public function test_territorial_managers_can_only_download_archives_inside_their_area(): void
    {
        $lpManager = User::where('email', 'operaciones.lp@importsystemgo.example')->firstOrFail();
        $elAltoManager = User::where('email', 'operaciones.ea@importsystemgo.example')->firstOrFail();
        $lpBranchIds = Branch::whereIn('code', ['LP01', 'LP02', 'LP03', 'LP04'])->orderBy('id')->pluck('id')->all();
        $archive = new BackupArchive([
            'kind' => 'territorial',
            'status' => BackupArchive::STATUS_COMPLETED,
            'scope' => ['type' => 'territorial', 'branch_ids' => $lpBranchIds],
            'created_by' => $lpManager->id,
            'expires_at' => now()->addDay(),
        ]);
        $access = app(BackupAccessService::class);

        $this->assertTrue($access->canDownload($lpManager, $archive));
        $this->assertFalse($access->canDownload($elAltoManager, $archive));
        $this->assertFalse($access->canCreateFull($lpManager));
    }

    public function test_administrator_can_generate_and_verify_a_mysql_full_backup_zip(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('La generación SQL de backup requiere MySQL.');
        }

        $branch = Branch::where('code', 'LP01')->firstOrFail();
        $admin = User::where('email', 'admin.demo@importsystemgo.example')->firstOrFail();
        app(BranchContext::class)->activate($admin, $branch);

        $this->actingAs($admin)
            ->post(route('backups.full'))
            ->assertRedirect(route('backups.index'))
            ->assertSessionHas('status');
        $archive = BackupArchive::query()->where('created_by', $admin->id)->latest('id')->firstOrFail();
        (new GenerateBackupArchive($archive->id))->handle(
            app(BackupArchiveBuilder::class),
            app(BackupAccessService::class),
        );

        $archive->refresh();
        $this->assertSame(BackupArchive::STATUS_COMPLETED, $archive->status);
        $this->assertTrue(app(BackupIntegrityService::class)->verify($archive));

        $zipPath = storage_path('app/private/'.$archive->storage_path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath, ZipArchive::CHECKCONS) === true);
        $sql = $zip->getFromName('database.sql');
        $zip->close();
        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE TABLE `inventory_balances`', $sql);
        $this->assertStringContainsString('products_after_insert_audit', $sql);

        if (getenv('IMPORTSYSTEMGO_KEEP_TEST_BACKUP') !== '1') {
            @unlink($zipPath);
        }
    }
}
