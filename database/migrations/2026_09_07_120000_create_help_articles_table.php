<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One guide per screen, shown under Self Assistance.
     *
     * The seeder ships a guide for every page; administrators edit them, add
     * their own and reorder them, so the wording can follow how a particular
     * company actually works rather than staying frozen at whatever shipped.
     */
    public function up(): void
    {
        Schema::create('help_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('group', 64);
            $table->string('title');
            $table->string('icon', 32)->default('document');
            $table->string('summary', 500)->nullable();
            $table->text('body')->nullable();

            // The screen this documents, so the header can offer help for the
            // page someone is actually on.
            $table->string('route_name')->nullable();
            // Only shown to people who could open that screen anyway.
            $table->string('permission')->nullable();

            $table->json('fields')->nullable();          // term => what it means
            $table->json('tasks')->nullable();           // "How do I ...?"
            $table->json('troubleshooting')->nullable(); // "It is not working"

            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_published')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['group', 'position']);
            $table->index('route_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('help_articles');
    }
};
