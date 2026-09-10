<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * User ↔ School membership. A user may belong to several schools (e.g. a
     * proprietor running two campuses); the pivot carries no role yet —
     * roles/permissions are a later milestone.
     *
     * The composite primary key `(school_id, user_id)` serves the "members of
     * this school" query; the reverse "schools for this user" lookup used by
     * EnforceTenant is covered by the index MySQL creates for the `user_id`
     * foreign key.
     */
    public function up(): void
    {
        Schema::create('school_user', function (Blueprint $table) {
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['school_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_user');
    }
};
