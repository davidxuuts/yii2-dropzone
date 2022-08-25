<?php
use yii\bootstrap4\Html;
use yii\base\Model;

/**
 * @var Model $model
 * @var string $attribute
 * @var string $value
 * @var array $options
 * @var bool $hasModel
 */

echo $hasModel
    ? Html::activeHiddenInput($model, $attribute, $options)
    : Html::hiddenInput($name, $value, $options);
?>
<div class="row" id="previews">
    <div class="col">
        <div class="fileinput-button">
            <i class="fas fa-plus"></i>
        </div>
    </div>
</div>
<div id="template">
    <div class="col">
        <div class="preview"><img data-dz-thumbnail /></div>
        <div class="info">
            <p class="size text-right" data-dz-size></p>
            <p class="name text-center text-middle" data-dz-name></p>
        </div>
        <div
                class="progress active"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="0"
        >
            <div
                    class="progress-bar progress-bar-striped progress-bar-animated progress-bar-success"
                    style="width: 0%"
                    role="progressbar"
                    data-dz-uploadprogress
            ></div>
        </div>

    </div>
</div>

<div class="modal fade" id="dropzone-modal" aria-hidden="true" data-backdrop="static" style="display: none;" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title"><?= Yii::t('app', 'Basic information') ?></h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">
                <p><?= Yii::t('app', 'Loading ...') ?></p>
            </div>
            <div class="modal-footer">
                <?= Html::button(Yii::t('app', 'Close'), [
                    'class' => 'btn btn-secondary',
                    'data-dismiss' => 'modal'
                ]) ?>
                <?= Html::submitButton('OK', ['class' => 'btn btn-primary']) ?>
            </div>
        </div>
    </div>
</div>
