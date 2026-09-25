<?php

namespace Tests\Feature;

use App\Models\ExamReadinessPrediction;
use App\Models\User;
use App\Services\Ml\FeatureExtractionService;
use App\Services\Ml\ReadinessPredictionService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Regression tests for the "sometimes it errors" class of bugs: a briefly unreachable ML service, a malformed ML response. */
class ResilienceTest extends TestCase
{
    private ?User $user = null;

    protected function tearDown(): void
    {
        if ($this->user) {
            $tables = collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]);
            for ($pass = 0; $pass < 3; $pass++) {
                foreach ($tables as $table) {
                    if ($table !== 'users' && Schema::hasColumn($table, 'user_id')) {
                        try {
                            DB::table($table)->where('user_id', $this->user->id)->delete();
                        } catch (\Throwable) {
                            // a dependent table is cleared on a later pass
                        }
                    }
                }
            }
            $this->user->delete();
        }

        parent::tearDown();
    }

    private function makeUser(): User
    {
        return $this->user = User::create([
            'name' => 'Resilience Test User',
            'email' => 'resilience-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'auth_provider' => 'password',
            'role' => 'user',
            'locale' => 'en',
        ])->fresh();
    }

    private function bindMlResponses(array $queue): void
    {
        $client = new Client(['handler' => HandlerStack::create(new MockHandler($queue))]);
        $this->app->bind(ReadinessPredictionService::class, fn ($app) => new ReadinessPredictionService($app->make(FeatureExtractionService::class), $client));
    }

    private function goodMlBody(): string
    {
        return json_encode([
            'readiness_percent' => 71.0,
            'readiness_label' => 'almost_ready',
            'reasons' => [],
            'model_version' => 'resilience-test',
        ]);
    }

    private function connectFailure(): ConnectException
    {
        return new ConnectException('Connection refused', new Request('POST', 'http://127.0.0.1:8100/predict'));
    }

    public function test_a_briefly_unreachable_ml_service_is_retried_once_and_then_succeeds()
    {
        $user = $this->makeUser();
        $this->bindMlResponses([$this->connectFailure(), new Response(200, [], $this->goodMlBody())]);

        $this->actingAs($user, 'web')->postJson('/api/readiness/predict')
            ->assertStatus(200)
            ->assertJsonPath('data.model_version', 'resilience-test');

        $this->assertSame(1, ExamReadinessPrediction::where('user_id', $user->id)->count());
    }

    public function test_an_ml_service_that_stays_down_gives_a_clean_503_and_stores_nothing()
    {
        $user = $this->makeUser();
        $this->bindMlResponses([$this->connectFailure(), $this->connectFailure()]);

        $this->actingAs($user, 'web')->postJson('/api/readiness/predict')->assertStatus(503);

        $this->assertSame(0, ExamReadinessPrediction::where('user_id', $user->id)->count());
    }

    public function test_a_malformed_ml_response_is_a_clean_503_not_a_server_error()
    {
        $user = $this->makeUser();
        $this->bindMlResponses([new Response(200, [], json_encode(['unexpected' => true]))]);

        $this->actingAs($user, 'web')->postJson('/api/readiness/predict')->assertStatus(503);

        $this->assertSame(0, ExamReadinessPrediction::where('user_id', $user->id)->count());
    }

    public function test_a_client_error_from_the_ml_service_is_not_retried()
    {
        $user = $this->makeUser();
        // Only ONE response is queued: a retry would exhaust the mock and throw a different exception.
        $this->bindMlResponses([new Response(422, [], '{"detail":"bad input"}')]);

        $this->actingAs($user, 'web')->postJson('/api/readiness/predict')->assertStatus(503);
    }

    public function test_matching_your_best_game_score_is_not_a_new_best()
    {
        $user = $this->makeUser();
        $submit = fn (int $score) => $this->actingAs($user, 'web')
            ->postJson('/api/games/math_rush/score', ['score' => $score, 'duration_seconds' => 60])
            ->assertStatus(201)
            ->json('data');

        $first = $submit(300);
        $tie = $submit(300);
        $worse = $submit(200);
        $better = $submit(450);

        $this->assertTrue($first['is_new_best'], 'the very first score is a best');
        $this->assertFalse($tie['is_new_best'], 'tying the best is not a new best');
        $this->assertFalse($worse['is_new_best']);
        $this->assertTrue($better['is_new_best']);
        $this->assertSame(450, (int) $better['best_score']);
    }
}
