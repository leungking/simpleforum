<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\bootstrap4\ActiveForm;
use app\models\SignupForm;


if ($model->action === SignupForm::ACTION_AUTH_SIGNUP) {
    $this->title = Yii::t('app', 'Create an account and bind a third-party account');
    $btnLabel = Yii::t('app', 'Create an account and bind it');
} else {
    $this->title = Yii::t('app', 'Sign up');
    $btnLabel = $this->title;
}
?>

<div class="row">
<!-- sf-left start -->
<div class="col-lg-8 sf-left">

<div class="card sf-box">
    <div class="card-header sf-box-header sf-navi">
        <?php echo Html::a(Yii::t('app', 'Home'), ['topic/index']), '&nbsp;/&nbsp;', $this->title; ?>
    </div>
    <div class="card-body sf-box-form">
<?php $form = ActiveForm::begin([
            'layout' => 'horizontal',
            'id' => 'form-signup',
            'fieldConfig' => [
              'horizontalCssClasses' => [
                  'label' => 'col-form-label col-sm-3 text-sm-right',
                  'wrapper' => 'col-sm-9',
              ],
            ],
        ]); ?>
<?php if ($model->action === SignupForm::ACTION_AUTH_SIGNUP) : ?>
        <div class="form-group row">
            <div class="offset-sm-3 col-sm-9">
                <?php echo Yii::t('app', 'You have signed in with your {name} account.', ['name'=>$authInfo['sourceName']]); ?>
            </div>
        </div>
        <div class="form-group row">
            <label class="control-label col-sm-3 text-sm-right"><?php echo Yii::t('app', 'Have an account?'); ?></label>
            <div class="col-sm-9">
                <?php echo Html::a(Yii::t('app', 'Bind your account'), ['auth-bind-account'], ['class'=>'btn sf-btn']); ?>
            </div>
        </div>
        <br /><strong><?php echo Yii::t('app', 'Create an account'); ?></strong><hr>
<?php endif; ?>
            <?php echo $form->field($model, 'email')->textInput(['maxlength'=>50, 'id' => 'signup-email']); ?>
            
            <div class="form-group row field-signupform-email_code required">
                <label class="col-form-label col-sm-3 text-sm-right" for="signupform-email_code"><?php echo Yii::t('app', 'Email Verification Code'); ?> <span class="required">*</span></label>
                <div class="col-sm-9">
                    <div class="input-group">
                        <?php echo Html::activeTextInput($model, 'email_code', ['class' => 'form-control', 'id' => 'signupform-email_code', 'maxlength' => 8]); ?>
                        <div class="input-group-append">
                            <button class="btn btn-outline-secondary" type="button" id="send-signup-code-btn"><?php echo Yii::t('app', 'Send Code'); ?></button>
                        </div>
                    </div>
                    <div class="invalid-feedback"></div>
                    <p class="form-text text-muted"><?php echo Yii::t('app', 'A 6-digit verification code will be sent to your email'); ?></p>
                </div>
            </div>
            
            <?php echo $form->field($model, 'password')->passwordInput(['maxlength'=>32]); ?>
            <?php echo $form->field($model, 'password_repeat')->passwordInput(['maxlength'=>32]); ?>
<?php
if ( intval(Yii::$app->params['settings']['close_register']) === 2 ) {
    echo $form->field($model, 'invite_code')->textInput(['maxlength'=>6]);
}
        $captcha = ArrayHelper::getValue(Yii::$app->params, 'settings.captcha', '');
        if(!empty($captcha) && ($plugin=ArrayHelper::getValue(Yii::$app->params, 'plugins.' . $captcha, []))) {
            $plugin['class']::captchaWidget('signup', $form, $model, null, $plugin);
        }
?>
            <div class="form-group">
                <div class="offset-sm-3 col-sm-9">
                <?php echo Html::submitButton($btnLabel, ['class' => 'btn sf-btn', 'name' => 'signup-button']); ?>
                </div>
            </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

</div>
<!-- sf-left end -->

<!-- sf-right start -->
<div class="col-lg-4 sf-right">
<?php echo $this->render('@app/views/common/_right'); ?>
</div>
<!-- sf-right end -->

</div>

<?php
$sendCodeUrl = \yii\helpers\Url::to(['site/send-signup-code']);
$msgEnterEmail = Yii::t('app', 'Please enter your email');
$msgSending = Yii::t('app', 'Sending...');
$msgSendCode = Yii::t('app', 'Send Code');
$msgFailed = Yii::t('app', 'Failed to send verification code.');

$this->registerJs(<<<JS
var countdown = 0;
var timer = null;

$('#send-signup-code-btn').click(function() {
    if (countdown > 0) {
        return;
    }
    
    var email = $('#signup-email').val();
    if (!email) {
        alert('{$msgEnterEmail}');
        return;
    }
    
    var btn = $(this);
    btn.prop('disabled', true).text('{$msgSending}');
    
    $.ajax({
        url: '{$sendCodeUrl}',
        type: 'POST',
        data: {
            email: email,
            _csrf: yii.getCsrfToken()
        },
        dataType: 'json',
        success: function(data) {
            if (data.status === 'success') {
                countdown = 60;
                updateButton();
                timer = setInterval(function() {
                    countdown--;
                    if (countdown <= 0) {
                        clearInterval(timer);
                        btn.prop('disabled', false).text('{$msgSendCode}');
                    } else {
                        updateButton();
                    }
                }, 1000);
                alert(data.msg);
            } else {
                btn.prop('disabled', false).text('{$msgSendCode}');
                alert(data.msg);
            }
        },
        error: function() {
            btn.prop('disabled', false).text('{$msgSendCode}');
            alert('{$msgFailed}');
        }
    });
});

function updateButton() {
    $('#send-signup-code-btn').text(countdown + 's');
}
JS
);
?>
