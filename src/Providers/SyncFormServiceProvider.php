<?php

namespace YouMixx\SleepingowlSyncForm\Providers;

use Illuminate\Support\ServiceProvider;
use SleepingOwl\Admin\Contracts\ModelConfigurationInterface;
use SleepingOwl\Admin\Model\ModelConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use SleepingOwl\Admin\Admin;
use SleepingOwl\Admin\Providers\AdminServiceProvider;
use SleepingOwl\Admin\Routing\ModelRouter;

class SyncFormServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // $this->loadRoutesFrom(__DIR__ . '/../../routes/sync.php');

        if (file_exists($file = __DIR__.'/../../routes/sync.php')) {
            $this->registerRoutes(function (Router $route) use ($file) {
                require $file;
            });
        }
    }

    /**
     * @param  \Closure  $callback
     */
    protected function registerRoutes(\Closure $callback)
    {
        $domain = config('sleeping_owl.domain', false);
        $configGroup = collect([
            'prefix' => $this->getConfig('url_prefix'),
            // 'middleware' => $this->getConfig('middleware'),
        ]);

        if ($domain) {
            $configGroup->put('domain', $domain);
        }

        $this->app['router']->group($configGroup->toArray(), function (Router $route) use ($callback) {
            call_user_func($callback, $route);
        });
    }

    /**
     * @param  string  $key
     * @return mixed
     */
    protected function getConfig($key)
    {
        return $this->app['config']->get('sleeping_owl.'.$key);
    }
}