<?php

namespace davidxu\dropzone\actions;

use davidxu\base\enums\UploadTypeEnum;
use Yii;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\BadRequestHttpException;
use yii\web\JsonParser;
use yii\web\Response;
use yii\web\UploadedFile;

class QiniuAction extends BaseAction
{
    /**
     * @return void
     * @throws BadRequestHttpException
     */
    public function run()
    {
        $this->allowAnony = true;
        Yii::$app->request->parsers['application/json'] = JsonParser::class;
        Yii::$app->response->format = Response::FORMAT_JSON;
        return $this->qiniuInfo(Yii::$app->request->post());
    }
}
