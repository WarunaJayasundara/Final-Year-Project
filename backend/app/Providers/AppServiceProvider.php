<?php

namespace App\Providers;

use App\Contracts\AiCoachServiceInterface;
use App\Contracts\AiFeedbackServiceInterface;
use App\Contracts\AiQuestionGeneratorServiceInterface;
use App\Services\AiCoach\GeminiAiCoachService;
use App\Services\AiCoach\MockAiCoachService;
use App\Services\AiFeedback\GeminiAiFeedbackService;
use App\Services\AiFeedback\MockAiFeedbackService;
use App\Services\AiQuestionGeneration\GeminiAiQuestionGeneratorService;
use App\Services\AiQuestionGeneration\MockAiQuestionGeneratorService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(AiFeedbackServiceInterface::class, function () {
            return match (config('services.ai_feedback_driver')) {
                'gemini' => $this->app->make(GeminiAiFeedbackService::class),
                default => $this->app->make(MockAiFeedbackService::class),
            };
        });

        $this->app->bind(AiCoachServiceInterface::class, function () {
            return match (config('services.ai_coach_driver')) {
                'gemini' => $this->app->make(GeminiAiCoachService::class),
                default => $this->app->make(MockAiCoachService::class),
            };
        });

        $this->app->bind(AiQuestionGeneratorServiceInterface::class, function () {
            return match (config('services.ai_question_generator_driver')) {
                'gemini' => $this->app->make(GeminiAiQuestionGeneratorService::class),
                default => $this->app->make(MockAiQuestionGeneratorService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }
}
