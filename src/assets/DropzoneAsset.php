<?php

namespace davidxu\dropzone\assets;

use yii\web\AssetBundle;

class DropzoneAsset extends AssetBundle
{
    public $sourcePath = '@davidxu/dropzone';

    public $js = [
        'js/dropzone.common' . (YII_ENV_PROD ? '.min' : '') . '.js',
    ];

    public $css = [
        'css/dropzone.css',
    ];

    public $depends = [
        DropzoneBasicAsset::class,
    ];
}
