<?php

namespace Tests\Feature\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_activity_uses_human_readable_description_and_payload_summary(): void
    {
        $user = User::factory()->create([
            'name' => 'Nama Lama',
            'email' => 'lama@example.com',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Nama Baru',
                'email' => 'baru@example.com',
            ]);

        $response->assertRedirect(route('profile.edit', absolute: false));

        $log = ActivityLog::query()->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Berhasil mengubah Profil Pengguna', $log->action);
        $this->assertSame('profile.update', $log->route_name);
        $this->assertSame('PATCH', $log->method);
        $this->assertSame('Berhasil', $log->payload['ringkasan']['hasil']);
        $this->assertSame('Nama Baru', $log->payload['ringkasan']['data_utama']['Nama']);
        $this->assertSame('baru@example.com', $log->payload['ringkasan']['data_utama']['Email']);
        $this->assertSame('Nama Baru', $log->payload['data_dikirim']['name']);
        $this->assertArrayNotHasKey('password', $log->payload['data_dikirim']);
    }
}
