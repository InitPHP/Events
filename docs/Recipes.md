# Recipes

Practical, copy-pasteable patterns that combine the building blocks
documented elsewhere in this wiki. Each recipe links back to the
relevant reference page.

## 1. WordPress-style action hooks

A drop-in for the `do_action` / `add_action` mental model: a global
event name, multiple subscribers, no return value coordination.

```php
use InitPHP\Events\Events;

// Subscribers — declared anywhere in your app.
Events::on('post.published', function (array $post) {
    PingSitemap::send($post['url']);
});

Events::on('post.published', function (array $post) {
    Newsletter::queue($post['id']);
});

Events::on('post.published', function (array $post) {
    Cache::invalidate('feed');
});

// Trigger — from wherever the action happens.
Events::trigger('post.published', $post);
```

Reference: [`Events` facade](Events-Facade.md).

## 2. Veto-able pre-save hook

Validate a payload via a chain of listeners. Any listener can stop the
save by returning `false`. Use `Events::trigger()` and check its
return value.

Pass the payload as an **object** (here an `ArrayObject`, which keeps
array-style access) so listeners can mutate it. A plain array cannot be
mutated through `Events::trigger()`: the variadic arguments are collected by
value, so a listener declared with `array &$user` receives a copy and PHP
raises a warning.

```php
use InitPHP\Events\Events;

Events::on('user.before_save', function (ArrayObject $user) {
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
        return false; // veto
    }
});

Events::on('user.before_save', function (ArrayObject $user) {
    $user['email'] = strtolower($user['email']); // mutate
});

Events::on('user.before_save', function (ArrayObject $user) {
    if (User::emailTaken($user['email'])) {
        return false; // veto
    }
});

$user = new ArrayObject($userArray);

if (Events::trigger('user.before_save', $user) === false) {
    throw new RuntimeException('Validation failed.');
}

User::save($user->getArrayCopy());
```

