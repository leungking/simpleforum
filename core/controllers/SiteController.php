<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

namespace app\controllers;

use Yii;
use yii\base\InvalidParamException;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;
use yii\helpers\Html;
use yii\web\ServerErrorHttpException;
use yii\web\Cookie;
use app\models\LoginForm;
use app\models\ForgotPasswordForm;
use app\models\ResetPasswordForm;
use app\models\ChangeEmailForm;
use app\models\SignupForm;
use app\models\Auth;
use app\models\User;
use app\models\Token;

/**
 * Site controller
 */
class SiteController extends AppController
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout', 'signup', 'forgot-password', 'reset-password'],
                'rules' => [
                    [
                        'actions' => ['signup', 'forgot-password', 'reset-password'],
                        'allow' => true,
                        'roles' => ['?'],
                    ],
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
                'height' => 40,
                'maxLength' => 5,
                'minLength' => 4,
            ],
            'auth' => [
                'class' => 'yii\authclient\AuthAction',
                'successCallback' => [$this, 'onAuthSuccess'],
            ],
        ];
    }

    public function onAuthSuccess($client)
    {
        $sourceName = [
            'weibo'=>'微博',
            'weixin'=>'微信',
        ];
        $me = Yii::$app->getUser();

        $source = $client->getId();
        $attr = $client->getUserAttributes();

        $auth = Auth::findOne([
            'source' => $source,
            'source_id' => (string)$attr['id'],
        ]);

        if ($me->getIsGuest()) {
            if ($auth) { // login
                $user = $auth->user;
                $me->login($user);
                if ($user->isWatingActivation() || $user->isWatingVerification()) {
                    return $this->redirect(['my/settings']);
                }
                return $this->goHome();
            } else { // signup
                $attr['source'] = $source;
//              $attr['sourceName'] = $client->defaultName();
                $attr['sourceName'] = empty($sourceName[$source])?$source:$sourceName[$source];
                $session = Yii::$app->getSession();
                $session->set('authInfo', $attr);
                return $this->redirect(['auth-bind-account']);

            }
        } else { // user already signed in
            if (!$auth) { // add auth provider
                $auth = new Auth([
                    'user_id' => Yii::$app->getUser()->id,
                    'source' => $source,
                    'source_id' => (string)$attr['id'],
                ]);
                $auth->save();
                if(Yii::$app->getRequest()->get('action') === 'bind') {
                    $this->redirect(['my/settings', '#'=>'auth']);
                }
            }
        }
    }

    public function actionAuthSignup()
    {
        $session = Yii::$app->getSession();
        if( !$session->has('authInfo') ) {
            return $this->redirect(['login']);
        }
        $attr = $session->get('authInfo');

        $model = new SignupForm(['action' => SignupForm::ACTION_AUTH_SIGNUP]);
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                $auth = new Auth([
                    'user_id' => $user->id,
                    'source' => (string)$attr['source'],
                    'source_id' => (string)$attr['id'],
                ]);
                if ($auth->save()) {
                    $session->remove('authInfo');
                } else {
                    throw new ServerErrorHttpException(implode('<br />', $auth->getFirstErrors()));
                }
                if (Yii::$app->getUser()->login($user)) {
                    return $this->goHome();
                }
            }
        }

        return $this->render('signup', [
            'model' => $model,
            'authInfo' => $attr,
        ]);
    }

    public function actionAuthBindAccount()
    {
        if (! Yii::$app->getUser()->getIsGuest()) {
            return $this->goHome();
        }

        $session = Yii::$app->getSession();
        if( !$session->has('authInfo') ) {
            return $this->redirect(['login']);
        }
        $attr = $session->get('authInfo');

        $model = new LoginForm(['scenario' => LoginForm::SCENARIO_BIND]);
        if ($model->load(Yii::$app->getRequest()->post()) && $model->login()) {
            $auth = new Auth([
                'user_id' => Yii::$app->getUser()->id,
                'source' => (string)$attr['source'],
                'source_id' => (string)$attr['id'],
            ]);
            if ($auth->save()) {
                $session->remove('authInfo');
            } else {
                throw new ServerErrorHttpException(implode('<br />', $auth->getFirstErrors()));
            }
            return $this->goHome();
        } else {
            return $this->render('authBindAccount', [
                'model' => $model,
                'authInfo' => $attr,
            ]);
        }

    }

    public function actionLanguage()
    {
        $language = Yii::$app->getRequest()->get('language');
        if($language) {
            //Yii::$app->language = $language;
            $languageCookie = new Cookie([
                'name' => 'language',
                'value' => $language,
                'expire' => time() + 60 * 60 * 24 * 30, // 30 days
            ]);
            Yii::$app->getResponse()->getCookies()->add($languageCookie);
        }
        //$this->redirect(['topic/index']);
        return $this->goBack();
    }

    public function actionLogin()
    {
        if (! Yii::$app->getUser()->getIsGuest()) {
            return $this->goHome();
        }

        $model = new LoginForm();
        $post = Yii::$app->getRequest()->post();
        if (isset($post['login-type']) && $post['login-type'] === 'otp') {
            $model->scenario = 'otp-login';
        }

        if ($model->load($post) && $model->login()) {
            return $this->goBack();
        } else {
            return $this->render('login', [
                'model' => $model,
            ]);
        }
    }

    public function actionSendOtp()
    {
        Yii::$app->getResponse()->format = \yii\web\Response::FORMAT_JSON;
        $email = Yii::$app->getRequest()->post('email');
        if (empty($email)) {
            return ['status' => 'error', 'msg' => Yii::t('app', 'Email cannot be blank.')];
        }

        $user = User::findByEmail($email);
        if (!$user) {
            return ['status' => 'error', 'msg' => Yii::t('app', 'User not found.')];
        }

        if (Token::sendOTP($user)) {
            return ['status' => 'success', 'msg' => Yii::t('app', 'Verification code has been sent to your email.')];
        } else {
            return ['status' => 'error', 'msg' => Yii::t('app', 'Failed to send verification code.')];
        }
    }

    public function actionSendSignupCode()
    {
        Yii::$app->getResponse()->format = \yii\web\Response::FORMAT_JSON;
        $email = Yii::$app->getRequest()->post('email');
        
        if (empty($email)) {
            return ['status' => 'error', 'msg' => Yii::t('app', 'Email cannot be blank.')];
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'error', 'msg' => Yii::t('app', 'Invalid email format.')];
        }
        
        // Check if email already exists
        if (User::findByEmail($email)) {
            return ['status' => 'error', 'msg' => Yii::t('app', 'This email is already registered.')];
        }
        
        // Generate 6-digit code
        $code = sprintf('%06d', mt_rand(0, 999999));
        
        // Create token
        $token = new Token();
        $token->user_id = 0; // No user yet
        $token->token = $code;
        $token->type = Token::TYPE_EMAIL;
        $token->status = Token::STATUS_VALID;
        $token->expires = time() + 600; // 10 minutes
        $token->ext = $email; // Store email in ext field
        
        if ($token->save()) {
            // Send email with template fallback
            try {
                $view = '@app/mail/' . Yii::$app->language . '/signup-code';
                $viewFile = \Yii::getAlias($view . '.php');
                if (!is_file($viewFile)) {
                    $view = '@app/mail/en-US/signup-code';
                }

                $fromEmail = Yii::$app->params['settings']['admin_email'] ?? ('no-reply@' . parse_url(Yii::$app->request->hostInfo, PHP_URL_HOST));
                $siteName = Yii::$app->params['settings']['site_name'] ?? 'SimpleForum';

                $settings = Yii::$app->params['settings'];
                $fromMailbox = $settings['mailer_username'] ?? $fromEmail;
                Yii::$app->mailer->compose($view, ['code' => $code])
                    ->setFrom([$fromMailbox => $siteName])
                    ->setTo($email)
                    ->setSubject(Yii::t('app', 'Email Verification Code'))
                    ->send();
                
                return ['status' => 'success', 'msg' => Yii::t('app', 'Verification code has been sent to your email.')];
            } catch (\Exception $e) {
                \Yii::error('Signup code mail send failed: ' . $e->getMessage(), __METHOD__);
                return ['status' => 'error', 'msg' => Yii::t('app', 'Failed to send verification code.')];
            }
        }
        
        return ['status' => 'error', 'msg' => Yii::t('app', 'Failed to send verification code.')];
    }

    public function actionOffline()
    {
        return $this->render('offline');
    }

    public function actionLogout()
    {
        Yii::$app->getUser()->logout();

        return $this->goHome();
    }

    public function actionSignup()
    {
        if ( intval($this->settings['close_register']) === 1) {
            return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Registration Closed'), 'status'=>'success', 'msg'=>Yii::t('app', 'Please use {url} to sign in.', ['url' => \yii\helpers\Html::a(Yii::t('app', 'Third-Party Accounts'), ['site/login'])])]);
        }
        $model = new SignupForm();
        if ($model->load(Yii::$app->getRequest()->post())) {
            if ($user = $model->signup()) {
                if (Yii::$app->getUser()->login($user)) {
                    if ($user->status == User::STATUS_INACTIVE) {
                        return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Thank You For Your Registration'), 'status'=>'success', 'msg'=>Yii::t('app', 'An email has been sent to your email address containing an activation link. Please click on the link to activate your account. If you do not receive the email within a few minutes, please check your spam folder.')]);
                    } else if ($user->status == User::STATUS_ADMIN_VERIFY) {
                        return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Thank You For Your Registration'), 'status'=>'success', 'msg'=>Yii::t('app', 'Please wait for the admin approval.')]);
                    } else {
                        return $this->goHome();
                    }
                }
            }
        }

        return $this->render('signup', [
            'model' => $model,
        ]);
    }

    public function actionForgotPassword()
    {
        try {
            $model = new ForgotPasswordForm();
        } catch (InvalidParamException $e) {
            return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Reset Password'), 'status'=>'warning', 'msg'=>$e->getMessage()]);
        }
        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate()) {
            try {
                $model->apply();
                return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Reset Password'), 'status'=>'success', 'msg'=>Yii::t('app', 'An email has been sent to your email address containing a verification link. Please click on the link to reset your password. If you do not receive the email within a few minutes, please check your spam folder.')]);
            } catch (InvalidParamException $e) {
                Yii::$app->getSession()->setFlash('sendPwdNG', $e->getMessage());
            }
        }

        return $this->render('forgotPassword', [
            'model' => $model,
        ]);
    }

    public function actionResetPassword($token)
    {
        try {
            $model = new ResetPasswordForm($token);
        } catch (InvalidParamException $e) {
            return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Password Reset Failed'), 'status'=>'warning', 'msg'=>$e->getMessage()]);
        }

        if ($model->load(Yii::$app->getRequest()->post()) && $model->validate() && $model->resetPassword()) {
            return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Password Reset Successfully'), 'status'=>'success', 'msg'=>Yii::t('app', 'You can use new password to sign in.')]);
        }

        return $this->render('resetPassword', [
            'model' => $model,
        ]);
    }

    public function actionVerifyEmail($token)
    {
        try {
            $token = Token::findByToken($token, Token::TYPE_EMAIL);
        } catch (InvalidParamException $e) {
            return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Email Change Failed'), 'status'=>'warning', 'msg'=>$e->getMessage()]);
        }

        if ( Yii::$app->getRequest()->getIsPost() ) {
            $token->status = Token::STATUS_PENDING_APPROVAL;
            $token->save(false);

            $result = ['title'=>Yii::t('app', 'Email Verification Succeeded'), 'status'=>'success', 'msg'=>Yii::t('app', 'Your email has been verified. Please wait for administrator approval.')];
        } else {
                $result = [
                    'title'=>Yii::t('app', 'Email Change Verification'),
                    'status'=>'info', 
                    'msg'=>Yii::t('app', 'Please click to {url} .', ['url' => Html::a('<i class="fa fa-link" aria-hidden="true"></i> '. Yii::t('app', 'verify your new email'), Yii::$app->getRequest()->url, [
                        'title' => Yii::t('app', 'Email Change Verification'),
                        'data' => [
                            'method' => 'post',
                        ]])
                    ])
                ];
        }
        return $this->render('@app/views/common/info', $result);
    }

    public function actionActivate($token)
    {
        try {
            $token = Token::findByToken($token, Token::TYPE_REG);
        } catch (InvalidParamException $e) {
            return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Account Activation Failed'), 'status'=>'warning', 'msg'=>$e->getMessage()]);
        }

        if ( Yii::$app->getRequest()->getIsPost() ) {
            $user = $token->user;

            $token->status = Token::STATUS_USED;
            $token->save(false);

            if ( !empty($token->ext) && $user->email !== $token->ext && User::findOne(['email'=>$token->ext]) ) {
                return $this->render('@app/views/common/info', ['title'=>Yii::t('app', 'Account Activation Failed'), 'status'=>'warning', 'msg'=> Yii::t('app', '{attribute} is already in use.', ['attribute' => Yii::t('app', 'Email') . '(' . $token->ext . ')'])]);
            }
            if (intval($this->settings['admin_verify']) === 1) {
                $user->status = User::STATUS_ADMIN_VERIFY;
                $result = ['title'=>Yii::t('app', 'Email Verification Succeeded'), 'status'=>'success', 'msg'=>Yii::t('app', 'Your email address is now verified. Please wait for the admin approval.')];
            } else {
                $user->status = User::STATUS_ACTIVE;
                $result = ['title'=>Yii::t('app', 'Account Activation Succeeded'), 'status'=>'success', 'msg'=>Yii::t('app', 'Your account has been activated successfully. You can now {url}.', ['url' => \yii\helpers\Html::a(Yii::t('app', 'Sign in'), ['site/login'])])];
            }
            $user->email = $token->ext;
            $user->save(false);

        } else {
                $result = [
                    'title'=>Yii::t('app', 'Account Activation'),
                    'status'=>'info', 
                    'msg'=>Yii::t('app', 'Please click to {url} .', ['url' => Html::a('<i class="fa fa-link" aria-hidden="true"></i>' . Yii::t('app', 'activate your account'), Yii::$app->getRequest()->url, [
                        'title' => Yii::t('app', 'Account Activation'),
                        'data' => [
                            'method' => 'post',
                        ]])
                    ])
                ];
        }
        return $this->render('@app/views/common/info', $result);
    }

}
