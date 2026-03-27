<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Log\Log;
use Illuminate\Support\Carbon;

class LogPruneCommandTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // logs:prune — basic pruning
    // -------------------------------------------------------------------------

    /** @test */
    public function prune_command_deletes_entries_older_than_specified_days()
    {
        $old    = Log::factory()->create(['created_at' => Carbon::now()->subDays(91)]);
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subDays(10)]);

        $this->artisan('logs:prune', ['--days' => 90])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('logs', ['id' => $old->id]);
        $this->assertDatabaseHas('logs', ['id' => $recent->id]);
    }

    /** @test */
    public function prune_command_keeps_entries_exactly_at_boundary()
    {
        // Exactly 90 days old — should NOT be pruned (boundary is exclusive)
        $boundary = Log::factory()->create(['created_at' => Carbon::now()->subDays(90)->addSecond()]);

        $this->artisan('logs:prune', ['--days' => 90])
             ->assertExitCode(0);

        $this->assertDatabaseHas('logs', ['id' => $boundary->id]);
    }

    /** @test */
    public function prune_command_deletes_multiple_old_entries()
    {
        Log::factory()->count(5)->create(['created_at' => Carbon::now()->subDays(100)]);
        Log::factory()->count(3)->create(['created_at' => Carbon::now()->subDays(5)]);

        $this->artisan('logs:prune', ['--days' => 90])
             ->assertExitCode(0);

        $this->assertDatabaseCount('logs', 3);
    }

    /** @test */
    public function prune_command_outputs_correct_count()
    {
        Log::factory()->count(4)->create(['created_at' => Carbon::now()->subDays(100)]);

        $this->artisan('logs:prune', ['--days' => 90])
             ->expectsOutput('Pruned 4 log entries older than 90 days.')
             ->assertExitCode(0);
    }

    /** @test */
    public function prune_command_outputs_singular_when_one_entry_deleted()
    {
        Log::factory()->create(['created_at' => Carbon::now()->subDays(100)]);

        $this->artisan('logs:prune', ['--days' => 90])
             ->expectsOutput('Pruned 1 log entry older than 90 days.')
             ->assertExitCode(0);
    }

    /** @test */
    public function prune_command_outputs_zero_when_nothing_to_prune()
    {
        Log::factory()->count(3)->create(['created_at' => Carbon::now()->subDays(5)]);

        $this->artisan('logs:prune', ['--days' => 90])
             ->expectsOutput('Pruned 0 log entries older than 90 days.')
             ->assertExitCode(0);
    }

    /** @test */
    public function prune_command_works_with_no_logs_in_database()
    {
        $this->artisan('logs:prune', ['--days' => 90])
             ->expectsOutput('Pruned 0 log entries older than 90 days.')
             ->assertExitCode(0);
    }

    // -------------------------------------------------------------------------
    // logs:prune — --days option
    // -------------------------------------------------------------------------

    /** @test */
    public function prune_command_respects_custom_days_option()
    {
        $old    = Log::factory()->create(['created_at' => Carbon::now()->subDays(10)]);
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        $this->artisan('logs:prune', ['--days' => 7])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('logs', ['id' => $old->id]);
        $this->assertDatabaseHas('logs', ['id' => $recent->id]);
    }

    /** @test */
    public function prune_command_fails_with_zero_days()
    {
        $this->artisan('logs:prune', ['--days' => 0])
             ->assertExitCode(1);
    }

    /** @test */
    public function prune_command_fails_with_negative_days()
    {
        $this->artisan('logs:prune', ['--days' => -5])
             ->assertExitCode(1);
    }

    // -------------------------------------------------------------------------
    // logs:prune — env variable
    // -------------------------------------------------------------------------

    /** @test */
    public function prune_command_uses_env_variable_when_no_days_option_given()
    {
        // Set env to 30 days retention via both putenv and $_ENV so Laravel picks it up
        putenv('LOG_RETENTION_DAYS=30');
        $_ENV['LOG_RETENTION_DAYS'] = '30';

        $old    = Log::factory()->create(['created_at' => Carbon::now()->subDays(31)]);
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subDays(5)]);

        $this->artisan('logs:prune')
             ->assertExitCode(0);

        $this->assertDatabaseMissing('logs', ['id' => $old->id]);
        $this->assertDatabaseHas('logs', ['id' => $recent->id]);

        // Restore default
        putenv('LOG_RETENTION_DAYS=90');
        $_ENV['LOG_RETENTION_DAYS'] = '90';
    }

    /** @test */
    public function prune_command_days_option_overrides_env_variable()
    {
        // Env says 90 days, but we pass --days=7
        putenv('LOG_RETENTION_DAYS=90');

        $old    = Log::factory()->create(['created_at' => Carbon::now()->subDays(10)]);
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        $this->artisan('logs:prune', ['--days' => 7])
             ->assertExitCode(0);

        $this->assertDatabaseMissing('logs', ['id' => $old->id]);
        $this->assertDatabaseHas('logs', ['id' => $recent->id]);
    }
}
