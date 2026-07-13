<?php

namespace App\Console\Commands;

use App\Models\ExamProfile;
use App\Models\Feedback;
use App\Models\GameScore;
use App\Models\SessionAnswer;
use App\Models\TestSession;
use App\Models\User;
use App\Models\UserDailyCheckin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes every is_demo_user / is_demo_feedback row created by
 * `demo:generate` - a standalone cleanup command for when you want the demo
 * accounts gone without immediately regenerating them (demo:generate --fresh
 * does the same removal inline before regenerating).
 */
class RemoveDemoData extends Command
{
    protected $signature = 'demo:remove';

    protected $description = 'Delete all synthetic demo users and demo feedback (is_demo_user / is_demo_feedback rows).';

    public function handle(): int
    {
        $demoUserIds = User::where('is_demo_user', true)->pluck('id');
        $feedbackCount = Feedback::where('is_demo_feedback', true)->count();

        if ($demoUserIds->isEmpty() && $feedbackCount === 0) {
            $this->info('No demo data found.');

            return self::SUCCESS;
        }

        Feedback::where('is_demo_feedback', true)->delete();

        if ($demoUserIds->isNotEmpty()) {
            $sessionIds = TestSession::whereIn('user_id', $demoUserIds)->pluck('id');
            SessionAnswer::whereIn('test_session_id', $sessionIds)->delete();
            TestSession::whereIn('user_id', $demoUserIds)->delete();
            GameScore::whereIn('user_id', $demoUserIds)->delete();
            UserDailyCheckin::whereIn('user_id', $demoUserIds)->delete();
            DB::table('user_progress_snapshots')->whereIn('user_id', $demoUserIds)->delete();
            DB::table('exam_readiness_predictions')->whereIn('user_id', $demoUserIds)->delete();
            ExamProfile::whereIn('user_id', $demoUserIds)->delete();
            User::whereIn('id', $demoUserIds)->delete();
        }

        $this->info("Removed {$demoUserIds->count()} demo users and {$feedbackCount} demo feedback rows.");

        return self::SUCCESS;
    }
}
