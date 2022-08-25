<?php

namespace davidxu\dropzone\actions;

use Yii;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use League\Flysystem\FilesystemException;
use yii\web\Response;
use yii\web\UploadedFile;

class LocalAction extends BaseAction
{
    public $filePath;

    /**
     * @return void
     * @throws BadRequestHttpException
     * @throws FilesystemException | UnableToCheckDirectoryExistence | UnableToCreateDirectory
     */
    public function run()
    {
        //TODO
        if (empty($this->url) || $this->url === '') {
            $this->url = Yii::getAlias('@web/uploads');
        }
        if (empty($this->filePath) || $this->filePath === '') {
            $this->filePath = Url::to('@webroot/uploads');
        }
        $file = $_FILES;
        $filename = Yii::$app->security->generateRandomString();
        Yii::$app->response->format = Response::FORMAT_JSON;
        return [
            'file_path' => $this->url . $filename,
        ];
    }
}
