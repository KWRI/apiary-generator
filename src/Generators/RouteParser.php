<?php

namespace KWRI\ApiaryGenerator\Generators;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Request;
use KWRI\ApiaryGenerator\Generators\AbstractParser;
use Illuminate\Support\Facades\Validator;
use ReflectionClass;
use Illuminate\Foundation\Http\FormRequest;
use Faker\Factory;

/**
 * Class RouteParser.
 */
class RouteParser
{
    public $rules;

    /**
     * Return parsed request parameters
     * @param $route
     * @return mixed
     */
    public function getRouteParameters($route)
    {
        return $this->getParameters([
            'id' => md5($route->getUri().':'.implode($route->getMethods())),
            'methods' => $route->getMethods(),
            'uri' => $route->getUri()
        ], $route->getAction());
    }

    /**
     * Parse request
     * @param $routeData
     * @param $routeAction
     * @return mixed
     */
    public function getParameters($routeData, $routeAction)
    {
        $this->rules = $this->getRouteRules($routeAction['uses']);
        $validator = Validator::make([], $this->rules);

        foreach ($validator->getRules() as $attribute => $rules) {
            //Relations temporary removed
            if (str_contains($attribute, 'relationships')) {
                continue;
            }

            $skipRule = false;
            $attributeData = [
                'required' => false,
                'type' => null,
                'value' => '',
                'options' => []
            ];

            if (count($rules)) {
                foreach ($rules as $rule) {
                    if (!$this->parseRule($rule, $attribute, $attributeData, $routeData['id'], $routeData['uri'])) {
                        $skipRule = true;
                        break;
                    }
                }
            }

            if (!$skipRule) {
                $routeData['parameters'][$attribute] = $attributeData;
            }
        }

//dd($routeData);
        return $routeData;
    }

