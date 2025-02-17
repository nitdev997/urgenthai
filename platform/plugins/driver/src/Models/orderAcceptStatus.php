<?php

namespace Botble\Driver\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Laravel\Sanctum\HasApiTokens;

class orderAcceptStatus extends BaseModel
{
    use HasApiTokens;
    protected $table = 'order_accept_status';

    protected $fillable = [
        'order_id',
        'driver_id',
        'status',
    ];

    protected $hidden = [
        'created_at', 'updated_at'
    ];
}
