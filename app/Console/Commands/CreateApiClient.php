<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Web API istemcisi açar (anahtar yalnız bir kez gösterilir) ya da kapatır. */
class CreateApiClient extends Command
{
    protected $signature = 'pms:api-client {name? : İstemci adı (ör. "Web sitesi")} {--property=RCK} {--revoke= : Bu numaralı istemciyi kapat} {--list}';

    protected $description = 'Web API istemcisi (anahtar) açar, listeler ya da kapatır';

    public function handle(): int
    {
        if ($this->option('list')) {
            $this->table(['No', 'Ad', 'Anahtar', 'Aktif', 'Son kullanım'], DB::table('pms_api_clients')->orderBy('id')->get()
                ->map(fn ($c) => [$c->id, $c->name, $c->token_prefix.'…', $c->is_active ? 'evet' : 'hayır', $c->last_used_at])->all());

            return self::SUCCESS;
        }
        if ($id = $this->option('revoke')) {
            $done = DB::table('pms_api_clients')->where('id', $id)->update(['is_active' => false, 'updated_at' => now()]);
            $this->info($done ? "İstemci {$id} kapatıldı." : "İstemci {$id} bulunamadı.");

            return $done ? self::SUCCESS : self::FAILURE;
        }
        $name = (string) ($this->argument('name') ?? '');
        if ($name === '') {
            $this->error('İstemci adı gerekli.');

            return self::FAILURE;
        }
        $propertyId = DB::table('pms_properties')->where('code', $this->option('property'))->value('id');
        if (! $propertyId) {
            $this->error('Tesis bulunamadı.');

            return self::FAILURE;
        }
        $token = 'rspms_'.Str::random(40);
        DB::table('pms_api_clients')->insert(['property_id' => $propertyId, 'name' => $name, 'token_hash' => hash('sha256', $token),
            'token_prefix' => substr($token, 0, 8), 'created_at' => now(), 'updated_at' => now()]);
        $this->info("API istemcisi açıldı: {$name}");
        $this->line("Anahtar (yalnız şimdi gösterilir, web sitesinin sunucu ayarına yazın): {$token}");

        return self::SUCCESS;
    }
}
