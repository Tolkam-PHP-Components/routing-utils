<?php declare(strict_types=1);

namespace Tolkam\Routing\Utils;

use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use Tolkam\Utils\Url;

class RouteUtil
{
    /**
     * @var string
     */
    public static string $returnToKey = 'r';
    
    /**
     * Adds return-to value to url string
     *
     * @param string $url
     *
     * @param string $returnToUrl
     *
     * @return string
     */
    public static function addReturnTo(string $url, string $returnToUrl): string
    {
        $parsed = Url::parse($url);
        $parsed['query'][self::$returnToKey] = base64_encode($returnToUrl);
        
        return Url::build($parsed);
    }
    
    /**
     * Gets return-to value
     *
     * @param ServerRequestInterface $request
     *
     * @return string|null
     */
    public static function getReturnTo(ServerRequestInterface $request): ?string
    {
        if ($value = $request->getQueryParams()[self::$returnToKey] ?? '') {
            try {
                return base64_decode($value);
            } catch (Throwable $t) {
                return null;
            }
        }
        
        return null;
    }
}
