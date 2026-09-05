<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CompanySetting extends Model
{
    protected $fillable = [
        'name',
        'tagline',
        'logo_path',
        'email',
        'phone',
        'address',
        'tax_id',
        'charges_iva',
        'iva_rate',
        'website',
        'bank_name',
        'bank_account_type',
        'bank_account_number',
        'bank_account_holder',
        'primary_color',
        'sidebar_theme',
        'sidebar_color',
    ];

    protected function casts(): array
    {
        return [
            'charges_iva' => 'boolean',
            'iva_rate' => 'decimal:2',
        ];
    }
    public static function current(): self
    {
        return Cache::rememberForever('company_settings.current', function (): self {
            $settings = static::query()->first();

            if ($settings) {
                return $settings;
            }

            return static::query()->create([
                'name' => config('app.name', 'Korapp'),
                'primary_color' => '#3B82F6',
                'sidebar_theme' => 'indigo',
                'sidebar_color' => '#313A82',
            ]);
        });
    }

    public static function flushCache(): void
    {
        Cache::forget('company_settings.current');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        // asset() respeta APP_URL (incluye /korapp en cPanel).
        return asset('storage/'.ltrim(str_replace('\\', '/', $this->logo_path), '/'));
    }

    public function usesCustomSidebar(): bool
    {
        return $this->sidebar_theme === 'custom';
    }

    public function usesIndigoSidebar(): bool
    {
        if ($this->usesCustomSidebar()) {
            return $this->isDarkSidebar();
        }

        return $this->sidebar_theme === 'indigo';
    }

    public function sidebarColor(): string
    {
        return match ($this->sidebar_theme) {
            'light' => '#FFFFFF',
            'indigo' => '#313A82',
            default => strtoupper($this->sidebar_color ?: '#313A82'),
        };
    }

    public function isDarkSidebar(): bool
    {
        return $this->relativeLuminance($this->sidebarColor()) < 0.55;
    }

    /**
     * Effective theme token used by CSS (`light` | `indigo`).
     * Custom colors map to indigo/light based on luminance.
     */
    public function sidebarThemeToken(): string
    {
        return $this->isDarkSidebar() ? 'indigo' : 'light';
    }

    /**
     * @return array<string, string>
     */
    public function sidebarCssVariables(): array
    {
        $bg = $this->sidebarColor();

        if ($this->isDarkSidebar()) {
            return [
                '--saas-sidebar' => $bg,
                '--saas-sidebar-text' => '#F8FAFC',
                '--saas-sidebar-muted' => 'rgba(248, 250, 252, 0.72)',
                '--saas-sidebar-hover' => 'rgba(255, 255, 255, 0.12)',
                '--saas-sidebar-active' => '#FFFFFF',
                '--saas-sidebar-active-text' => '#1E293B',
                '--saas-sidebar-border' => 'rgba(255, 255, 255, 0.14)',
            ];
        }

        return [
            '--saas-sidebar' => $bg,
            '--saas-sidebar-text' => '#1E293B',
            '--saas-sidebar-muted' => '#64748B',
            '--saas-sidebar-hover' => '#F1F5F9',
            '--saas-sidebar-active' => '#F1F5F9',
            '--saas-sidebar-active-text' => '#1E293B',
            '--saas-sidebar-border' => '#E2E8F0',
        ];
    }

    protected function relativeLuminance(string $hex): float
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return 0.2;
        }

        $channels = array_map(
            function (string $channel): float {
                $value = hexdec($channel) / 255;

                return $value <= 0.03928
                    ? $value / 12.92
                    : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split($hex, 2),
        );

        return (0.2126 * $channels[0]) + (0.7152 * $channels[1]) + (0.0722 * $channels[2]);
    }
}
