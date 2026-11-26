<?php

use App\Support\Database\ProisvViews;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** İlk girişte şifre değiştirme zorunluluğu (rastgele şifreyle açılan kullanıcılar) ve Fidelio kullanıcı bilgileri. */
return new class extends Migration
{
    public function up(): void
    {
        (new ProisvViews)->drop();
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->timestamp('password_changed_at')->nullable()->after('must_change_password');
            $table->string('initials', 5)->nullable()->after('username');   // F: Initials
            $table->string('title', 60)->nullable()->after('initials');     // F: Title
        });
        (new ProisvViews)->create();
    }

    public function down(): void
    {
        (new ProisvViews)->drop();
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['must_change_password', 'password_changed_at', 'initials', 'title']));
        (new ProisvViews)->create();
    }
};
