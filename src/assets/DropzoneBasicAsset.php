<?php

namespace davidxu\dropzone\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

class DropzoneBasicAsset extends AssetBundle
{
    public $sourcePath = '@npm/dropzone/dist';

    public $js = [
//        'https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/dropzone' . (YII_ENV_PROD ? '/dropzone.min' : '') . '.js',
        (YII_ENV_PROD ? 'min/' : '') . 'dropzone' . (YII_ENV_PROD ? '.min' : '') . '.js',
    ];

    public $css = [
        (YII_ENV_PROD ? 'min/' : '') . 'dropzone' . (YII_ENV_PROD ? '.min' : '') . '.css',
//        'https://cdn.jsdelivr.net/npm/dropzone@5.9.3/dist/dropzone' . (YII_ENV_PROD ? '/dropzone.min' : '') . '.css'
    ];

    public $depends = [
        JqueryAsset::class,
    ];
}
