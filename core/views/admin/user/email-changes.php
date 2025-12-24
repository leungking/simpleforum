<?php
/**
 * @link https://610000.xyz/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Leon admin@610000.xyz
 */

use yii\helpers\Html;
use yii\widgets\LinkPager;

$this->title = Yii::t('app/admin', 'Pending Email Changes');
?>

<div class="row">
<div class="col-lg-8 sf-left">

<ul class="list-group sf-box">
    <li class="list-group-item sf-box-header sf-navi">
        <?php echo Html::a(Yii::t('app/admin', 'Forum Manager'), ['admin/setting/all']), '&nbsp;/&nbsp;', $this->title; ?>
    </li>
    <li class="list-group-item list-group-item-info"><strong><?php echo $this->title; ?></strong></li>
    <li class="list-group-item">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><?php echo Yii::t('app', 'Username'); ?></th>
                    <th><?php echo Yii::t('app', 'Current Email'); ?></th>
                    <th><?php echo Yii::t('app', 'New Email'); ?></th>
                    <th><?php echo Yii::t('app', 'Action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($tokens as $token): ?>
                <tr>
                    <td><?php echo Html::encode($token->user->username); ?></td>
                    <td><?php echo Html::encode($token->user->email); ?></td>
                    <td><?php echo Html::encode($token->ext); ?></td>
                    <td>
                        <?php echo Html::a(Yii::t('app', 'Approve'), ['approve-email', 'id' => $token->id], [
                            'class' => 'btn btn-sm btn-success',
                            'data' => ['confirm' => Yii::t('app', 'Are you sure you want to approve this email change?'), 'method' => 'post']
                        ]); ?>
                        <?php echo Html::a(Yii::t('app', 'Reject'), ['reject-email', 'id' => $token->id], [
                            'class' => 'btn btn-sm btn-danger',
                            'data' => ['confirm' => Yii::t('app', 'Are you sure you want to reject this email change?'), 'method' => 'post']
                        ]); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </li>
    <li class="list-group-item">
        <?php echo LinkPager::widget(['pagination' => $pages]); ?>
    </li>
</ul>

</div>

<div class="col-lg-4 sf-right">
<?php echo $this->render('@app/views/admin/setting/_left'); ?>
</div>

</div>
