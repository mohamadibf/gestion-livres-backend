<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Réglages de la bibliothèque modifiables par un administrateur.
 * Les valeurs enregistrées en base priment ; sinon on retombe sur config/loans.php.
 */
class Settings
{
    private const CACHE_KEY = 'app.settings.stored';

    /** clé de réglage => clé de configuration fournissant la valeur par défaut */
    private const DEFAULTS = [
        'library_name' => 'loans.library_name',
        'loan_duration_days' => 'loans.duration_days',
        'max_active_loans' => 'loans.max_active_per_user',
        'max_renewals' => 'loans.max_renewals',
        'due_soon_days' => 'loans.due_soon_days',
    ];

    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = $this->stored();
        $values = [];

        foreach (self::DEFAULTS as $key => $configKey) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : config($configKey);
        }

        return $values;
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): void
    {
        DB::transaction(function () use ($values) {
            foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => $key],
                    ['value' => json_encode($value), 'updated_at' => now(), 'created_at' => now()]
                );
            }
        });

        Cache::forget(self::CACHE_KEY);
    }

    /** Seules les valeurs enregistrées sont mises en cache : les valeurs par défaut restent lues à chaud. */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => DB::table('settings')->pluck('value', 'key')
            ->map(fn ($json) => json_decode($json, true))
            ->all());
    }
}
