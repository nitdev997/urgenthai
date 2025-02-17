<?php

namespace Botble\Driver;

use Illuminate\Support\Facades\Schema;
use Botble\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('Drivers');
        Schema::dropIfExists('Drivers_translations');
    }
}
