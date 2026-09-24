<?php

namespace Wexample\SymfonyUser\Traits;

use Wexample\SymfonyHelpers\Traits\BundleClassTrait;
use Wexample\SymfonyUser\WexampleSymfonyUserBundle;

trait SymfonyUserBundleClassTrait
{
    use BundleClassTrait;

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyUserBundle::class;
    }
}
