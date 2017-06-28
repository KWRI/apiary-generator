<?php

namespace KWRI\ApiaryGenerator\Generators;

/**
 * Class GenerateDocumentationHelpers.
 */
class GenerateDocumentationHelpers
{
    /**
     * Replace dots to array.
     *
     * @param $key
     *
     * @return string
     */
    public static function replaceDotsToArray($key)
    {
        if (strpos($key, '.') !== false) {
            $array = explode('.', $key);
            $key = $array[0];
            unset($array[0]);
            $key .= '['.implode('][', $array).']';
        }

        return $key;
    }

    /**
     * Prepare json.
     *
     * @param $parameters
     *
     * @return array
     */
    public static function buildJson($parameters)
    { //dd(self::explodeParameters($parameters));
        return self::explodeParameters($parameters);
    }

    /**
     * Build valid parameters.
     *
     * @param $parameters
     *
     * @return array
     */
    public static function explodeParameters($parameters)
    {
        $exploded = [];
        $merged = [];

        foreach ($parameters as $parameter => $rules) {
            $exploded[] = explode('.', $parameter);
        }

        foreach ($exploded as $key => $param) {
            //This is a missed part. In fact all this function needs comments and possibly refactor
            if (count($param) == 1) {
                $merged[] = [$param[0] => $parameters[$param[0]]['value']];
                continue;
            }

            $temp = [];
            for ($i = count($param) - 1; $i > 0; --$i) {
                if (count($temp) == 0) {
                    $temp = [(string) $param[$i] => $parameters[implode('.', $exploded[$key])]['value']];
                }

                if (isset($param[$i - 1])) {
                    if (is_numeric($param[$i - 1])) {
                        $param[$i - 1] = $param[$i - 1].'|';
                    }
                    $temp = [$param[$i - 1] => $temp];
                }
            }
            $merged[] = $temp;
        }

        $return = [];
        foreach ($merged as $item) {
            $return = array_merge_recursive($item, $return);
        }

        $return = self::normalizeArray($return);

        return $return;
    }

    /**
     * Create directory.
     *
     * @param $documentarian
     * @param $outputPath
     */
    public static function writeDir($documentarian, $outputPath)
    {
        if (!is_dir($outputPath)) {
            $documentarian->create($outputPath);
        }
    }

    /**
     * Normilize array.
     *
     * @param $array
     *
     * @return array
     */
    public static function normalizeArray($array)
    {
        $normalizedArray = [];

        foreach ($array as $key => $item) {
            if (is_array($item)) {
                $item = array_reverse(self::normalizeArray($item));
            }

            if (strpos($key, '|') !== false) {
                $key = str_replace('|', '', $key);
            }

            if (!isset($normalizedArray[$key])) {
                $normalizedArray[$key] = $item;
            }
        }

        return $normalizedArray;
    }

    /**
     * Build URL.
     *
     * @param $rules
     * @param $bindings
     * @param $uri
     *
     * @return string
     */
    public static function getUri($rules, $bindings, $uri)
    {
        $uriParams = [];

        foreach (array_keys($rules) as $rule) {
            if (isset($bindings[$rule])) {
                $uriParams[] = $rule.'='.$bindings[$rule];
            } elseif (strpos($rule, '.') !== false) {
                $replacement = explode('.', $rule);
                $replacement = $replacement[count($replacement) - 1];

                if (isset($bindings[$replacement])) {
                    $rule = self::replaceDotsToArray($rule);
                    $uriParams[] = $rule.'='.$bindings[$replacement];
                }
            }
        }

        return $uri.'?'.implode('&', $uriParams);
    }
}
