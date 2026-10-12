<?php

namespace Usamamuneerchaudhary\CommandPalette\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\Facades\Filament;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\DataStore;
use Orchestra\Testbench\TestCase as Orchestra;
use Usamamuneerchaudhary\CommandPalette\CommandPaletteServiceProvider;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\PaletteUser;
use Usamamuneerchaudhary\CommandPalette\Tests\Fixtures\TestPanelProvider;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['view']->share('errors', new ViewErrorBag);

        $store = $this->app->make(DataStore::class);
        $this->app->singleton(DataStore::class, fn () => $store);
        $this->app->instance(DataStore::class, $store);

        Schema::dropIfExists('users');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
        });
    }

    protected function actingAsPaletteUser(?PaletteUser $user = null): PaletteUser
    {
        $user ??= PaletteUser::query()->create([
            'name' => 'Tester',
            'email' => 'tester@example.com',
        ]);

        $this->actingAs($user);

        try {
            Filament::setCurrentPanel(Filament::getPanel('admin'));
        } catch (\Throwable) {
            // Panel may not be registered in a narrow unit test.
        }

        return $user;
    }

    protected function getPackageProviders($app): array
    {
        $providers = [
            LivewireServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            NotificationsServiceProvider::class,
            SupportServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            BladeIconsServiceProvider::class,
            FilamentServiceProvider::class,
            CommandPaletteServiceProvider::class,
            TestPanelProvider::class,
        ];

        if (class_exists(SchemasServiceProvider::class)) {
            array_splice($providers, 2, 0, [SchemasServiceProvider::class]);
        }

        if (class_exists(InfolistsServiceProvider::class)) {
            array_splice($providers, 3, 0, [InfolistsServiceProvider::class]);
        }

        return $providers;
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('command-palette.include_publish_views_command', false);
        $app['config']->set('command-palette.cache.ttl', 0);
    }
}
