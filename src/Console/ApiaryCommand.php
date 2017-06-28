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
                             {--bindings= : Bindings}
                        ';

    protected $db;

    protected $resource;


    public function __construct()
    {
        parent::__construct();
        $this->db = app()->make('db');
    }


    public function handle()
    {
        $this->setUserToBeImpersonated($this->option('user'));
        $route = $this->option('route');

        $this->setResource($route);


        $routeParser = new RouteParser();
        $parsedRoutes = $this->processLaravelRoutes($routeParser, $route);

        $this->write();
    }


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


    private function processLaravelRoutes(AbstractParser $generator, $routePrefix)
    {
        $routes = $this->getRoutes();
        $bindings = $this->getBindings();

        $parsedRoutes = [];
        foreach ($routes as $route) {
            //$this->info($route->getUri());

            try {
                $this->db->beginTransaction();

                if (str_contains($route->getUri(), $routePrefix)) {

                    $this->info($route->getUri());

                    if ($parsedRoute = $this->setRouteResponse($route, $generator, $bindings)) {
                        dd($parsedRoutes);
                        $parsedRoutes[] = $parsedRoute;
                    }
                }

                $this->db->rollBack();
            } catch (\Exception $e) {
                $this->error($e->getMessage());
            }
        }

        return $parsedRoutes;
    }

    private function getRoutes()
    {
        return \Route::getRoutes();
    }

    private function getBindings()
    {
        $bindings = $this->option('bindings');
        if (empty($bindings)) {
            return [];
        }
        $bindings = explode('|', $bindings);
        $resultBindings = [];
        foreach ($bindings as $binding) {
            list($name, $id) = explode(',', $binding);
            $resultBindings[$name] = $id;
        }

        return $resultBindings;
    }


    private function setRouteResponse($route, $generator, $bindings)
    {
        $generator->processRoute($route, $bindings);
    }


    /**
     * @param $fileContent
     * @return mixed
     */
    public function prepare($fileContent)
    {
        $replacings = [
            '{name}',
            '{namespace}',
            '{testNamespace}',
            '{table}',
            '{getters}',
            '{interfaceGetters}',
            '{fillable}',
            '{repository}',
            '{directory}',
            '{eloquentTest}',
            '{repositoryTest}',
            '{repositoryTestData}',
        ];

        $replacements = [

        ];

        return str_replace($replacings, $replacements, $fileContent);
    }
}
