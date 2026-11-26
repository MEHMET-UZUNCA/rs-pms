<?php

namespace App\Domain\Core\Services;

use App\Domain\Core\Models\ExchangeRate;
use App\Domain\Core\Models\Property;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

/**
 * Döviz kurları: KKTC Merkez Bankası günlük kurları (XML) → otel kurları. PMS kurları her zaman kendi veritabanından okur
 * (pms_exchange_rates); banka yalnız günlük servis (pms:fetch-exchange-rates) tarafından okunur.
 *
 * Otel kuru = dayanak banka kuru (döviz/efektif alış/satış) × (1 + kâr payı / 100). Kâr payı eksi olabilir.
 * Fidelio'daki üç kur: Rate (folyo kuru), Cash Rate (nakit döviz alırken), Ratecode Rate (döviz fiyat kodları).
 */
class ExchangeRateService
{
    public const BASES = ['forex_buy', 'forex_sell', 'cash_buy', 'cash_sell'];

    /** Kur kaynakları (Ayarlar → Döviz kurları). TCMB ile başlanır; KKTC Merkez Bankası sunucudan erişilince seçilir. */
    public const SOURCES = [
        'tcmb' => 'https://www.tcmb.gov.tr/kurlar/today.xml',
        'kktcmb' => 'https://mb.gov.ct.tr/kur/gunluk.xml',
        'manual' => null,
    ];

    public const SETTING = 'exchange.source';

    public static function source(int $propertyId): string
    {
        $value = DB::table('pms_settings')->where('property_id', $propertyId)->where('key', self::SETTING)->value('value');
        $value = is_string($value) ? json_decode($value, true) : null;

        return is_string($value) && array_key_exists($value, self::SOURCES) ? $value : 'tcmb';
    }

    public static function setSource(int $propertyId, string $source, ?int $userId): void
    {
        DB::table('pms_settings')->updateOrInsert(['property_id' => $propertyId, 'key' => self::SETTING],
            ['value' => json_encode($source), 'updated_by' => $userId, 'updated_at' => now(), 'created_at' => now()]);
    }

    /** Bankadan kurları indirir (ya da verilen XML'i kullanır), kurallara göre otel kurlarını yazar. Yazılan döviz sayısı. */
    public function fetch(int $propertyId, ?string $xml = null, ?int $userId = null): int
    {
        $source = self::source($propertyId);
        if ($xml === null) {
            $url = self::SOURCES[$source];
            if ($url === null) {
                throw new RuntimeException('Kur kaynağı "elle" seçili; kurlar ekrandan girilir.');
            }
            $response = Http::timeout(30)->retry(2, 2000)->get($url);
            if (! $response->successful()) {
                throw new RuntimeException('Kur servisi cevap vermedi: HTTP '.$response->status());
            }
            $xml = $response->body();
        }
        $bank = self::parse($xml);
        if ($bank === []) {
            throw new RuntimeException('Kur listesinde döviz bulunamadı (XML biçimi tanınmadı).');
        }

        $date = Property::query()->findOrFail($propertyId)->current_business_date->toDateString();
        $count = 0;
        foreach ($this->rules($propertyId) as $rule) {
            $bankRates = $bank[$rule->source_currency_code ?: $rule->currency_code] ?? null;
            if (! $rule->is_auto || ! $bankRates) {
                continue;
            }
            $this->store($propertyId, $rule, $bankRates, $date, $source, $userId);
            $count++;
        }

        return $count;
    }

    /**
     * Elle kur girişi (banka kuru yoksa ya da otel farklı kur kullanacaksa): banka kurları verilir, otel kurları kurala göre hesaplanır;
     * otel kurları doğrudan da verilebilir.
     *
     * @param  array{forex_buy?: ?float, forex_sell?: ?float, cash_buy?: ?float, cash_sell?: ?float, posting_rate?: ?float, cash_rate?: ?float, ratecode_rate?: ?float}  $rates
     */
    public function manual(int $propertyId, string $currency, array $rates, ?int $userId, string $source = 'manual'): ExchangeRate
    {
        $rule = $this->rules($propertyId)->firstWhere('currency_code', $currency) ?? (object) ['currency_code' => $currency, 'base' => 'forex_buy',
            'rate_margin_pct' => 0, 'cash_margin_pct' => 0, 'ratecode_margin_pct' => 0];
        $date = Property::query()->findOrFail($propertyId)->current_business_date->toDateString();

        return $this->store($propertyId, $rule, array_filter([
            'forex_buy' => $rates['forex_buy'] ?? null, 'forex_sell' => $rates['forex_sell'] ?? null,
            'cash_buy' => $rates['cash_buy'] ?? null, 'cash_sell' => $rates['cash_sell'] ?? null,
        ], fn ($v) => $v !== null), $date, $source, $userId, array_filter([
            'posting_rate' => $rates['posting_rate'] ?? null, 'cash_rate' => $rates['cash_rate'] ?? null, 'ratecode_rate' => $rates['ratecode_rate'] ?? null,
        ], fn ($v) => $v !== null));
    }

