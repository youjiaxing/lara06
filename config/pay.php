<?php

return [
    'alipay' => [
        'app_id'         => env('PAY_ALIPAY_APP_ID'),
        'ali_public_key' => env('PAY_ALIPAY_PUBLIC_KEY'),
        'private_key'    => env('PAY_ALIPAY_PRIVATE_KEY'),
        'log'            => [
            'file' => storage_path('logs/alipay.log'),
        ],
    ],

    'wechat' => [
        'app_id'      => '',
        'mch_id'      => '',
        'key'         => '',
        'cert_client' => '',
        'cert_key'    => '',
        'log'         => [
            'file' => storage_path('logs/wechat_pay.log'),
        ],
    ],
];
