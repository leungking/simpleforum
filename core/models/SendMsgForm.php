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
use app\lib\Util;

class SendMsgForm extends Model
{
    public $user_id;
    public $msg;
    public $captcha;
    protected $_user;

    public function rules()
    {
        $rules = [
            [['user_id', 'msg'], 'trim'],
            [['user_id', 'msg'], 'required'],
            ['user_id', 'integer'],
            ['msg', 'string', 'max'=>255],
            ['user_id', 'validateUser'],
        ];

        $captcha = ArrayHelper::getValue(Yii::$app->params, 'settings.captcha', '');
        if(!empty($captcha) && ($plugin=ArrayHelper::getValue(Yii::$app->params, 'plugins.' . $captcha, []))) {
           $rule = $plugin['class']::captchaValidate('sms', $plugin);
           if(!empty($rule)) {
             $rules[] = $rule;
           }
        }

        return $rules;
    }

    public function attributeLabels()
    {
        return [
            'user_id' => Yii::t('app', 'Recipient'),
            'msg' => Yii::t('app', 'Message'),
            'captcha' => Yii::t('app', 'Enter code'),
        ];
    }

    public function validateUser($attribute, $params)
    {
        $me = Yii::$app->getUser()->getIdentity();
      if( $me->id == $this->$attribute) {
            $this->addError($attribute, Yii::t('app', 'Can\'t send a message to yourself.'));
            return;
        }
        $this->_user = User::findOne($this->$attribute);
        if ( !$this->_user ) {
            $this->addError($attribute, Yii::t('app', '{attribute} doesn\'t exist.', ['attribute'=>Yii::t('app', 'User')]));
        }
    }

    public function apply()
    {
        /** @var \app\models\User $me */
        $me = Yii::$app->getUser()->getIdentity();
        (new Notice([
            'target_id' => $this->_user->id,
            'source_id' => $me->id,
            'type' => Notice::TYPE_MSG,
            'msg' => $this->msg,
        ]))->save(false);
        $cost = User::getCost('sendMsg');
        $me->updateScore($cost);
        (new History([
            'user_id' => $me->id,
            'type' => History::TYPE_POINT,
            'action' => History::ACTION_MSG,
            'action_time' => time(),
            'target' => $this->_user->id,
            'ext' => json_encode(['score'=>$me->score, 'cost'=>$cost, 'target'=>['id'=>$this->_user->id, 'name'=>$this->_user->name], 'msg'=>$this->msg]),
        ]))->save(false);
        return true;
    }

}
