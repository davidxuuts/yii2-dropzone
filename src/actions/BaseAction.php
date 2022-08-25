<?php

namespace davidxu\dropzone\actions;
use yii\base\Action;
use Yii;
use yii\web\Response;
use yii\i18n\PhpMessageSource;

class BaseAction extends Action
{
    public $url;
    public $allowAnony = false;
    public $useDB = true;

    public function init()
    {
        parent::init();
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

    private function registerTranslations()
    {
        $i18n = Yii::$app->i18n;
        $i18n->translations['dropzone*'] = [
            'class' => PhpMessageSource::class,
            'sourceLanguage' => 'en-US',
            'basePath' => __DIR__ . '../messages',
            'fileMap' => [
                '*' => 'dropzone.php',
            ],
        ];
    }
}
