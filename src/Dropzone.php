<?php

namespace davidxu\dropzone;

use Yii;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\base\Widget;
use davidxu\dropzone\assets\DropzoneAsset;
use yii\web\JsExpression;
use yii\web\View;

class Dropzone extends Widget
{
    public $clientOptions = [];
    public $dropzoneClass = 'dropzone';
    public $dropzoneName;
    public $lang;
    public $metaData = [];
    public $maxFiles = 1;
    public $acceptedFiles = 'image/*';
    public $url;
    public $crop = false;
    public $aspectRatio = 1;
    public $cropOptions = [];

    public $model;
    public $attribute;
    public $name;
    public $value;
    public $htmlOptions = [];

    public $message;
    public $messageOptions = [];

    private $_encodedClientOptions;
    private $_encodedMetaData;
    private $_encodedCropOptions;

    public function init()
    {
        if ($this->name === null && !$this->hasModel()) {
            throw new InvalidConfigException("Either 'name', or 'model' and 'attribute' properties must be specified.");
        }

        if (empty($this->name) && (!empty($this->model) && !empty($this->attribute))) {
            $this->name = Html::getInputName($this->model, $this->attribute);
        }

        parent::init();
        $this->lang = $this->lang ?? Yii::$app->language;
        $this->dropzoneName = 'dropzone_' . $this->id;

        $this->htmlOptions = [
            'class' => $this->dropzoneClass,
            'id' => $this->dropzoneName,
        ];

        if (Yii::$app->request->enableCsrfValidation) {
            $this->metaData[Yii::$app->request->csrfParam] = Yii::$app->request->getCsrfToken();
        }

        $this->configureClientOptions();
        $this->_encodedClientOptions = Json::encode(array_merge([
                'acceptedFiles' => $this->acceptedFiles,
                'maxFiles' => $this->crop ? 1 : $this->maxFiles,
                'maxFiles' => $this->maxFiles,
                'maxFilesize' => 2,
            ], $this->clientOptions)
        );
        $this->_encodedMetaData = Json::encode($this->metaData);
        $this->_encodedCropOptions = Json::encode(array_merge(
            [
                'aspectRatio' => $this->aspectRatio,
            ], $this->cropOptions
        ));
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
let previewNode = document.querySelector('#template')
previewNode.id = ''
const previewTemplate = previewNode.innerHTML
previewNode.parentNode.removeChild(previewNode)

const clientOptions = {$this->_encodedClientOptions}
clientOptions.previewTemplate = previewTemplate
let myDropzone = new Dropzone(document.body, clientOptions)
myDropzone.on('sending', function (file, xhr, formData) {
    $.each({$this->_encodedMetaData},function(key,value) {
        formData.append(key,value);
    })
})
myDropzone.on('success', function(file, json) {
   console.log('success')
   console.log('file', file)
   console.log('json', json)
   $(file.previewElement).children('.progress').attr({"style":"opacity:0;"})
})
myDropzone.on("maxfilesexceeded", function(file) {
   myDropzone.removeFile(file);
   $('#previews').find('.fileinput-button').parentNode.addClass('none')
})
myDropzone.on("addedfiles", function() {
    if (myDropzone.files.length >= myDropzone.options.maxFiles) {
        $('#previews').find('.fileinput-button').parent().addClass('none')
    }
})
myDropzone.on("removedfile", function(file) {
    $('#previews').find('.fileinput-button').parent().removeClass('none')
})
$('#dropzone-modal').on('hidden.bs.modal', function() {
    $('#dropzone-modal').find('.modal-body').html('')
})
JS;
        $_view->registerJs($script, View::POS_READY);
    }

    private function configureClientOptions()
    {
        $transformFile = /** @lang JavaScript */ <<<JS_TF
function (file, done) {
    $('#dropzone-modal').modal('show')
    let submitButton = $('#dropzone-modal').find('button[type="submit"]')
    let myDropZone = this
    submitButton.on('click', function() {
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
    $('#dropzone-modal').find('.modal-body').html(image)
    const cropper = new Cropper(image, {$this->_encodedCropOptions})
}
JS_TF;

        $dropzoneInit = /** @lang JavaScript */ <<< JS_INIT
function () {
    console.log('init')
    let myDropzone = this
    console.log('myDropzone', myDropzone)
    // let existFiles = { name: '123.jpg', size: 456 }
    // myDropzone.emit("addedfile", existFiles)
    // myDropzone.emit("thumbnail", existFiles, value.path)
    // myDropzone.emit("complete", existFiles)
}
JS_INIT;


        $clientOptions = [
            'url' => $this->url,
            'paramName' => $this->name,
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
        $translateOptions = $this->registerTranslations();
        $this->clientOptions = array_merge($translateOptions, $this->clientOptions);
    }

    protected function registerAssets($view)
    {
        DropzoneAsset::register($view);
    }

    /**
     * @return bool whether this widget is associated with a data model.
     */
    protected function hasModel()
    {
        return $this->model instanceof Model && $this->attribute !== null;
    }

    /**
     * @return array|mixed
     */
    protected function registerTranslations()
    {
        $translateFile = __DIR__ . '/messages/' . $this->lang . '/dropzone.php';
        if (file_exists($translateFile)) {
            $translate = require $translateFile;
        } else {
            $translate = [];
        }
        return $translate;
    }
}
