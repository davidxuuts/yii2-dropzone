<?php

namespace davidxu\dropzone\actions;

use davidxu\base\enums\AttachmentTypeEnum;
use davidxu\base\enums\UploadTypeEnum;
use davidxu\dropzone\models\Attachment;
use Qiniu\Etag;
use yii\base\Action;
use Yii;
use yii\db\ActiveRecord;
use yii\helpers\Json;
use yii\web\Response;
use yii\i18n\PhpMessageSource;
use FFMpeg\FFProbe;
use yii\web\UploadedFile;
use yii\base\Exception;

class BaseAction extends Action
{
    /** @var string */
    public $url;

    /** @var string */
    public $fileDir;

    /** @var bool */
    public $allowAnony = false;

//    /** @var bool  */
//    public $storeInDB = true;
    /** @var ActiveRecord  */
    public $attachmentModel = Attachment::class;

    public function init()
    {
        parent::init();
        $this->registerTranslations();
        if (Yii::$app->user->isGuest && !$this->allowAnony) {
            $result = [
                'error' => [
                    'message' => Yii::t('dropzone', 'Anonymous user is not allowed, please login first'),
                ],
            ];
            Yii::$app->response->format = Response::FORMAT_JSON;
            return $result;
        }
    }

    /**
     * @param UploadedFile $file
     * @param array $params
     * @param string $url
     * @param string $dir
     * @return array
     * @throws Exception
     */
    protected function localInfo($file, $params, $url, $dir) {
        $mime = (explode('/', $file->type))[0];
        if ($mime === 'image') {
            $mimeType = AttachmentTypeEnum::TYPE_IMAGES;
        } elseif ($mime === 'audio') {
            $mimeType = AttachmentTypeEnum::TYPE_AUDIOS;
        } elseif ($mime === 'video') {
            $mimeType = AttachmentTypeEnum::TYPE_VIDEOS;
        } else {
            $mimeType = AttachmentTypeEnum::TYPE_OTHERS;
        }
        if (substr($url, -1) === '/') {
            $url = rtrim($url);
        }
        if (substr($dir, -1) === '/') {
            $dir = rtrim($dir);
        }
        if ($params['key']) {
            if (substr($key, 1) === '/') {
                $key = ltrim($key, 1);
            }
            $urlPath = $url . DIRECTORY_SEPARATOR . $key;
            $savePath = $dir . DIRECTORY_SEPARATOR . $key;
        } else {
            $relativePath = DIRECTORY_SEPARATOR
                . $mimeType . DIRECTORY_SEPARATOR
                . date('Ymd') . DIRECTORY_SEPARATOR
                . Yii::$app->security->generateRandomString() . '.' . $file->extension;
            $urlPath = $url . $relativePath;
            $savePath = $dir . $relativePath;
        }
        $result = $this->saveLocal($file, $savePath);
        if ($result && $mimeType === 'images') {
            [$width, $height] = getimagesize($savePath);
        }
        $info = [
            'member_id' => Yii::$app->user->isGuest ? 0 : Yii::$app->user->id,
            'drive' => UploadTypeEnum::DRIVE_LOCAL,
            'specific_type' => $file->type,
            'file_type' => $mimeType,
            'path' => $urlPath,
            'name' => $file->name,
            'extension' => $file->extension,
            'size' => $file->size,
            'year' => date('Y'),
            'month' => date('m'),
            'day' => date('d'),
            'width' => $width,
            'height' => $height,
            'duration' => (strcmp($mime, 'video') === 0 || strcmp($mime, 'audio')) ? $this->getDuration($file) : '0',
            'hash' => Etag::sum($savePath)[0],
            'upload_ip' => Yii::$app->request->remoteIP,
        ];
        if ($params['store_in_db']) {
            /** @var ActiveRecord $model */
            $model = new $this->attachmentModel;
            $model->attributes = $info;
            if ($model->save()) {
                $model->refresh();
                $result = [
                    'success' => true,
                    'result' => $model,
                ];
            } else {
                $msg = YII_ENV_PROD ? Yii::t('dropzone', 'Data writting error') : array_values($model->getFirstErrors())[0];
                $result = [
                    'error' => true,
                    'result' => $msg,
                ];
            }
        } else {
            $result = [
                'sucess' => true,
                'result' => $info,
            ];
        }
        return $result;
    }

    /**
     * @param array $params
     * @return array
     */
    protected function qiniuInfo($params) {
        if ($params['store_in_db'] === true || $params['store_in_db'] === 'true') {
            /** @var ActiveRecord $model */
            $model = new $this->attachmentModel;
            $model->attributes = $params;
            $extension = explode('.', $model->extension);
            $model->extension = $extension[count($extension) - 1];
            if ($model->width === 'null') {
                $model->width = 0;
            }
            if ($model->height === 'null') {
                $model->height = 0;
            }
            if ($model->duration === 'null' || $model->duration === '') {
                $model->duration = null;
            }
            $model->year = date('Y');
            $model->month = date('m');
            $model->day = date('d');
            if ($model->save()) {
                $model->refresh();
                $result = [
                    'success' => true,
                    'result' => $model,
                ];
            } else {
                $msg = YII_ENV_PROD
                    ? Yii::t('dropzone', 'Data writting error')
                    : array_values($model->getFirstErrors())[0];
                $result = [
                    'error' => true,
                    'result' => $msg,
                ];
            }
        } else {
            $result = [
                'success' => true,
                'result' => $params,
            ];
        }
        return $result;
    }

    /**
     * @param UploadedFile $file
     * @param string $savePath
     * @return false|string
     * @throws Exception
     */
    private function saveLocal($file, $savePath)
    {
        $ds = DIRECTORY_SEPARATOR;
        $tmpArray = explode($ds, $savePath);
        $tmpArray = array_pop($tmpArray);
        $storePath = implode(DIRECTORY_SEPARATOR, $tmpArray);
        if (!is_dir($storePath)) {
            @mkdir($storePath, 0755, true);
        }
        return $file->saveAs($savePath);
    }

    /**
     * @param UploadedFile $file
     * @return mixed|void|null
     */
    private function getDuration($file)
    {
        try {
            $mimeType = (explode('/', $file->type))[0];
            if ($mimeType === 'video' || $mimeType === 'audio') {
                $ffProbe = isset(Yii::$app->params['ffmpeg'])
                && isset(Yii::$app->params['ffmpeg']['ffmpeg.binaries'])
                && isset(Yii::$app->params['ffmpeg']['ffmpeg.binaries'])
                    ? FFProbe::create(Yii::$app->params['ffmpeg'])
                    : FFProbe::create();
                return $ffProbe->format($file)->get('duration');
            }
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @return void
     */
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
        $i18n->translations['base*'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en-US',
            'basePath' => Yii::getAlias('@davidxu/base/messeages'),
            'fileMap' => [
                '*' => 'base.php',
            ],
        ];
    }
}
