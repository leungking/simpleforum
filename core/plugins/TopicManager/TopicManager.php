<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

namespace app\plugins\TopicManager;

use Yii;
use app\components\PluginInterface;
use app\models\Setting;

class TopicManager implements PluginInterface
{
    public static function info()
    {
        return [
            'id' => 'TopicManager',
            'name' => 'Topic Manager',
            'description' => 'Centralized management of topics by nodes and tags.',
            'author' => 'SimpleForum',
            'url' => 'http://simpleforum.org',
            'version' => '1.0',
            'config' => [],
        ];
    }

    public static function install()
    {
        return true;
    }

    public static function uninstall()
    {
        return true;
    }
}
