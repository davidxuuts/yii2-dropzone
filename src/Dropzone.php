<?php

namespace davidxu\dropzone;

use davidxu\base\enums\UploadTypeEnum;
use davidxu\base\helpers\StringHelper;
use davidxu\config\helpers\ArrayHelper;
use davidxu\sweetalert2\assets\SweetAlert2Asset;
use Qiniu\Auth;
use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\base\Widget;
use davidxu\dropzone\assets\DropzoneAsset;
use davidxu\base\enums\QiniuUploadRegionEnum;
use yii\i18n\PhpMessageSource;
use yii\web\JsExpression;
use yii\web\View;

class Dropzone extends Widget
{
    public $clientOptions = [];
    public $lang;
    public $metaData = [];
    public $maxFiles = 9;
    public $maxFilesize;
    public $acceptedFiles;
    public $url;
    public $headers = [];
    public $uploadBasePath = 'uploads/';
    public $drive = UploadTypeEnum::DRIVE_LOCAL;
    public $crop = false;
    public $aspectRatio = 1 / 1;
    public $cropOptions = [];

    public $model;
    public $attribute;
    public $name;
    public $value;
    public $htmlOptions = [];
    public $storeInDB = true;

    // Qiniu
    public $qiniuBucket;
    public $qiniuAccessKey;
    public $qiniuSecretKey;
    public $qiniuCallbackUrl;
    public $qiniuCallbackBody = [
        'drive' => UploadTypeEnum::DRIVE_QINIU,
        'specific_type' => '$(mimeType)',
        'file_type' => '$(x:file_type)',
        'path' => '$(key)',
        'hash' => '$(etag)',
        'size' => '$(fsize)',
        'name' => '$(fname)',
        'extension' => '$(ext)',
        'member_id' => '$(x:member_id)',
        'width' => '$(imageInfo.width)',
        'height' => '$(imageInfo.height)',
        'duration' => '$(avinfo.format.duration)',
        'store_in_db' => '$(x:store_in_db)',
        'upload_ip' => '$(x:upload_ip)',
    ];

    /** @var array {name, size, path, id if store in db} */
    public $existFiles = [];

    private $_encodedClientOptions;
    private $_encodedMetaData;
    private $_encodedCropOptions;
    private $_encodedExistFiles;
    private $_storeInDB;
    private $containerId;

