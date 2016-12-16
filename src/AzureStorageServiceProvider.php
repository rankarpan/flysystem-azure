<?php

namespace Laravel\Flysystem\Azure;

use Storage;
use League\Flysystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Azure\AzureAdapter;
use MicrosoftAzure\Storage\Common\ServicesBuilder;
use App\Iconscout\Azure\AzureSignedUrl;

class AzureStorageServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        Storage::extend('azure', function($app, $config) {
            $endpoint = sprintf(
                'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s',
                $config['name'],
                $config['key']
            );
            $blobRestProxy = ServicesBuilder::getInstance()->createBlobService($endpoint);
            $filesystem = new Filesystem(new AzureAdapter($blobRestProxy, $config, null));
            return $filesystem->addPlugin(new AzureSignedUrl);
        });
    }
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}