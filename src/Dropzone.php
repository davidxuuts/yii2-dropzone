<?php

namespace davidxu\dropzone;

use davidxu\base\assets\DropzoneAsset;
use davidxu\base\assets\QETagAsset;
use davidxu\base\assets\QiniuJsAsset;
use davidxu\base\enums\UploadTypeEnum;
use davidxu\base\helpers\StringHelper;
use davidxu\base\widgets\InputWidget;
use davidxu\base\assets\SweetAlert2Asset;
use Yii;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\i18n\PhpMessageSource;
use yii\web\View;

class Dropzone extends InputWidget
{
    public ?int $maxFilesize = null;
    public string|array|null $acceptedFiles = 'image/*';
    public ?array $headers = [];
    public bool $secondUpload = false;

    public array $htmlOptions = [];

    /** @var ?array {name, size, path, id if store in db} */
    public ?array $existFiles = [];

    private string $_encodedClientOptions = '';
    private string $containerId = '';

    /**
     * @throws InvalidConfigException
     * @throws Exception
     */
    public function init(): void
    {
        parent::init();
        $this->containerId = 'dz' . StringHelper::generateRandomString(14);
        if ($this->drive === UploadTypeEnum::DRIVE_QINIU) {
            $this->url = 'http://fake';
        }
        $this->registerTranslations();

        $this->configureClientOptions();
        $clientOptions = [
            'acceptedFiles' => $this->acceptedFiles,
            'maxFiles' => $this->maxFiles,
            'maxFilesize' => $this->maxFilesize ?? null,
            'autoProcessQueue' => false,
        ];

        if ($this->drive === UploadTypeEnum::DRIVE_LOCAL) {
            $clientOptions = array_merge($clientOptions, [
                'chunking' => true,
                'forceChunking' => true,
                'chunkSize' => $this->chunkSize,
                'retryChunks' => true,
            ]);
        }
        $this->_encodedClientOptions = Json::encode(array_merge($clientOptions, $this->clientOptions)
        );
        $this->registerAssets($this->_view);
    }

    /**
     * @return string
     */
    public function run(): string
    {
        $this->registerClientScript();
        return $this->renderPreviewContainer();
    }

    protected function renderPreviewContainer(): string
    {
        return $this->render('dropzone', [
            'model' => $this->model,
            'name' => $this->name,
            'value' => $this->value,
            'attribute' => $this->attribute,
            'options' => $this->htmlOptions,
            'containerId' => $this->containerId,
            'hasModel' => $this->hasModel(),
        ]);
    }

    /**
     * @return void
     */
    protected function registerClientScript(): void
    {
        $_view = $this->getView();
        $script = /**  @lang JavaScript */ <<< JS
let myDropzone_$this->containerId = new Dropzone(`#$this->containerId`, $this->_encodedClientOptions)
if ($.isFunction(dropzoneInit)) {
    myDropzone_{$this->containerId}.options.init = dropzoneInit(
        myDropzone_$this->containerId, 
        $('#{$this->getFieldId()}'), 
        $this->_encodedExistFiles, 
        $this->_storeInDB,
        `$this->drive`
    )
}
dropzoneEvents(
    myDropzone_$this->containerId, 
    $(`#{$this->getFieldId()}`), 
    $this->_encodedMetaData,
    $this->_storeInDB, 
    `$this->uploadBasePath`,
    `$this->drive`,
    `{$this->getQiniuToken()}`,
    $this->_secondUpload,
    `$this->getHashUrl`
)
JS;
        $_view->registerJs($script);
    }

    private function configureClientOptions(): void
    {
        $previewTemplate = '<div class="col">'
            . '    <div class="preview"><img data-dz-thumbnail alt="unknown" /></div>'
            . '        <div class="info">'
            . '            <p class="size text-right" data-dz-size></p>'
            . '            <p class="name text-center text-middle" data-dz-name></p>'
            . '        </div>'
            . '    <div class="progress active" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">'
            . '        <div'
            . '            class="progress-bar progress-bar-striped progress-bar-animated progress-bar-success"'
            . '            style="width: 0" role="progressbar" data-dz-uploadprogress>'
            . '        </div>'
            . '    </div>'
            . '</div>';
        $clientOptions = [
            'url' => $this->url,
            'paramName' => 'file',
            'addRemoveLinks' => true,
            'parallelUploads' => 20,
            'previewsContainer' => '#previews_' . $this->containerId,
            'clickable' => '#fileinput_' . $this->containerId,
            'previewTemplate' => $previewTemplate,
        ];
        if ($this->headers) {
            $clientOptions['headers'] = $this->headers;
        }
        $this->clientOptions = array_merge($this->clientOptions, $clientOptions);
        $translateOptions = $this->registerClientTranslations();
        $this->clientOptions = array_merge($translateOptions, $this->clientOptions);
    }

    /**
     * @param string|View $view
     * @return DropzoneAsset
     */
    protected function registerAssets(string|View $view): DropzoneAsset
    {
        SweetAlert2Asset::register($view);
        if ($this->drive === UploadTypeEnum::DRIVE_QINIU) {
            QiniuJsAsset::register($view);
        }
        if ($this->secondUpload) {
            QETagAsset::register($view);
        }
        return DropzoneAsset::register($view);
    }

    protected function getFieldId(): string
    {
        return $this->hasModel() ? Html::getInputId($this->model, $this->attribute) : StringHelper::getInputId($this->name);
    }

    /**
     * @return array|mixed
     */
    protected function registerClientTranslations(): mixed
    {
        $translateFile = __DIR__ . '/messages/' . $this->lang . '/dropzone.php';
        if (file_exists($translateFile)) {
            $translate = require $translateFile;
        } else {
            $translate = [];
        }
        return $translate;
    }

    protected function registerTranslations(): void
    {
        $i18n = Yii::$app->i18n;
        $i18n->translations['dropzone*'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en-US',
            'basePath' => Yii::getAlias('@davidxu/dropzone/messages'),
            'fileMap' => [
                'dropzone' => 'dropzone.php',
            ],
        ];
    }
}
