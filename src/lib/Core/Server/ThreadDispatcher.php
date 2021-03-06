<?php
/**
 * Created by PhpStorm.
 * User: gourab
 * Date: 27/4/16
 * Time: 5:37 PM
 */

namespace Leloutama\lib\Core\Server;

class ThreadDispatcher extends \Thread {
    private $ToPerform;
    private $arguments;
    public $response;

    public function __construct(callable $ToPerform, array $arguments) {
        $this->ToPerform = $ToPerform;
        $this->arguments = (array) $arguments;
    }

    public function run() {
        $ToPerform = $this->ToPerform;
        if(is_object($ToPerform) && !$ToPerform instanceof \Closure) {
            $ToPerform = (array) $ToPerform;
        }
        return $ToPerform($this, ...$this->arguments);
    }
}
