<?php

return [
    '__pattern__' => [
        'id'      => '\d+',
        'pinyin'  => '[a-zA-Z0-9]+',
        'cate'    => '[a-zA-Z][a-zA-Z0-9-]*',
        'keyword' => '[^/]+',
        'slug'    => '\d+-[a-zA-Z0-9]+',
    ],

    '__alias__' => [],

    '__domain__' => [],

    'login'    => 'index/user/login',
    'register' => 'index/user/register',
    'logout'   => 'index/user/logout',

    'member'                 => 'index/user/index',
    'member/profile'         => 'index/user/profile',
    'member/changepwd'       => 'index/user/changepwd',
    'member/posts'           => 'index/user/posts',
    'member/favorites'       => 'index/user/favorites',

    'signin'                 => 'index/signin/index',

    'publish'                => 'index/info/publish_select',
    'publish/:cate'          => ['index/info/publish', [], ['cate' => '[a-zA-Z][a-zA-Z0-9-]*']],
    'info/publish/:category_id/:sub_category_id' => ['index/info/publish', [], ['category_id' => '\d+', 'sub_category_id' => '\d+']],
    'info/publish/:category_id' => ['index/info/publish', [], ['category_id' => '\d+']],

    'search'                 => 'index/info/lists',
    'search/keyword/:keyword' => 'index/info/lists',

    'info/lists'             => 'index/info/lists',
    'info/detail/id/:id'     => 'index/info/detail',
    'info/edit/id/:id'       => 'index/info/edit',
    'info/:slug'             => ['index/info/detail', [], ['slug' => '\d+-[a-zA-Z0-9]+']],

    ':cate'                  => ['index/info/lists', [], ['cate' => '[a-zA-Z][a-zA-Z0-9-]*']],
];
