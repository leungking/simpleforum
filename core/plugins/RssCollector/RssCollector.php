<?php
namespace app\plugins\RssCollector;

use Yii;
use app\components\PluginInterface;
use yii\helpers\Url;

class RssCollector implements PluginInterface
{
    public static function info()
    {
        $collectUrl = Url::to(['admin/rss-collector/index']);
        return [
            'id' => 'RssCollector',
            'name' => 'RSS/ATOM Collector',
            'description' => 'Collect content from RSS/ATOM feeds and publish to forum. <br><a href="' . $collectUrl . '" class="btn btn-xs btn-primary">Go to Collector</a>',
            'author' => 'GitHub Copilot',
            'url' => 'https://github.com/copilot',
            'version' => '1.0',
            'config' => [
                [
                    'label'=>'RSS Feeds Configuration',
                    'key'=>'feeds_config',
                    'type'=>'textarea',
                    'value'=>"https://news.un.org/feed/subscribe/zh/news/topic/climate-change/feed/rss.xml|1|1|气候,变暖",
                    'description'=>'One feed per line. Format: URL|NodeID|UserID|Keywords(optional, comma separated)',
                ],
            ],
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
