<?php

namespace Botble\Commission;

use Illuminate\Support\Facades\Schema;
use Botble\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('Commissions');
        Schema::dropIfExists('Commissions_translations');
    }
}
