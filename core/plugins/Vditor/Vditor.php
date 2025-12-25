<?php
/**
 * Vditor Markdown编辑器插件
 * Based on SimpleForum (https://github.com/SimpleForum/SimpleForum)
 * Modified for https://610000.xyz/
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
            'name' => 'Vditor编辑器（基于EasyMDE）',
            'description' => 'Vditor使用EasyMDE作为底层编辑器，提供简洁高效的Markdown编辑体验。支持实时预览、图片上传、工具栏自定义等功能。',
            'author' => 'GitHub Copilot',
            'url' => 'https://easymde.tk/',
            'version' => '1.2',
            'config' => [
                [
                    'label' => '编辑器高度',
                    'key' => 'editor_height',
                    'type' => 'text',
                    'value' => '240px',
                    'description' => '编辑器最小高度，默认240px',
                ],
            ],
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
        $uploadUrl = \yii\helpers\Url::to(['service/upload'], true);
        $csrf = \Yii::$app->request->getCsrfToken();
        
        // 从插件配置中读取编辑器高度
        $editorHeight = isset(\Yii::$app->params['plugins']['Vditor']['editor_height']) 
            ? \Yii::$app->params['plugins']['Vditor']['editor_height'] 
            : '240px';

        $js = <<<JS
        if ($('#editor').length > 0) {
            var textarea = document.getElementById('editor');
            var easyMDE = new EasyMDE({
                element: textarea,
                autofocus: false,
                spellChecker: false,
                autoDownloadFontAwesome: false,
                minHeight: '{$editorHeight}',
                status: false,
                forceSync: true,
                promptURLs: true,
                sideBySideFullscreen: false,
                toolbar: ['bold','italic','strikethrough','heading','link','quote','unordered-list','ordered-list','code','image','preview'],
                imageUploadFunction: function(file, onSuccess, onError) {
                    var fd = new FormData();
                    fd.append('UploadForm[files][]', file);
                    fetch('{$uploadUrl}', {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-Token': '{$csrf}' },
                        body: fd
                    }).then(function(res){ return res.json(); }).then(function(data){
                        if (Array.isArray(data) && data[0]) {
                            onSuccess(data[0]);
                        } else if (data && (data.error || data['jquery-upload-file-error'])) {
                            onError(data.error || data['jquery-upload-file-error'] || 'Upload failed');
                        } else {
                            onError('Upload failed');
                        }
                    }).catch(function(err){ onError(err && err.message ? err.message : 'Upload failed'); });
                }
            });

            // handle insert-image buttons (from legacy upload widget)
            $(document).off('click', '.insert-image').on('click', '.insert-image', function () {
                if (!easyMDE) return;
                easyMDE.codemirror.replaceSelection('![](' + $(this).attr('id') + ')');
            });

            // handle reply-to quick insert
            $(document).off('click', '.reply-to').on('click', '.reply-to', function () {
                if (!easyMDE) return;
                var atString = '@' + $(this).attr('params') + ' ';
                easyMDE.codemirror.replaceSelection(atString);
                $('html, body').animate({ scrollTop: $(textarea).offset().top - 100 }, 300);
            });
        }
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
