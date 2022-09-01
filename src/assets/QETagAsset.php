<?php

namespace davidxu\dropzone\assets;

use yii\web\AssetBundle;

class QETagAsset extends AssetBundle
{
    public $sourcePath = __DIR__ . '/..';
    public $js = [
        'js/sha1.min.js',
        'js/qetag.js',
    ];
    public $css = [
    ];
}
