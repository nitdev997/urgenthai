<?php

namespace Botble\Driver\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;

class Account extends BaseModel
{
    protected $table = 'accounts';

    protected $fillable = [
        'user_id',
        'user_type',
        'account_type',
        'bank_name',
        'account_holder_name',
        'account_number',
        'ifsc',
        'upi_id',
        'default',
        'fund_account_id'
    ];

    protected $hidden = [
        'created_at', 'updated_at'
    ];

    protected $casts = [
        'default' => 'boolean'
    ];
}
