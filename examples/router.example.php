<?php
$dispatcher = \FastRoute\simpleDispatcher(function (\FastRoute\RouteCollector $r) {
    $r->addRoute("GET", "/", function ($request, $vars) {
        $response = (new \Leloutama\lib\Core\Modules\Responses\HttpResponse($request));
        $response
            ->setContent("ohai")
            ->setMime("text/html")
            ->setStatus(200);

        return $response;
    });

    $r->addRoute("GET", "/greet/{name}", function ($request, $vars) {
        $response = (new \Leloutama\lib\Core\Modules\Responses\HttpResponse($request))
            ->setContent("<h1>Hi There, " . $vars["name"] . "</h1>")
            ->setMime("text/html")
            ->setStatus(200);

        return $response;
    });

    $r->addRoute("GET", "/template/show/{name}", function ($request, $vars) {
        $response = (new \Leloutama\lib\Core\Modules\Responses\HttpResponse($request))
            ->useTwigTemplate("/template.twig", $vars)
            ->setMime("text/html")
            ->setStatus(200);

        return $response;
    });
});

return $dispatcher;