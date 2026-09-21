<?php

namespace Tests\Unit;

use App\Services\AiCoach\MockAiCoachService;
use App\Services\Analytics\IqScoreService;
use App\Services\Analytics\StreakService;
use App\Services\Analytics\StudentContextService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MockAiCoachKeywordMatchingTest extends TestCase
{
    private function coachMatches(string $message, array $keywords): bool
    {
        $coach = new MockAiCoachService(new StudentContextService(new IqScoreService(), new StreakService()));
        $method = new ReflectionMethod($coach, 'matchesAny');
        $method->setAccessible(true);

        return $method->invoke($coach, mb_strtolower($message), $keywords);
    }

    public function test_short_greeting_keyword_does_not_fire_inside_other_words()
    {
        $this->assertFalse($this->coachMatches('which game should I play', ['hi', 'hello', 'hey']));
        $this->assertFalse($this->coachMatches('what is this about', ['hi', 'hello', 'hey']));
    }

    public function test_keyword_still_matches_as_a_word_and_with_suffixes()
    {
        $this->assertTrue($this->coachMatches('hi there', ['hi']));
        $this->assertTrue($this->coachMatches('I am studying a lot', ['study']));
        $this->assertTrue($this->coachMatches('any games to play?', ['game']));
    }
}
