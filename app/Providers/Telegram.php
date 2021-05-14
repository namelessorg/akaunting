<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Command;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider as Provider;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;
use Telegram\Bot\Api;
use Telegram\Bot\Commands\CommandInterface;

class Telegram extends Provider implements DeferrableProvider
{
    public function provides()
    {
        return [
            Api::class,
        ];
    }

    public function register()
    {
        $this->app->singleton(Api::class, function() {
            $api = new Api('empty');
            $api->addCommands($this->loadTelegramCommands(app_path('Console/Telegram')));

            return $api;
        });
    }


    /**
     * Register all of the telegram commands in the given directory.
     *
     * @param  array|string  $paths
     * @return array
     */
    protected function loadTelegramCommands($paths): array
    {
        $paths = array_unique(Arr::wrap($paths));

        $paths = array_filter($paths, function ($path) {
            return is_dir($path);
        });

        if (empty($paths)) {
            return [];
        }

        $namespace = $this->app->getNamespace();
        $commands = [];
        foreach ((new Finder)->in($paths)->files() as $command) {
            $command = $namespace.str_replace(
                    ['/', '.php'],
                    ['\\', ''],
                    Str::after($command->getRealPath(), realpath(app_path()).DIRECTORY_SEPARATOR)
                );

            if (is_subclass_of($command, CommandInterface::class) &&
                ! (new \ReflectionClass($command))->isAbstract()) {
                $commands[] = $command;
            }
        }

        return $commands;
    }
}
