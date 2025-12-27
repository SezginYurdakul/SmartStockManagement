<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'description',
        'is_system',
    ];

    protected $casts = [
        'value' => 'array',
        'is_system' => 'boolean',
    ];

    /**
     * Cache TTL in seconds (1 hour)
     */
    protected const CACHE_TTL = 3600;

    /**
     * Cache tag for settings
     */
    protected const CACHE_TAG = 'settings';

    /**
     * Get a setting value by group.key notation
     *
     * @param string $key Format: "group.key" (e.g., "qc.sampling_methods")
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        [$group, $settingKey] = self::parseKey($key);

        $cacheKey = "settings.{$group}.{$settingKey}";

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($group, $settingKey, $default) {
            $setting = self::where('group', $group)
                ->where('key', $settingKey)
                ->first();

            return $setting?->value ?? $default;
        });
    }

    /**
     * Set a setting value
     *
     * @param string $key Format: "group.key"
     * @param mixed $value
     * @param string|null $description
     * @param bool $isSystem
     * @return Setting
     */
    public static function set(string $key, mixed $value, ?string $description = null, bool $isSystem = false): Setting
    {
        [$group, $settingKey] = self::parseKey($key);

        $setting = self::updateOrCreate(
            ['group' => $group, 'key' => $settingKey],
            ['value' => $value, 'description' => $description, 'is_system' => $isSystem]
        );

        // Clear specific key and group cache
        Cache::tags([self::CACHE_TAG])->forget("settings.{$group}.{$settingKey}");
        Cache::tags([self::CACHE_TAG])->forget("settings.{$group}");

        return $setting;
    }

    /**
     * Get all settings for a group
     *
     * @param string $group
     * @return array
     */
    public static function getGroup(string $group): array
    {
        $cacheKey = "settings.{$group}";

        return Cache::tags([self::CACHE_TAG])->remember($cacheKey, self::CACHE_TTL, function () use ($group) {
            return self::where('group', $group)
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    /**
     * Clear all settings cache
     */
    public static function clearCache(): void
    {
        Cache::tags([self::CACHE_TAG])->flush();
    }

    /**
     * Parse "group.key" format
     */
    protected static function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);

        if (count($parts) !== 2) {
            throw new \InvalidArgumentException("Setting key must be in 'group.key' format. Given: {$key}");
        }

        return $parts;
    }

    /**
     * Scope: By group
     */
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    /**
     * Scope: System settings only
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }
}
