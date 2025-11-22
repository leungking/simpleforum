<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\base;

// PHP 7.2+ reserves "object", so we expose the historical alias through class_alias.
if (!class_exists(BaseObject::class, false)) {
	require __DIR__ . '/BaseObject.php';
}

if (!class_exists(__NAMESPACE__ . '\\Object', false)) {
	class_alias(BaseObject::class, __NAMESPACE__ . '\\Object', false);
}
