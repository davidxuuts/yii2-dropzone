<?php
use yii\bootstrap4\Html;
use yii\base\Model;
use davidxu\base\helpers\StringHelper;
/**
 * @var Model $model
 * @var string $attribute
 * @var string $value
 * @var string $name
 * @var array $options
 * @var bool $hasModel
 * @var string $containerId
 */
echo Html::beginTag('div', ['class' => 'dropzone-container', 'id' => $containerId]);
if ($hasModel) {
    echo Html::activeHiddenInput($model, $attribute, $options);
} else {
    if (!array_key_exists('id', $options)) {
        $options['id'] = StringHelper::getInputId($name);
    }
    echo Html::hiddenInput($name, $value, $options);
}
?>
    <div class="row dropzone-previews" id = "previews_<?= $containerId ?>">
        <div class="col">
            <?= Html::tag(
                'div',
                '<i class="bi bi-plus"></i>',
                ['class' => 'fileinput-button', 'id' => 'fileinput_' . $containerId]
            ) ?>
        </div>
    </div>
<?= Html::endTag('div');
