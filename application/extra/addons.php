<?php

return [
    'autoload' => false,
    'hooks' => [
        'baidupush' => [
            'baidupush',
        ],
        'action_begin' => [
            'clicaptcha',
            'epay',
            'third',
        ],
        'view_filter' => [
            'clicaptcha',
            'third',
        ],
        'captcha_mode' => [
            'clicaptcha',
        ],
        'epay_config_init' => [
            'epay',
        ],
        'addon_action_begin' => [
            'epay',
        ],
        'user_sidenav_after' => [
            'invite',
            'recharge',
            'signin',
        ],
        'user_register_successed' => [
            'invite',
        ],
        'config_init' => [
            'nkeditor',
            'qcloudsms',
            'third',
        ],
        'sms_send' => [
            'qcloudsms',
        ],
        'sms_notice' => [
            'qcloudsms',
        ],
        'sms_check' => [
            'qcloudsms',
        ],
        'user_delete_successed' => [
            'third',
        ],
        'user_logout_successed' => [
            'third',
        ],
        'module_init' => [
            'third',
        ],
    ],
    'route' => [
        '/invite/[:id]$' => 'invite/index/index',
        '/third$' => 'third/index/index',
        '/third/connect/[:platform]' => 'third/index/connect',
        '/third/callback/[:platform]' => 'third/index/callback',
        '/third/bind/[:platform]' => 'third/index/bind',
        '/third/unbind/[:platform]' => 'third/index/unbind',
    ],
    'priority' => [],
    'domain' => '',
];
