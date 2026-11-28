<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.0.4: users.password_set_at (staff who have not chosen a password yet get
 * "Get invite link"), users.removed_at (removed staff kept anonymised for the
 * audit log), and the developer (support) account.
 */
return new class extends Migration
{
    /**
     * SHA-256 of the lower-cased email of the developer account, so the
     * address itself is not published in the repository (DECISIONS 105).
     */
    private const DEVELOPER_EMAIL_SHA256 = ['1297564d305d0069a4a2b8428e78ff14687ba96c711b47db9d7543e7455925c6'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
            $table->timestamp('removed_at')->nullable()->after('is_active');
        });

        // Everyone who has logged in, and every tenant (tenants choose a password
        // when they accept the invite), has a password. Staff who never logged in
        // are still waiting on their invite.
        DB::table('users')->whereNotNull('last_login_at')->update(['password_set_at' => DB::raw('last_login_at')]);
        DB::table('users')->whereNull('password_set_at')->where('role', 'tenant')->update(['password_set_at' => DB::raw('created_at')]);

        foreach (DB::table('users')->select(['id', 'email'])->get() as $user) {
            if (in_array(hash('sha256', strtolower(trim((string) $user->email))), self::DEVELOPER_EMAIL_SHA256, true)) {
                DB::table('users')->where('id', $user->id)->update(['role' => 'developer', 'is_active' => true, 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'developer')->update(['role' => 'owner']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['password_set_at', 'removed_at']);
        });
    }
};
