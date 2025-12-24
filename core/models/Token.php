<?php
/**
 * @link http://simpleforum.org/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Jiandong Yu admin@simpleforum.org
 */

namespace app\models;

use Yii;
use yii\base\InvalidArgumentException;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use app\components\Util;

class Token extends ActiveRecord
{
    const TYPE_REG = 0;
    const TYPE_PWD = 1;
    const TYPE_EMAIL = 2;
    const TYPE_INVITE_CODE = 3;
    const TYPE_OTP = 4;
    const STATUS_VALID = 0;
    const STATUS_USED = 1;
    const STATUS_PENDING_APPROVAL = 2;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%token}}';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_at', 'updated_at'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_at'],
                ],
            ],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id'])
            ->select(['id', 'username', 'status', 'email']);
    }

    public static function generateToken($length=32)
    {
        return Util::generateRandomString($length);
    }

    public static function findByToken($token, $type=self::TYPE_PWD)
    {
        if (empty($token) || !is_string($token)) {
            throw new InvalidArgumentException(Yii::t('app', 'Parameter error'));
        }

       $model = static::find()->where(['token'=>$token])->with(['user'])->one();

        if (!$model || $model->type != $type) {
            throw new InvalidArgumentException(Yii::t('app', 'Parameter error'));
        } else if ( $model->status == self::STATUS_VALID && $model->expires > 0 && $model->expires < time() || !$model->user ) {
            throw new InvalidArgumentException(Yii::t('app', 'The token has expired. Please apply again.'));
        } else if ($model->status == self::STATUS_USED ) {
            throw new InvalidArgumentException(Yii::t('app', 'The token was used.'));
        }
        return $model;
    }

    public static function sendActivateMail($user, $email='')
    {
        $settings = Yii::$app->params['settings'];
        $email = empty($email)? $user['email']:$email;

        $model = self::findByType(self::TYPE_REG, $user['id'], $email);

        if ( $model ) {
            try {
                return Yii::$app->getMailer()->compose('@app/mail/' . Yii::$app->language . '/registerVerifyToken-text', ['token' => $model, 'username'=>$user['name']])
                    ->setFrom([$settings['mailer_username'] => $settings['site_name']])
                    ->setTo($email)
                    ->setSubject(Yii::t('app', '{name}: Activate account', ['name' => $settings['site_name']]))
                    ->send();
            } catch(\Exception $e) {
                return false;
            }
        }
        return false;
    }

    public static function findByType($type, $user_id, $ext='')
    {
        $model = static::find()
            ->where(['user_id'=>$user_id, 'type'=>$type, 'status'=>self::STATUS_VALID])
            ->andWhere(['>', 'expires', time()])
            ->one();
        if ( !$model ) {
            $model = new Token();
            $model->user_id = $user_id;
            $model->type = $type;
            $model->token = ($type == self::TYPE_OTP) ? sprintf('%06d', mt_rand(100000, 999999)) : self::generateToken();
            $model->ext = $ext;
            $model->expires = time() + 1800; // 30 minutes
            $model->status = self::STATUS_VALID;
            $model->save();
        }
        return $model;
    }

    public static function sendOTP($user)
    {
        $settings = Yii::$app->params['settings'];
        $model = self::findByType(self::TYPE_OTP, $user->id, $user->email);

        if ($model) {
            try {
                return Yii::$app->getMailer()->compose()
                    ->setFrom([$settings['mailer_username'] => $settings['site_name']])
                    ->setTo($user->email)
                    ->setSubject(Yii::t('app', '{name}: Login verification code', ['name' => $settings['site_name']]))
                    ->setTextBody(Yii::t('app', 'Your login verification code is: {code}. It will expire in 30 minutes.', ['code' => $model->token]))
                    ->send();
            } catch (\Exception $e) {
                return false;
            }
        }
        return false;
    }

    public static function validateOTP($user_id, $otp)
    {
        $model = static::find()
            ->where(['user_id' => $user_id, 'type' => self::TYPE_OTP, 'token' => $otp, 'status' => self::STATUS_VALID])
            ->andWhere(['>', 'expires', time()])
            ->one();
        if ($model) {
            $model->status = self::STATUS_USED;
            $model->save();
            return true;
        }
        return false;
    }

}
