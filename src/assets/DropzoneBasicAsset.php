<?php

namespace davidxu\dropzone\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;
use Yii;

class DropzoneBasicAsset extends AssetBundle
{
    public $sourcePath = '@npm/dropzone/dist';

    public $js = [
        'dropzone' . (YII_ENV_PROD ? '.min' : '') . '.js',
    ];

    public $css = [
    ];

    public $depends = [
        JqueryAsset::class,
        CropperAsset::class,
    ];
}
