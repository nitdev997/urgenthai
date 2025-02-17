<?php

namespace Botble\Driver\Models;

use Botble\Base\Casts\SafeContent;
use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Models\BaseModel;
use Laravel\Sanctum\HasApiTokens;

class Driver extends BaseModel
{
    use HasApiTokens;
    protected $table = 'drivers';

    protected $fillable = [
        'name',
        'status',
        'fullname',
        'email',
        'password',
        'avatar',
        'dob',
        'phone',
        'address',
        'account_status',
        'otp',
        'otp_expires_at',
        'dl_number',
        'dl_image',
        'country',
        'number_plate_no',
        'number_plate_image',
        'admin_verify_at',
        'document_verification_status',
        'device_token',
        'device_type',
        'player_id',
        'rating'
    ];

    protected $hidden = [
        'password', 'otp', 'created_at', 'updated_at'
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'name' => SafeContent::class,
        'otp_expires_at' => 'datetime',
        'admin_verify_at' => 'datetime',
    ];
}
