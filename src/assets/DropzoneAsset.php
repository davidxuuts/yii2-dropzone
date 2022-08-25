<?php

namespace davidxu\drozone\assets;

use yii\web\AssetBundle;

class DropzoneAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/..';

    public $js = [
    ];

    public $css = [
        'css/dropzone-style.css',
    ];

    public $depends = [
        DropzoneBasicAsset::class,
    ];
}
