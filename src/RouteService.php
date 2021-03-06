<?php declare(strict_types=1);

namespace Tolkam\Routing\Utils;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tolkam\Routing\Route;
use Tolkam\Routing\RouterContainer;
use Tolkam\Utils\Url;

class RouteService
{
    /**
     * @var ServerRequestInterface
     */
    protected ServerRequestInterface $request;
    
    /**
     * @var RouterContainer
     */
    protected RouterContainer $routerContainer;
    
    /**
     * @param ServerRequestInterface $request
     * @param RouterContainer        $routerContainer
     */
    public function __construct(
        ServerRequestInterface $request,
        RouterContainer $routerContainer
    ) {
        $this->request = $request;
        $this->routerContainer = $routerContainer;
    }
    
    /**
     * Gets route by name or current one if name is not provided
     *
     * @param string|null $name
     *
     * @return Route
     */
    public function getRoute(string $name = null): Route
    {
        if ($name) {
            /** @var Route $route */
            $route = $this->routerContainer->getMap()->getRoute($name);
            
            return $route;
        }
        
        $matcher = $this->routerContainer->getMatcher();
        
        /** @var Route $route */
        if ($route = $matcher->getMatchedRoute()) {
            return $route;
        }
        
        /** @var Route $route */
        if ($route = $matcher->getFailedRoute()) {
            return $route;
        }
        
        throw new RouteServiceException('Failed to get current route');
    }
    
    /**
     * Gets route attribute value
     *
     * @param string|null $name
     * @param string      $attribute
     * @param null        $default
     *
     * @return string|null
     * @throws RouteServiceException
     */
    public function getRouteAttribute(?string $name, string $attribute, $default = null): ?string
    {
        return $this->getRoute($name)->attributes[$attribute] ?? $default;
    }
    
    /**
     * Gets current route name
     *
     * @return string
     * @throws RouteServiceException
     */
    public function currentRouteName(): string
    {
        return $this->getRoute()->name;
    }
    
    /**
     * Gets current route url
     *
     * @param string|null $name
     * @param array|null  $attrs
     * @param bool|null   $absolute
     * @param bool|null   $preserveReturnTo
     *
     * @return string
     */
    public function getRouteUrl(
        string $name = null,
        array $attrs = [],
        bool $absolute = true,
        bool $preserveReturnTo = true
    ): string {
        if (!$name) {
            $currentRoute = $this->getRoute();
            $name = $currentRoute->name;
            $attrs = array_replace($currentRoute->attributes, $attrs);
        }
        
        $url = $this->generate($name, $attrs);
        
        if ($absolute) {
            $url = Url::toAbsolute($url, $this->getHost(), $this->getScheme());
        }
        
        // preserve return-to parameter between urls
        if ($preserveReturnTo && ($returnTo = RouteUtil::getReturnTo($this->request))) {
            $url = RouteUtil::addReturnTo($url, $returnTo);
        }
        
        return $url;
    }
    
    /**
     * Adds redirect header to response
     *
     * @param ResponseInterface $response
     * @param string            $redirectRouteName
     * @param bool              $returnToRouteName
     *
     * @return ResponseInterface
     */
    public function withRedirect(
        ResponseInterface $response,
        string $redirectRouteName,
        bool $returnToRouteName = false
    ): ResponseInterface {
        $location = $this->getRouteUrl($redirectRouteName);
        
        if (
            $returnToRouteName !== false
            && (is_null($returnToRouteName) || is_string($returnToRouteName))
        ) {
            $returnTo = $this->getRouteUrl($returnToRouteName);
        }
        else {
            $returnTo = RouteUtil::getReturnTo($this->request);
        }
        
        if ($returnTo !== null) {
            $location = RouteUtil::addReturnTo($location, $returnTo);
        }
        
        return $response->withStatus(302)->withHeader('Location', $location);
    }
    
    /**
     * Gets current request URI scheme
     *
     * @return string
     */
    public function getScheme(): string
    {
        // get from uri or from globals when uri is not available (CLI)
        $fallback = 'http';
        if (isset($_SERVER['HTTPS'])) {
            $fallback .= 's';
        }
        
        return $this->request->getUri()->getScheme() ?: $fallback;
    }
    
    /**
     * Gets current request URI host
     *
     * @return string
     */
    public function getHost(): string
    {
        // get from uri or from globals when uri is not available (CLI)
        return $this->request->getUri()->getHost()
            ?: ($_SERVER['HTTP_HOST'] ?? '');
    }
    
    /**
     * Gets current request URI path
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->request->getUri()->getPath();
    }
    
    /**
     * Generates route url
     *
     * @param string $routeName
     * @param array  $attributes
     * @param bool   $raw
     *
     * @return string
     */
    private function generate(
        string $routeName,
        array $attributes = [],
        bool $raw = false
    ): string {
        $methodName = 'generate' . ($raw ? 'Raw' : '');
        
        // convert objects to strings
        $attributes = array_map(
            fn($v) => is_object($v) ? (string) $v : $v,
            $attributes
        );
        
        $generated = $this->routerContainer
            ->getGenerator()
            ->$methodName($routeName, $attributes);
        
        // remove trailing slashes for sub-pages
        $sep = '/';
        if ($generated !== $sep) {
            $generated = rtrim($generated, $sep);
        }
        
        return $generated;
    }
}
