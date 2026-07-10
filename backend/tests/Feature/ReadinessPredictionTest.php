<?php

namespace Tests\Feature;

use App\Models\ExamReadinessPrediction;
use App\Models\User;
use App\Models\UserDailyCheckin;
use App\Services\Ml\FeatureExtractionService;
use App\Services\Ml\ReadinessPredictionService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The exam-readiness ML service is a separate FastAPI process (ml-service/),
 * not something the test suite should require running - so these tests bind
 * ReadinessPredictionService with a Guzzle mock handler returning a canned
 * /predict response, exercising every other layer (feature extraction,
 * controller, persistence, response shape) against the real dev database
 * (no RefreshDatabase in this project - see AdaptivePlacementTest), with
 * explicit tearDown cleanup.
 */
class ReadinessPredictionTest extends TestCase
{
    private ?User $testUser = null;

    protected function tearDown(): void
    {
        if ($this->testUser) {
            ExamReadinessPrediction::where('user_id', $this->testUser->id)->delete();
            UserDailyCheckin::where('user_id', $this->testUser->id)->delete();
            $this->testUser->delete();
        }

        parent::tearDown();
    }

    private function bindMockedPredictionService(array $responseBody): void
    {
        $mock = new MockHandler([new Response(200, [], json_encode($responseBody))]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);

        $this->app->bind(ReadinessPredictionService::class, function ($app) use ($client) {
            return new ReadinessPredictionService($app->make(FeatureExtractionService::class), $client);
        });
    }

    public function test_predict_endpoint_stores_and_returns_prediction()
    {
        $this->testUser = User::create([
            'name' => 'Readiness Test User',
            'email' => 'readiness-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $this->bindMockedPredictionService([
            'readiness_percent' => 82.5,
            'readiness_label' => 'ready',
            'reasons' => [
                ['feature' => 'avg_test_score', 'message' => 'High average test scores', 'direction' => 'positive', 'impact' => 0.5],
            ],
            'model_version' => 'test-version-1',
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/readiness/predict');

        $response->assertStatus(200);
        $response->assertJsonPath('data.readiness_percent', 82.5);
        $response->assertJsonPath('data.readiness_label', 'ready');
        $response->assertJsonPath('data.model_version', 'test-version-1');

        $this->assertEquals(1, ExamReadinessPrediction::where('user_id', $this->testUser->id)->count());

        $stored = ExamReadinessPrediction::where('user_id', $this->testUser->id)->first();
        $this->assertNotNull($stored);
        $this->assertEquals('ready', $stored->readiness_label);
        $this->assertIsArray($stored->features);
        $this->assertArrayHasKey('theta', $stored->features);
    }

    public function test_latest_endpoint_returns_null_when_no_prediction_exists()
    {
        $this->testUser = User::create([
            'name' => 'Readiness Test User No Prediction',
            'email' => 'readiness-test-none-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($this->testUser, 'web')->getJson('/api/readiness/latest');

        $response->assertStatus(200);
        $response->assertJsonPath('data', null);
    }

    public function test_checkin_can_be_created_and_updated_for_today()
    {
        $this->testUser = User::create([
            'name' => 'Checkin Test User',
            'email' => 'checkin-test-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $first = $this->actingAs($this->testUser, 'web')->postJson('/api/checkins', [
            'study_hours' => 2.5,
            'motivation_score' => 7,
            'attended' => true,
        ]);
        $first->assertStatus(200);
        $first->assertJsonPath('data.study_hours', 2.5);

        // Submitting again the same day should update, not duplicate, the row.
        $second = $this->actingAs($this->testUser, 'web')->postJson('/api/checkins', [
            'study_hours' => 3.5,
            'motivation_score' => 8,
            'attended' => true,
        ]);
        $second->assertStatus(200);

        $this->assertEquals(1, UserDailyCheckin::where('user_id', $this->testUser->id)->count());
        $this->assertEquals(3.5, (float) UserDailyCheckin::where('user_id', $this->testUser->id)->first()->study_hours);
    }
}
