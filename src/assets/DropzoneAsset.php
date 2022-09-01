<?php

namespace davidxu\dropzone\assets;

use yii\web\AssetBundle;

class DropzoneAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/..';

    public $js = [
    ];

    public $css = [
        'css/dropzone.css',
    ];

    public $depends = [
        DropzoneBasicAsset::class,
//        QETagAsset::class,
    ];
}