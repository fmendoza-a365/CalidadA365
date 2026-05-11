<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value', 'type', 'description'];

    /**
     * Obtener un valor de configuración
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = Cache::remember("setting.{$key}", 3600, function () use ($key) {
            return static::where('key', $key)->first();
        });

        if (!$setting) {
            return $default;
        }

        $value = $setting->value;
        if (static::isSensitiveKey($setting->key)) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                $value = $setting->value;
            }
        }

        return match($setting->type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Establecer un valor de configuración
     */
    public static function set(string $key, mixed $value, string $type = 'string', ?string $group = null, ?string $description = null): void
    {
        $storedValue = match($type) {
            'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        if (static::isSensitiveKey($key) && $storedValue !== '') {
            $storedValue = Crypt::encryptString($storedValue);
        }

        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $storedValue,
                'type' => $type,
                'group' => $group ?? 'general',
                'description' => $description,
            ]
        );

        Cache::forget("setting.{$key}");
    }

    private static function isSensitiveKey(string $key): bool
    {
        return str_contains($key, 'api_key')
            || str_contains($key, 'token')
            || str_contains($key, 'secret')
            || str_contains($key, 'password');
    }

    /**
     * Obtener todas las configuraciones de un grupo
     */
    public static function getGroup(string $group): array
    {
        $settings = static::where('group', $group)->get();
        
        $result = [];
        foreach ($settings as $setting) {
            $result[$setting->key] = static::get($setting->key);
        }
        
        return $result;
    }
}
