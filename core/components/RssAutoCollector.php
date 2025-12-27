<?php
/**
 * RSS自动采集组件
 * 在用户访问时自动触发RSS采集（类似WordPress的wp-cron机制）
 */

namespace app\components;

use Yii;
use yii\base\BootstrapInterface;
use yii\web\Application;

class RssAutoCollector implements BootstrapInterface
{
    /**
     * Bootstrap方法，在应用启动时执行
     */
    public function bootstrap($app)
    {
        // 只在Web应用中执行
        if (!($app instanceof Application)) {
            return;
        }

        // 使用APPLICATION_END事件，在响应发送后执行，不影响页面加载速度
        $app->on(Application::EVENT_AFTER_REQUEST, function() {
            $this->tryAutoCollect();
        });
    }

    /**
     * 尝试执行自动采集
     */
    protected function tryAutoCollect()
    {
        try {
            // 检查插件是否启用
            $plugin = \app\models\admin\Plugin::findOne(['pid' => 'RssCollector']);
            if (!$plugin) {
                return;
            }

            $settings = json_decode($plugin->settings, true);
            if (!$settings || empty($settings['auto_collect_enabled']) || $settings['auto_collect_enabled'] != '1') {
                return;
            }

            // 解析feeds配置
            $feedsConfig = explode("\n", str_replace("\r", "", $settings['feeds_config'] ?? ''));
            if (empty($feedsConfig)) {
                return;
            }

            $cache = Yii::$app->cache;
            $now = time();

            foreach ($feedsConfig as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $parts = explode('|', $line);
                if (count($parts) < 3) continue;

                $url = trim($parts[0]);
                $interval = isset($parts[4]) ? intval(trim($parts[4])) : 60; // 默认60分钟
                
                // 为每个feed创建唯一的缓存key
                $cacheKey = 'rss_auto_collect_' . md5($url);
                $lastCollectTime = $cache->get($cacheKey);

                // 检查是否需要采集
                if ($lastCollectTime === false || ($now - $lastCollectTime) >= ($interval * 60)) {
                    // 采集这个feed
                    $this->collectSingleFeed($parts, $settings);
                    
                    // 更新最后采集时间
                    $cache->set($cacheKey, $now, 86400 * 7); // 缓存7天
                }
            }
        } catch (\Exception $e) {
            // 记录错误但不影响正常运行
            Yii::error('RSS Auto Collector Error: ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * 采集单个feed
     */
    protected function collectSingleFeed($parts, $appSettings)
    {
        $url = trim($parts[0]);
        $nodeId = trim($parts[1]);
        $userId = trim($parts[2]);
        $feedKeywords = isset($parts[3]) ? trim($parts[3]) : '';
        
        $keywords = !empty($feedKeywords) ? explode(',', $feedKeywords) : [];
        $keywords = array_map('trim', $keywords);
        $keywords = array_filter($keywords);

        try {
            // 设置超时时间
            set_time_limit(30);

            $opts = [
                "http" => [
                    "method" => "GET",
                    "timeout" => 15,
                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n" .
                                "Accept: text/xml,application/xml,application/rss+xml,*/*\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            set_error_handler(function() {});
            $content = @file_get_contents($url, false, $context);
            restore_error_handler();
            
            if (!$content) {
                return;
            }

            // 处理Gzip
            $isGzipped = (bin2hex(substr($content, 0, 2)) === '1f8b');
            if ($isGzipped && function_exists('gzdecode')) {
                $content = gzdecode($content);
            }

            // 移除BOM
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string(trim($content), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$xml) {
                return;
            }

            $items = [];
            
            // 解析RSS 2.0
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    $contentEncoded = '';
                    $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
                    if (isset($contentNs->encoded)) {
                        $contentEncoded = (string)$contentNs->encoded;
                    }
                    
                    $description = !empty($contentEncoded) ? $contentEncoded : (string)$item->description;
                    
                    // 提取发布日期
                    $pubDate = null;
                    if (isset($item->pubDate)) {
                        $pubDate = strtotime((string)$item->pubDate);
                    } elseif (isset($item->date)) {
                        $pubDate = strtotime((string)$item->date);
                    }

                    $items[] = [
                        'title' => (string)$item->title,
                        'link' => (string)$item->link,
                        'description' => $description,
                        'pubDate' => $pubDate,
                    ];
                }
            } 
            // 解析Atom
            elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    $link = '';
                    if (isset($entry->link['href'])) {
                        $link = (string)$entry->link['href'];
                    } else if (isset($entry->link)) {
                        $link = (string)$entry->link;
                    }

                    $description = (string)$entry->content ?: (string)$entry->summary;
                    
                    // 提取发布日期
                    $pubDate = null;
                    if (isset($entry->published)) {
                        $pubDate = strtotime((string)$entry->published);
                    } elseif (isset($entry->updated)) {
                        $pubDate = strtotime((string)$entry->updated);
                    }

                    $items[] = [
                        'title' => (string)$entry->title,
                        'link' => $link,
                        'description' => $description,
                        'pubDate' => $pubDate,
                    ];
                }
            }

            if (empty($items)) {
                return;
            }

            // 限制每次自动采集的数量，避免一次性采集太多
            $items = array_slice($items, 0, 5);

            $editor = isset($appSettings['editor']) ? $appSettings['editor'] : (Yii::$app->params['settings']['editor'] ?? '');
            $isMarkdown = ($editor == 'SmdEditor' || $editor == 'Vditor');

            foreach ($items as $item) {
                $title = trim($item['title']);
                $description = trim($item['description']);
                if (empty($title)) continue;

                // 关键词过滤
                if (!empty($keywords)) {
                    $found = false;
                    foreach ($keywords as $kw) {
                        if (mb_stripos($title, $kw) !== false || mb_stripos($description, $kw) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) continue;
                }

                // 检查是否已存在
                $exists = \app\models\Topic::find()->where(['title' => $title, 'node_id' => $nodeId])->exists();
                if ($exists) {
                    continue;
                }

                // 创建帖子
                $topic = new \app\models\Topic([
                    'scenario' => \app\models\Topic::SCENARIO_ADD,
                    'node_id' => $nodeId,
                    'user_id' => $userId,
                    'title' => $title,
                ]);
                
                // 使用原始发布日期
                if (!empty($item['pubDate'])) {
                    $topic->created_at = $item['pubDate'];
                    $topic->updated_at = $item['pubDate'];
                }
                
                // 提取标签
                $topic->tags = $this->extractKeywords($title, $description);

                // 转换为Markdown
                $finalContent = $description;
                if ($isMarkdown) {
                    $finalContent = $this->htmlToMarkdown($finalContent);
                }

                $topicContent = new \app\models\TopicContent([
                    'content' => $finalContent,
                ]);

                if ($topic->validate() && $topicContent->validate()) {
                    $topic->save(false);
                    $topicContent->link('topic', $topic);
                }
            }

        } catch (\Exception $e) {
            Yii::error('RSS Feed Collection Error for ' . $url . ': ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * 提取关键词
     */
    protected function extractKeywords($title, $content)
    {
        $text = $title . ' ' . strip_tags($content);
        preg_match_all('/[a-zA-Z]{3,}/', $text, $enMatches);
        $enWords = array_map('strtolower', $enMatches[0]);
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $text, $zhMatches);
        $zhWords = $zhMatches[0];
        $words = array_merge($enWords, $zhWords);
        
        $counts = [];
        $zhStopWords = ['这个', '那个', '什么', '如何', '可以', '进行', '已经', '通过', '目前', '其中', '同时', '以及', '因为', '所以', '但是', '如果', '虽然', '不仅', '而且', '就是', '这样', '这些', '那些', '一些', '非常'];
        $enStopWords = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'they', 'have', 'been', 'were', 'will', 'would', 'their', 'there', 'about', 'which'];
        $stopWords = array_merge($zhStopWords, $enStopWords);
        
        foreach ($words as $word) {
            if (in_array($word, $stopWords)) continue;
            if (!isset($counts[$word])) {
                $counts[$word] = 0;
            }
            $counts[$word]++;
        }
        
        arsort($counts);
        $topWords = array_slice(array_keys($counts), 0, 3);
        return implode(',', $topWords);
    }
    
    /**
     * HTML转Markdown
     */
    protected function htmlToMarkdown($html)
    {
        $html = preg_replace('/<div class="editor">[\s\S]*?<\/div>/i', '', $html);
        $html = preg_replace('/\sstyle="[^"]*"/i', '', $html);
        $html = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($m){
            return '![](' . $m[1] . ')';
        }, $html);
        $html = preg_replace('/<\/?p[^>]*>/i', "\n\n", $html);
        $text = strip_tags($html);
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}
