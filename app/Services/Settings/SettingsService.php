<?php

namespace App\Services\Settings;

use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    /**
     * @param  array<string, array{value: mixed, type?: string, is_public?: bool}>  $values
     */
    public function updateGroup(string $group, array $values, ?int $updatedBy = null): void
    {
        $updatedBy ??= Auth::id();

        foreach ($values as $key => $config) {
            $fullKey = str_contains($key, '.') ? $key : "{$group}.{$key}";
            $type = $config['type'] ?? $this->inferType($config['value']);
            $storedValue = $this->serializeValue($config['value'], $type);

            Setting::query()->updateOrCreate(
                ['key' => $fullKey],
                [
                    'value' => $storedValue,
                    'type' => $type,
                    'group' => $group,
                    'is_public' => $config['is_public'] ?? false,
                    'updated_by' => $updatedBy,
                ],
            );
        }
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->value($key, $default);

        return $value === null ? $default : (string) $value;
    }

    public function getInteger(string $key, ?int $default = null): ?int
    {
        $value = $this->value($key, $default);

        return $value === null ? $default : (int) $value;
    }

    public function getFloat(string $key, ?float $default = null): ?float
    {
        $value = $this->value($key, $default);

        return $value === null ? $default : (float) $value;
    }

    public function getBoolean(string $key, ?bool $default = null): ?bool
    {
        $value = $this->value($key, $default);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getJson(string $key, ?array $default = null): ?array
    {
        $value = $this->value($key, $default);

        if ($value === null) {
            return $default;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : $default;
    }

    public function getEncrypted(string $key, ?string $default = null): ?string
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting || $setting->value === null || $setting->value === '') {
            return $default;
        }

        try {
            return Crypt::decryptString($setting->value);
        } catch (DecryptException) {
            return $default;
        }
    }

    public function maskEncrypted(string $key): ?string
    {
        $plain = $this->getEncrypted($key);

        if ($plain === null || $plain === '') {
            return null;
        }

        $length = strlen($plain);

        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        $prefix = substr($plain, 0, 3);
        $suffix = substr($plain, -4);

        return $prefix.'****'.$suffix;
    }

    public function setEncrypted(string $key, string $value, string $group = 'ai', ?int $updatedBy = null): void
    {
        $this->updateGroup($group, [
            $key => [
                'value' => Crypt::encryptString($value),
                'type' => 'encrypted',
            ],
        ], $updatedBy);
    }

    public function removeEncrypted(string $key, ?int $updatedBy = null): void
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return;
        }

        $setting->update([
            'value' => null,
            'updated_by' => $updatedBy ?? Auth::id(),
        ]);
    }

    public function hasEncrypted(string $key): bool
    {
        return filled($this->getEncrypted($key));
    }

    /**
     * @return array<string, mixed>
     */
    public function getGroupForDisplay(string $group, array $keys, bool $includeMaskedSecrets = true): array
    {
        $result = [];

        foreach ($keys as $key => $meta) {
            if (is_int($key)) {
                $key = $meta;
                $meta = [];
            }

            $fullKey = str_contains($key, '.') ? $key : "{$group}.{$key}";
            $type = $meta['type'] ?? 'string';

            if ($type === 'encrypted') {
                $result[$key] = $includeMaskedSecrets ? $this->maskEncrypted($fullKey) : null;

                continue;
            }

            $result[$key] = match ($type) {
                'integer' => $this->getInteger($fullKey),
                'float', 'decimal' => $this->getFloat($fullKey),
                'boolean' => $this->getBoolean($fullKey),
                'json' => $this->getJson($fullKey),
                default => $this->getString($fullKey),
            };
        }

        return $result;
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $setting = Setting::query()->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'float', 'decimal' => (float) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            'encrypted' => $this->getEncrypted($key, is_string($default) ? $default : null),
            default => $setting->value,
        };
    }

    protected function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => 'float',
            is_array($value) => 'json',
            default => 'string',
        };
    }

    protected function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
