<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Log\Log;
use Illuminate\Support\Carbon;

class LogApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    private function apiGet(string $uri, array $params = [])
    {
        $url = $params ? $uri . '?' . http_build_query($params) : $uri;
        return $this->actingAs($this->user, 'api')->getJson($url);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs  (index)
    // -------------------------------------------------------------------------

    /** @test */
    public function unauthenticated_requests_are_rejected()
    {
        $this->getJson('/api/logs')->assertStatus(401);
    }

    /** @test */
    public function index_returns_all_logs_when_no_filter_given()
    {
        Log::factory()->count(5)->create();

        $this->apiGet('/api/logs')
             ->assertStatus(200)
             ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function index_returns_empty_array_when_no_logs_exist()
    {
        $this->apiGet('/api/logs')
             ->assertStatus(200)
             ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function index_returns_logs_ordered_newest_first()
    {
        $old = Log::factory()->create(['created_at' => Carbon::now()->subDays(3)]);
        $new = Log::factory()->create(['created_at' => Carbon::now()]);

        $response = $this->apiGet('/api/logs');
        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertEquals([$new->id, $old->id], $ids);
    }

    /** @test */
    public function index_response_contains_expected_fields()
    {
        Log::factory()->create([
            'message'  => 'Successfully deployed Mist site.',
            'username' => 'jsmith@kiewit.com',
        ]);

        $this->apiGet('/api/logs')
             ->assertStatus(200)
             ->assertJsonFragment([
                 'message'  => 'Successfully deployed Mist site.',
                 'username' => 'jsmith@kiewit.com',
             ]);
    }

    /** @test */
    public function index_response_includes_pagination_meta()
    {
        Log::factory()->count(3)->create();

        $this->apiGet('/api/logs')
             ->assertStatus(200)
             ->assertJsonStructure(['data', 'links', 'current_page', 'per_page', 'total']);
    }

    /** @test */
    public function index_respects_per_page_parameter()
    {
        Log::factory()->count(10)->create();

        $this->apiGet('/api/logs', ['per_page' => 3])
             ->assertStatus(200)
             ->assertJsonCount(3, 'data')
             ->assertJsonPath('per_page', 3)
             ->assertJsonPath('total', 10);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs?hours=N
    // -------------------------------------------------------------------------

    /** @test */
    public function index_filters_logs_by_hours()
    {
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subHours(2)]);
        $old    = Log::factory()->create(['created_at' => Carbon::now()->subHours(10)]);

        $this->apiGet('/api/logs', ['hours' => 6])
             ->assertStatus(200)
             ->assertJsonCount(1, 'data')
             ->assertJsonFragment(['id' => $recent->id])
             ->assertJsonMissing(['id' => $old->id]);
    }

    /** @test */
    public function index_hours_filter_returns_empty_when_no_recent_logs()
    {
        Log::factory()->create(['created_at' => Carbon::now()->subHours(48)]);

        $this->apiGet('/api/logs', ['hours' => 1])
             ->assertStatus(200)
             ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function index_hours_filter_includes_boundary_entry()
    {
        $boundary = Log::factory()->create(['created_at' => Carbon::now()->subHours(24)->addSecond()]);

        $this->apiGet('/api/logs', ['hours' => 24])
             ->assertStatus(200)
             ->assertJsonFragment(['id' => $boundary->id]);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs?days=N
    // -------------------------------------------------------------------------

    /** @test */
    public function index_filters_logs_by_days()
    {
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subDays(2)]);
        $old    = Log::factory()->create(['created_at' => Carbon::now()->subDays(10)]);

        $this->apiGet('/api/logs', ['days' => 7])
             ->assertStatus(200)
             ->assertJsonCount(1, 'data')
             ->assertJsonFragment(['id' => $recent->id])
             ->assertJsonMissing(['id' => $old->id]);
    }

    /** @test */
    public function index_days_filter_returns_empty_when_no_recent_logs()
    {
        Log::factory()->create(['created_at' => Carbon::now()->subDays(30)]);

        $this->apiGet('/api/logs', ['days' => 7])
             ->assertStatus(200)
             ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function index_hours_filter_takes_precedence_over_days_filter()
    {
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subHours(3)]);
        $mid    = Log::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        $this->apiGet('/api/logs', ['hours' => 6, 'days' => 7])
             ->assertStatus(200)
             ->assertJsonCount(1, 'data')
             ->assertJsonFragment(['id' => $recent->id])
             ->assertJsonMissing(['id' => $mid->id]);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs?username=X
    // -------------------------------------------------------------------------

    /** @test */
    public function index_filters_logs_by_username()
    {
        $match   = Log::factory()->create(['username' => 'jsmith@kiewit.com']);
        $nomatch = Log::factory()->create(['username' => 'bjones@kiewit.com']);

        $this->apiGet('/api/logs', ['username' => 'jsmith'])
             ->assertStatus(200)
             ->assertJsonCount(1, 'data')
             ->assertJsonFragment(['id' => $match->id])
             ->assertJsonMissing(['id' => $nomatch->id]);
    }

    /** @test */
    public function index_username_filter_is_case_insensitive_partial_match()
    {
        $match = Log::factory()->create(['username' => 'jsmith@kiewit.com']);

        $this->apiGet('/api/logs', ['username' => 'JSMITH'])
             ->assertStatus(200)
             ->assertJsonFragment(['id' => $match->id]);
    }

    /** @test */
    public function index_username_filter_returns_null_username_entries_when_not_filtered()
    {
        $system = Log::factory()->create(['username' => null]);
        $user   = Log::factory()->create(['username' => 'jsmith@kiewit.com']);

        // No filter — both should appear
        $this->apiGet('/api/logs')
             ->assertStatus(200)
             ->assertJsonCount(2, 'data');
    }

    // -------------------------------------------------------------------------
    // GET /api/logs/{id}  (show)
    // -------------------------------------------------------------------------

    /** @test */
    public function show_returns_a_single_log_entry()
    {
        $log = Log::factory()->create([
            'message'  => 'Successfully deployed Mist site.',
            'username' => 'jsmith@kiewit.com',
        ]);

        $this->apiGet("/api/logs/{$log->id}")
             ->assertStatus(200)
             ->assertJsonFragment([
                 'id'       => $log->id,
                 'message'  => 'Successfully deployed Mist site.',
                 'username' => 'jsmith@kiewit.com',
             ]);
    }

    /** @test */
    public function show_returns_404_for_nonexistent_log()
    {
        $this->apiGet('/api/logs/99999')->assertStatus(404);
    }

    /** @test */
    public function show_unauthenticated_request_is_rejected()
    {
        $log = Log::factory()->create();
        $this->getJson("/api/logs/{$log->id}")->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Log::log() dual-write helper
    // -------------------------------------------------------------------------

    /** @test */
    public function log_helper_persists_to_database()
    {
        Log::log('Dual write test message', 'jsmith@kiewit.com');

        $this->assertDatabaseHas('logs', [
            'message'  => 'Dual write test message',
            'username' => 'jsmith@kiewit.com',
        ]);
    }

    /** @test */
    public function log_helper_persists_with_null_username()
    {
        Log::log('System job message');

        $this->assertDatabaseHas('logs', [
            'message'  => 'System job message',
            'username' => null,
        ]);
    }

    /** @test */
    public function log_helper_writes_to_file_log()
    {
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(fn($msg, $ctx) => $msg === 'File log test' && $ctx['username'] === 'jsmith@kiewit.com');

        Log::log('File log test', 'jsmith@kiewit.com');
    }

    /** @test */
    public function log_helper_returns_a_log_model_instance()
    {
        $result = Log::log('Returns model test', 'jsmith@kiewit.com');

        $this->assertInstanceOf(Log::class, $result);
        $this->assertNotNull($result->id);
    }

    /** @test */
    public function log_helper_uses_specified_channel_for_file_log()
    {
        \Illuminate\Support\Facades\Log::shouldReceive('channel')
            ->once()
            ->with('provisioning')
            ->andReturnSelf();

        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(fn($msg, $ctx) => $msg === 'Channel test' && $ctx['username'] === 'jsmith@kiewit.com');

        Log::log('Channel test', 'jsmith@kiewit.com', 'provisioning');
    }

    /** @test */
    public function log_helper_with_channel_still_persists_to_database()
    {
        Log::log('Channel DB test', 'jsmith@kiewit.com', 'provisioning');

        $this->assertDatabaseHas('logs', [
            'message'  => 'Channel DB test',
            'username' => 'jsmith@kiewit.com',
        ]);
    }

    // -------------------------------------------------------------------------
    // Log::createEntry() helper
    // -------------------------------------------------------------------------

    /** @test */
    public function create_entry_helper_persists_a_log_record()
    {
        $log = Log::createEntry('Test message', 'jsmith@kiewit.com');

        $this->assertDatabaseHas('logs', [
            'id'       => $log->id,
            'message'  => 'Test message',
            'username' => 'jsmith@kiewit.com',
        ]);
    }

    /** @test */
    public function create_entry_helper_defaults_username_to_null()
    {
        $log = Log::createEntry('No username test');

        $this->assertNull($log->username);
    }

    /** @test */
    public function create_entry_helper_allows_null_username()
    {
        $log = Log::createEntry('No username', null);

        $this->assertNull($log->username);
        $this->assertDatabaseHas('logs', ['id' => $log->id, 'message' => 'No username']);
    }
}
