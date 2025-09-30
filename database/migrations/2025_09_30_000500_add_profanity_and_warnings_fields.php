<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->boolean('is_auto_generated')->default(false);
            $table->decimal('profanity_score', 5, 2)->nullable()->after('is_auto_generated');
            $table->json('detected_words')->nullable()->after('profanity_score');
            $table->string('context')->nullable()->after('detected_words');
            $table->unsignedBigInteger('context_id')->nullable()->after('context');
            $table->string('original_text_hash')->nullable()->after('context_id');
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'warning_count')) {
                $table->unsignedInteger('warning_count')->default(0);
            }
        });

        if (!Schema::hasTable('blacklist_emails')) {
            Schema::create('blacklist_emails', function (Blueprint $table) {
                $table->id();
                $table->string('email')->unique();
                $table->string('reason')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            if (Schema::hasColumn('reports', 'is_auto_generated')) {
                $table->dropColumn('is_auto_generated');
            }
            if (Schema::hasColumn('reports', 'profanity_score')) {
                $table->dropColumn('profanity_score');
            }
            if (Schema::hasColumn('reports', 'detected_words')) {
                $table->dropColumn('detected_words');
            }
            if (Schema::hasColumn('reports', 'context')) {
                $table->dropColumn('context');
            }
            if (Schema::hasColumn('reports', 'context_id')) {
                $table->dropColumn('context_id');
            }
            if (Schema::hasColumn('reports', 'original_text_hash')) {
                $table->dropColumn('original_text_hash');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'warning_count')) {
                $table->dropColumn('warning_count');
            }
        });

        Schema::dropIfExists('blacklist_emails');
    }
};


