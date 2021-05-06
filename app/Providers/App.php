<?php

namespace App\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Str;

class App extends Provider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        if (config('app.installed') && config('app.debug')) {
            $this->app->register(\Barryvdh\Debugbar\ServiceProvider::class);
        }

        if (config('app.env') !== 'production') {
            $this->app->register(\Barryvdh\LaravelIdeHelper\IdeHelperServiceProvider::class);
        }

        if (config('app.log_all_database_queries')) {
            \DB::listen(function (QueryExecuted $query) {
                $sql = $query->sql;
                if (is_iterable($query->bindings)) {
                    $prepared = \DB::prepareBindings($query->bindings);
                    foreach ($prepared as $name => $binding) {
                        if (\is_string($name) && strpos($sql, ':' . $name) !== false) {
                            $sql = Str::replaceFirst(':' . $name, $binding, $sql);
                        } else {
                            $sql = preg_replace("#\?#", is_numeric($binding) ? $binding : "'" . $binding . "'", $sql, 1);
                        }
                    }
                }

                logger(
                    sprintf('[DB `%s` / %s ms] %s', $query->connectionName, $query->time, $sql)
                );
            });
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Laravel db fix
        Schema::defaultStringLength(191);

        Paginator::useBootstrap();
    }
}
