<?php

namespace KWRI\ApiaryGenerator\Generators;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use KWRI\ApiaryGenerator\Generators\AbstractParser;

/**
 * Class RouteParser.
 */
class RouteParser extends AbstractParser
{
    /**
     * @param Route $route
     *
     * @return mixed
     */
    protected function getUri($route)
    {
        return $route->getUri();
    }

    public function getRouteParameters($route, $bindings = [])
    {
        $routeAction = $route->getAction();
        $routeGroup = $this->getRouteGroup($routeAction['uses']);
        $routeDescription = $this->getRouteDescription($routeAction['uses']);

        return $this->getParameters([
            'id' => md5($route->getUri().':'.implode($route->getMethods())),
            'resource' => $routeGroup,
            'title' => $routeDescription['short'],
            'description' => $routeDescription['long'],
            'methods' => $route->getMethods(),
            'uri' => $route->getUri(),
            'parameters' => [],
            'response' => '',
            'responseCode' => '',
        ], $routeAction, $bindings);
    }



    /**
     * @param \Illuminate\Routing\Route $route
     * @param array                     $bindings
     * @param array                     $headers
     * @param bool                      $withResponse
     *
     * @return array
     */
    public function processRoute($route, $bindings = [], $headers = [])
    {
        $parsedRoute = $this->getRouteParameters($route, $bindings);

        $response = $this->getRouteResponse($route, $bindings, $headers, $parsedRoute);
        if ($response->headers->get('Content-Type') === 'application/json' || !empty($response)) {
            $content = json_encode(json_decode($response->getContent()), JSON_PRETTY_PRINT);
        } else {
            $content = $response->getContent();
        }

        if ($response->getStatusCode() < 301) {
            $parsedRoute['response'] = $content;
            $parsedRoute['responseCode'] = $response->getStatusCode();
        }

        return $parsedRoute;
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param string $method
     * @param string $uri
     * @param array  $parameters
     * @param array  $cookies
     * @param array  $files
     * @param array  $server
     * @param string $content
     *
     * @return \Illuminate\Http\Response
     */
    public function callRoute($method, $uri, $parameters = [],
                              $cookies = [], $files = [], $server = [], $content = null)
    {
        $kernel = App::make('Illuminate\Contracts\Http\Kernel');
        App::instance('middleware.disable', true);

        $server = collect([
            //'CONTENT_TYPE' => 'application/json',
            //'Accept' => 'application/json',
        ])->merge($server)->toArray();

        $request = Request::create(
            $uri, $method, $parameters,
            $cookies, $files, $this->transformHeadersToServerVars($server), $content
        );

        //if(!empty($parameters)) dd($request);
        $response = $kernel->handle($request);

        //if(!empty($parameters)) dd($response);

        $kernel->terminate($request, $response);

        return $response;
    }
}
