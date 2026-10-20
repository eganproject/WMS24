<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class BackupDatabaseCommandTest extends TestCase
{
    public function test_it_refuses_non_mysql_connections_without_writing_files(): void
    {
        $path = sys_get_temp_dir().'/db-backup-test-'.uniqid();
        config(['backup.path' => $path]);

        $this->artisan('db:backup')
            ->expectsOutputToContain('only supports MySQL/MariaDB')
            ->assertFailed();

        $this->assertDirectoryDoesNotExist($path);
    }

    public function test_it_is_scheduled_nightly(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'db:backup'));

        $this->assertCount(1, $events);
        $this->assertSame('0 1 * * *', $events->first()->expression);
    }
}
