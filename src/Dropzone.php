<?php

namespace davidxu\dropzone;

use davidxu\base\enums\UploadTypeEnum;
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
    public $dropzoneName;
    public $lang;
    public $metaData = [];
    public $maxFiles = 1;
    public $maxFilesize;
    public $acceptedFiles = 'image/*';
    public $url;
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

    public function init()
    {
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
        $this->dropzoneName = 'dropzone_' . $this->id;

        $this->htmlOptions = [
//            'class' => $this->dropzoneClass,
            'id' => $this->dropzoneName,
        ];

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
    }

    public function run()
    {
        $this->registerClientScript();
        return $this->render('dropzone', [
            'model' => $this->model,
            'name' => $this->name,
            'value' => $this->value,
            'attribute' => $this->attribute,
            'options' => $this->htmlOptions,
            'hasModel' => $this->hasModel(),
        ]);
    }

    protected function registerClientScript()
    {
        $_view = $this->getView();
        $this->registerAssets($_view);
        $js = <<<JS
Dropzone.autoDiscover = false
JS;

        $_view->registerJs($js, View::POS_END);
        $script = <<<JS

Array.prototype.indexOf = function(val) { 
    for (var i = 0; i < this.length; i++) { 
        if (this[i] === val) return i; 
    } 
    return -1; 
}
Array.prototype.remove = function(val) { 
    var index = this.indexOf(val); 
    if (index > -1) { 
        this.splice(index, 1); 
    } 
}

// const getHash = function (file) {
//     return new Promise(function(resolve, reject) {
//         let hash = ''
//         let reader = new FileReader()
//         reader.readAsArrayBuffer(file)
//         reader.onload = () => {
//             hash = getEtag(reader.result)
//             resolve(hash)
//         }
//     })
// }
const crop = `{$this->crop}` ? true : false
let previewNode = document.querySelector('#template')
previewNode.id = ''
const previewTemplate = previewNode.innerHTML
previewNode.parentNode.removeChild(previewNode)
let existFiles = {$this->_encodedExistFiles}, cropOptions = {$this->_encodedCropOptions}, clientOptions = {$this->_encodedClientOptions}
clientOptions.previewTemplate = previewTemplate
let myDropzone = new Dropzone(document.body, clientOptions)
myDropzone.on("addedfile", function (file) {
    // if (!crop) {
    //     getHash(file).then(hash => {
    //         console.log('file hash', hash)
    //     })
    // }
})
myDropzone.on("addedfiles", function() {
    if (myDropzone.files.length >= myDropzone.options.maxFiles) {
        $('#previews').find('.fileinput-button').parent().addClass('none')
    }
})
myDropzone.on('sending', function (file, xhr, formData) {
    $.each({$this->_encodedMetaData}, function(key, value) {
        formData.append(key,value)
    })
    let key = '{$this->generateKey()}', extension = file.name.substr(file.name.lastIndexOf('.'))
    const mimeType = file.type.split('/', 1)[0]
    let fileType = 'others'
    if (mimeType === 'image') {
        fileType = 'images'
    } else if (mimeType === 'video') {
        fileType = 'videos'
    } else if (mimeType === 'audio') {
        fileType = 'audios'
    }
    formData.append('key', '{$this->uploadBasePath}' + fileType + '/' + key + extension)
    if ({$this->isQiniuDrive()}) {
        formData.append('x:file_type', fileType)
        formData.append('token', '{$this->getQiniuToken()}')
    }
})
myDropzone.on('error', (file, message) => {
    myDropzone.removeFile(file)
    Swal.fire({
        toast: true,
        position: 'top-end',
        title: myDropzone.options.dictResponseError,
        showConfirmButton: false,
        icon: 'error'
    })
})
// myDropzone.on('uploadprogress', function (file, progress, bytesSent) {
//     console.log('uploadprogress', file, progress, bytesSent)
// })
// myDropzone.on('complete', function (file) {
//     console.log('complete', file)
// })
myDropzone.on('success', function(file, response) {
    if (response.success === true || response.success === 'true') {
        if ({$this->storeInDB}) {
            if (myDropzone.options.maxFiles > 1) {
                let value = $('#{$this->dropzoneName}').val()
                let valueArray = value.split(',')
                if (valueArray.includes('0')) {
                    valueArray.remove('0')
                }
                valueArray.push(response.result.id)
                $('#{$this->dropzoneName}').val(valueArray.toString())
            } else {
                $('#{$this->dropzoneName}').val(response.result.id)
            }
        } else {
            if (myDropzone.options.maxFiles > 1) {
                let value = $('#{$this->dropzoneName}').val()
                let valueArray = value.split(',')
                valueArray.push(response.result.path)
                $('#{$this->dropzoneName}').val(valueArray.toString())
            } else {
                $('#{$this->dropzoneName}').val(response.result.pathes)
            }
        }
        $(file.previewElement).children('.progress').attr({"style":"opacity:0;"})
    } else {
        myDropzone.removeFile(file)
        Swal.fire({
            toast: true,
            position: 'top-end',
            title: response.result,
            showConfirmButton: false,
            icon: 'error'
        })
    }
})
myDropzone.on("maxfilesexceeded", function(file) {
    myDropzone.removeFile(file)
    $('#previews').find('.fileinput-button').parentNode.addClass('none')
})
myDropzone.on("removedfile", function(file) {
    let value = $('#{$this->dropzoneName}').val()
    let valueArray = value.split(',')    
    // Add array remove function
    Array.prototype.removeValue = function(v) {
        for(let i = 0, j = 0; i < this.length; i++) {
            if(this[i] != v) {
                this[j++] = this[i];
            }
        }
	    this.length -= 1;
    }
    
    if ({$this->storeInDB}) {
        valueArray.removeValue(file.id)
    } else {
        valueArray.removeValue(file.path)
    }
    $('#{$this->dropzoneName}').val(valueArray.toString())
    $('#previews').find('.fileinput-button').parent().removeClass('none')
})
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
    const cropper = new Cropper(image, cropOptions)
})
JS_TF;

        $dropzoneInit = /** @lang JavaScript */ <<< JS_INIT
