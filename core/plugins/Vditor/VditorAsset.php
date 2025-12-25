<?php
/**
 * Vditor资源包 - 添加FontAwesome支持
 * Based on SimpleForum (https://github.com/SimpleForum/SimpleForum)
 * Modified for https://610000.xyz/
 */

namespace app\plugins\Vditor;

use yii\web\AssetBundle;

class VditorAsset extends AssetBundle
{
    // Use EasyMDE as the underlying editor
    public $baseUrl = 'https://cdn.jsdelivr.net/npm/easymde@2.18.0';
    public $css = [
        'dist/easymde.min.css',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
    ];
    public $js = [
        'dist/easymde.min.js',
    ];
    public $depends = [
        'yii\web\JqueryAsset',
    ];
}
