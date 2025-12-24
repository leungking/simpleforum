<?php
namespace app\controllers\admin;

use Yii;
use app\models\admin\Plugin;
use app\models\Topic;
use app\models\TopicContent;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

class RssCollectorController extends CommonController
{
    public function actionIndex()
    {
        $plugin = Plugin::findOne(['pid' => 'RssCollector']);
        if (!$plugin) {
            Yii::$app->getSession()->setFlash('error', 'Plugin RssCollector is not installed.');
            return $this->redirect(['admin/plugin/index']);
        }

        $settings = json_decode($plugin->settings, true);
        $feeds = explode("\n", str_replace("\r", "", $settings['feeds_config']));
        
        return $this->render('@app/plugins/RssCollector/views/index', [
            'settings' => $settings,
            'feeds' => $feeds,
        ]);
    }

    public function actionCollect()
    {
        $plugin = Plugin::findOne(['pid' => 'RssCollector']);
        if (!$plugin) {
            return $this->asJson(['status' => 'error', 'message' => 'Plugin not installed.']);
        }

        $settings = json_decode($plugin->settings, true);
        $feedsConfig = explode("\n", str_replace("\r", "", $settings['feeds_config']));
        
        $results = [];
        $totalCount = 0;
        $allItems = [];

        foreach ($feedsConfig as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode('|', $line);
            if (count($parts) < 3) {
                $results[] = "Invalid config line: $line";
                continue;
            }

            $url = trim($parts[0]);
            $nodeId = trim($parts[1]);
            $userId = trim($parts[2]);
            $feedKeywords = isset($parts[3]) ? trim($parts[3]) : '';
            
            $keywords = !empty($feedKeywords) ? explode(',', $feedKeywords) : [];
            $keywords = array_map('trim', $keywords);
            $keywords = array_filter($keywords);

            try {
                $opts = [
                    "http" => [
                        "method" => "GET",
                        "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36\r\n" .
                                    "Accept: text/xml,application/xml,application/rss+xml,application/atom+xml,*/*\r\n" .
                                    "Accept-Encoding: gzip, deflate\r\n"
                    ]
                ];
                $context = stream_context_create($opts);
                $content = @file_get_contents($url, false, $context);
                
                if (!$content) {
                    throw new \Exception("Failed to fetch content from $url (Content is empty)");
                }

                // Handle Gzip decompression
                $isGzipped = (bin2hex(substr($content, 0, 2)) === '1f8b');
                if ($isGzipped) {
                    if (function_exists('gzdecode')) {
                        $content = gzdecode($content);
                    } else {
                        throw new \Exception("Content is gzipped but gzdecode function is not available.");
                    }
                }

                // Remove BOM if exists
                $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

                // Handle potential encoding issues
                if (!mb_check_encoding($content, 'UTF-8')) {
                    $content = mb_convert_encoding($content, 'UTF-8', 'auto');
                }

                libxml_use_internal_errors(true);
                $xml = simplexml_load_string(trim($content), 'SimpleXMLElement', LIBXML_NOCDATA);
                if (!$xml) {
                    $errors = libxml_get_errors();
                    $errMsgs = [];
                    foreach ($errors as $error) {
                        $errMsgs[] = trim($error->message);
                    }
                    libxml_clear_errors();
                    
                    // Debug: show first 50 chars of content if parse fails
                    $preview = Html::encode(substr(trim($content), 0, 50));
                    throw new \Exception("XML Parse Error for $url: " . implode("; ", $errMsgs) . ". Content start: [$preview]");
                }

                $items = [];
                if (isset($xml->channel->item)) { // RSS 2.0
                    foreach ($xml->channel->item as $item) {
                        $description = (string)$item->description;
                        $img = '';
                        // Try to extract image from description HTML
                        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $imgMatch)) {
                            $img = $imgMatch[1];
                        }
                        // Check enclosure if no image found in description
                        if (!$img && isset($item->enclosure) && strpos((string)$item->enclosure['type'], 'image') !== false) {
                            $img = (string)$item->enclosure['url'];
                        }
                        // Check media:content
                        $media = $item->children('http://search.yahoo.com/mrss/');
                        if (!$img && isset($media->content)) {
                            $img = (string)$media->content->attributes()->url;
                        }

                        $items[] = [
                            'title' => (string)$item->title,
                            'link' => (string)$item->link,
                            'description' => $description,
                            'image' => $img,
                        ];
                    }
                } elseif (isset($xml->entry)) { // Atom
                    foreach ($xml->entry as $entry) {
                        $link = '';
                        if (isset($entry->link['href'])) {
                            $link = (string)$entry->link['href'];
                        } else if (isset($entry->link)) {
                            $link = (string)$entry->link;
                        }

                        $description = (string)$entry->content ?: (string)$entry->summary;
                        $img = '';
                        // Try to extract image from description HTML
                        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $description, $imgMatch)) {
                            $img = $imgMatch[1];
                        }

                        $media = $entry->children('http://search.yahoo.com/mrss/');
                        if (!$img && isset($media->content)) {
                            $img = (string)$media->content->attributes()->url;
                        } else if (!$img && isset($media->thumbnail)) {
                            $img = (string)$media->thumbnail->attributes()->url;
                        }

                        $items[] = [
                            'title' => (string)$entry->title,
                            'link' => $link,
                            'description' => $description,
                            'image' => $img,
                        ];
                    }
                }

                if (empty($items)) {
                    $results[] = "No items found in $url";
                    continue;
                }

                $feedCount = 0;
                foreach ($items as $item) {
                    $title = trim($item['title']);
                    $description = trim($item['description']);
                    if (empty($title)) continue;

                    // Keywords Filter
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

                    $exists = Topic::find()->where(['title' => $title, 'node_id' => $nodeId])->exists();
                    if ($exists) {
                        continue;
                    }

                    $cleanedDescription = $this->cleanDescription($description);
                    if (empty($cleanedDescription)) {
                        continue;
                    }

                    $allItems[] = [
                        'node_id' => $nodeId,
                        'user_id' => $userId,
                        'title' => $title,
                        'description' => $cleanedDescription,
                        'image' => $item['image'],
                    ];
                    $feedCount++;
                }
                $results[] = "Found $feedCount new items from $url";

            } catch (\Exception $e) {
                $results[] = "Error for $url: " . $e->getMessage();
            }
        }

        if (!empty($allItems)) {
            shuffle($allItems);
            foreach ($allItems as $item) {
                $topic = new Topic([
                    'scenario' => Topic::SCENARIO_ADD,
                    'node_id' => $item['node_id'],
                    'user_id' => $item['user_id'],
                    'title' => $item['title'],
                ]);
                
                // Auto extract keywords as tags
                $topic->tags = $this->extractKeywords($item['title'], $item['description']);

                $finalContent = $item['description'];
                if (!empty($item['image'])) {
                    $finalContent .= "\n" . '[img]' . $item['image'] . '[/img]';
                }

                $topicContent = new TopicContent([
                    'content' => $finalContent,
                ]);

                if ($topic->validate() && $topicContent->validate()) {
                    $topic->save(false);
                    $topicContent->link('topic', $topic);
                    $totalCount++;
                }
            }
        }

        return $this->asJson([
            'status' => $totalCount > 0 ? 'success' : 'info',
            'message' => implode("<br>", $results) . "<br><strong>Total published: $totalCount</strong>",
            'total' => $totalCount
        ]);
    }

    private function cleanDescription($text)
    {
        $text = trim(strip_tags($text));
        // Match everything up to the last sentence-ending punctuation that is NOT part of an ellipsis
        // Supports English (.!?) and Chinese (。！？)
        if (preg_match('/^.*(?<!\.)[.!?。！？](?!\.)/us', $text, $match)) {
            return trim($match[0]);
        }
        // If no complete sentence is found, return empty string to trigger skip
        return '';
    }

    private function extractKeywords($title, $content)
    {
        $text = $title . ' ' . strip_tags($content);
        
        // Extract English words (3+ chars)
        preg_match_all('/[a-zA-Z]{3,}/', $text, $enMatches);
        $enWords = array_map('strtolower', $enMatches[0]);
        
        // Extract Chinese "words" (2-4 characters)
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $text, $zhMatches);
        $zhWords = $zhMatches[0];
        
        $words = array_merge($enWords, $zhWords);
        $counts = [];
        
        // Common Chinese stop words
        $zhStopWords = ['这个', '那个', '什么', '如何', '可以', '进行', '已经', '通过', '目前', '其中', '同时', '以及', '因为', '所以', '但是', '如果', '虽然', '不仅', '而且', '就是', '这样', '这些', '那些', '一些', '非常', '特别', '相当', '比较', '更加', '可能', '应该', '必须', '需要', '能够', '为了', '关于', '对于', '由于', '因此', '从而', '或者', '还是', '甚至', '甚至于', '并且', '而且'];
        
        // Common English stop words
        $enStopWords = ['the', 'and', 'for', 'with', 'from', 'that', 'this', 'they', 'have', 'been', 'were', 'will', 'would', 'their', 'there', 'about', 'which', 'when', 'where', 'who', 'how', 'all', 'any', 'can', 'not', 'but', 'our', 'your', 'his', 'her', 'its', 'into', 'onto', 'than', 'then', 'also', 'some', 'such', 'only', 'more', 'most', 'very', 'just', 'than', 'them', 'these', 'those'];

        $stopWords = array_merge($zhStopWords, $enStopWords);
        
        foreach ($words as $word) {
            if (in_array($word, $stopWords)) continue;
            if (!isset($counts[$word])) {
                $counts[$word] = 0;
            }
            $counts[$word]++;
        }
        
        arsort($counts);
        $topWords = array_slice(array_keys($counts), 0, 5);
        return implode(',', $topWords);
    }
}
