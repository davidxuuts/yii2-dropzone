<?php

namespace davidxu\dropzone\actions;

use Yii;
use yii\helpers\Url;
use yii\base\Exception;
use yii\web\Response;
use yii\web\UploadedFile;

class LocalAction extends BaseAction
{
    /**
     * @return array
     * @throws Exception
     */
    public function run()
    {
        if (empty($this->url) || $this->url === '') {
            $this->url = Yii::getAlias('@web');
        }
        if (empty($this->fileDir) || $this->fileDir === '') {
            $this->fileDir = Url::to('@webroot');
        }
        $post = Yii::$app->request->post();
        $file = UploadedFile::getInstanceByName('file');

        Yii::$app->response->format = Response::FORMAT_JSON;
        return $this->localInfo($file, $post, $this->url, $this->fileDir);
    }
}
