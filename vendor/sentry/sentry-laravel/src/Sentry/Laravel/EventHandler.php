<?php

namespace Sentry\Laravel;

use Exception;
use Illuminate\Auth\Events as AuthEvents;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events as DatabaseEvents;
use Illuminate\Http\Request;
use Illuminate\Log\Events as LogEvents;
use Illuminate\Routing\Events as RoutingEvents;
use Laravel\Octane\Events as Octane;
use Laravel\Sanctum\Events as Sanctum;
use RuntimeException;
use Sentry\Breadcrumb;
use Sentry\Laravel\Tracing\Middleware;
use Sentry\SentrySdk;
use Sentry\State\Scope;

class EventHandler
{
    /**
     * Map event handlers to events.
     *
     * @var array
     */
    protected static $eventHandlerMap = [
        LogEvents\MessageLogged::class => 'messageLogged',
        RoutingEvents\RouteMatched::class => 'routeMatched',
        DatabaseEvents\QueryExecuted::class => 'queryExecuted',
    ];

    /**
     * Map authentication event handlers to events.
     *
     * @var array
     */
    protected static $authEventHandlerMap = [
        AuthEvents\Authenticated::class => 'authenticated',
        Sanctum\TokenAuthenticated::class => 'sanctumTokenAuthenticated', // Since Sanctum 2.13
    ];

    /**
     * Map Octane event handlers to events.
     *
     * @var array
     */
    protected static $octaneEventHandlerMap = [
        Octane\RequestReceived::class => 'octaneRequestReceived',
        Octane\RequestTerminated::class => 'octaneRequestTerminated',

        Octane\TaskReceived::class => 'octaneTaskReceived',
        Octane\TaskTerminated::class => 'octaneTaskTerminated',

        Octane\TickReceived::class => 'octaneTickReceived',
        Octane\TickTerminated::class => 'octaneTickTerminated',

        Octane\WorkerErrorOccurred::class => 'octaneWorkerErrorOccurred',
        Octane\WorkerStopping::class => 'octaneWorkerStopping',
    ];

    /**
     * The Laravel container.
     *
     * @var \Illuminate\Contracts\Container\Container
     */
    private $container;

    /**
     * Indicates if we should add SQL queries to the breadcrumbs.
     *
     * @var bool
     */
    private $recordSqlQueries;

    /**
     * Indicates if we should add query bindings to the breadcrumbs.
     *
     * @var bool
     */
    private $recordSqlBindings;

    /**
     * Indicates if we should add Laravel logs to the breadcrumbs.
     *
     * @var bool
     */
    private $recordLaravelLogs;

    /**
     * Indicates if we should add tick info to the breadcrumbs.
     *
     * @var bool
     */
    private $recordOctaneTickInfo;

    /**
     * Indicates if we should add task info to the breadcrumbs.
     *
     * @var bool
     */
    private $recordOctaneTaskInfo;

    /**
     * Indicates if we pushed a scope for Octane.
     *
     * @var bool
     */
    private $pushedOctaneScope = false;

    /**
     * EventHandler constructor.
     *
     * @param \Illuminate\Contracts\Container\Container $container
     * @param array                                     $config
     */
    public function __construct(Container $container, array $config)
    {
        $this->container = $container;

        $this->recordSqlQueries = ($config['breadcrumbs.sql_queries'] ?? $config['breadcrumbs']['sql_queries'] ?? true) === true;
        $this->recordSqlBindings = ($config['breadcrumbs.sql_bindings'] ?? $config['breadcrumbs']['sql_bindings'] ?? false) === true;
        $this->recordLaravelLogs = ($config['breadcrumbs.logs'] ?? $config['breadcrumbs']['logs'] ?? true) === true;
        $this->recordOctaneTickInfo = ($config['breadcrumbs.octane_tick_info'] ?? $config['breadcrumbs']['octane_tick_info'] ?? true) === true;
        $this->recordOctaneTaskInfo = ($config['breadcrumbs.octane_task_info'] ?? $config['breadcrumbs']['octane_task_info'] ?? true) === true;
    }

    /**
     * Attach all event handlers.
     */
    public function subscribe(Dispatcher $dispatcher): void
    {
        foreach (static::$eventHandlerMap as $eventName => $handler) {
            $dispatcher->listen($eventName, [$this, $handler]);
        }
    }

