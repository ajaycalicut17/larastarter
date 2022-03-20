<?php

namespace Ajaycalicut17\Larastarter\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larastarter:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install laravel starter';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // publish
        $this->callSilent('vendor:publish', ['--provider' => 'Laravel\Fortify\FortifyServiceProvider', '--force' => true]);

        // fortify provider
        $this->replaceInFile('App\Providers\RouteServiceProvider::class,', '        App\Providers\FortifyServiceProvider::class,', config_path('app.php'));

        // call method from the boot method
        $search = "public function boot()" . PHP_EOL . "    {";
        $replace = "        Fortify::loginView(function () {" . PHP_EOL . "            return view('admin.auth.login');" . PHP_EOL . "        });" .
            PHP_EOL . "        Fortify::twoFactorChallengeView(function () {" . PHP_EOL . "            return view('admin.auth.two-factor-challenge');" . PHP_EOL . "        });" .
            PHP_EOL . "        Fortify::registerView(function () {" . PHP_EOL . "            return view('admin.auth.register');" . PHP_EOL . "        });" .
            PHP_EOL . "        Fortify::requestPasswordResetLinkView(function () {" . PHP_EOL . "            return view('admin.auth.forgot-password');" . PHP_EOL . "        });" .
            PHP_EOL . "        Fortify::resetPasswordView(function (" . '$request' . ") {" . PHP_EOL . "            return view('admin.auth.reset-password', ['request' => " . '$request' . "]);" . PHP_EOL . "        });" .
            PHP_EOL . "        Fortify::verifyEmailView(function () {" . PHP_EOL . "            return view('admin.auth.verify-email');" . PHP_EOL . "        });" .
            PHP_EOL . "        Fortify::confirmPasswordView(function () {" . PHP_EOL . "            return view('admin.auth.confirm-password');" . PHP_EOL . "        });";
        $this->replaceInFile($search, $replace, app_path('Providers/FortifyServiceProvider.php'));

        // resources files
        (new Filesystem)->ensureDirectoryExists(base_path('resources/css'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/resources/css', base_path('resources/css'));
        (new Filesystem)->ensureDirectoryExists(base_path('resources/img'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/resources/img', base_path('resources/img'));
        (new Filesystem)->ensureDirectoryExists(base_path('resources/js'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/resources/js', base_path('resources/js'));
        (new Filesystem)->ensureDirectoryExists(base_path('resources/views/admin'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/resources/views/admin', base_path('resources/views/admin'));
        (new Filesystem)->ensureDirectoryExists(base_path('resources/views/components'));
        (new Filesystem)->copyDirectory(__DIR__ . '/../../stubs/resources/views/components', base_path('resources/views/components'));

        // npm packages
        $this->updateNodePackages(function ($packages) {
            return [
                'tailwindcss' => '^3.0.23',
                '@tailwindcss/forms' => '^0.5.0',
                'postcss' => '^8.4.7',
                'autoprefixer' => '^10.4.2',
                'alpinejs' => '^3.9.0',
            ] + $packages;
        });

        // tailwind configuration
        copy(__DIR__ . '/../../stubs/tailwind.config.js', base_path('tailwind.config.js'));
        copy(__DIR__.'/../../stubs/webpack.mix.js', base_path('webpack.mix.js'));

        $search = "public function run()" . PHP_EOL . "    {";
        $replace = '        $this->call([' . PHP_EOL . '            UserSeeder::class' . PHP_EOL . '        ]);' . PHP_EOL;
        $this->replaceInFile($search, $replace, base_path('database/seeders/DatabaseSeeder.php'));

        copy(__DIR__ . '/../../stubs/database/seeders/UserSeeder.php', base_path('database/seeders/UserSeeder.php'));
    }

    protected function replaceInFile(string $search = '', string $replace = '', string $path = ''): void
    {
        if (file_exists($path)) {
            $fileContents = file_get_contents($path);
            if (!str_contains($fileContents, $replace)) {
                file_put_contents($path, str_replace($search, ($search . PHP_EOL . $replace), $fileContents));
            }
        }
    }

    protected static function updateNodePackages(callable $callback, bool $dev = true): void
    {
        if (!file_exists(base_path('package.json'))) {
            return;
        }

        $configurationKey = $dev ? 'devDependencies' : 'dependencies';

        $packages = json_decode(file_get_contents(base_path('package.json')), true);

        $packages[$configurationKey] = $callback(
            array_key_exists($configurationKey, $packages) ? $packages[$configurationKey] : [],
            $configurationKey
        );

        ksort($packages[$configurationKey]);

        file_put_contents(
            base_path('package.json'),
            json_encode($packages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL
        );
    }
}