    /**
     * Parse rule rules
     * @param $rule
     * @param $attributeName
     * @param $attributeData
     * @param $seed
     * @param null $uri
     * @return bool
     */
    protected function parseRule($rule, $attributeName, &$attributeData, $seed, $uri = null)
    {
        $resource = null;
        if ($uri) {
            $resource = array_last(explode('/', $uri));
        }

        $faker = Factory::create();
        $faker->seed(crc32($seed));

        $parsedRule = $this->parseStringRule($rule);
        $parsedRule[0] = $this->normalizeRule($parsedRule[0]);

        list($rule, $parameters) = $parsedRule;

        switch ($rule) {
            case 'email':
                $attributeData['value'] = $faker->safeEmail;
                $attributeData['type'] = $rule;
                break;
            case 'required':
                $attributeData['required'] = true;
                break;
            case 'accepted':
                $attributeData['required'] = true;
                $attributeData['type'] = 'boolean';
                $attributeData['value'] = true;
                break;
            case 'after':
                $attributeData['type'] = 'string';
                $attributeData['value'] = date(DATE_RFC850, strtotime('+1 day', strtotime($parameters[0])));
                break;
            case 'alpha':
                $attributeData['value'] = $faker->word;
                break;
            case 'alpha_dash':
                break;
            case 'alpha_num':
                break;
            case 'in':
                $attributeData['type'] = 'enum';
                $attributeData['options'] = $parameters;
                if (isset(array_flip($parameters)[$resource])) {
                    $attributeData['value'] = $resource;
                } else {
                    $attributeData['value'] = $faker->randomElement($parameters);
                }
                break;
            case 'not_in':
                $attributeData['value'] = $faker->word;
                break;
            case 'min':
                if (array_get($attributeData, 'type') === 'numeric' || array_get($attributeData, 'type') === 'integer') {
                    $attributeData['value'] = rand($parameters[0], $parameters[0] + 10);
                }
                $attributeData['type'] = 'number';
                break;
            case 'max':
                if (array_get($attributeData, 'type') === 'numeric' || array_get($attributeData, 'type') === 'integer') {
                    $attributeData['value'] = rand($parameters[0], $parameters[0] - 10);
                }
                $attributeData['type'] = 'number';
                break;
            case 'between':
                if (!isset($attributeData['type'])) {
                    $attributeData['type'] = 'number';
                }
                $attributeData['value'] = $faker->numberBetween($parameters[0], $parameters[1]);
                break;
            case 'before':
                $attributeData['type'] = 'string';
                $attributeData['value'] = date(DATE_RFC850, strtotime('-1 day', strtotime($parameters[0])));
                break;
            case 'date_format':
                $attributeData['type'] = 'string';
                $attributeData['value'] = date($parameters[0]);
                break;
            case 'different':
                break;
            case 'digits':
                $attributeData['type'] = 'numeric';
                $attributeData['value'] = 1;
                break;
            case 'digits_between':
                $attributeData['type'] = 'number';
                break;
            case 'file':
                $attributeData['type'] = 'file';
                $attributeData['value'] = 'provide file';
                break;
            case 'image':
                $attributeData['type'] = 'image';
                break;
            case 'json':
                $attributeData['type'] = 'string';
                $attributeData['value'] = json_encode(['foo', 'bar', 'baz']);
                break;
            case 'timezone':
                $attributeData['value'] = $faker->timezone;
                break;
            case 'active_url':
                $attributeData['type'] = 'url';
                $attributeData['value'] = $faker->url;
                break;
            case 'regex':
                $attributeData['type'] = 'string';
                break;
            case 'boolean':
                $attributeData['value'] = true;
                $attributeData['type'] = $rule;
                break;
            case 'array':
                $attributeData['value'] = '';
                $attributeData['type'] = $rule;
                break;
            case 'date':
                $attributeData['value'] = $faker->date();
                $attributeData['type'] = $rule;
                break;
            case 'string':
                $attributeData['value'] = 'string';
                $attributeData['type'] = $rule;
                break;
            case 'integer':
                $attributeData['value'] = 1;
                $attributeData['type'] = 'number';
                break;
            case 'numeric':
                $attributeData['value'] = 1;
                $attributeData['type'] = 'number';
                break;
            case 'url':
                $attributeData['value'] = $faker->url;
                $attributeData['type'] = $rule;
                break;
            case 'ip':
                $attributeData['value'] = $faker->ipv4;
                $attributeData['type'] = $rule;
                break;
        }

        if ($attributeData['value'] == '' and $attributeData['type'] != 'array') {
            $attributeData['value'] = 1;
        }

        if (is_null($attributeData['type'])) {
            $attributeData['type'] = 'string';
        }

        if ($attributeData['type'] == 'array') {
            if (array_has($this->rules, "$attributeName.*")) {
                $validator = Validator::make([], [array_get($this->rules, "$attributeName.*")]);

                foreach (array_first($validator->getRules()) as $rule) {
                    $this->parseRule($rule, $attributeName, $attributeData, $seed, $uri);
                }
            }
        }

        return true;
    }

    /**
     * Return route request rules
     * @param $route
     * @return array
     */
    protected function getRouteRules($route)
    {
        list($class, $method) = explode('@', $route);
        $reflection = new ReflectionClass($class);
        $reflectionMethod = $reflection->getMethod($method);

        foreach ($reflectionMethod->getParameters() as $parameter) {
            $parameterType = $parameter->getClass();
            if (!is_null($parameterType) && class_exists($parameterType->name)) {
                $className = $parameterType->name;

                if (is_subclass_of($className, FormRequest::class)) {
                    $parameterReflection = new $className();
                    return $parameterReflection->rules();
                }
            }
        }

        return [];
    }

    /**
     * Parse string rules
     * @param $rules
     * @return array
     */
    protected function parseStringRule($rules)
    {
        $parameters = [];

        // The format for specifying validation rules and parameters follows an
        // easy {rule}:{parameters} formatting convention. For instance the
        // rule "Max:3" states that the value may only be three letters.
        if (strpos($rules, ':') !== false) {
            list($rules, $parameter) = explode(':', $rules, 2);

            $parameters = $this->parseParameters($rules, $parameter);
        }

        return [strtolower(trim($rules)), $parameters];
    }

    /**
     * Replace short rules
     * @param $rule
     * @return string
     */
    protected function normalizeRule($rule)
    {
        switch ($rule) {
            case 'int':
                return 'integer';
            case 'bool':
                return 'boolean';
            default:
                return $rule;
        }
    }

    /**
     * Parse parameters
     * @param $rule
     * @param $parameter
     * @return array
     */
    protected function parseParameters($rule, $parameter)
    {
        if (strtolower($rule) === 'regex') {
            return [$parameter];
        }

        return str_getcsv($parameter);
    }
}