    /**
     * Attach all authentication event handlers.
     */
    public function subscribeAuthEvents(Dispatcher $dispatcher): void
    {
        foreach (static::$authEventHandlerMap as $eventName => $handler) {
            $dispatcher->listen($eventName, [$this, $handler]);
        }
    }

    /**
     * Attach all Octane event handlers.
     */
    public function subscribeOctaneEvents(Dispatcher $dispatcher): void
    {
        foreach (static::$octaneEventHandlerMap as $eventName => $handler) {
            $dispatcher->listen($eventName, [$this, $handler]);
        }
    }

    /**
     * Pass through the event and capture any errors.
     *
     * @param string $method
     * @param array  $arguments
     */
    public function __call(string $method, array $arguments)
    {
        $handlerMethod = "{$method}Handler";

        if (!method_exists($this, $handlerMethod)) {
            throw new RuntimeException("Missing event handler: {$handlerMethod}");
        }

        try {
            $this->{$handlerMethod}(...$arguments);
        } catch (Exception $exception) {
            // Ignore
        }
    }

    protected function routeMatchedHandler(RoutingEvents\RouteMatched $match): void
    {
        $routeAlias = $match->route->action['as'] ?? '';

        // Ignore the route if it is the route for the Laravel Folio package
        // We handle that route separately in the FolioPackageIntegration
        if ($routeAlias === 'laravel-folio') {
            return;
        }

        Middleware::signalRouteWasMatched();

        [$routeName] = Integration::extractNameAndSourceForRoute($match->route);

        Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_NAVIGATION,
            'route',
            $routeName
        ));

        Integration::setTransaction($routeName);
    }

    protected function queryExecutedHandler(DatabaseEvents\QueryExecuted $query): void
    {
        if (!$this->recordSqlQueries) {
            return;
        }

        $data = ['connectionName' => $query->connectionName];

        if ($query->time !== null) {
            $data['executionTimeMs'] = $query->time;
        }

        if ($this->recordSqlBindings) {
            $data['bindings'] = $query->bindings;
        }

        Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'db.sql.query',
            $query->sql,
            $data
        ));
    }

    protected function messageLoggedHandler(LogEvents\MessageLogged $logEntry): void
    {
        if (!$this->recordLaravelLogs) {
            return;
        }

        // A log message with `null` as value will not be recorded by Laravel
        // however empty strings are logged so we mimick that behaviour to
        // check for `null` to stay consistent with how Laravel logs it
        if ($logEntry->message === null) {
            return;
        }

        Integration::addBreadcrumb(new Breadcrumb(
            $this->logLevelToBreadcrumbLevel($logEntry->level),
            Breadcrumb::TYPE_DEFAULT,
            'log.' . $logEntry->level,
            $logEntry->message,
            $logEntry->context
        ));
    }

    protected function authenticatedHandler(AuthEvents\Authenticated $event): void
    {
        $this->configureUserScopeFromModel($event->user);
    }

    protected function sanctumTokenAuthenticatedHandler(Sanctum\TokenAuthenticated $event): void
    {
        $this->configureUserScopeFromModel($event->token->tokenable);
    }

    /**
     * Configures the user scope with the user data and values from the HTTP request.
     *
     * @param mixed $authUser
     *
     * @return void
     */
    private function configureUserScopeFromModel($authUser): void
    {
        $userData = [];

        // If the user is a Laravel Eloquent model we try to extract some common fields from it
        if ($authUser instanceof Model) {
            $email = null;

            if ($this->modelHasAttribute($authUser, 'email')) {
                $email = $authUser->getAttribute('email');
            } elseif ($this->modelHasAttribute($authUser, 'mail')) {
                $email = $authUser->getAttribute('mail');
            }

            $username = $this->modelHasAttribute($authUser, 'username')
                ? (string)$authUser->getAttribute('username')
                : null;

            $userData = [
                'id' => $authUser instanceof Authenticatable
                    ? $authUser->getAuthIdentifier()
                    : $authUser->getKey(),
                'email' => $email,
                'username' => $username,
            ];
        }

        try {
            /** @var \Illuminate\Http\Request $request */
            $request = $this->container->make('request');

            if ($request instanceof Request) {
                $ipAddress = $request->ip();

                if ($ipAddress !== null) {
                    $userData['ip_address'] = $ipAddress;
                }
            }
        } catch (BindingResolutionException $e) {
            // If there is no request bound we cannot get the IP address from it
        }

        Integration::configureScope(static function (Scope $scope) use ($userData): void {
            $scope->setUser(array_filter($userData));
        });
    }

    private function modelHasAttribute(Model $model, string $key): bool
    {
        // Taken from: https://github.com/laravel/framework/blob/v11.41.3/src/Illuminate/Database/Eloquent/Concerns/HasAttributes.php#L445
        // Laravel 11 introduced the `hasAttribute` method we are (almost) mirroring here since it's not available in earlier Laravel versions we support
        return array_key_exists($key, $model->getAttributes()) ||
            $model->hasGetMutator($key) ||
            (method_exists($model, 'hasAttributeMutator') && $model->hasAttributeMutator($key));
    }

    protected function octaneRequestReceivedHandler(Octane\RequestReceived $event): void
    {
        $this->prepareScopeForOctane();
    }

    protected function octaneRequestTerminatedHandler(Octane\RequestTerminated $event): void
    {
        $this->cleanupScopeForOctane();
    }

    protected function octaneTaskReceivedHandler(Octane\TaskReceived $event): void
    {
        $this->prepareScopeForOctane();

        if (!$this->recordOctaneTaskInfo) {
            return;
        }

        Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'octane.task',
            'Processing Octane task'
        ));
    }

    protected function octaneTaskTerminatedHandler(Octane\TaskTerminated $event): void
    {
        $this->cleanupScopeForOctane();
    }

    protected function octaneTickReceivedHandler(Octane\TickReceived $event): void
    {
        $this->prepareScopeForOctane();

        if (!$this->recordOctaneTickInfo) {
            return;
        }

        Integration::addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_INFO,
            Breadcrumb::TYPE_DEFAULT,
            'octane.tick',
            'Processing Octane tick'
        ));
    }

    protected function octaneTickTerminatedHandler(Octane\TickTerminated $event): void
    {
        $this->cleanupScopeForOctane();
    }

    protected function octaneWorkerErrorOccurredHandler(Octane\WorkerErrorOccurred $event): void
    {
        $this->afterTaskWithinLongRunningProcess();
    }

    protected function octaneWorkerStoppingHandler(Octane\WorkerStopping $event): void
    {
        $this->afterTaskWithinLongRunningProcess();
    }

    private function prepareScopeForOctane(): void
    {
        $this->cleanupScopeForOctane();

        $this->prepareScopeForTaskWithinLongRunningProcess();

        $this->pushedOctaneScope = true;
    }

    private function cleanupScopeForOctane(): void
    {
        $this->cleanupScopeForTaskWithinLongRunningProcessWhen($this->pushedOctaneScope);

        $this->pushedOctaneScope = false;
    }

    /**
     * Translates common log levels to Sentry breadcrumb levels.
     *
     * @param string $level Log level. Maybe any standard.
     *
     * @return string Breadcrumb level.
     */
    private function logLevelToBreadcrumbLevel(string $level): string
    {
        switch (strtolower($level)) {
            case 'debug':
                return Breadcrumb::LEVEL_DEBUG;
            case 'warning':
                return Breadcrumb::LEVEL_WARNING;
            case 'error':
                return Breadcrumb::LEVEL_ERROR;
            case 'critical':
            case 'alert':
            case 'emergency':
                return Breadcrumb::LEVEL_FATAL;
            case 'info':
            case 'notice':
            default:
                return Breadcrumb::LEVEL_INFO;
        }
    }

    /**
     * Should be called after a task within a long running process has ended so events can be flushed.
     */
    private function afterTaskWithinLongRunningProcess(): void
    {
        Integration::flushEvents();
    }

    /**
     * Should be called before starting a task within a long running process, this is done to prevent
     * the task to have effect on the scope for the next task to run within the long running process.
     */
    private function prepareScopeForTaskWithinLongRunningProcess(): void
    {
        SentrySdk::getCurrentHub()->pushScope();

        // When a job starts, we want to make sure the scope is cleared of breadcrumbs
        SentrySdk::getCurrentHub()->configureScope(static function (Scope $scope) {
            $scope->clearBreadcrumbs();
        });
    }

    /**
     * Cleanup a previously prepared scope.
     *
     * @param bool $when Only cleanup the scope when this is true.
     *
     * @see prepareScopeForTaskWithinLongRunningProcess
     */
    private function cleanupScopeForTaskWithinLongRunningProcessWhen(bool $when): void
    {
        if (!$when) {
            return;
        }

        $this->afterTaskWithinLongRunningProcess();

        SentrySdk::getCurrentHub()->popScope();
    }
}
