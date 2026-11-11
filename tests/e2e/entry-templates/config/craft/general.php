<?php

use CraftCms\Cms\Config\GeneralConfig;

return GeneralConfig::create()->aliases(['@webroot' => public_path()]);
