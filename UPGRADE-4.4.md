UPGRADE FROM 4.3 to 4.4
=======================

DependencyInjection
-------------------

 * Deprecated support for short factories and short configurators in Yaml

   Before:
   ```yaml
   services:
     my_service:
       factory: factory_service:method
   ```

   After:
   ```yaml
   services:
     my_service:
       factory: ['@factory_service', method]
   ```
 * Deprecated `tagged` in favor of `tagged_iterator`

   Before:
   ```yaml
   services:
       App\Handler:
           tags: ['app.handler']
   
       App\HandlerCollection:
           arguments: [!tagged app.handler]
   ```

   After:
   ```yaml
   services:
       App\Handler:
       tags: ['app.handler']
    
   App\HandlerCollection:
       arguments: [!tagged_iterator app.handler]
   ```

FrameworkBundle
---------------

 * Deprecated support for `templating` engine in `TemplateController`, use Twig instead
 * The `$parser` argument of `ControllerResolver::__construct()` and `DelegatingLoader::__construct()`
   has been deprecated.
 * The `ControllerResolver` and `DelegatingLoader` classes have been marked as `final`.
 * The `controller_name_converter` and `resolve_controller_name_subscriber` services have been deprecated.

HttpClient
----------

 * Added method `cancel()` to `ResponseInterface`

HttpKernel
----------

 * The `DebugHandlersListener` class has been marked as `final`

Messenger
---------

 * Deprecated passing a `ContainerInterface` instance as first argument of the `ConsumeMessagesCommand` constructor,
   pass a `RoutableMessageBus`  instance instead.

MonologBridge
--------------

 * The `RouteProcessor` has been marked final.

Security
--------

 * Implementations of `PasswordEncoderInterface` and `UserPasswordEncoderInterface` should add a new `needsRehash()` method

TwigBridge
----------

 * Deprecated to pass `$rootDir` and `$fileLinkFormatter` as 5th and 6th argument respectively to the 
   `DebugCommand::__construct()` method, swap the variables position.

Validator
---------

 * Deprecated passing an `ExpressionLanguage` instance as the second argument of `ExpressionValidator::__construct()`. 
   Pass it as the first argument instead.
