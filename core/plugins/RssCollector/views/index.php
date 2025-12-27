<?php
use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'RSS Collector';
?>
<div class="row">
    <div class="col-lg-8 sf-left">
        <ul class="list-group sf-box">
            <li class="list-group-item sf-box-header">
                <?= Html::a('Admin', ['admin/setting/all']) ?> / <?= Html::a('Plugins', ['admin/plugin/index']) ?> / RSS Collector
            </li>
            <li class="list-group-item sf-box-form">
                <?php if (!empty($settings['auto_collect_enabled']) && $settings['auto_collect_enabled'] == '1'): ?>
                <div class="alert alert-success">
                    <strong>Auto Collection Status: ENABLED</strong><br>
                    Feeds will be automatically collected when users visit the site based on the configured intervals.
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <strong>Auto Collection Status: DISABLED</strong><br>
                    Enable auto collection in plugin settings to collect feeds automatically.
                </div>
                <?php endif; ?>
                
                <p><strong>Current Feeds Configuration:</strong></p>
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th>RSS URL</th>
                            <th>Node ID</th>
                            <th>User ID</th>
                            <th>Keywords</th>
                            <th>Interval (min)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeds as $line): 
                            $parts = explode('|', trim($line));
                            if (count($parts) < 3) continue;
                        ?>
                        <tr>
                            <td><?= Html::encode($parts[0]) ?></td>
                            <td><?= Html::encode($parts[1]) ?></td>
                            <td><?= Html::encode($parts[2]) ?></td>
                            <td><?= isset($parts[3]) && !empty(trim($parts[3])) ? Html::encode($parts[3]) : '<span class="text-muted">All</span>' ?></td>
                            <td><?= isset($parts[4]) && !empty(trim($parts[4])) ? Html::encode($parts[4]) : '<span class="text-muted">60</span>' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><?= Html::a('Change Configuration', ['admin/plugin/settings', 'pid' => 'RssCollector'], ['class' => 'btn btn-secondary']) ?></p>
                <hr>
                <button id="btn-collect" class="btn btn-primary">Collect All Feeds Manually</button>
                <div id="collect-result" style="margin-top: 20px;"></div>
            </li>
        </ul>
    </div>
    <div class="col-lg-4 sf-right">
        <?= $this->render('@app/views/common/_admin-right') ?>
    </div>
</div>

<?php
$collectUrl = Url::to(['admin/rss-collector/collect']);
$js = <<<JS
$('#btn-collect').click(function() {
    var btn = $(this);
    btn.prop('disabled', true).text('Collecting...');
    $('#collect-result').html('<div class="alert alert-info">Collecting content, please wait...</div>');
    
    $.getJSON('$collectUrl', function(data) {
        btn.prop('disabled', false).text('Collect All Feeds Manually');
        var alertClass = 'alert-info';
        if (data.status === 'success') alertClass = 'alert-success';
        if (data.status === 'error') alertClass = 'alert-danger';
        if (data.status === 'warning') alertClass = 'alert-warning';
        
        $('#collect-result').html('<div class="alert ' + alertClass + '">' + data.message + '</div>');
    }).fail(function() {
        btn.prop('disabled', false).text('Collect All Feeds Manually');
        $('#collect-result').html('<div class="alert alert-danger">An error occurred during collection.</div>');
    });
});
JS;
$this->registerJs($js);
?>
