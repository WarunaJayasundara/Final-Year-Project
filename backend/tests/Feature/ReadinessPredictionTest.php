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

    public function test_research_grade_fields_are_persisted_and_returned()
    {
        $this->testUser = User::create([
            'name' => 'Readiness Research Fields Test User',
            'email' => 'readiness-research-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $this->bindMockedPredictionService([
            'readiness_percent' => 61.0,
            'readiness_label' => 'needs_improvement',
            'reasons' => [],
            'model_version' => 'test-version-2',
            'plain_english_explanation' => 'Your readiness estimate reflects that your weekly practice volume was a weak point.',
            'risk_of_dropping_practice' => ['probability' => 0.72, 'at_risk' => true],
            'predicted_next_assessment_score' => 58.3,
            'predicted_score_change' => -4.2,
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/readiness/predict');

        $response->assertStatus(200);
        $response->assertJsonPath('data.plain_english_explanation', 'Your readiness estimate reflects that your weekly practice volume was a weak point.');
        $response->assertJsonPath('data.risk_of_dropping_practice.probability', 0.72);
        $response->assertJsonPath('data.risk_of_dropping_practice.at_risk', true);
        $response->assertJsonPath('data.predicted_next_assessment_score', 58.3);
        $response->assertJsonPath('data.predicted_score_change', -4.2);

        $stored = ExamReadinessPrediction::where('user_id', $this->testUser->id)->first();
        $this->assertEquals(0.72, (float) $stored->risk_of_dropping_practice_probability);
        $this->assertTrue((bool) $stored->at_risk_of_dropping_practice);
        $this->assertEquals(58.3, (float) $stored->predicted_next_assessment_score);
        $this->assertEquals(-4.2, (float) $stored->predicted_score_change);
    }

    public function test_research_grade_fields_are_null_when_service_omits_them()
    {
        // A deployed model before train_multioutput.py has ever been run
        // returns a response with no multi-output keys at all - the whole
        // pipeline (service -> model -> controller) must degrade to null
        // fields rather than erroring.
        $this->testUser = User::create([
            'name' => 'Readiness Backward Compat Test User',
            'email' => 'readiness-backcompat-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $this->bindMockedPredictionService([
            'readiness_percent' => 70.0,
            'readiness_label' => 'almost_ready',
            'reasons' => [],
            'model_version' => 'test-version-legacy',
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/readiness/predict');

        $response->assertStatus(200);
        $response->assertJsonPath('data.risk_of_dropping_practice', null);
        $response->assertJsonPath('data.predicted_next_assessment_score', null);
        $response->assertJsonPath('data.predicted_score_change', null);
    }

    public function test_second_prediction_sends_previous_features_for_trend_explanation()
    {
        $this->testUser = User::create([
            'name' => 'Readiness Trend Test User',
            'email' => 'readiness-trend-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        // First prediction: no history yet, so the request must NOT include
        // a previous_features key at all (see ReadinessPredictionService).
        $capturedRequests = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'readiness_percent' => 75.0, 'readiness_label' => 'almost_ready', 'reasons' => [], 'model_version' => 'v1',
            ])),
            new Response(200, [], json_encode([
                'readiness_percent' => 60.0, 'readiness_label' => 'needs_improvement', 'reasons' => [], 'model_version' => 'v1',
            ])),
        ]);
        $handlerStack = HandlerStack::create($mock);
        $handlerStack->push(\GuzzleHttp\Middleware::history($capturedRequests));
        $client = new Client(['handler' => $handlerStack]);
        $this->app->bind(ReadinessPredictionService::class, function ($app) use ($client) {
            return new ReadinessPredictionService($app->make(FeatureExtractionService::class), $client);
        });

        $this->actingAs($this->testUser, 'web')->postJson('/api/readiness/predict')->assertStatus(200);
        $this->actingAs($this->testUser, 'web')->postJson('/api/readiness/predict')->assertStatus(200);

        $this->assertCount(2, $capturedRequests);
        $firstBody = json_decode((string) $capturedRequests[0]['request']->getBody(), true);
        $secondBody = json_decode((string) $capturedRequests[1]['request']->getBody(), true);

        $this->assertArrayNotHasKey('previous_features', $firstBody);
        $this->assertArrayHasKey('previous_features', $secondBody);
        $this->assertArrayHasKey('theta', $secondBody['previous_features']);
    }

    public function test_time_aware_fields_are_persisted_and_returned()
    {
        $this->testUser = User::create([
            'name' => 'Readiness Time Aware Test User',
            'email' => 'readiness-time-aware-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ]);

        $this->bindMockedPredictionService([
            'readiness_percent' => 65.0,
            'readiness_label' => 'needs_improvement',
            'reasons' => [],
            'model_version' => 'test-version-time-aware',
            'time_management_readiness_percent' => 42.5,
            'predicted_score_range' => ['low' => 50.5, 'high' => 80.5],
        ]);

        $response = $this->actingAs($this->testUser, 'web')->postJson('/api/readiness/predict');

        $response->assertStatus(200);
        $response->assertJsonPath('data.time_management_readiness_percent', 42.5);
        $response->assertJsonPath('data.predicted_score_range.low', 50.5);
        $response->assertJsonPath('data.predicted_score_range.high', 80.5);

        $stored = ExamReadinessPrediction::where('user_id', $this->testUser->id)->first();
        $this->assertEquals(42.5, (float) $stored->time_management_readiness_percent);
        $this->assertEquals(['low' => 50.5, 'high' => 80.5], $stored->predicted_score_range);
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
