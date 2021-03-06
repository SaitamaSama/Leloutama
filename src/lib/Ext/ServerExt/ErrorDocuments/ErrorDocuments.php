<?php
/**
 * Created by PhpStorm.
 * User: gourab
 * Date: 11/5/16
 * Time: 12:10 PM
 */

namespace Leloutama\lib\Ext\ServerExt\ErrorDocuments;

use FastRoute\Dispatcher;
use Leloutama\lib\Core\Utility\Response;
use Leloutama\lib\Ext\ServerExt\ServerExtension;
use Leloutama\lib\Core\Utility\Request;
use Leloutama\lib\Core\Server\Http;

include_once __DIR__ . "/../ServerExtension.php";

class ErrorDocuments implements ServerExtension {
    private $config;
    private $docRoot;

    public function __construct(array $configuration, string $docRoot) {
        $this->config = $configuration;
        $this->docRoot = $docRoot;
    }

    public function beforeConstruct(Dispatcher $router, string $stringHeaders, string $rawRequestBody) {
        return null;
    }

    public function afterRequestBuild(Request $request, Http $http) {
        return null;
    }

    public function beforeHeaderCreationCall(string $content, string $mime, int $status) {
        if(array_key_exists($status, $this->config)) {
            $file = $this->replaceVarsInString(["%docRoot%" => $this->docRoot], $this->config[$status]);
            $fileContent = file_get_contents($file);
            $mimeType = mime_content_type($file);
            return array(
                "content" => $fileContent,
                "mime" => $mimeType,
                "status" => $status,
                "fileName" => $file
            );
        }
        return null;
    }

    protected function replaceVarsInString(array $vars, string $content): string {
        foreach($vars as $varName => $value) {
            $content = str_replace($varName, $value, $content);
        }
        return $content;
    }

    public function afterCreation(Response $response) {
        return null;
    }
}