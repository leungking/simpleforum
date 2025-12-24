<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

namespace app\models;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;
use app\components\SfHook;
use app\components\SfEvent;
use app\components\RateLimiter;

/**
 * Login form
 */
class LoginForm extends Model
{
    const SCENARIO_BIND = 2;

    public $username; // This will be used for Email in frontend
    public $password;
    public $otp;
    public $rememberMe = true;
    public $captcha;

    private $_user = false;


    /**
     * @inheritdoc
     */
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios[self::SCENARIO_BIND] = ['username', 'password'];
        $scenarios['otp-login'] = ['username', 'otp'];
        return $scenarios;
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        $rules = [
            [['username'], 'required'],
            [['password'], 'required', 'on' => ['default', self::SCENARIO_BIND]],
            [['otp'], 'required', 'on' => ['otp-login']],
            ['username', 'filter', 'filter' => 'strtolower'],
            ['username', 'email'],
            ['rememberMe', 'boolean'],
            ['password', 'validatePassword', 'on' => ['default', self::SCENARIO_BIND]],
            ['otp', 'validateOtp', 'on' => ['otp-login']],
            ['username', 'validateVerificationPeriod'],
        ];
        $captcha = ArrayHelper::getValue(Yii::$app->params, 'settings.captcha', '');
        if(!empty($captcha) && ($plugin=ArrayHelper::getValue(Yii::$app->params, 'plugins.' . $captcha, []))) {
           $rule = $plugin['class']::captchaValidate('signin', $plugin);
           if(!empty($rule)) {
             $rules[] = $rule;
           }
        }

        return $rules;
    }

    public function attributeLabels()
    {
        return [
            'username' => Yii::t('app', 'Email'),
            'password' => Yii::t('app', 'Password'),
            'otp' => Yii::t('app', 'Verification Code'),
            'rememberMe' => Yii::t('app', 'Remember me for one week'),
            'captcha' => Yii::t('app', 'Enter code'),
        ];
    }

    public function validateVerificationPeriod($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if ($user && $user->isVerificationExpired()) {
                $this->addError($attribute, Yii::t('app', 'Your email has not been verified within the 7-day grace period. Please contact the administrator.'));
            }
        }
    }

    public function validateOtp($attribute, $params)
    {
        if (!$this->hasErrors()) {
            $user = $this->getUser();
            if (!$user || !Token::validateOTP($user->id, $this->otp)) {
                $this->addError($attribute, Yii::t('app', 'Invalid verification code.'));
            }
        }
    }

    /**
     * Validates the password.
     * This method serves as the inline validation for password.
     *
     * @param string $attribute the attribute currently being validated
     * @param array $params the additional name-value pairs given in the rule
     */
    public function validatePassword($attribute, $params)
    {
        if (!$this->hasErrors()) {
            // 速率限制：每个IP在5分钟内最多尝试5次
            $ip = Yii::$app->getRequest()->getUserIP();
            try {
                RateLimiter::checkLimit("login_{$ip}", 5, 300);
            } catch (\yii\web\TooManyRequestsHttpException $e) {
                $this->addError($attribute, $e->getMessage());
                return;
            }
            
            $user = $this->getUser();
            if (!$user || !$user->validatePassword($this->password)) {
                $this->addError($attribute, Yii::t('app', 'The username or password you entered is incorrect.'));
            } else {
                // 登录成功，重置速率限制
                RateLimiter::resetLimit("login_{$ip}");
            }
        }
    }

    /**
     * Logs in a user using the provided username and password.
     *
     * @return boolean whether the user is signed in successfully
     */
    public function login()
    {
        if ($this->validate()) {
            if ( ($status = Yii::$app->getUser()->login($this->getUser(), $this->rememberMe ? 3600 * 24 * 7 : 0)) ) {
                $userIP = sprintf("%u", ip2long(Yii::$app->getRequest()->getUserIP()));
                UserInfo::updateAll([
                    'last_login_at'=>time(),
                    'last_login_ip'=>$userIP,
                ], ['user_id'=> $this->getUser()->id]);
                (new History([
                    'user_id' => $this->getUser()->id,
                    'action' => History::ACTION_LOGIN,
                    'target' => $userIP,
                    'ext' => '',
                ]))->save(false);
            }

            return $status;

        } else {
            return false;
        }
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    public function getUser()
    {
        if ($this->_user === false) {
            $this->_user = User::findByEmail($this->username);
        }

        return $this->_user;
    }
}
