<?php

namespace Intervention\Image;

use Illuminate\Foundation\Application as IlluminateApplication;
use Illuminate\Support\ServiceProvider;
use Laravel\Lumen\Application as LumenApplication;

class ImageServiceProvider extends ServiceProvider
{
    protected $defer = false;

    protected $provider;

    public function __construct($app)
    {
        parent::__construct($app);

        $this->provider = $this->getProvider();
    }

    public function boot(): void
    {
        if (method_exists($this->provider, 'boot')) {
            $this->provider->boot();
        }
    }

    public function register(): void
    {
        $this->provider->register();
    }

    private function getProvider()
    {
        if ($this->app instanceof LumenApplication) {
            $provider = '\Intervention\Image\ImageServiceProviderLumen';
        } elseif (version_compare(IlluminateApplication::VERSION, '5.0', '<')) {
            $provider = '\Intervention\Image\ImageServiceProviderLaravel4';
        } else {
            $provider = '\Intervention\Image\ImageServiceProviderLaravelRecent';
        }

        return new $provider($this->app);
    }

    public function provides(): array
    {
        return ['image'];
    }
}
