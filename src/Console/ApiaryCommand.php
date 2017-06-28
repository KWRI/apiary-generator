<?php

namespace KWRI\ApiaryGenerator\Console;

use Illuminate\Console\Command;
use KWRI\ApiaryGenerator\Generators\AbstractParser;
use KWRI\ApiaryGenerator\Generators\RouteParser;
use File;

class ApiaryCommand extends Command
{
    protected $template = __DIR__ . '/../../resources/template';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'apiary:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates Apiary MSON';

    protected $signature = 'apiary:generate 
                             {--route= : The router to be used}
                             {--user= : The user ID to use for API response calls}
                        ';

    protected $resource;
    protected $attributes;
    protected $route;


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $this->setUserToBeImpersonated($this->option('user'));
        $this->route = $this->option('route');
        $this->resource = $this->setResource($this->route);

        $routeParser = new RouteParser();

        //We get POST route because it has most of available attributes defined in the request
        $route = $this->getPostRoute($this->route);

        //Get formatted attributes list
        $this->attributes = $this->prepareAttributes($routeParser->getRouteParameters($route));

        //Write the file into /storage/apiary
        $this->write();
    }

    /**
     * Prepare attributes
     * @param $parameters
     * @return null|string
     */
    public function prepareAttributes($parameters)
    {
        $attributes = null;

        if (array_has($parameters, 'parameters')) {
            foreach (array_get($parameters, 'parameters') as $name => $parameter) {
                $enum = null;

                //If enum then need to write available options
                if (array_get($parameter, 'type') == 'enum') {
                    foreach (array_get($parameter, 'description') as $enumOption) {
                        $enum .= "        - $enumOption\n";
                    }
                }

                $attributes .= "    - `" . array_last(explode('.', $name)) . "`: `"
                    . array_get($parameter, 'value')
                    . "` (" . array_get($parameter, 'type')  . ")\n$enum";
            }
        }

        return $attributes;
    }

    /**
     * Writes file based on template
     */
    public function write()
    {
        if (!File::exists(storage_path('apiary'))) {
            File::makeDirectory(storage_path('apiary'));
        }

        if (!File::exists(storage_path('apiary/' . $this->resource))) {
            File::makeDirectory(storage_path('apiary/' . $this->resource));
        }

        File::put(
            storage_path('apiary/' . $this->resource) . "/" . $this->resource,
            $this->prepare(File::get($this->template))
        );
    }

    /**
     * Resource usually capitalized singular route
     * @param $resource
     */
    public function setResource($resource)
    {
        $this->resource = ucfirst(str_singular($resource));
    }

    /**
     * @param $actAs
     */
    private function setUserToBeImpersonated($actAs)
    {
        if (!empty($actAs)) {
            $user = app()->make(config('api-docs.user'))->find($actAs);

            if ($user) {
                return $this->laravel['auth']->guard()->setUser($user);
            }
        }
    }

    /**
     * Get POST route of resource
     * @param $routePrefix
     * @return null
     */
    private function getPostRoute($routePrefix)
    {
        $routes = $this->getRoutes();

        foreach ($routes as $route) {
            if (in_array("POST", $route->getMethods()) and str_contains($route->getUri(), $routePrefix)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Get routes list
     * @return mixed
     */
    private function getRoutes()
    {
        return \Route::getRoutes();
    }

    /**
     * Prepare template
     * @param $fileContent
     * @return mixed
     */
    public function prepare($fileContent)
    {
        $replacings = [
            '{attributes}',
            '{route}',
            '{resource}',
        ];

        $replacements = [
            $this->attributes,
            $this->route,
            $this->resource,
        ];

        return str_replace($replacings, $replacements, $fileContent);
    }
}
