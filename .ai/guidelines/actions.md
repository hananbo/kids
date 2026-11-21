# Action Classes

- Put discrete business operations in single-purpose action classes and keep controllers focused on HTTP concerns.
- Define the action's business operation in a typed `__invoke()` method. Do not use `handle()` or `execute()` as the action's business-method name.
- Resolve actions through Laravel's service container and inject their dependencies through the constructor.
- Invoke synchronous actions directly as callables: `$result = $action(...$arguments);`.
- When an action must support queued execution, add the `Spatie\QueueableAction\QueueableAction` trait to the action class.
- Dispatch a queueable invokable action with `$action->onQueue()->execute(...$arguments)`. The package's `execute()` call is only the queue-dispatch proxy; the action's business logic must remain in `__invoke()`.
- Only add the `QueueableAction` trait when the action genuinely needs queued execution.