    public function init()
    {
        $this->containerId = 'dz' . StringHelper::generateRandomString(14);
        $this->registerTranslations();
        if ($this->name === null && !$this->hasModel()) {
            throw new InvalidConfigException("Either 'name', or 'model' and 'attribute' properties must be specified.");
        }

        if (empty($this->name) && (!empty($this->model) && !empty($this->attribute))) {
            $this->name = Html::getInputName($this->model, $this->attribute);
        }

        // Qiniu
        if ($this->drive === UploadTypeEnum::DRIVE_QINIU) {
            if (empty($this->qiniuCallbackUrl) || $this->qiniuCallbackUrl === '') {
                if (!isset(Yii::$app->params['qiniu.callbackUrl']) || Yii::$app->params['qiniu.callbackUrl'] === '') {
                    throw new InvalidConfigException(Yii::t('dropzone', 'Invalid configuration: {attribute}', [
                        'attribute' => 'qiniu.callbackUrl',
                    ]));
                }
                $this->qiniuCallbackUrl = Yii::$app->params['qiniu.callbackUrl'];
            }

            if (empty($this->qiniuBucket) || $this->qiniuBucket === '') {
                if (!isset(Yii::$app->params['qiniu.bucket']) || Yii::$app->params['qiniu.bucket'] === '') {
                    throw new InvalidConfigException(Yii::t('dropzone', 'Invalid configuration: {attribute}', [
                        'attribute' => 'qiniu.bucket',
                    ]));
                }
                $this->qiniuBucket = Yii::$app->params['qiniu.bucket'];
            }

            if (empty($this->qiniuAccessKey) || $this->qiniuAccessKey === '') {
                if (!isset(Yii::$app->params['qiniu.accessKey']) || Yii::$app->params['qiniu.accessKey'] === '') {
                    throw new InvalidConfigException(Yii::t('dropzone', 'Invalid configuration: {attribute}', [
                        'attribute' => 'qiniu.accessKey',
                    ]));
                }
                $this->qiniuAccessKey = Yii::$app->params['qiniu.accessKey'];
            }

            if (empty($this->qiniuSecretKey) || $this->qiniuSecretKey === '') {
                if (!isset(Yii::$app->params['qiniu.secretKey']) || Yii::$app->params['qiniu.secretKey'] === '') {
                    throw new InvalidConfigException(Yii::t('dropzone', 'Invalid configuration: {attribute}', [
                        'attribute' => 'qiniu.secretKey',
                    ]));
                }
                $this->qiniuSecretKey = Yii::$app->params['qiniu.secretKey'];
            }
            if (!in_array($this->url, QiniuUploadRegionEnum::getMap())) {
                throw new InvalidConfigException(Yii::t('dropzone', 'Invalid configuration: {attribute}', [
                    'attribute' => 'URL',
                ]));
            }
        }

        parent::init();
        $this->lang = $this->lang ?? Yii::$app->language;
        if ($this->drive === UploadTypeEnum::DRIVE_LOCAL) {
            $this->metaData['file_field'] = $this->name;
            $this->metaData['store_in_db'] = $this->storeInDB;
            if (Yii::$app->request->enableCsrfValidation) {
                $this->metaData[Yii::$app->request->csrfParam] = Yii::$app->request->getCsrfToken();
            }
        }
        if ($this->drive === UploadTypeEnum::DRIVE_QINIU) {
            $this->metaData = ArrayHelper::merge([
                'x:store_in_db' => $this->storeInDB,
                'x:member_id' => Yii::$app->user->isGuest ? 0 : Yii::$app->user->id,
                'x:upload_ip' => Yii::$app->request->remoteIP,
            ], $this->metaData);
        }

        $this->configureClientOptions();
        $this->_encodedClientOptions = Json::encode(array_merge([
                'acceptedFiles' => $this->acceptedFiles,
                'maxFiles' => $this->crop ? 1 : $this->maxFiles,
                'maxFilesize' => $this->maxFilesize ?? null,
            ], $this->clientOptions)
        );
        $this->_encodedMetaData = Json::encode($this->metaData);
        $this->cropOptions = array_merge([
            'aspectRatio' => $this->aspectRatio,
        ], $this->cropOptions);
        $this->_encodedCropOptions = Json::encode($this->cropOptions);
        $this->_encodedExistFiles = Json::encode($this->existFiles);
        $this->_storeInDB = $this->storeInDB ? 'true' : 'false';
    }

    public function run()
    {
        $this->registerClientScript();
        return $this->renderPreviewContainer();
    }

    protected function renderPreviewContainer()
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

    protected function registerClientScript()
    {
        $_view = $this->getView();
        $this->registerAssets($_view);
        $script = <<<JS
let myDropzone_{$this->containerId} = new Dropzone('#{$this->containerId}', {$this->_encodedClientOptions})
myDropzone_{$this->containerId}.options.init = init(
    myDropzone_{$this->containerId}, 
    $('#{$this->getFieldId()}'), 
    {$this->_encodedExistFiles}, 
    {$this->_storeInDB}
)
if ($.isFunction(dropzoneEvents)) {
    dropzoneEvents(
        myDropzone_{$this->containerId}, 
        $('#{$this->getFieldId()}'), 
        $this->_encodedMetaData, 
        {$this->_storeInDB}, 
        '{$this->uploadBasePath}', 
        '{$this->generateKey()}', 
        {$this->isQiniuDrive()},
        '{$this->getQiniuToken()}'
    )
}
JS;
        $_view->registerJs($script, View::POS_READY);
    }

