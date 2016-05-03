<?php
/**
 * Created by PhpStorm.
 * User: gourab
 * Date: 27/4/16
 * Time: 10:28 PM
 */

namespace Leloutama\lib\Core\Server;
require __DIR__ . "/Http.php";
use Leloutama\lib\Core\Router\Router;

class Client {
    protected $router;
    protected $http;
    protected $stringHeaders;
    private $config;

    public function __construct(Router $router, $stringHeaders) {
        $this->router = $router;
        $this->stringHeaders = $stringHeaders;
        $this->http = new Http($stringHeaders);
    }

    public function serve(): string {
        $this->http->Headerize();
        $this->http->parseHeaders();

        printf("Request Recieved \n \t Time: %s \n \t Requested Resource: %s \n \t Method: %s \n",
            date("M d Y-H:i:s "),
            $this->http->getRequestedResource(),
            $this->http->getMethod()
        );

        $responseBody = $this->processBody();

        //var_dump($responseBody);
        $headerOP = $this->createHeaders($responseBody[0], $responseBody[1]);

        //var_dump($headerOP);

        $responseBody = $headerOP[1];
        $headers = $headerOP[0];


        $finalPacket = $headers . "\r\n\r\n" . $responseBody;

        //var_dump($finalPacket);
        return $finalPacket;
    }

    private function processBody(): array {
        $getContent = $this->http->getInfo($this->http->getRequestedResource(), $this->router);
        $toServeContent = "";
        if(!$getContent) {
            $toServeContent = $this->get404();
            return [$toServeContent, "text/html"];
        } elseif($this->http->getMethod() !== "GET") {
            $toServeContent = $this->get405();
            return [$toServeContent, "text/html"];
        } else {
            $toServeContent = $getContent[0];
        }
        $toServeContent = implode("\r\n", explode("\n", $toServeContent));
        return [$toServeContent, $getContent[1]];
    }

    protected function get404(): string {
        $html404 = file_get_contents(__DIR__ . "/../Resources/400Errors.html");

        $vars = array(
            "%error_code%" => "404",
            "%error_code_meaning%" => "Not Found",
            "%error_description%" => "The requested resource was not found."
        );

        $html404 = $this->replaceVarsInErrorPage($vars, $html404);

        return $html404;
    }

    protected function get405(): string {
        $html405 = file_get_contents(__DIR__ . "/../Resources/400Errors.html");

        $vars = array(
            "%error_code%" => "405",
            "%error_code_meaning%" => "Method Not Supported",
            "%error_description%" => "The method requested is not supported by the server."
        );

        $html405 = $this->replaceVarsInErrorPage($vars, $html405);

        return $html405;
    }

    protected function replaceVarsInErrorPage(array $vars, string $content): string {
        foreach($vars as $varName => $value) {
            $content = str_replace($varName, $value, $content);
        }
        return $content;
    }

    private function createHeaders(string $content, string $mimeType, string $fileName = "", int $status = 200): array {
        $headers = [];
        switch($status) {
            case 200:
                $headers[] = "HTTP/1.1 200 OK";
                break;
            case 404:
                $headers[] = "HTTP/1.1 404 Not Found";
                break;
            case 405:
                $headers[] = "HTTP/1.1 405 Method Not Supported";
                break;
        }

        //$encodeOP = $this->encodeBody($content);

        $headers[] = sprintf("Content-Type: %s", $mimeType);

        $headers[] = sprintf("Content-Length: %d", strlen($content));

        //if(!empty($encodeOP)) {
        //    $content = $encodeOP[0];
        //    $headers[] = sprintf("Content-Encoding: %s", $encodeOP[1]);
        //}

        $headers = implode("\r\n", $headers);
        return [$headers, $content];
    }

    private function encodeBody(string $body): array {
        $toReturn = [];
        if(in_array("gzip", $this->http->getAcceptedEncoding())) {
            $body = gzencode($body);
            $toReturn = [$body, "gzip"];
        } elseif(in_array("deflate", $this->http->getAcceptedEncoding())) {
            $body = gzcompress($body);
            $toReturn = [$body, "deflate"];
        }

        return $toReturn;
    }
}