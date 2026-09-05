<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\TutorialManejo;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Models\CompanySetting;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Enums\UserMenuPosition;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->passwordReset()
            ->brandName(fn (): string => rescue(fn () => CompanySetting::current()->name, config('app.name', 'Korapp'), report: false) ?: config('app.name', 'Korapp'))
            ->brandLogo(fn (): HtmlString => new HtmlString(view('filament.components.brand')->render()))
            ->brandLogoHeight('2rem')
            ->font('Inter')
            ->colors([
                'primary' => Color::generatePalette(
                    rescue(fn () => CompanySetting::current()->primary_color, '#3B82F6', report: false) ?: '#3B82F6'
                ),
                'gray' => Color::Slate,
                'warning' => Color::Orange,
            ])
            ->darkMode(false)
            ->topbar(false)
            ->maxContentWidth(Width::None)
            ->sidebarCollapsibleOnDesktop()
            ->collapsedSidebarWidth('4rem')
            ->sidebarWidth('15rem')
            ->globalSearch(false)
            ->userMenu(true, UserMenuPosition::Sidebar)
            ->databaseNotifications(false)
            ->profile(EditProfile::class, isSimple: false)
            ->userMenuItems([
                'profile' => Action::make('profile')
                    ->label('Mi perfil')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => EditProfile::getUrl())
                    ->sort(-1),
                'help' => Action::make('help')
                    ->label('Tutorial')
                    ->icon(Heroicon::OutlinedAcademicCap)
                    ->url(fn (): string => TutorialManejo::getUrl())
                    ->sort(20),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => view('filament.hooks.company-theme')->render(),
            )
            ->navigationGroups([
                NavigationGroup::make('General'),
                NavigationGroup::make('Ventas'),
                NavigationGroup::make('Configuración Acrílico')
                    ->collapsed(),
                NavigationGroup::make('Lista de precios')
                    ->collapsed(),
                NavigationGroup::make('Gestión'),
                NavigationGroup::make('Producción'),
                NavigationGroup::make('Reportes'),
                NavigationGroup::make('Auditoría'),
                NavigationGroup::make('Seguridad'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordIsChanged::class,
            ], isPersistent: true)
            ->plugin(
                FilamentShieldPlugin::make()
                    ->navigationGroup('Seguridad')
            );
    }
}
