# Recipes

Concrete patterns built on top of the package. Each one is a worked
example — copy it, adapt the event names to your domain, ship.

- [WordPress-style action hooks](#wordpress-style-action-hooks)
- [WordPress-style filter hooks (value-passing)](#wordpress-style-filter-hooks-value-passing)
- [Request lifecycle hooks in an HTTP application](#request-lifecycle-hooks-in-an-http-application)
- [A tiny plugin system](#a-tiny-plugin-system)
- [Per-request dispatcher in a long-running worker](#per-request-dispatcher-in-a-long-running-worker)
- [Testing code that publishes events](#testing-code-that-publishes-events)

## WordPress-style action hooks

"Do this when X happens" — listeners are notified, no return value
matters.

```php
use InitPHP\Events\Events;

// publish
function do_action(string $hook, ...$args): void
{
    Events::trigger($hook, ...$args);
}

// subscribe
function add_action(string $hook, callable $listener, int $priority = Events::PRIORITY_NORMAL): void
{
    Events::on($hook, $listener, $priority);
}

// usage
add_action('user.registered', function (array $user): void {
    sendWelcomeEmail($user);
});

do_action('user.registered', ['id' => 42, 'email' => 'x@example.com']);
```

The fact that `Events::trigger()` returns a `bool` is irrelevant
here — actions don't care about return values. Side-effect-only
listeners can return `null` (the default).

## WordPress-style filter hooks (value-passing)

"Let every interested listener massage this value before I use it" —
the value threads through the listeners. The package's
short-circuit-on-false contract is *not* quite the same as
`apply_filters`, so this recipe uses an explicit reference.

```php
use InitPHP\Events\Events;

function apply_filters(string $hook, $value, ...$args)
{
    // listeners receive (&$value, ...$args); they mutate $value.
    Events::trigger($hook, ...array_merge([&$value], $args));
    return $value;
}

Events::on('the.title', function (string &$title): void {
    $title = ucfirst($title);
});

Events::on('the.title', function (string &$title): void {
    $title = '› ' . $title;
}, Events::PRIORITY_LOW);

echo apply_filters('the.title', 'hello world');
// › Hello world
```

If you'd rather use the package's "return false stops the chain"
contract for a veto-style filter:

```php
$value = 'untrusted input';
if (!Events::trigger('validate.input', $value)) {
    throw new \DomainException('input failed validation');
}
```

## Request lifecycle hooks in an HTTP application

```php
use InitPHP\Events\Event;

final class App
{
    private Event $events;

    public function __construct()
    {
        $this->events = new Event();
    }

    public function on(string $hook, callable $listener, int $priority = Event::PRIORITY_NORMAL): self
    {
        $this->events->on($hook, $listener, $priority);
        return $this;
    }

    public function handle(Request $request): Response
    {
        $this->events->trigger('request.received', $request);

        try {
            $response = $this->dispatch($request);
        } catch (\Throwable $e) {
            $this->events->trigger('request.exception', $request, $e);
            throw $e;
        }

        $this->events->trigger('response.ready', $request, $response);

        return $response;
    }
}

$app = new App();

$app->on('request.received', function (Request $req): void {
    Log::info('inbound', ['method' => $req->method(), 'path' => $req->path()]);
}, Event::PRIORITY_HIGH);

$app->on('response.ready', function (Request $req, Response $res): void {
    Metrics::record('http.duration', $req->elapsedMs(), ['status' => $res->status()]);
}, Event::PRIORITY_LOW);

$response = $app->handle($request);
```

`App` owns its own `Event` dispatcher rather than reaching into the
static facade — important for testability and for long-running
servers that handle many requests in one process.

## A tiny plugin system

```php
use InitPHP\Events\Event;

interface Plugin
{
    public function register(Event $events): void;
}

final class PluginHost
{
    /** @var Plugin[] */
    private array $plugins = [];

    public function __construct(private Event $events) {}

    public function install(Plugin $plugin): void
    {
        $plugin->register($this->events);
        $this->plugins[] = $plugin;
    }
}

// A plugin
final class AuditLogPlugin implements Plugin
{
    public function register(Event $events): void
    {
        $events->on('user.created',  [$this, 'logCreate']);
        $events->on('user.deleted',  [$this, 'logDelete']);
        $events->on('user.modified', [$this, 'logModify'], Event::PRIORITY_LOW);
    }

    public function logCreate(array $user): void { /* ... */ }
    public function logDelete(array $user): void { /* ... */ }
    public function logModify(array $user): void { /* ... */ }
}

// Wire it up
$events = new Event();
$host = new PluginHost($events);
$host->install(new AuditLogPlugin());

// Now every part of the app that triggers user.* events automatically
// hits the plugin's listeners.
$events->trigger('user.created', ['id' => 1, 'email' => 'a@example.com']);
```

If you want plugins to be removable, have the `register()` method
return a list of `[$event, $listener]` pairs and store them on the
host, then call `$events->off(...)` for each on uninstall.

## Per-request dispatcher in a long-running worker

In a long-lived process (queue worker, HTTP server with persistent
PHP) the static `Events` facade keeps listeners between requests.
That is almost always a bug. Two ways to avoid it:

### Option A — reset the facade at the boundary

```php
while ($job = $queue->reserve()) {
    \InitPHP\Events\Events::reset();
    handle($job);
}
```

Simple, but relies on every part of your code remembering to
register its listeners on every iteration. Fragile.

### Option B — own a dispatcher, scope it to the request

```php
while ($job = $queue->reserve()) {
    $events = new \InitPHP\Events\Event();

    // Register the per-request listeners
    JobHandlers::register($events, $job);

    handle($job, $events);
}
```

Robust, and the boundary is explicit. Recommended.

## Testing code that publishes events

### Pattern 1 — capture with a closure

```php
public function test_user_registration_publishes_event(): void
{
    Events::reset();

    $captured = null;
    Events::on('user.registered', function (array $user) use (&$captured): void {
        $captured = $user;
    });

    registerUser(['email' => 'x@example.com']);

    $this->assertNotNull($captured);
    $this->assertSame('x@example.com', $captured['email']);
}
```

### Pattern 2 — assert via the debug log

When you have many events to assert on and don't want to register a
listener for each:

```php
public function test_checkout_flow_publishes_the_expected_sequence(): void
{
    $dispatcher = (new Event())->setDebugMode(true);
    Events::setInstance($dispatcher);

    runCheckout($order);

    $names = array_column(Events::getDebug(), 'event');
    $this->assertSame(
        ['cart.locked', 'payment.captured', 'order.confirmed'],
        $names
    );
}
```

### Pattern 3 — use simulate mode for "what would happen"

```php
public function test_dry_run_destructive_listener_is_not_executed(): void
{
    $dispatcher = (new Event())->setSimulate(true)->setDebugMode(true);
    Events::setInstance($dispatcher);

    $fired = false;
    Events::on('payment.capture', function () use (&$fired): void {
        $fired = true;
    });

    Events::trigger('payment.capture', $order);

    $this->assertFalse($fired, 'simulate mode must not invoke listeners');
    $this->assertCount(1, Events::getDebug(), 'but the event was still recorded');
}
```

### Always call `Events::reset()` in `setUp()` / `tearDown()`

Otherwise listeners from one test bleed into the next. This is the
single most common test-stability bug when working with a static
event facade.

## Next

- [Chapter 9 — API reference](09-api-reference.md) — for the dry
  details once you know what shape you want your code to take.