function () {
    let myDropzone = this
    let typeOfExistFiles = 'undefined'
    if (Object.prototype.toString.call(existFiles) === '[object Array]') {
        typeOfExistFiles = 'array'
    }
    if (Object.prototype.toString.call(existFiles) === '[object Object]') {
        typeOfExistFiles = 'object'
    }
    // Is array
    if (typeOfExistFiles === 'array' && existFiles.length > 0) {
        let valueArray = []
        existFiles.map(function(existFile) {
            if (Object.prototype.toString.call(existFile) === '[object Object]' 
                && existFile.hasOwnProperty('name')
                && existFile.hasOwnProperty('size')
                && existFile.hasOwnProperty('path')
            ) {
                myDropzone.displayExistingFile(existFile, existFile.path)
                if ({$this->storeInDB}) {
                    valueArray.push(existFile.id)
                } else {
                    valueArray.push(existFile.path)
                }
                $(existFile.previewElement).children('.progress').attr({"style":"opacity:0;"})
            }
        })
        $('#{$this->dropzoneName}').val(valueArray.toString())
    }
    // Is object
    if (
        typeOfExistFiles === 'object'
        && existFiles.hasOwnProperty('name')
        && existFiles.hasOwnProperty('size')
        && existFiles.hasOwnProperty('path')
        ) {
            myDropzone.displayExistingFile(existFiles, existFiles.path)            
            if ({$this->storeInDB}) {
                $('#{$this->dropzoneName}').val(existFiles.id)
            } else {
                $('#{$this->dropzoneName}').val(existFiles.path)
            }
            $(existFiles.previewElement).children('.progress').attr({"style":"opacity:0;"})
    }
    if (
        (typeOfExistFiles === 'array' && existFiles.length >= myDropzone.options.maxFiles)
        || (typeOfExistFiles === 'object' && myDropzone.options.maxFiles <= 1)
    ) {
        $('#previews').find('.fileinput-button').parent().addClass('none')
    }
}
JS_INIT;

        $clientOptions = [
            'url' => $this->url,
            'paramName' => 'file',
            'addRemoveLinks' => true,
            'parallelUploads' => 20,
            'previewsContainer' => '#previews',
            'clickable' => '.fileinput-button',
            'init' => new JsExpression($dropzoneInit),
        ];
        if ($this->crop) {
            $clientOptions['transformFile'] = new JsExpression($transformFile);
        }
        $this->clientOptions = array_merge($this->clientOptions, $clientOptions);
        $translateOptions = $this->registerClientTranslations();
        $this->clientOptions = array_merge($translateOptions, $this->clientOptions);
    }

    protected function registerAssets($view)
    {
        DropzoneAsset::register($view);
        SweetAlert2Asset::register($view);
    }

    /**
     * @return bool whether this widget is associated with a data model.
     */
    protected function hasModel()
    {
        return $this->model instanceof Model && $this->attribute !== null;
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
