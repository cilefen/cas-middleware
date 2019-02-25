<?php
declare(strict_types=1);

namespace Cilefen\Middleware;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class CasMiddleware
{
    private $sessionManager;

    public function __construct(SessionManagerInterface $sessionManager, ClientInterface $client)
    {
        $this->sessionManager = $sessionManager;
        $this->client = $client;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next): ResponseInterface
    {
        $newResponse = $next($request, $response);
        if (isset($request->getQueryParams()['ticket'])) {
            $validationResponse = $this->validateTicket($request);
            $xml = $this->parseXml($validationResponse);
            if ($this->authenticationSucceeded($xml)) {
                $newResponse = $this->initiateSession($request, $response, $xml);
            }
        }
        else if (!$this->sessionManager->isAuthenticated()) {
            $newResponse = $this->initiateLogin($request, $response);
        }

        return $newResponse;
    }

    private function validateTicket(ServerRequestInterface $request): ResponseInterface
    {
        $validationResponse = $this->client->request(
            'GET',
            $this->sessionManager->serverUrl() . '/serviceValidate',
            [
                'query' => [
                    'service' => $this->removeTicketFromUri((string)$request->getUri()),
                    'ticket' => $request->getQueryParams()['ticket'],
                ]
            ]
        );
        return $validationResponse;
    }

    private function parseXml(ResponseInterface $validationResponse): \SimpleXMLElement
    {
        $rawXml = $validationResponse->getBody()->getContents();
        return new \SimpleXMLElement($rawXml, 0, false, 'cas', true);
    }

    private function authenticationSucceeded(\SimpleXMLElement $xml): bool
    {
        return isset($xml->authenticationSuccess);
    }

    private function initiateLogin(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $service = urlencode((string)$request->getUri());
        return $response
            ->withStatus(302)
            ->withHeader('Location', $this->sessionManager->serverUrl() . '/login?service=' . $service);
    }

    private function initiateSession(ServerRequestInterface $request, ResponseInterface $response, \SimpleXMLElement $xml): ResponseInterface
    {
        $this->sessionManager->startSession((string)$xml->authenticationSuccess->user);
        return $response
            ->withStatus(302)
            ->withHeader('Location', $this->removeTicketFromUri((string)$request->getUri()));
    }

    private function removeTicketFromUri($uri) {
        $parsed_url = parse_url($uri);
        // If there are no query parameters, then there is nothing to do.
        if (empty($parsed_url['query'])) {
            return $uri;
        }
        parse_str($parsed_url['query'], $query_params);
        // If there is no 'ticket' parameter, there is nothing to do.
        if (!isset($query_params['ticket'])) {
            return $uri;
        }
        // Remove the ticket parameter and rebuild the query string.
        unset($query_params['ticket']);
        if (empty($query_params)) {
            unset($parsed_url['query']);
        } else {
            $parsed_url['query'] = http_build_query($query_params);
        }

        // Rebuild the URI from the parsed components.
        // Source: https://secure.php.net/manual/en/function.parse-url.php#106731
        $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
        $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
        $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
        $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
        $pass     = ($user || $pass) ? "$pass@" : '';
        $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
        $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
        $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
        return "$scheme$user$pass$host$port$path$query$fragment";
    }
}