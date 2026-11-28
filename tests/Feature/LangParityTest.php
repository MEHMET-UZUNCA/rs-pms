<?php

namespace Tests\Feature;

use Tests\TestCase;

/** İngilizce ve Türkçe dil dosyalarında aynı anahtarlar olmalı; servis hata mesajları iki dilde de çevrilmiş olmalı. */
class LangParityTest extends TestCase
{
    private function flat(array $a, string $p = ''): array
    {
        $out = [];
        foreach ($a as $k => $v) {
            $key = $p === '' ? (string) $k : $p.'.'.$k;
            is_array($v) ? $out += $this->flat($v, $key) : $out[$key] = $v;
        }

        return $out;
    }

    public function test_en_and_tr_have_the_same_keys(): void
    {
        foreach (['pms', 'letter'] as $file) {
            $en = $this->flat(require lang_path("en/{$file}.php"));
            $tr = $this->flat(require lang_path("tr/{$file}.php"));
            $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($tr))), "{$file}: yalnız İngilizcede");
            $this->assertSame([], array_values(array_diff(array_keys($tr), array_keys($en))), "{$file}: yalnız Türkçede");
        }
    }

    public function test_service_errors_follow_the_interface_language(): void
    {
        app()->setLocale('tr');
        $this->assertSame('Girişten önce oda atanmalı.', __('pms.err.checkin_room'));
        $this->assertSame('Oda 1102 dolu.', __('pms.err.room_occupied', ['room' => '1102']));
        app()->setLocale('en');
        $this->assertSame('Room 1102 is occupied.', __('pms.err.room_occupied', ['room' => '1102']));
    }

    /** Kodda ve ekranlarda sabit yazılmış her __('pms.…') anahtarı dil dosyasında bulunmalı (eksikse ekranda anahtar adı görünür). */
    public function test_every_literal_translation_key_used_in_code_exists(): void
    {
        $en = $this->flat(require lang_path('en/pms.php'));
        $missing = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('resources/views')));
        $paths = array_merge(iterator_to_array($files), iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()))));
        foreach ($paths as $file) {
            if (! $file->isFile() || ! preg_match('/\.php$/', $file->getFilename())) {
                continue;
            }
            preg_match_all("/__\\('pms\\.([a-z0-9_.]+)'[,)]/", file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $key) {
                if (! array_key_exists($key, $en) && ! $this->isGroup($key, $en)) {
                    $missing[] = $key.' ('.basename($file->getPathname()).')';
                }
            }
        }
        $this->assertSame([], array_values(array_unique($missing)));
    }

    private function isGroup(string $key, array $flat): bool
    {
        foreach (array_keys($flat) as $k) {
            if (str_starts_with($k, $key.'.')) {
                return true;
            }
        }

        return false;
    }
}
