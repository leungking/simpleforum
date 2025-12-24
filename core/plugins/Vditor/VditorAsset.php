<?php
/**
 * @link https://610000.xyz/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Leon admin@610000.xyz
 */

namespace app\plugins\Vditor;

use yii\web\AssetBundle;

class VditorAsset extends AssetBundle
{
    public $baseUrl = 'https://cdn.jsdelivr.net/npm/vditor@3.9.6';
    public $css = [
        'dist/index.css',
    ];
    public $js = [
        'dist/index.min.js',
    ];
    public $depends = [
        'yii\web\JqueryAsset',
    ];
}
