<?php

namespace davidxu\dropzone\assets;

use yii\web\AssetBundle;
use yii\web\JqueryAsset;

class CropperAsset extends AssetBundle
{
    public $sourcePath = '@npm/cropperjs/dist';
    public $js = [
    ];
    public $css = [
    ];

    /**
     * @inheritdoc
     */
    public function init()
    {
        $min = YII_ENV_DEV ? '' : '.min';
        $this->css[] = 'cropper' . $min . '.css';
        $this->js[] = 'cropper' . $min . '.js';
    }

    public $depends = [
        JqueryAsset::class
    ];
}
