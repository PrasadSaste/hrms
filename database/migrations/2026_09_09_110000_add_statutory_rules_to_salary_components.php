<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rules that make a statutory deduction right.
 *
 * A percentage of a figure is not what provident fund, employee state
 * insurance or professional tax actually are. Provident fund is a percentage
 * of a wage capped at a statutory figure, so somebody on a large basic pays
 * the same as somebody on the cap. Employee state insurance stops applying
 * altogether above its ceiling rather than being charged on a capped wage.
 * Professional tax is a table of slabs set by each state, not a percentage at
 * all. Without these, every one of the three was wrong for anybody well paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            // The wage the percentage is worked out on is capped at this.
            // Provident fund: 12% of a basic capped at 15,000.
            $table->decimal('wage_ceiling', 12, 2)->nullable()->after('percentage_of');

            // Above this the component does not apply at all. Employee state
            // insurance: nothing once gross passes 21,000.
            $table->decimal('eligibility_ceiling', 12, 2)->nullable()->after('wage_ceiling');

            // A table of slabs, for professional tax. Each entry is
            // {"up_to": 24999, "amount": 0}; the last has a null up_to.
            $table->json('slabs')->nullable()->after('eligibility_ceiling');

            // Which state's rules these are, since professional tax differs by
            // state and somebody has to be able to see which one is loaded.
            $table->string('statutory_note', 255)->nullable()->after('slabs');
        });
    }

    public function down(): void
    {
        Schema::table('salary_components', function (Blueprint $table) {
            $table->dropColumn(['wage_ceiling', 'eligibility_ceiling', 'slabs', 'statutory_note']);
        });
    }
};
