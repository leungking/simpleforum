<?php
/**
 * RSS采集器控制器
 * Based on SimpleForum (https://github.com/SimpleForum/SimpleForum)
 * Modified for https://610000.xyz/
 */

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

            // 增加执行时间限制，避免超时
            set_time_limit(60);

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
                set_error_handler(function() {});
                $fp = fopen($url, 'r', false, $context);
                if ($fp) {
                    stream_set_timeout($fp, 15);
                    $content = stream_get_contents($fp);
                    fclose($fp);
                } else {
                    $content = false;
                }
                restore_error_handler();
                
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
                    
                    $preview = Html::encode(substr(trim($content), 0, 50));
                    throw new \Exception("XML Parse Error for $url: " . implode("; ", $errMsgs) . ". Content start: [$preview]");
                }

                $items = [];
                if (isset($xml->channel->item)) { // RSS 2.0
                    foreach ($xml->channel->item as $item) {
                        // Try to get full content from content:encoded
                        $contentEncoded = '';
                        $contentNs = $item->children('http://purl.org/rss/1.0/modules/content/');
                        if (isset($contentNs->encoded)) {
                            $contentEncoded = (string)$contentNs->encoded;
                        }
                        
                        $description = !empty($contentEncoded) ? $contentEncoded : (string)$item->description;
                        $media = $this->extractMedia($description, $item);

                        // Check for enclosure
                        if (isset($item->enclosure)) {
                            $attributes = $item->enclosure->attributes();
                            $url = (string)$attributes->url;
                            $type = (string)$attributes->type;
                            if (strpos($type, 'image') !== false) {
                                $media[] = ['type' => 'image', 'url' => $url];
                            }
                        }

                        // Dedupe media (HTML + enclosure)
                        if (!empty($media)) {
                            $media = $this->dedupeMedia($media);
                        }

                        // Extract publish date
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
                            'media' => $media,
                            'pubDate' => $pubDate,
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
                        $media = $this->extractMedia($description, $entry);
                        if (!empty($media)) {
                            $media = $this->dedupeMedia($media);
                        }

                        // Extract publish date for Atom
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
                            'media' => $media,
                            'pubDate' => $pubDate,
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
                        'description' => $description, // Use full description/content
                        'media' => $item['media'],
                        'pubDate' => isset($item['pubDate']) ? $item['pubDate'] : null,
                    ];
                    $feedCount++;
                }
                $results[] = "Found $feedCount new items from $url";

            } catch (\Exception $e) {
                $errorMsg = "Error for $url: " . $e->getMessage();
                $results[] = $errorMsg;
                Yii::error($errorMsg . '\nLine: ' . $e->getLine() . '\nFile: ' . $e->getFile() . '\nTrace: ' . $e->getTraceAsString(), __METHOD__);
            }
        }

        if (!empty($allItems)) {
            $appSettings = Yii::$app->params['settings'];
            $editor = $appSettings['editor'];
            $isMarkdown = ($editor == 'SmdEditor' || $editor == 'Vditor');

            // Preserve feed order and assign sequential fallback timestamps for items without dates
            $fallbackBase = time();
            $fallbackOffset = 0;
            foreach ($allItems as &$it) {
                if (empty($it['pubDate'])) {
                    $it['pubDate'] = $fallbackBase + $fallbackOffset;
                    $fallbackOffset++;
                }
            }
            unset($it);

            foreach ($allItems as $item) {
                $topic = new Topic([
                    'scenario' => Topic::SCENARIO_ADD,
                    'node_id' => $item['node_id'],
                    'user_id' => $item['user_id'],
                    'title' => $item['title'],
                ]);
                
                // Use original or fallback publish date
                if (!empty($item['pubDate'])) {
                    $topic->created_at = $item['pubDate'];
                    $topic->updated_at = $item['pubDate'];
                }
                
                $topic->tags = $this->extractKeywords($item['title'], $item['description']);

                $finalContent = $item['description'];
                
                // Extract existing images from description to avoid duplicates
                $existingImages = [];
                if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $finalContent, $imgMatches)) {
                    $existingImages = $imgMatches[1];
                }
                
                if (!empty($item['media'])) {
                    $imageAdded = false;
                    $videoAdded = false;
                    foreach ($item['media'] as $media) {
                        if ($media['type'] == 'image' && !$imageAdded) {
                            // Only add if not already in content
                            if (!in_array($media['url'], $existingImages)) {
                                $imageAdded = true;
                                if ($isMarkdown) {
                                    $finalContent .= "\n\n![](" . $media['url'] . ")";
                                } else {
                                    $finalContent .= "\n\n[img]" . $media['url'] . "[/img]";
                                }
                            }
                        } else if ($media['type'] == 'video' && !$videoAdded) {
                            $videoAdded = true;
                            $finalContent .= "\n\n" . $media['url'];
                        }
                        if ($imageAdded && $videoAdded) {
                            break; // only first image and first video
                        }
                    }
                }

                // If using Markdown editor, convert HTML to Markdown
                if ($isMarkdown) {
                    $finalContent = $this->htmlToMarkdown($finalContent);
                }

                $topicContent = new TopicContent([
                    'content' => $finalContent,
                ]);

                if ($topic->validate() && $topicContent->validate()) {
                    $topic->save(false);
                    $topicContent->link('topic', $topic);
                    $totalCount++;
                    Yii::info('Successfully collected: ' . $item['title'], __METHOD__);
                } else {
                    $errors = array_merge($topic->getErrors(), $topicContent->getErrors());
                    Yii::warning('Validation failed: ' . $item['title'] . ' - ' . json_encode($errors), __METHOD__);
                }
            }
        }

        return $this->asJson([
            'status' => $totalCount > 0 ? 'success' : 'info',
            'message' => implode("<br>", $results) . "<br><strong>Total published: $totalCount</strong>",
            'total' => $totalCount
        ]);
    }

    private function extractMedia($html, $entry)
    {
        $media = [];
        
        // Common tracking pixel domains or patterns
        $trackingPatterns = [
            'pixel.wp.com',
            'feeds.feedburner.com',
            'statcounter.com',
            'google-analytics.com',
            'doubleclick.net',
            'scorecardresearch.com',
            '/pixel',
            '/track',
            '1x1',
        ];

        // Extract images from HTML
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                $url = $this->normalizeMediaUrl($url);
                if (!$url) { continue; }
                $isTracking = false;
                foreach ($trackingPatterns as $pattern) {
                    if (strpos($url, $pattern) !== false) {
                        $isTracking = true;
                        break;
                    }
                }
                if (!$isTracking) {
                    $media[] = ['type' => 'image', 'url' => $url];
                }
            }
        }

        // Extract from Media RSS namespace
        $mediaNs = $entry->children('http://search.yahoo.com/mrss/');
        if (isset($mediaNs->content)) {
            foreach ($mediaNs->content as $content) {
                $attributes = $content->attributes();
                $url = $this->normalizeMediaUrl((string)$attributes->url);
                if (!$url) { continue; }
                $type = (string)$attributes->type;
                if (strpos($type, 'image') !== false) {
                    $media[] = ['type' => 'image', 'url' => $url];
                } else if (strpos($type, 'video') !== false) {
                    $media[] = ['type' => 'video', 'url' => $url];
                }
            }
        }
        
        // Extract YouTube/Vimeo etc from links or iframes
        if (preg_match_all('/https?:\/\/(www\.)?(youtube\.com|youtu\.be|v\.qq\.com|v\.youku\.com|player\.vimeo\.com|bilibili\.com\/video\/)[^\s"\'<>]+/i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $url = $this->normalizeMediaUrl($url);
                if ($url) {
                    $media[] = ['type' => 'video', 'url' => $url];
                }
            }
        }

        // De-duplicate by URL
        $uniqueMedia = [];
        $urls = [];
        foreach ($media as $m) {
            if (!in_array($m['url'], $urls)) {
                $uniqueMedia[] = $m;
                $urls[] = $m['url'];
            }
        }

        return $uniqueMedia;
    }

    private function dedupeMedia($media)
    {
        $seen = [];
        $unique = [];
        foreach ($media as $m) {
            $url = isset($m['url']) ? trim($m['url']) : '';
            if ($url === '') {
                continue;
            }

            // Normalize by filename (ignore query) to drop duplicates with same basename
            $parsed = parse_url($url);
            $path = isset($parsed['path']) ? $parsed['path'] : $url;
            $basename = strtolower(basename($path));
            $key = $basename ?: strtolower($url);

            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $m;
            }
        }
        return $unique;
    }

    private function normalizeMediaUrl($url)
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        // Upgrade to https to avoid mixed-content; drop protocol-relative to https
        if (strpos($url, '//') === 0) {
            $url = 'https:' . $url;
        } else if (stripos($url, 'http://') === 0) {
            $url = 'https://' . substr($url, 7);
        }
        // Skip data URIs or javascript
        if (stripos($url, 'data:') === 0 || stripos($url, 'javascript:') === 0) {
            return '';
        }
        return $url;
    }

    private function cleanDescription($text)
    {
        $text = trim(strip_tags($text));
        if (empty($text)) return '';
        
        // Try to get first few sentences
        if (preg_match('/^.*(?<!\.)[.!?。！？](?!\.)/us', $text, $match)) {
            return trim($match[0]);
        }
        
        // If no sentence found, return first 200 chars
        if (mb_strlen($text) > 200) {
            return mb_substr($text, 0, 200) . '...';
        }
        
        return $text;
    }

    private function extractKeywords($title, $content)
    {
        $text = $title . ' ' . strip_tags($content);
        preg_match_all('/[a-zA-Z]{3,}/', $text, $enMatches);
        $enWords = array_map('strtolower', $enMatches[0]);
        preg_match_all('/[\x{4e00}-\x{9fa5}]{2,4}/u', $text, $zhMatches);
        $zhWords = $zhMatches[0];
        $words = array_merge($enWords, $zhWords);
        $counts = [];
        $zhStopWords = ['这个', '那个', '什么', '如何', '可以', '进行', '已经', '通过', '目前', '其中', '同时', '以及', '因为', '所以', '但是', '如果', '虽然', '不仅', '而且', '就是', '这样', '这些', '那些', '一些', '非常', '特别', '相当', '比较', '更加', '可能', '应该', '必须', '需要', '能够', '为了', '关于', '对于', '由于', '因此', '从而', '或者', '还是', '甚至', '甚至于', '并且', '而且'];
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
        $topWords = array_slice(array_keys($counts), 0, 3);
        return implode(',', $topWords);
    }
    
    private function htmlToMarkdown($html)
    {
        // Remove editor footer blocks
        $html = preg_replace('/<div class="editor">[\s\S]*?<\/div>/i', '', $html);
        // Remove inline styles
        $html = preg_replace('/\sstyle="[^"]*"/i', '', $html);
        // Convert images
        $html = preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', function($m){
            return '![](' . $m[1] . ')';
        }, $html);
        // Convert paragraphs to line breaks
        $html = preg_replace('/<\/?p[^>]*>/i', "\n\n", $html);
        // Strip remaining tags
        $text = strip_tags($html);
        // Normalize whitespace
        $text = preg_replace("/\n{3,}/", "\n\n", $text);
        return trim($text);
    }
}

