<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\AuthorizeMenuPermission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QcScanWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_qc_scan_workbench_page(): void
    {
        $this->withoutMiddleware(AuthorizeMenuPermission::class);

        $role = Role::firstOrCreate(
            ['slug' => 'admin'],
            [
                'name' => 'Administrator',
                'description' => 'Full access to system',
            ]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role);

        $this->actingAs($user)
            ->get(route('admin.outbound.qc-scan.index'))
            ->assertOk()
            ->assertSee('Workbench QC untuk Scanner Desktop')
            ->assertSee('Scan Resi')
            ->assertSee('Scan SKU');
    }
}
