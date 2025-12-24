<?php
/**
 * @link https://610000.xyz/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Leon admin@610000.xyz
 */

namespace app\plugins\Vditor;

use Yii;
use yii\helpers\Html;
use app\components\Editor;
use app\components\PluginInterface;
use app\models\Setting;

class Vditor extends Editor implements PluginInterface
{
    public static function info()
    {
        return [
            'id' => 'Vditor',
            'name' => 'Vditor编辑器',
            'description' => 'Vditor是一款现代化的Markdown编辑器，支持所见即所得、即时渲染和分屏预览模式。',
            'author' => 'GitHub Copilot',
            'url' => 'https://b3log.org/vditor/',
            'version' => '1.0',
            'config' => [],
        ];
    }

    public static function install()
    {
        if ( ($setting = Setting::findOne(['key'=>'editor'])) ) {
            $option = json_decode($setting->option, true);
            $option['Vditor']='Vditor编辑器';
            $setting->option = json_encode($option);
            $setting->save();
        }
        return true;
    }

    public static function uninstall()
    {
        if ( ($setting = Setting::findOne(['key'=>'editor'])) ) {
            $option = json_decode($setting->option, true);
            unset($option['Vditor']);
            $setting->option = json_encode($option);
            $setting->save();
        }
        return true;
    }

    public function registerAsset($view)
    {
        VditorAsset::register($view);
        $lang = (Yii::$app->language == 'zh-CN') ? 'zh_CN' : 'en_US';
        
        $js = <<<JS
$('#editor').hide();
$('<div id="vditor-container" style="margin-bottom: 1rem;"></div>').insertBefore('#editor');
window.vditor = new Vditor('vditor-container', {
    height: 400,
    mode: 'ir',
    placeholder: 'Markdown is supported',
    lang: '{$lang}',
    cache: { enable: false },
    value: $('#editor').val(),
    input(value) {
        $('#editor').val(value);
    },
    after() {
        // Handle image insertion from upload component
        $(document).off('click', '.insert-image').on('click', '.insert-image', function () {
            if (window.vditor) {
                window.vditor.insertValue('![](' + $(this).attr('id') + ')');
            }
        });
        // Handle reply-to
        $(document).off('click', '.reply-to').on('click', '.reply-to', function () {
            if (window.vditor) {
                var atString = '@' + $(this).attr('params') + " ";
                window.vditor.insertValue(atString);
                if ($('#vditor-container').length > 0) {
                    $('html, body').animate({
                        scrollTop: $("#vditor-container").offset().top - 100
                    }, 500);
                }
            }
        });
    }
});
JS;
        $view->registerJs($js);
    }

    public function parseEditor($text, $autoLink=false)
    {
        if ( empty($this->_parser) ) {
            $this->_parser = new VditorParser();
        }
        return $this->_parser->setUrlsLinked($autoLink)->setMarkupEscaped(true)->text($text);
    }
}