    /** Geçerli kur: verilen günde ya da öncesindeki son kur (PMS her yerde bunu kullanır). */
    public function current(int $propertyId, string $currency, ?string $date = null): ?ExchangeRate
    {
        $date ??= Property::query()->findOrFail($propertyId)->current_business_date->toDateString();

        return ExchangeRate::query()->where('property_id', $propertyId)->where('currency_code', $currency)->where('rate_type', 'day')
            ->where('business_date', '<=', $date)->orderByDesc('business_date')->first();
    }

    /**
     * KKTC Merkez Bankası XML'i → [döviz kodu => [forex_buy, forex_sell, cash_buy, cash_sell]] (birim başına).
     * Alan adları esnek okunur (Sembol/Kod/CurrencyCode, Doviz_Alis/ForexBuying, Efektif_Alis/BanknoteBuying, Birim/Unit).
     *
     * @return array<string, array<string, float>>
     */
    public static function parse(string $xml): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        if ($doc === false) {
            throw new RuntimeException('Kur listesi okunamadı (geçersiz XML).');
        }
        $patterns = [
            'code' => '/^(sembol|symbol|kod|kodu|code|currencycode|doviz_?kodu|kur_?kodu|doviz_?cinsi)$/i',
            'unit' => '/^(birim|unit)$/i',
            'forex_buy' => '/^(doviz_?alis|dovizalis|forexbuying|alis|buying)$/i',
            'forex_sell' => '/^(doviz_?satis|dovizsatis|forexselling|satis|selling)$/i',
            'cash_buy' => '/^(efektif_?alis|banknotebuying|cashbuying)$/i',
            'cash_sell' => '/^(efektif_?satis|banknoteselling|cashselling)$/i',
        ];
        $number = function (string $raw): ?float {
            $raw = trim(str_replace(' ', '', $raw));
            if ($raw === '') {
                return null;
            }
            if (str_contains($raw, ',') && str_contains($raw, '.')) {
                $raw = str_replace('.', '', $raw);
            }
            $raw = str_replace(',', '.', $raw);

            return is_numeric($raw) ? (float) $raw : null;
        };

        $rates = [];
        foreach ($doc->xpath('//*[*]') ?: [] as $node) {
            $fields = [];
            foreach ($node->attributes() as $name => $value) {
                $fields[(string) $name] = (string) $value;
            }
            foreach ($node->children() as $child) {
                if ($child->count() === 0) {
                    $fields[$child->getName()] = (string) $child;
                }
            }
            $row = [];
            foreach ($fields as $name => $value) {
                foreach ($patterns as $key => $pattern) {
                    if (! isset($row[$key]) && preg_match($pattern, (string) $name)) {
                        $row[$key] = $value;
                    }
                }
            }
            $code = strtoupper(trim($row['code'] ?? ''));
            if (! preg_match('/^[A-Z]{3}$/', $code)) {
                continue;
            }
            $unit = max(1.0, (float) ($number($row['unit'] ?? '1') ?? 1));
            $values = [];
            foreach (['forex_buy', 'forex_sell', 'cash_buy', 'cash_sell'] as $key) {
                $v = isset($row[$key]) ? $number($row[$key]) : null;
                if ($v !== null && $v > 0) {
                    $values[$key] = round($v / $unit, 8);
                }
            }
            if ($values !== []) {
                $rates[$code] = $values;
            }
        }

        return $rates;
    }

    /** Kurallar; tanımsız dövizler için kural yoksa liste boş döner (Ayarlar → Döviz kurlarından eklenir). */
    public function rules(int $propertyId)
    {
        return DB::table('pms_exchange_rate_rules')->where('property_id', $propertyId)->orderBy('currency_code')->get();
    }

    /** @param array<string, float> $bank @param array<string, float> $fixed */
    private function store(int $propertyId, object $rule, array $bank, string $date, string $source, ?int $userId, array $fixed = []): ExchangeRate
    {
        $base = $bank[$rule->base] ?? $bank['forex_buy'] ?? $bank['forex_sell'] ?? $bank['cash_buy'] ?? $bank['cash_sell'] ?? null;
        $calc = fn (float $pct) => $base !== null ? round($base * (1 + $pct / 100), 6) : null;
        $posting = $fixed['posting_rate'] ?? $calc((float) $rule->rate_margin_pct);
        if ($posting === null) {
            throw new RuntimeException("{$rule->currency_code} için kur girilmedi.");
        }

        return ExchangeRate::query()->updateOrCreate(
            ['property_id' => $propertyId, 'currency_code' => $rule->currency_code, 'business_date' => $date, 'rate_type' => 'day'],
            [
                'buy_rate' => $bank['forex_buy'] ?? null, 'sell_rate' => $bank['forex_sell'] ?? null,
                'cash_buy_rate' => $bank['cash_buy'] ?? null, 'cash_sell_rate' => $bank['cash_sell'] ?? null,
                'posting_rate' => $posting,
                'cash_rate' => $fixed['cash_rate'] ?? $calc((float) $rule->cash_margin_pct) ?? $posting,
                'ratecode_rate' => $fixed['ratecode_rate'] ?? $calc((float) $rule->ratecode_margin_pct) ?? $posting,
                'source' => $source, 'created_by' => $userId,
            ]);
    }
}
