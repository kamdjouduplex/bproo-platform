<?php

namespace App\Providers;

use App\Services\TenantManager;
use App\Support\InovComPackageProviders;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });

        // Garantit que les routes des modules path sont disponibles pour le menu
        // dès que le code est présent (activation admin + permissions).
        InovComPackageProviders::register($this->app);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->environment('local') && ! $this->app->runningInConsole()) {
            // Livewire signed upload URLs must match the browser host/port (127.0.0.1:8000 vs localhost).
            $rootUrl = request()->getSchemeAndHttpHost();
            if (is_string($rootUrl) && $rootUrl !== '') {
                URL::forceRootUrl($rootUrl);
            }
        }

        if (
            $this->app->environment('production')
            || filter_var(env('FORCE_HTTPS', false), FILTER_VALIDATE_BOOL)
        ) {
            $rootUrl = config('app.url');
            if (is_string($rootUrl) && $rootUrl !== '') {
                URL::forceRootUrl($rootUrl);
            }
            URL::forceScheme('https');
        }

        Blade::directive('money', function (string $expression) {
            return "<?php echo e(fmt_money($expression)); ?>";
        });

        Blade::directive('num', function (string $expression) {
            return "<?php echo e(fmt_num($expression)); ?>";
        });

        $uploadMaxKb = max(1, (int) floor(min(
            self::parseIniSize((string) ini_get('upload_max_filesize')),
            self::parseIniSize((string) ini_get('post_max_size'))
        ) / 1024));

        config([
            'livewire.temporary_file_upload.disk' => 'local',
            'livewire.temporary_file_upload.rules' => ['required', 'file', 'image', "max:{$uploadMaxKb}"],
        ]);
    }

    private static function parseIniSize(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