References: [Stopping Propagation](Stopping-Propagation.md),
[FAQ › Can a listener mutate the event payload?](FAQ.md#can-a-listener-mutate-the-event-payload).

## 3. Decoupled domain events with `EventEmitter`

When you would rather depend-inject an event bus than reach for a
global, instantiate one and pass it through your DI container.

```php
use InitPHP\Events\EventEmitter;
use InitPHP\Events\EventEmitterInterface;

final class OrderService
{
    /** @var EventEmitterInterface */
    private $events;

    public function __construct(EventEmitterInterface $events)
    {
        $this->events = $events;
    }

    public function place(array $order): void
    {
        // … persist …
        $this->events->emit('order.placed', [$order]);
    }
}

// composition root
$bus = new EventEmitter();
$bus->on('order.placed', new SendOrderConfirmation());
$bus->on('order.placed', new EnqueueShipping());

$service = new OrderService($bus);
$service->place($order);
```

Reference: [`EventEmitter`](EventEmitter.md).

## 4. One-shot bootstrap step

Run an initialiser exactly once, even if the trigger fires more than
once (e.g. lazy initialisation). Both APIs expose `once()`:

```php
use InitPHP\Events\Events;

Events::once('app.ready', function () {
    Container::warmup();
});

Events::trigger('app.ready'); // warmup runs
Events::trigger('app.ready'); // silent no-op
```

Or, with the low-level emitter:

```php
use InitPHP\Events\EventEmitter;

$bus = new EventEmitter();
$bus->once('app.ready', fn () => Container::warmup());
$bus->emit('app.ready'); // warmup runs
$bus->emit('app.ready'); // silent no-op
```

Reference: [`Events` facade › `once`](Events-Facade.md#eventsoncestring-name-callable-callback-int-priority--priority_normal)
and [`EventEmitter` › `once`](EventEmitter.md#oncestring-event-callable-listener-int-priority--100-this).

## 5. Test isolation — start each test with a clean facade

Call `Events::reset()` in `setUp()` / `tearDown()` so listeners,
simulate / debug flags, and the debug log from one test never bleed
into the next:

```php
use InitPHP\Events\Events;

final class MyTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void  { Events::reset(); }
    protected function tearDown(): void { Events::reset(); }

    public function test_save_path_is_reached(): void
    {
        $captured = null;
        Events::on('save', function ($payload) use (&$captured): void {
            $captured = $payload;
        });

        runProductionCodePath();

        $this->assertSame('expected', $captured);
    }
}
```

If you need a *dry-run* of a code path (walk the listener queue without
actually firing the side effects), combine `reset()` with
`setSimulate(true)` — or inject a pre-configured dispatcher:

```php
$dryRun = (new Event())->setSimulate(true)->setDebugMode(true);
Events::setInstance($dryRun);

runProductionCodePath();

$dispatched = array_column(Events::getDebug(), 'event');
// $dispatched contains the events that *would* have fired.
```

Reference: [Simulate & Debug Mode](Simulate-and-Debug.md),
[`Events` facade › Singleton lifecycle](Events-Facade.md#singleton-lifecycle).

## 6. Lightweight profiler for a hot path

Enable debug mode around a critical section and inspect per-listener
timings:

```php
use InitPHP\Events\Events;

Events::setDebugMode(true);

Events::trigger('checkout.flow', $cart);

$debug = Events::getDebug();
usort($debug, fn($a, $b) => ($b['end']-$b['start']) <=> ($a['end']-$a['start']));

foreach (array_slice($debug, 0, 5) as $row) {
    $ms = ($row['end'] - $row['start']) * 1000;
    printf("%6.2fms  %s\n", $ms, $row['event']);
}

Events::setDebugMode(false);
```

Reference: [Simulate & Debug Mode › Debug mode](Simulate-and-Debug.md#debug-mode).

## 7. Removing a listener you cannot recapture

`EventEmitter::removeListener()` needs the exact callable. If you can't
keep a reference (e.g. the original code is a third-party plugin),
nuke the entire event:

```php
$bus->removeAllListeners('order.placed');
```

If you only want to drop listeners temporarily, snapshot first:

```php
$snapshot = $bus->listeners('order.placed');
$bus->removeAllListeners('order.placed');

try {
    $bus->emit('order.placed', [$order]); // no listeners run
} finally {
    foreach ($snapshot as $listener) {
        $bus->on('order.placed', $listener);
    }
}
```

References: [`EventEmitter` › `removeListener`](EventEmitter.md#removelistenerstring-event-callable-listener-void),
[`removeAllListeners`](EventEmitter.md#removealllistenersstring-event--null-void).

## 8. Class-based listener with `__invoke`

Group listener logic into a class — handy when the handler needs
dependencies of its own.

```php
final class SendOrderConfirmation
{
    public function __construct(private Mailer $mailer) {}

    public function __invoke(array $order): void
    {
        $this->mailer->send($order['email'], 'Order confirmation', /* … */);
    }
}

$bus->on('order.placed', new SendOrderConfirmation($mailer));
```

The same trick works on the [`Events` facade](Events-Facade.md):

```php
Events::on('order.placed', new SendOrderConfirmation($mailer));
```

## 9. Forwarding events between buses

You can wire one bus's emissions into another by registering a thin
adapter listener:

```php
$bus = new EventEmitter();
$bus->on('user.registered', function (...$args) {
    Events::trigger('user.registered', ...$args);
});
```

Useful while migrating from one API to the other, or when integrating
two subsystems that each prefer a different style.

## 10. WordPress-style filter hooks (value-passing)

"Let every interested listener massage this value before I use it." The
package's "return `false` stops the chain" contract is not the same as
`apply_filters`, and `Events::trigger()` cannot pass a plain value by
reference, so this recipe threads the value through a small holder object:

```php
use InitPHP\Events\Events;

function apply_filters(string $hook, $value, ...$args)
{
    $box = (object) ['value' => $value];

    Events::trigger($hook, $box, ...$args);

    return $box->value;
}

Events::on('the.title', function (object $box): void {
    $box->value = ucfirst($box->value);
});

Events::on('the.title', function (object $box): void {
    $box->value = '› ' . $box->value;
}, Events::PRIORITY_LOW);

echo apply_filters('the.title', 'hello world');
// › Hello world
```

If you would rather use the package's "return `false` stops the chain"
contract for a veto-style filter, check the return value of `trigger()`:

```php
if (!Events::trigger('validate.input', $input)) {
    throw new \DomainException('input failed validation');
}
```

References: [Stopping Propagation](Stopping-Propagation.md).

## 11. Request lifecycle hooks in an HTTP application

Own an `Event` dispatcher inside the application object instead of reaching
into the static facade — important for testability and for long-running
servers that handle many requests in one process.

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

(`Request`, `Response`, `Log` and `Metrics` stand in for your own classes.)

## 12. A tiny plugin system

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

// Every part of the app that triggers user.* events now reaches the plugin.
$events->trigger('user.created', ['id' => 1, 'email' => 'a@example.com']);
```

If you want plugins to be removable, have `register()` return a list of
`[$event, $listener]` pairs, store them on the host, and call
`$events->off(...)` for each on uninstall. `[$this, 'logCreate']` pairs match
by value, so they can also be rebuilt at uninstall time — see
[Once-Listeners & Removal](Once-and-Removal.md#identity-not-equality).

## 13. Per-request dispatcher in a long-running worker

In a long-lived process (queue worker, HTTP server with persistent PHP) the
static `Events` facade keeps listeners between requests, which is almost
always a bug. Two ways to avoid it:

**Option A — reset the facade at the boundary.** Simple, but relies on every
part of your code remembering to register its listeners on every iteration:

```php
while ($job = $queue->reserve()) {
    \InitPHP\Events\Events::reset();
    handle($job);
}
```

**Option B — own a dispatcher and scope it to the job.** Robust, and the
boundary is explicit. Recommended:

```php
while ($job = $queue->reserve()) {
    $events = new \InitPHP\Events\Event();

    JobHandlers::register($events, $job);   // per-job listeners

    handle($job, $events);
}
```

## See also

- [Once-Listeners & Removal](Once-and-Removal.md) — the lifecycle controls
  behind several of these recipes.
- [`Events` facade](Events-Facade.md) and [`EventEmitter`](EventEmitter.md) —
  the two APIs.
- [Listeners & Priorities](Listeners-and-Priorities.md) — ordering and
  callables.
- [Troubleshooting](Troubleshooting.md) — when things don't fire.
