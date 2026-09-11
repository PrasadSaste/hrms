<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which Saturdays of the month a branch does not work.
     *
     * `working_days` says whether Saturday is a working day at all. This says
     * which ones are the exception: [1, 3] means the first and third Saturday
     * of each month are off and the rest are worked, which is how a great many
     * Indian offices run their week.
     *
     * Empty or null means every Saturday in `working_days` is worked, so
     * nothing changes for a branch that has not been told otherwise.
     */
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->json('saturday_offs')->nullable()->after('working_days');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('saturday_offs');
        });
    }
};
