<?php
/**
 * @link https://610000.xyz/
 * @copyright Copyright (c) 2015 SimpleForum
 * @author Leon admin@610000.xyz
 */

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\LinkPager;

$this->title = Yii::t('app/admin', 'Topic Manager');
?>

<div class="row">
<div class="col-lg-8 sf-left">

<div class="card sf-box">
    <div class="card-header sf-box-header">
        <?php echo Html::encode($this->title); ?>
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo Url::to(['admin/topic-manager/index']); ?>" class="form-inline mb-3">
            <div class="form-group mr-2">
                <?php echo Html::dropDownList('node_id', $node_id, $nodes, ['prompt' => Yii::t('app', 'All Nodes'), 'class' => 'form-control']); ?>
            </div>
            <div class="form-group mr-2">
                <?php echo Html::dropDownList('tag_id', $tag_id, $tags, ['prompt' => Yii::t('app', 'All Tags'), 'class' => 'form-control']); ?>
            </div>
            <div class="form-group mr-2">
                <input type="text" name="q" value="<?php echo Html::encode($q); ?>" class="form-control" placeholder="<?php echo Yii::t('app', 'Search Title'); ?>">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo Yii::t('app', 'Filter'); ?></button>
            <a href="<?php echo Url::to(['admin/topic-manager/index']); ?>" class="btn btn-secondary ml-2"><?php echo Yii::t('app', 'Reset'); ?></a>
        </form>

        <?php echo Html::beginForm(['admin/topic-manager/batch-delete'], 'post', ['id' => 'batch-form']); ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th><input type="checkbox" id="select-all"></th>
                    <th>ID</th>
                    <th><?php echo Yii::t('app', 'Title'); ?></th>
                    <th><?php echo Yii::t('app', 'Node'); ?></th>
                    <th><?php echo Yii::t('app', 'Author'); ?></th>
                    <th><?php echo Yii::t('app', 'Created At'); ?></th>
                    <th><?php echo Yii::t('app', 'Action'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topics as $topic): ?>
                <tr>
                    <td><input type="checkbox" name="ids[]" value="<?php echo $topic->id; ?>" class="topic-checkbox"></td>
                    <td><?php echo $topic->id; ?></td>
                    <td><?php echo Html::a(Html::encode($topic->title), ['topic/view', 'id' => $topic->id], ['target' => '_blank']); ?></td>
                    <td><?php echo Html::encode($topic->node->name); ?></td>
                    <td><?php echo Html::encode($topic->author->username); ?></td>
                    <td><?php echo Yii::$app->getFormatter()->asDatetime($topic->created_at, 'short'); ?></td>
                    <td>
                        <?php echo Html::a(Yii::t('app', 'Edit'), ['admin/topic/edit', 'id' => $topic->id], ['class' => 'btn btn-sm btn-primary']); ?>
                        <?php echo Html::a(Yii::t('app', 'Delete'), ['admin/topic-manager/delete', 'id' => $topic->id], [
                            'class' => 'btn btn-sm btn-danger',
                            'data' => [
                                'confirm' => Yii::t('app', 'Are you sure you want to delete this item?'),
                                'method' => 'post',
                            ],
                        ]); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="mt-3 d-flex justify-content-between align-items-center">
            <div>
                <?php echo Html::submitButton(Yii::t('app', 'Batch Delete'), [
                    'class' => 'btn btn-danger',
                    'data' => [
                        'confirm' => Yii::t('app', 'Are you sure you want to delete selected items?'),
                    ],
                ]); ?>
            </div>
            <div>
                <?php echo LinkPager::widget(['pagination' => $pages]); ?>
            </div>
        </div>
        <?php echo Html::endForm(); ?>
    </div>
</div>

</div>

<div class="col-lg-4 sf-right">
<?php echo $this->render('@app/views/common/_admin-right'); ?>
</div>

</div>

<?php
$js = <<<JS
$('#select-all').click(function() {
    $('.topic-checkbox').prop('checked', this.checked);
});
JS;
$this->registerJs($js);
?>
