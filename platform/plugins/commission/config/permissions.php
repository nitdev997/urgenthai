<?php

return [
    [
        'name' => 'Commissions',
        'flag' => 'commission.index',
    ],
    [
        'name' => 'Create',
        'flag' => 'commission.create',
        'parent_flag' => 'commission.index',
    ],
    [
        'name' => 'Edit',
        'flag' => 'commission.edit',
        'parent_flag' => 'commission.index',
    ],
    [
        'name' => 'Delete',
        'flag' => 'commission.destroy',
        'parent_flag' => 'commission.index',
    ],
];
