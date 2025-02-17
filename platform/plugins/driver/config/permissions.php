<?php

return [
    [
        'name' => 'Drivers',
        'flag' => 'driver.index',
    ],
    [
        'name' => 'Create',
        'flag' => 'driver.create',
        'parent_flag' => 'driver.index',
    ],
    [
        'name' => 'Edit',
        'flag' => 'driver.edit',
        'parent_flag' => 'driver.index',
    ],
    [
        'name' => 'Delete',
        'flag' => 'driver.destroy',
        'parent_flag' => 'driver.index',
    ],
];
