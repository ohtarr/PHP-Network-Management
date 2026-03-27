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

    /**
     * Authenticated user for all tests.
     */
    protected User $user;

    /**
     * Set up an authenticated user before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /**
     * Make an authenticated GET request to the API as the test user.
     */
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
        $response = $this->getJson('/api/logs');
        $response->assertStatus(401);
    }

    /** @test */
    public function index_returns_all_logs_when_no_filter_given()
    {
        Log::factory()->count(5)->create();

        $response = $this->apiGet('/api/logs');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data');
    }

    /** @test */
    public function index_returns_empty_array_when_no_logs_exist()
    {
        $response = $this->apiGet('/api/logs');

        $response->assertStatus(200)
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
            'controller' => 'ManagementController',
            'method'     => 'getSiteSummary',
            'message'    => 'Successfully retrieved NETBOX SITE.',
            'status'     => true,
        ]);

        $response = $this->apiGet('/api/logs');

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'controller' => 'ManagementController',
                     'method'     => 'getSiteSummary',
                     'message'    => 'Successfully retrieved NETBOX SITE.',
                     'status'     => true,
                 ]);
    }

    /** @test */
    public function index_response_includes_pagination_meta()
    {
        Log::factory()->count(3)->create();

        $response = $this->apiGet('/api/logs');

        $response->assertStatus(200)
                 ->assertJsonStructure(['data', 'links', 'current_page', 'per_page', 'total']);
    }

    /** @test */
    public function index_respects_per_page_parameter()
    {
        Log::factory()->count(10)->create();

        $response = $this->apiGet('/api/logs', ['per_page' => 3]);

        $response->assertStatus(200)
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

        $response = $this->apiGet('/api/logs', ['hours' => 6]);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $recent->id])
                 ->assertJsonMissing(['id' => $old->id]);
    }

    /** @test */
    public function index_hours_filter_returns_empty_when_no_recent_logs()
    {
        Log::factory()->create(['created_at' => Carbon::now()->subHours(48)]);

        $response = $this->apiGet('/api/logs', ['hours' => 1]);

        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function index_hours_filter_includes_boundary_entry()
    {
        $boundary = Log::factory()->create(['created_at' => Carbon::now()->subHours(24)->addSecond()]);

        $response = $this->apiGet('/api/logs', ['hours' => 24]);

        $response->assertStatus(200)
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

        $response = $this->apiGet('/api/logs', ['days' => 7]);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $recent->id])
                 ->assertJsonMissing(['id' => $old->id]);
    }

    /** @test */
    public function index_days_filter_returns_empty_when_no_recent_logs()
    {
        Log::factory()->create(['created_at' => Carbon::now()->subDays(30)]);

        $response = $this->apiGet('/api/logs', ['days' => 7]);

        $response->assertStatus(200)
                 ->assertJsonCount(0, 'data');
    }

    /** @test */
    public function index_hours_filter_takes_precedence_over_days_filter()
    {
        $recent = Log::factory()->create(['created_at' => Carbon::now()->subHours(3)]);
        $mid    = Log::factory()->create(['created_at' => Carbon::now()->subDays(2)]);

        $response = $this->apiGet('/api/logs', ['hours' => 6, 'days' => 7]);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $recent->id])
                 ->assertJsonMissing(['id' => $mid->id]);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs?controller=X
    // -------------------------------------------------------------------------

    /** @test */
    public function index_filters_logs_by_controller()
    {
        $match   = Log::factory()->create(['controller' => 'ProvisioningController']);
        $nomatch = Log::factory()->create(['controller' => 'ManagementController']);

        $response = $this->apiGet('/api/logs', ['controller' => 'Provisioning']);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $match->id])
                 ->assertJsonMissing(['id' => $nomatch->id]);
    }

    /** @test */
    public function index_controller_filter_is_case_insensitive_partial_match()
    {
        $match = Log::factory()->create(['controller' => 'ProvisioningController']);

        $response = $this->apiGet('/api/logs', ['controller' => 'provisioning']);

        $response->assertStatus(200)
                 ->assertJsonFragment(['id' => $match->id]);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs?status=0|1
    // -------------------------------------------------------------------------

    /** @test */
    public function index_filters_logs_by_status_success()
    {
        $success = Log::factory()->create(['status' => true]);
        $failure = Log::factory()->create(['status' => false]);

        $response = $this->apiGet('/api/logs', ['status' => 1]);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $success->id])
                 ->assertJsonMissing(['id' => $failure->id]);
    }

    /** @test */
    public function index_filters_logs_by_status_failure()
    {
        $success = Log::factory()->create(['status' => true]);
        $failure = Log::factory()->create(['status' => false]);

        $response = $this->apiGet('/api/logs', ['status' => 0]);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $failure->id])
                 ->assertJsonMissing(['id' => $success->id]);
    }

    /** @test */
    public function index_can_combine_controller_and_status_filters()
    {
        $match   = Log::factory()->create(['controller' => 'SyncDeviceDnsJob', 'status' => false]);
        $wrong1  = Log::factory()->create(['controller' => 'SyncDeviceDnsJob', 'status' => true]);
        $wrong2  = Log::factory()->create(['controller' => 'ManagementController', 'status' => false]);

        $response = $this->apiGet('/api/logs', ['controller' => 'SyncDeviceDnsJob', 'status' => 0]);

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonFragment(['id' => $match->id])
                 ->assertJsonMissing(['id' => $wrong1->id])
                 ->assertJsonMissing(['id' => $wrong2->id]);
    }

    // -------------------------------------------------------------------------
    // GET /api/logs/{id}  (show)
    // -------------------------------------------------------------------------

    /** @test */
    public function show_returns_a_single_log_entry()
    {
        $log = Log::factory()->create([
            'controller' => 'ProvisioningController',
            'method'     => 'deployMistSite',
            'message'    => 'Successfully deployed Mist site.',
            'status'     => true,
        ]);

        $response = $this->apiGet("/api/logs/{$log->id}");

        $response->assertStatus(200)
                 ->assertJsonFragment([
                     'id'         => $log->id,
                     'controller' => 'ProvisioningController',
                     'method'     => 'deployMistSite',
                     'message'    => 'Successfully deployed Mist site.',
                     'status'     => true,
                 ]);
    }

    /** @test */
    public function show_returns_404_for_nonexistent_log()
    {
        $response = $this->apiGet('/api/logs/99999');

        $response->assertStatus(404);
    }

    /** @test */
    public function show_unauthenticated_request_is_rejected()
    {
        $log = Log::factory()->create();

        $response = $this->getJson("/api/logs/{$log->id}");

        $response->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // Log::log() dual-write helper
    // -------------------------------------------------------------------------

    /** @test */
    public function log_helper_persists_to_database()
    {
        Log::log('Dual write test message', true, 'TestController', 'testMethod');

        $this->assertDatabaseHas('logs', [
            'controller' => 'TestController',
            'method'     => 'testMethod',
            'message'    => 'Dual write test message',
            'status'     => true,
        ]);
    }

    /** @test */
    public function log_helper_writes_to_file_log_on_success()
    {
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(fn($msg, $ctx) => $msg === 'Success message' && $ctx['status'] === true);

        Log::log('Success message', true, 'TestController', 'testMethod');
    }

    /** @test */
    public function log_helper_writes_error_to_file_log_on_failure()
    {
        \Illuminate\Support\Facades\Log::shouldReceive('error')
            ->once()
            ->withArgs(fn($msg, $ctx) => $msg === 'Failure message' && $ctx['status'] === false);

        Log::log('Failure message', false, 'TestController', 'testMethod');
    }

    /** @test */
    public function log_helper_returns_a_log_model_instance()
    {
        $result = Log::log('Returns model test', true, 'TestController', 'testMethod');

        $this->assertInstanceOf(Log::class, $result);
        $this->assertNotNull($result->id);
    }

    /** @test */
    public function log_helper_passes_extra_context_to_file_log()
    {
        \Illuminate\Support\Facades\Log::shouldReceive('info')
            ->once()
            ->withArgs(fn($msg, $ctx) => isset($ctx['device_id']) && $ctx['device_id'] === 42);

        Log::log('Context test', true, 'TestController', 'testMethod', ['device_id' => 42]);
    }

    // -------------------------------------------------------------------------
    // Log::createEntry() helper
    // -------------------------------------------------------------------------

    /** @test */
    public function create_entry_helper_persists_a_log_record()
    {
        $log = Log::createEntry(
            'Test message',
            true,
            'TestController',
            'testMethod'
        );

        $this->assertDatabaseHas('logs', [
            'id'         => $log->id,
            'controller' => 'TestController',
            'method'     => 'testMethod',
            'message'    => 'Test message',
            'status'     => true,
        ]);
    }

    /** @test */
    public function create_entry_helper_defaults_status_to_true()
    {
        $log = Log::createEntry('Default status test');

        $this->assertTrue($log->status);
    }

    /** @test */
    public function create_entry_helper_allows_null_controller_and_method()
    {
        $log = Log::createEntry('No controller or method');

        $this->assertNull($log->controller);
        $this->assertNull($log->method);
        $this->assertDatabaseHas('logs', ['id' => $log->id, 'message' => 'No controller or method']);
    }

    /** @test */
    public function create_entry_helper_casts_status_as_boolean()
    {
        $success = Log::createEntry('Success', true);
        $failure = Log::createEntry('Failure', false);

        $this->assertIsBool($success->status);
        $this->assertIsBool($failure->status);
        $this->assertTrue($success->status);
        $this->assertFalse($failure->status);
    }
}
