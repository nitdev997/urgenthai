<?php

namespace Botble\Commission\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;

class Commission extends BaseModel
{
    protected $table = 'commissions';

    protected $fillable = [
        'driver',
        'vendor',
    ];
}
