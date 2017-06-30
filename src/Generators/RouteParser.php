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
     * Parse request
     * @param $route
     * @return mixed
     */
    public function getParameters($route)
    {
        $routeData = [];
        $routeAction = $route->getAction();
        $this->rules = $this->getRouteRules($routeAction['uses']);
        $validator = Validator::make([], $this->rules);

        foreach ($validator->getRules() as $attribute => $rules) {
            //Relations build in another loop
            if (str_contains($attribute, 'relationships')) {
                continue;
            }

            $routeData['attributes'][$this->getAttributeName($attribute)] = $this->parseRules($rules, $attribute);
        }

        $relations = $this->getRelationships($validator->getRules());
        foreach ($relations as $relationName => $relation) {
            $validator = Validator::make([], array_get($relation, 'rules'));

            foreach ($validator->getRules() as $attr => $rule) {
                $routeData['relationships'][$relationName][$attr] = $this->parseRules($rule, $attr);
                $routeData['relationships'][$relationName]['type'] = array_get($relation, 'type');
            }
        }

        return $routeData;
    }

    public function getRelationships($validatorRules)
    {
        $relations = [];
        foreach ($validatorRules as $attribute => $rules) {
            if (!str_contains($attribute, 'relationships')) {
                continue;
            }

            //Get relation name
            preg_match('/relationships\.(.{0,})\./', $attribute, $matches);
            $relation = array_last($matches);

            //if relation exists, then this relation is parsed. Skipping it.
            if (array_has($relations, $relation)) {
                continue;
            }

            //Get all resource "$relation" rules
            foreach ($this->rules as $attr => $rule) {
                if (str_contains($attr, 'relationships') and str_contains($attr, $relation)) {
                    $realAttr = $this->getAttributeName($attr);

                    //Usually data just descriptive. Skipping it
                    if ($realAttr == 'data') {
                        continue;
                    }

                    //Creating an empty array if not exists
                    if (!isset($relations[$relation]['rules'])) {
                        $relations[$relation]['rules'] = [];
                    }

                    //Merging arrays to avoid numeric indexes
                    $relations[$relation]['rules'] = array_merge($relations[$relation]['rules'], [$realAttr => $rule]);

                    //Usually '*' means that relationship is multiple
                    if (str_contains($attr, '*')) {
                        $relations[$relation]['type'] = 'multiple';
                    } else {
                        $relations[$relation]['type'] = 'single';
                    }
                }
            }
        }

        return $relations;
    }
    
    protected function parseRules($rules, $attributeName)
    {
        $ruleData = [];

        $ruleData['required'] = $this->isRequired($rules);

        foreach ($rules as $rule) {
            $parsedRule = $this->parseStringRule($rule);
            list($rule, $parameters) = $parsedRule;

            if (!array_has($ruleData, 'type') or array_get($ruleData, 'type') == 'string') {
                $ruleData['type'] = $type = $this->getValidType($rule);
            }

            $ruleData['value'] = $this->getPossibleValue($rule, $parameters, $type, $attributeName);

            if ($type == 'enum') {
                $ruleData['options'] = $parameters;
            }
        }

        return $ruleData;
    }

    public function isRequired(array $rules)
    {
        return in_array('required', $rules);
    }

    public function getValidType($rule)
    {
        if (in_array($rule, ['numeric', 'integer', 'int'])) {
            return 'number';
        }

        if (in_array($rule, ['boolean', 'bool'])) {
            return 'boolean';
        }

        if (in_array($rule, ['in', 'not in'])) {
            return 'enum';
        }

        return 'string';
    }


    public function getPossibleValue($rule, $parameters, $type, $attributeName)
    {
        $faker = Factory::create();

        switch ($rule) {
            case 'email':
                return $faker->safeEmail;
            case 'in':
                return $faker->randomElement($parameters);
            case 'not_in':
                return $faker->randomElement($parameters);
            case 'min':
                if ($type === 'numeric') {
                    return rand($parameters[0], $parameters[0] + 10);
                }
                break;
            case 'max':
                if ($type === 'numeric') {
                    return rand($parameters[0], $parameters[0] - 10);
                }
                break;
            case 'between':
                return $faker->numberBetween($parameters[0], $parameters[1]);
            case 'before':
                return date(DATE_RFC850, strtotime('-1 day', strtotime($parameters[0])));
            case 'date_format':
                return date($parameters[0]);
            case 'digits':
                return 1;
            case 'json':
                return json_encode(['foo', 'bar', 'baz']);
            case 'timezone':
                return $faker->timezone;
            case 'active_url':
                return $faker->url;
            case 'boolean':
                return $faker->randomElement(['true', 'false']);
            case 'date':
                return $faker->date();
            case 'string':
                $attribute = $this->getAttributeName($attributeName);

                try {
                    $value = $faker->{$attribute};
                } catch (\Exception $e) {
                    $value = $faker->word;
                }
                return $value;
            case 'integer':
                return 1;
            case 'numeric':
                return 1;
            case 'url':
                return $faker->url;
            case 'ip':
                return $faker->ipv4;
        }

        if ($type == 'number') {
            return $faker->randomDigit;
        }

        //Assumed that "id" is always integer
        if ($this->getAttributeName($attributeName) == 'id') {
            return $faker->randomDigit;
        }

        return $faker->word;
    }

    public function getAttributeName(string $attr)
    {
        return array_last(explode('.', $attr));
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
