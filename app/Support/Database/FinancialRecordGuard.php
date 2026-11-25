<?php

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mali kayıt tablolarını veritabanı seviyesinde korur: kayıt silinemez, değiştirilemez
 * (yalnız izin verilen alanlar güncellenebilir). Düzeltme ters kayıtla yapılır.
 *
 * MySQL/MariaDB ve SQLite tetikleyicileri üretir. SQLite'ta tabloya sonradan yabancı anahtar eklemek
 * tabloyu yeniden kurar ve tetikleyicileri siler; o durumda protect() yeniden çağrılmalıdır.
 */
class FinancialRecordGuard
{
    public const MESSAGE = 'Mali kayit degistirilemez veya silinemez; ters kayit kullanin.';

    /**
     * @param  array<int,string>|null  $updatable  Güncellenebilir kolonlar; null = hiçbir güncelleme engellenmez, [] = her güncelleme engellenir.
     */
    public static function protect(string $table, ?array $updatable = [], bool $blockDelete = true): void
    {
        self::drop($table);
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            if ($blockDelete) {
                DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} BEGIN SELECT RAISE(ABORT, '".self::MESSAGE."'); END;");
            }
            if ($updatable !== null) {
                $locked = array_values(array_diff(Schema::getColumnListing($table), $updatable));
                $of = $updatable === [] ? '' : ' OF '.implode(', ', $locked);
                DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE{$of} ON {$table} BEGIN SELECT RAISE(ABORT, '".self::MESSAGE."'); END;");
            }

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $signal = "SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '".self::MESSAGE."'";
            if ($blockDelete) {
                DB::unprepared("CREATE TRIGGER {$table}_no_delete BEFORE DELETE ON {$table} FOR EACH ROW {$signal}");
            }
            if ($updatable === []) {
                DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW {$signal}");
            } elseif ($updatable !== null) {
                $locked = array_values(array_diff(Schema::getColumnListing($table), $updatable));
                $changed = implode(' OR ', array_map(fn ($c) => "NOT (OLD.`{$c}` <=> NEW.`{$c}`)", $locked));
                DB::unprepared("CREATE TRIGGER {$table}_no_update BEFORE UPDATE ON {$table} FOR EACH ROW BEGIN IF {$changed} THEN {$signal}; END IF; END");
            }
        }
    }

    public static function drop(string $table): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_delete");
        DB::unprepared("DROP TRIGGER IF EXISTS {$table}_no_update");
    }

    /** Kasa aşamasının koruma kuralları (pms_postings ve bağlı tablolar). */
    public static function protectCashiering(): void
    {
        self::protect('pms_postings', ['reversed_by_id', 'invoice_id']);
        self::protect('pms_posting_taxes', []);
        self::protect('pms_invoice_lines', []);
        self::protect('pms_invoices', null);
    }
}
