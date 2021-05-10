<?php

/**
 * @package Sellony Api | Channel::Admin
 * @author Cemre Fatih Karakulak <cradexco@gmail.com>
 */

namespace Admin\App\Http\Routes\Cache;

use Slim\App;

class {{ROUTE-ANNOTATION-CLASSNAME}}
{
    public function __invoke(App $app) {
        {{ROUTE-ANNOTATION-CONTENT}}
    }

    public function getArrayControllersSerialize() {
        return '{{ARRAY-CONTROLLERS}}';
    }
}
