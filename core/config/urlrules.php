<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

return [
    '/' => 'topic/index', 
    'navi/<name:(\w|-)+>' => 'topic/navi',
    'nodes' => 'node/index',
    't/<id:\d+>' => 'topic/view',
    'new' => 'topic/new',
    'search' => 'topic/search',
    'new/<node:(\w|-)+>' => 'topic/add',
    'n/<name:(\w|-)+>' => 'topic/node',
    'tag/<name>' => 'tag/index',
    'member/<id:\d+>' => 'user/view',
    'member/<id:\d+>/topics' => 'user/topics',
    'member/<id:\d+>/comments' => 'user/comments',
    'site/auth-<authclient:(?!(signup|bind-account))\w+>' => 'site/auth',
    'admin' => 'admin/setting/all',
];
