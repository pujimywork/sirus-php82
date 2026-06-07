<?php

namespace App\Providers;

use App\Services\AppMenu;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('production', 'staging')
            || request()->server('HTTP_X_FORWARDED_PROTO') === 'https'
            || request()->isSecure()) {
            URL::forceScheme('https');
        }

        // Blade directive untuk render path TTD user.
        // - Standar baru: DB simpan filename saja (mis: 08052026081302.png)
        //   → prepend 'storage/UserTtd/'
        // - Legacy: DB simpan full path (mis: 'UserTtd/abc.png')
        //   → pakai apa adanya dengan prefix 'storage/'
        // Pemakaian: <img src="@ttdSrc($user->myuser_ttd_image)" />
        Blade::directive('ttdSrc', function ($expression) {
            return "<?php echo (function (\$v) { return empty(\$v) ? '' : 'storage/' . (str_contains(\$v, '/') ? \$v : 'UserTtd/' . \$v); })($expression); ?>";
        });

        // Share $sidebarMenus (grouped + filtered by user role) ke sidebar layout.
        // Tidak query DB di guest pages — guard via auth check.
        View::composer('layouts.app-sidebar', function ($view) {
            $roles = auth()->check()
                ? auth()->user()->getRoleNames()->map(fn($r) => trim(strtolower($r)))->values()->toArray()
                : [];

            $view->with('sidebarMenus', AppMenu::grouped($roles));
        });
    }
}
