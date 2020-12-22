<?php

namespace MGKProd\YTMusic\Parsers;

use Illuminate\Support\Str;

class Utils
{
    public static function getFlexColumnItem($item, $index)
    {
        if (
            ! array_key_exists('text', $item['flexColumns'][$index]['musicResponsiveListItemFlexColumnRenderer'])
            && ! array_key_exists('runs', $item['flexColumns'][$index]['musicResponsiveListItemFlexColumnRenderer']['text'])
        ) {
            return null;
        }

        return $item['flexColumns'][$index]['musicResponsiveListItemFlexColumnRenderer'] ?? null;
    }

    public static function getItemText($item, $index, $run_index = 0, $null_if_absent = false)
    {
        $column = self::getFlexColumnItem($item, $index);

        if (! $column) {
            return null;
        }

        if ($null_if_absent && count($column['text']['runs']) < $run_index + 1) {
            return null;
        }

        return $column['text']['runs'][$run_index]['text'] ?? null;
    }

    public static function parseSongArtists($data, $index)
    {
        $flex_item = self::getFlexColumnItem($data, $index);
        if (! $flex_item) {
            return null;
        } else {
            return self::parseSongArtistsRuns($flex_item['text']['runs']);
        }
    }

    public static function parseSongArtistsRuns($runs)
    {
        $artists = [];

        foreach ($runs as $i => $run) {
            if (
                array_key_exists('navigationEndpoint', $run)
                && Str::startsWith(data_get($run, Paths::NAVIGATION_BROWSE_ID), 'UC')
            ) {
                $artists[] = [
                    'name' => $run['text'],
                    'id' => data_get($run, Paths::NAVIGATION_BROWSE_ID),
                ];
            }
        }

        return $artists;
    }

    public static function getContinuations($results, $continuation_type, $limit, $request_func, $parse_func, $ctoken_path = '')
    {
        $items = [];

        while (array_key_exists('continuations', $results) && count($items) < $limit) {
            $additional_params = self::getContinuationParams($results, $ctoken_path);
            $response = $request_func($additional_params);

            if (array_key_exists('continuationContents', $response)) {
                $results = $response['continuationContents'][$continuation_type];
            } else {
                break;
            }

            $contents = self::getContinuationContents($results, $parse_func);
            $items = array_merge($items, $contents);
        }

        return $items;
    }

    public static function getValidatedContinuations($results, $continuation_type, $limit, $per_page, $request_func, $parse_func, $ctoken_path = '')
    {
        // $items = []
        // while 'continuations' in results and len(items) < limit:
        // additionalParams = get_continuation_params(results, ctoken_path)
        // wrapped_parse_func = lambda raw_response: get_parsed_continuation_items(
        // raw_response, parse_func, continuation_type)
        // validate_func = lambda parsed: validate_response(parsed, per_page, limit, len(items))

        // response = resend_request_until_parsed_response_is_valid(request_func, additionalParams,
        //                                 wrapped_parse_func, validate_func,
        //                                 3)
        // results = response['results']
        // items.extend(response['parsed'])

        // return items
    }

    public static function getParsedContinuationItems($response, $parse_func, $continuation_type)
    {
        $results = $response['continuationContents'][$continuation_type];

        return [
            'results' => $results,
            'parsed' => self::getContinuationContents($results, $parse_func),
        ];
    }

    public static function getContinuationParams($results, $ctoken_path)
    {
        $ctoken = data_get($results, 'continuations.0.next' . $ctoken_path . 'ContinuationData.continuation');

        return '&ctoken=' . $ctoken . '&continuation=' . $ctoken;
    }

    public static function getContinuationContents($continuation, $parse_func)
    {
        foreach (['contents', 'items'] as $term) {
            if (array_key_exists($term, $continuation)) {
                return $parse_func($continuation[$term]);
            }
        }

        return [];
    }

    public static function resendRequestUntilParsedResponseIsValid($request_func, $request_additional_params, $parse_func, $validate_func, $max_retries)
    {
        // response = request_func(request_additional_params)
        // parsed_object = parse_func(response)
        // retry_counter = 0
        // while not validate_func(parsed_object) and retry_counter < max_retries:
        // response = request_func(request_additional_params)
        // attempt = parse_func(response)
        // if len(attempt['parsed']) > len(parsed_object['parsed']):
        // parsed_object = attempt
        // retry_counter += 1

        // return parsed_object
    }

    public static function validateResponse($response, $per_page, $limit, $current_count)
    {
        // remaining_items_count = limit - current_count
        // expected_items_count = min(per_page, remaining_items_count)

        // # response is invalid, if it has less items then minimal expected count
        // return len(response['parsed']) >= expected_items_count
    }
}