    private function configureClientOptions()
    {
        $transformFile = /** @lang JavaScript */ <<<JS_TF
(function (file, done) {
    $('#dropzone-modal').modal('show')
    let submitButton = $('#dropzone-modal').find('button[type="submit"]')
    let myDropZone = this
    submitButton.on('click', function(event) {
        event.preventDefault()
        var canvas = cropper.getCroppedCanvas({width: 256, height: 256})
        canvas.toBlob(function(blob) {
            myDropZone.createThumbnail(
                blob,
                myDropZone.options.thumbnailWidth,
                myDropZone.options.thumbnailHeight,
                myDropZone.options.thumbnailMethod,
                false, 
                function(dataURL) {
                    myDropZone.emit('thumbnail', file, dataURL)
                    done(blob)
            })
        })
        $('#dropzone-modal').modal('hide')
    })
    const image = new Image()
    image.src = URL.createObjectURL(file)
    
    $('#dropzone-modal').find('.img-container').html(image)
    const cropper = new Cropper(image, {$this->_encodedCropOptions})
})
JS_TF;
        $previewTemplate = '<div class="col">'
            . '    <div class="preview"><img data-dz-thumbnail /></div>'
            . '        <div class="info">'
            . '            <p class="size text-right" data-dz-size></p>'
            . '            <p class="name text-center text-middle" data-dz-name></p>'
            . '        </div>'
            . '    <div class="progress active" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">'
            . '        <div'
            . '            class="progress-bar progress-bar-striped progress-bar-animated progress-bar-success"'
            . '            style="width: 0%" role="progressbar" data-dz-uploadprogress>'
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
        if ($this->crop) {
            $clientOptions['transformFile'] = new JsExpression($transformFile);
        }
        $this->clientOptions = array_merge($this->clientOptions, $clientOptions);
        $translateOptions = $this->registerClientTranslations();
        $this->clientOptions = array_merge($translateOptions, $this->clientOptions);
    }

    protected function registerAssets($view)
    {
        SweetAlert2Asset::register($view);
    }

    /**
     * @return bool whether this widget is associated with a data model.
     */
    protected function hasModel()
    {
        return $this->model instanceof Model && $this->attribute !== null;
    }

    protected function getFieldId()
    {
        return $this->hasModel() ? Html::getInputId($this->model, $this->attribute) : StringHelper::getInputId($this->name);
    }

    protected function getQiniuToken()
    {
        $auth = new Auth($this->qiniuAccessKey, $this->qiniuSecretKey);
        $policy = [
            'callbackUrl' => $this->qiniuCallbackUrl,
            'callbackBody' => Json::encode($this->qiniuCallbackBody),
            'callbackBodyType' => 'application/json',
        ];
        return $auth->uploadToken($this->qiniuBucket, null, 3600, $policy);
    }

    protected function generateKey()
    {
        return Yii::$app->security->generateRandomString();
    }

    protected function isLocalDrive()
    {
        return $this->drive === UploadTypeEnum::DRIVE_LOCAL ? 'true' : 'false';
    }

    protected function isQiniuDrive()
    {
        return $this->drive === UploadTypeEnum::DRIVE_QINIU ? 'true' : 'false';
    }

    protected function isCosDrive()
    {
        return $this->drive === UploadTypeEnum::DRIVE_COS ? 'true' : 'false';
    }

    protected function isOssDrive()
    {
        return $this->drive === UploadTypeEnum::DRIVE_OSS ? 'true' : 'false';
    }

    /**
     * @return array|mixed
     */
    protected function registerClientTranslations()
    {
        $translateFile = __DIR__ . '/messages/' . $this->lang . '/dropzone.php';
        if (file_exists($translateFile)) {
            $translate = require $translateFile;
        } else {
            $translate = [];
        }
        return $translate;
    }

    protected function registerTranslations()
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
