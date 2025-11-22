<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

namespace app\components;

use Yii;
use yii\base\BaseObject;
use yii\imagine\Image;

class Upload extends BaseObject implements UploadInterface
{
	const TYPE_AVATAR = 'avatar';
	const TYPE_COVER = 'cover';

    public static $maxWidth = 1000;

    public static $sizes = [
		'avatar' => [
	        'large'=>'73x73',
	        'normal'=>'48x48',
	        'small'=>'24x24',
		],
		'cover' => [
	        'cover_m'=>'320x122',
	        'cover_s'=>'260x100',
		],
    ];

    public function upload($source, $target)
    {
        if ( Yii::$app->params['settings']['upload_file'] === 'disable') {
            return false;
        }
        if (!is_file($source)) {
            Yii::error('Upload source file missing: '.$source, __METHOD__);
            return false;
        }
        $imgInfo = @getimagesize($source);
        if ($imgInfo === false) {
            Yii::error('Upload getimagesize failed: '.$source, __METHOD__);
            return false;
        }
        if($imgInfo[0] > self::$maxWidth) {
            $width = self::$maxWidth;
            $height = round($imgInfo[1]*self::$maxWidth/$imgInfo[0]);
        } else {
            $width = $imgInfo[0];
            $height = $imgInfo[1];
        }
		$path_parts = pathinfo($target);
        @mkdir($path_parts['dirname'], 0755, true);
        try {
            Image::thumbnail($source, $width, $height)->save($target);
            } catch (\Throwable $e) {
            // Fallback to GD-based resize
            if (!$this->gdResize($source, $target, $width, $height)) {
                Yii::error('Upload thumbnail failed: '.$e->getMessage(), __METHOD__);
                return false;
            }
        }

        return Yii::getAlias('@web/'.$target);
    }

    public function uploadThumbnails($source, $target, $type=self::TYPE_AVATAR)
    {
        if (!is_file($source)) {
            Yii::error('Avatar source file missing: '.$source, __METHOD__);
            return false;
        }
        $path_parts = pathinfo($target);
        @mkdir($path_parts['dirname'], 0755, true);
        foreach(self::$sizes[$type] as $key=>$resize) {
            $filePath = str_replace('{size}', $key, $target);
            list($width, $height) = explode('x', $resize);
            try {
                Image::thumbnail($source, $width, $height)->save($filePath);
                } catch (\Throwable $e) {
                // Fallback to GD-based thumbnail crop
                if (!$this->gdThumbnail($source, $filePath, (int)$width, (int)$height)) {
                    Yii::error('Avatar thumbnail failed ('.$key.'): '.$e->getMessage(), __METHOD__);
                    return false;
                }
            }
        }
        return true;
    }

    private function gdResize($source, $target, $dstW, $dstH)
    {
        $data = @file_get_contents($source);
        if ($data === false) { return false; }
        $src = @imagecreatefromstring($data);
        if (!$src) { return false; }
        $srcW = imagesx($src); $srcH = imagesy($src);
        $dst = imagecreatetruecolor($dstW, $dstH);
        if (!imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH)) { imagedestroy($src); imagedestroy($dst); return false; }
        $ok = imagejpeg($dst, $target, 90);
        imagedestroy($src); imagedestroy($dst);
        return $ok;
    }

    private function gdThumbnail($source, $target, $dstW, $dstH)
    {
        $data = @file_get_contents($source);
        if ($data === false) { return false; }
        $src = @imagecreatefromstring($data);
        if (!$src) { return false; }
        $srcW = imagesx($src); $srcH = imagesy($src);
        // cover scale (OUTBOUND): scale to fill, then center-crop
        $scale = max($dstW / $srcW, $dstH / $srcH);
        $tmpW = (int)ceil($srcW * $scale);
        $tmpH = (int)ceil($srcH * $scale);
        $tmp = imagecreatetruecolor($tmpW, $tmpH);
        if (!imagecopyresampled($tmp, $src, 0, 0, 0, 0, $tmpW, $tmpH, $srcW, $srcH)) { imagedestroy($src); imagedestroy($tmp); return false; }
        $x = (int)max(0, ($tmpW - $dstW) / 2);
        $y = (int)max(0, ($tmpH - $dstH) / 2);
        $dst = imagecreatetruecolor($dstW, $dstH);
        if (!imagecopy($dst, $tmp, 0, 0, $x, $y, $dstW, $dstH)) { imagedestroy($src); imagedestroy($tmp); imagedestroy($dst); return false; }
        $ok = imagejpeg($dst, $target, 90);
        imagedestroy($src); imagedestroy($tmp); imagedestroy($dst);
        return $ok;
    }
}
