<?php
/**
 * 性能监控组件
 * 监控应用程序性能并记录慢查询
 */

namespace app\components;

use Yii;
use yii\base\Component;
use yii\db\Connection;

class PerformanceMonitor extends Component
{
    /**
     * @var float 慢查询阈值（秒）
     */
    public $slowQueryThreshold = 1.0;
    
    /**
     * @var bool 是否启用性能监控
     */
    public $enabled = true;
    
    /**
     * @var array 性能指标
     */
    private $metrics = [];
    
    /**
     * 初始化组件
     */
    public function init()
    {
        parent::init();
        
        if ($this->enabled) {
            $this->attachEventHandlers();
        }
    }
    
    /**
     * 附加事件处理器
     */
    protected function attachEventHandlers()
    {
        // 监控数据库查询
        if (Yii::$app->has('db')) {
            $db = Yii::$app->db;
            
            $db->on(Connection::EVENT_AFTER_OPEN, function ($event) {
                $event->sender->enableQueryCache = true;
                $event->sender->queryCacheDuration = 3600;
            });
        }
        
        // 应用结束时记录性能数据
        Yii::$app->on(\yii\base\Application::EVENT_AFTER_REQUEST, [$this, 'logPerformance']);
    }
    
    /**
     * 记录性能数据
     */
    public function logPerformance()
    {
        if (!$this->enabled) {
            return;
        }
        
        $metrics = [
            'timestamp' => date('Y-m-d H:i:s'),
            'url' => Yii::$app->request->url,
            'method' => Yii::$app->request->method,
            'execution_time' => round((microtime(true) - YII_BEGIN_TIME) * 1000, 2), // ms
            'memory_usage' => round(memory_get_peak_usage() / 1024 / 1024, 2), // MB
        ];
        
        // 记录数据库查询统计
        if (Yii::$app->has('db') && YII_DEBUG) {
            $db = Yii::$app->db;
            $queryCount = count(Yii::$app->log->getLogger()->getProfiling(['yii\db\Command::query']));
            $metrics['query_count'] = $queryCount;
        }
        
        // 如果执行时间超过阈值，记录到日志
        if ($metrics['execution_time'] > $this->slowQueryThreshold * 1000) {
            Yii::warning(json_encode($metrics), 'performance');
        }
        
        // 在开发环境显示性能指标
        if (YII_DEBUG) {
            $this->displayMetrics($metrics);
        }
    }
    
    /**
     * 显示性能指标
     * @param array $metrics
     */
    protected function displayMetrics($metrics)
    {
        // 可以将指标添加到响应头或输出到控制台
        if (!Yii::$app->request->isAjax) {
            $header = sprintf(
                'Execution-Time: %sms; Memory: %sMB',
                $metrics['execution_time'],
                $metrics['memory_usage']
            );
            Yii::$app->response->headers->add('X-Performance', $header);
        }
    }
    
    /**
     * 开始性能追踪
     * @param string $label
     */
    public static function beginProfile($label)
    {
        Yii::beginProfile($label, __METHOD__);
    }
    
    /**
     * 结束性能追踪
     * @param string $label
     */
    public static function endProfile($label)
    {
        Yii::endProfile($label, __METHOD__);
    }
}
