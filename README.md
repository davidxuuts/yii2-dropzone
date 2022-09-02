Yii2 Dropzone
=============
A dropzone uploader extension for Yii2

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist davidxu/yii2-dropzone "^1.0.0"
```

or add

```
"davidxu/yii2-dropzone": "^1.0.0"
```

to the require section of your `composer.json` file.


Usage
-----
If you want to store files information in DB, please excute migration file by
```
yii migrate/up @davidxu/dropzone/migrations
```
and then simply use it in your code by:

### for Local upload

------

##### In View
```php
<?php
use davidxu\dropzone\Dropzone;
use yii\helpers\Url;

// without ActiveForm
echo Dropzone::widget([
    'model' => $model,
    'attribute' => 'image_src',
    'name' => 'image_src', // If no model and attribute pointed
    'url' => Url::to('@web/upload/local'),
    'maxFiles' => 3,
    'acceptedFiles' => 'image/*',
    'uploadBasePath' => 'uploads/',
    // for single file,
    'existFiles' => [
        'id' => 1,
        'name' => 'some_name.jpg',
        'path' => 'some_path_for_file',
        'size' => 1111,
    ],
    // for multiple files
//    'existFiles' => [
//        [
//            'id' => 1,
//            'name' => 'some_name.jpg',
//            'path' => 'some_path_for_file',
//            'size' => 1111,
//        ], [
//            'id' => 2,
//            'name' => 'some_other_name.jpg',
//            'path' => 'some_path_for_other_file',
//            'size' => 2222,
//        ],
//    ],
    'storeInDB' => false, // return file id in DB to image url instead of file url if true, migrate model db first
    'metaData' => ['foo' => 'bar',],
    'crop' => true, // default false, if true, the 'maxFiles' will be forced to 1
]); ?>

<?php
// with ActiveForm
echo $form->field($model, 'image_src')
    ->widget(Dropzone::class, [
   'url' => Url::to('@web/upload/local'),
    'maxFiles' => 3,
    'acceptedFiles' => 'image/*',
    'uploadBasePath' => 'uploads/',
    // for single file,
    'existFiles' => [
        'id' => 1,
        'name' => 'some_name.jpg',
        'path' => 'some_path_for_file',
        'size' => 1111,
    ],
    // for multiple files
//    'existFiles' => [
//        [
//            'id' => 1,
//            'name' => 'some_name.jpg',
//            'path' => 'some_path_for_file',
//            'size' => 1111,
//        ], [
//            'id' => 2,
//            'name' => 'some_other_name.jpg',
//            'path' => 'some_path_for_other_file',
//            'size' => 2222,
//        ],
//    ],
    'storeInDB' => false,
    'metaData' => ['foo' => 'bar',],
]);?>

```

##### In Upload Controller:
```php
use davidxu\dropzone\actions\LocalAction;
use davidxu\dropzone\models\Attachment;
use yii\web\Controller;

class UploadController extends Controller
{
    public function actions(): array
    {
        $actions = parent::actions();
        return ArrayHelper::merge([
            'local' => [
                'class' => LocalAction::class,
                'url' => Yii::getAlias('@web/uploads'), // default: '@web/uploads'. stored file base url,
                'fileDir' => Yii::getAlias('@webroot/uploads'), // default: '@webroot/uploads'. file store in this dirctory,
                'allowAnony' => true, // default false
                'attachmentModel' => Attachment::class,
            ],
        ], $actions);
    }
}
```

### for Qiniu upload

------

##### In View
```php
<?php
use davidxu\dropzone\Dropzone;
use davidxu\base\enums\QiniuUploadRegionEnum;
use davidxu\base\enums\UploadTypeEnum;
use yii\helpers\Url;

echo Dropzone::widget([
    'model' => $model,
    'attribute' => 'image_src',
    'name' => 'image_src', // If no model and attribute pointed
    'url' => QiniuUploadRegionEnum::getValue(QiniuUploadRegionEnum::EC_ZHEJIANG_2),
    'drive' => UploadTypeEnum::DRIVE_QINIU,
    // ...... (refer to local config in view)
]); ?>

<?php
// with ActiveForm
echo $form->field($model, 'image_src')
    ->widget(Dropzone::class, [
    'url' => QiniuUploadRegionEnum::getValue(QiniuUploadRegionEnum::EC_ZHEJIANG_2),
    'drive' => UploadTypeEnum::DRIVE_QINIU,
    'qiniuBucket' => Yii::$app->params['qiniu.bucket'],
    'qiniuAccessKey' => Yii::$app->params['qiniu.bucket'],
    'qiniuSecretKey' => Yii::$app->params['qiniu.bucket'],
    'qiniuCallbackUrl' => Yii::$app->params['qiniu.bucket'],
    // default 'qiniuCallbackBody' here, you can modify them.
    'qiniuCallbackBody' => [
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
    // ...... (refer to local config in view)
]);?>

```

##### In Upload Controller:
```php
use davidxu\dropzone\actions\QiniuAction;
use davidxu\dropzone\models\Attachment;
use yii\web\Controller;
use yii\web\BadRequestHttpException;

class UploadController extends Controller
{
     /**
     * @throws BadRequestHttpException
     */
    public function beforeAction($action): bool
    {
        $currentAction = $action->id;
        $novalidateActions = ['qiniu'];
        if(in_array($currentAction, $novalidateActions)) {
            // disable CSRF validation
            $action->controller->enableCsrfValidation = false;
        }
        parent::beforeAction($action);
        return true;
    }
    public function actions(): array
    {
        $actions = parent::actions();
        return ArrayHelper::merge([
            'qiniu' => [
                'class' => QiniuAction::class,
                'url' => Yii::getAlias('@web/uploads'), // default: '@web/uploads'. stored file base url,
                'allowAnony' => true, // default false
                'attachmentModel' => Attachment::class,
            ],
        ], $actions);
    }
}
```

Have fun!
