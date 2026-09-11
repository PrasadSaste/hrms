<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-event overrides for the notification catalogue.
     *
     * A row only exists once someone has edited an event from the console.
     * Every column is nullable so an administrator can change the subject
     * alone and keep the shipped wording for everything else.
     */
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('mail_enabled')->default(true);
            $table->boolean('database_enabled')->default(true);
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('action_label')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
