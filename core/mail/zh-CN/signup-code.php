<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $code string */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
</head>
<body>
    <h2>邮箱验证码</h2>
    <p>您的验证码是：</p>
    <h1 style="color: #007bff; font-size: 32px; letter-spacing: 5px;"><?php echo Html::encode($code); ?></h1>
    <p>此验证码将在10分钟后过期。</p>
    <p>如果您没有请求此验证码，请忽略此邮件。</p>
    <br>
    <p>祝好，<br><?php echo Html::encode(Yii::$app->params['settings']['site_name']); ?></p>
</body>
</html>
