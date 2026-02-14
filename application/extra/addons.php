<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'baidupush' => 
    array (
      0 => 'baidupush',
    ),
    'action_begin' => 
    array (
      0 => 'clicaptcha',
      1 => 'epay',
      2 => 'third',
    ),
    'view_filter' => 
    array (
      0 => 'clicaptcha',
      1 => 'third',
    ),
    'captcha_mode' => 
    array (
      0 => 'clicaptcha',
    ),
    'epay_config_init' => 
    array (
      0 => 'epay',
    ),
    'addon_action_begin' => 
    array (
      0 => 'epay',
    ),
    'user_sidenav_after' => 
    array (
      0 => 'invite',
      1 => 'recharge',
      2 => 'signin',
    ),
    'user_register_successed' => 
    array (
      0 => 'invite',
    ),
    'config_init' => 
    array (
      0 => 'nkeditor',
      1 => 'qcloudsms',
      2 => 'third',
    ),
    'sms_send' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_notice' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_check' => 
    array (
      0 => 'qcloudsms',
    ),
    'user_delete_successed' => 
    array (
      0 => 'third',
    ),
    'user_logout_successed' => 
    array (
      0 => 'third',
    ),
    'module_init' => 
    array (
      0 => 'third',
    ),
  ),
  'route' => 
  array (
    '/invite/[:id]$' => 'invite/index/index',
    '/third$' => 'third/index/index',
    '/third/connect/[:platform]' => 'third/index/connect',
    '/third/callback/[:platform]' => 'third/index/callback',
    '/third/bind/[:platform]' => 'third/index/bind',
    '/third/unbind/[:platform]' => 'third/index/unbind',
  ),
  'priority' => 
  array (
  ),
  'domain' => '',
);
