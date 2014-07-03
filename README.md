# What is Ninja

Ninja is a very simple firewall which you can configure to do awesome things. It's still in development, but it may already be used.


## How to configure

Ninja uses [Leaky Bucket](http://en.wikipedia.org/wiki/Leaky_bucket) for throttling requests. You can teach your Ninja about hazards, and block them where needed. 

``` php
<?php
use \Ninja\Ninja;

Ninja::addHazard(
    'throttle',
    Ninja::HAZARD_TYPE_THROTTLE,
    function (\Symfony\Component\HttpFoundation\Request $request) {
        return true;
    },
    array(
        'bucket_size' => 10,
        'bucket_leak' => 1
    )
);
```

When the hazard returns true, it means the hazard has been detected. To detect a hazard, you retrieve a Request object. You can check that for all sorts of things. Apart from the `bucket_size` and `bucket_leak` you can also specify a `timeout` for when attacks happen.

You should also give your Ninja something to protect.

``` php
use Ninja\Ninja;

// ...
Request::enableHttpMethodParameterOverride();
$request = Request::createFromGlobals();

// Send the Ninjas
Ninja::prepare(__DIR__ . '/../app/config/ninja.php', $request);
Ninja::protect();

$response = $kernel->handle($request);

// Inject the Ninja in the response
Ninja::inject($response);

$response->send();
$kernel->terminate($request, $response);
```

## Legals
You can find the LICENSE file in this project.
