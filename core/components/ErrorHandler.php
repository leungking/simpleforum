<?php
/**
 * 增强的错误处理器
 * 提供更好的错误日志记录和用户友好的错误页面
 */

namespace app\components;

use Yii;
use yii\web\ErrorHandler as BaseErrorHandler;

class ErrorHandler extends BaseErrorHandler
{
    /**
     * 记录异常到日志
     * @param \Throwable $exception
     */
    public function logException($exception)
    {
        // 调用父类方法
        parent::logException($exception);
        
        // 额外的错误日志记录
        if (YII_ENV_PROD) {
            $this->logToExternalService($exception);
        }
    }
    
    /**
     * 发送错误到外部服务（如Sentry）
     * @param \Exception|\Error $exception
     */
    protected function logToExternalService($exception)
    {
        // 这里可以集成如Sentry等错误追踪服务
        // 示例：
        // \Sentry\captureException($exception);
        
        // 或者记录到专门的错误日志文件
        $errorLog = Yii::getAlias('@runtime/logs/errors.log');
        $message = sprintf(
            "[%s] %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        file_put_contents($errorLog, $message, FILE_APPEND);
    }
    
    /**
     * 渲染开发环境错误
     */
    public function renderException($exception)
    {
        if (YII_ENV_DEV) {
            parent::renderException($exception);
        } else {
            // 生产环境：显示友好的错误页面
            $this->renderProductionError($exception);
        }
    }
    
    /**
     * 渲染生产环境错误页面
     * @param \Exception|\Error $exception
     */
    protected function renderProductionError($exception)
    {
        $statusCode = $exception instanceof \yii\web\HttpException 
            ? $exception->statusCode 
            : 500;
            
        Yii::$app->response->setStatusCode($statusCode);
        
        echo $this->renderFile($this->errorView, [
            'name' => Yii::t('app', 'An error occurred'),
            'message' => YII_DEBUG 
                ? $exception->getMessage() 
                : Yii::t('app', 'An internal server error occurred.'),
            'exception' => $exception,
        ]);
    }
}
