<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ids = App\Models\User::where('is_demo_user', true)->pluck('id');
echo "Students: {$ids->count()}\n";
echo 'Placement done: '.App\Models\User::whereIn('id',$ids)->whereNotNull('placement_completed_at')->count()."\n";
echo 'Placement sessions: '.App\Models\TestSession::whereIn('user_id',$ids)->where('session_type','placement')->count()."\n";
echo 'Daily sessions: '.App\Models\TestSession::whereIn('user_id',$ids)->where('session_type','daily')->count()."\n";
echo 'Check-ins: '.App\Models\UserDailyCheckin::whereIn('user_id',$ids)->count()."\n";
echo 'Exam profiles: '.App\Models\ExamProfile::whereIn('user_id',$ids)->count()."\n";
echo 'Feedback: '.App\Models\Feedback::whereIn('user_id',$ids)->count()."\n";
echo 'ML predictions: '.DB::table('exam_readiness_predictions')->whereIn('user_id',$ids)->count()."\n";

echo "\nNames & emails:\n";
foreach (App\Models\User::where('is_demo_user', true)->orderBy('name')->get(['name','email','placement_completed_at']) as $u) {
    echo "- {$u->name} | {$u->email} | placement: ".($u->placement_completed_at ? $u->placement_completed_at->format('Y-m-d') : 'no')."\n";
}
