<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the columns needed for the Rasch-model (1PL Item Response Theory)
 * adaptive testing engine:
 * - questions.irt_difficulty: calibrated item difficulty (b) on the logit scale,
 *   null until RaschCalibrationService has calibrated it (falls back to a prior
 *   derived from level_number/difficulty_weight until then).
 * - questions.irt_discrimination: fixed at 1.0 for the 1PL/Rasch model; kept as
 *   a column (not a constant) so a future 2PL extension is a data change, not
 *   a schema change.
 * - questions.irt_calibrated_at: when this item's difficulty was last calibrated.
 * - users.theta_estimate / theta_se: the student's running ability estimate on
 *   the latent trait scale and its standard error, re-estimated via MLE after
 *   every placement/daily session.
 * - test_sessions.theta / theta_se: the (possibly still-in-progress, for
 *   adaptively-delivered placement tests) ability estimate for this session
 *   specifically, used both as the CAT stopping rule and as the session's
 *   contribution to the user's running estimate.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->float('irt_difficulty')->nullable()->after('difficulty_weight');
            $table->float('irt_discrimination')->default(1.0)->after('irt_difficulty');
            $table->timestamp('irt_calibrated_at')->nullable()->after('irt_discrimination');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->float('theta_estimate')->nullable()->after('current_level_id');
            $table->float('theta_se')->nullable()->after('theta_estimate');
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->float('theta')->nullable()->after('score_percent');
            $table->float('theta_se')->nullable()->after('theta');
        });
    }

    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn(['irt_difficulty', 'irt_discrimination', 'irt_calibrated_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['theta_estimate', 'theta_se']);
        });

        Schema::table('test_sessions', function (Blueprint $table) {
            $table->dropColumn(['theta', 'theta_se']);
        });
    }
};
