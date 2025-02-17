<?php

namespace Intervention\Image;

use Illuminate\Support\ServiceProvider;

class ImageServiceProviderLaravelRecent extends ServiceProvider
{
    private function cacheIsInstalled(): bool
    {
        return class_exists('Intervention\\Image\\ImageCache');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/config.php' => config_path('image.php'),
        ]);

        if ($this->cacheIsInstalled()) {
            $this->bootstrapImageCache();
        }
    }

    public function register(): void
    {
        $app = $this->app;

        $this->mergeConfigFrom(
            __DIR__ . '/../../config/config.php',
            'image'
        );

        $app->singleton('image', function ($app) {
            return new ImageManager($this->getImageConfig($app));
        });

        $app->alias('image', 'Intervention\Image\ImageManager');
    }

    protected function bootstrapImageCache(): void
    {
        $app = $this->app;
        $config = __DIR__ . '/../../../../imagecache/src/config/config.php';

        $this->publishes([
            $config => config_path('imagecache.php'),
        ]);

        $this->mergeConfigFrom(
            $config,
            'imagecache'
        );

        if (is_string(config('imagecache.route'))) {

            $filename_pattern = '[ \w\\.\\/\\-\\@\(\)\=]+';

            $app['router']->get(config('imagecache.route') . '/{template}/{filename}', [
                'uses' => 'Intervention\Image\ImageCacheController@getResponse',
                'as' => 'imagecache',
            ])->where(['filename' => $filename_pattern]);
        }
    }

    private function getImageConfig($app)
    {
        $config = $app['config']->get('image');

        if (is_null($config)) {
            return [];
        }

        return $config;
    }
}
