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
    <h2><?php echo Yii::t('app', 'Email Verification Code'); ?></h2>
    <p><?php echo Yii::t('app', 'Your verification code is:'); ?></p>
    <h1 style="color: #007bff; font-size: 32px; letter-spacing: 5px;"><?php echo Html::encode($code); ?></h1>
    <p><?php echo Yii::t('app', 'This code will expire in 10 minutes.'); ?></p>
    <p><?php echo Yii::t('app', 'If you did not request this code, please ignore this email.'); ?></p>
    <br>
    <p><?php echo Yii::t('app', 'Best regards,'); ?><br><?php echo Html::encode(Yii::$app->params['settings']['site_name']); ?></p>
</body>
</html>
