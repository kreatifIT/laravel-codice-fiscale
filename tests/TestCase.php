<?php

namespace Kreatif\CodiceFiscale\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as Orchestra;
use Kreatif\CodiceFiscale\CodiceFiscaleServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Kreatif\\CodiceFiscale\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            CodiceFiscaleServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Livewire\LivewireServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
