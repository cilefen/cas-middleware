# PSR-7 Central Authentication System (CAS) Client Middleware

This middleware implements a [CAS](https://apereo.github.io/cas/) client for use
with frameworks such as [Slim](http://www.slimframework.com/). It is designed
to authenticate users but leaves the session implementation up to the developer
for maximum flexibility.

## Install

    $ composer require cilefen/cas-middleware

## Usage

Implement `Cilefen\Middleware\SessionManagerInterface`. In your implementation, start a
session your desired way:

```php
namespace App;

use Cilefen\Middleware\SessionManagerInterface;

class SessionManager implements SessionManagerInterface
{
    private $url;

    public function __construct(string $url)
    {
        $this->url = $url;
    }

    public function startSession(string $user)
    {
        $_SESSION['user'] = $user;
    }

    public function isAuthenticated(): bool
    {
        return !empty($_SESSION['user']);
    }

    public function serverUrl(): string
    {
        return $this->url;
    }
}
```

Add the middleware to routes or route groups needing authentication. CasMiddleware requires the
SessionManager you created above. In addition, the middleware requires a Guzzle client.

```php
$app = new \Slim\App();

$app->get('/login', function (Request $request, Response $response, array $args) {
    return $response
        ->withStatus(302)
        ->withHeader('Location', '/');
})->add(new \Cilefen\Middleware\CasMiddleware(new \App\SessionManager('https://localhost:8443/cas'), new \GuzzleHttp\Client()));
```
