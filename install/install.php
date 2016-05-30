<?php
/**
 * Created by PhpStorm.
 * User: lelouch
 * Date: 29/5/16
 * Time: 5:50 PM
 */
$RouteCollector_php = <<<ROUTE_COLLECTOR
<?php

namespace FastRoute;

use SuperClosure\Serializer;

class RouteCollector {
    private \$routeParser;
    private \$dataGenerator;

    /**
     * Constructs a route collector.
     *
     * @param RouteParser   \$routeParser
     * @param DataGenerator \$dataGenerator
     */
    public function __construct(RouteParser \$routeParser, DataGenerator \$dataGenerator) {
        \$this->routeParser = \$routeParser;
        \$this->dataGenerator = \$dataGenerator;
    }

    /**
     * Adds a route to the collection.
     *
     * The syntax used in the \$route string depends on the used route parser.
     *
     * @param string|string[] \$httpMethod
     * @param string \$route
     * @param mixed  \$handler
     */
    public function addRoute(\$httpMethod, \$route, \$handler) {
        /*
         * My custom patch...
         * Instantiate an SuperClosure instance.
         * Serialize the handler before inserting it.
         */
        \$serializer = new Serializer();

        // Serialize the handler.
        \$serializedHandler = \$serializer->serialize(\$handler);
        \$routeDatas = \$this->routeParser->parse(\$route);
        foreach ((array) \$httpMethod as \$method) {
            foreach (\$routeDatas as \$routeData) {
                // Use the serialized handler, rather than the original handler.
                \$this->dataGenerator->addRoute(\$method, \$routeData, \$serializedHandler);
            }
        }
    }

    /**
     * Returns the collected route data, as provided by the data generator.
     *
     * @return array
     */
    public function getData() {
        return \$this->dataGenerator->getData();
    }
}

ROUTE_COLLECTOR;

$RegexBasedAbstract_php = <<<REGEXBASEDABSTRACT
<?php

namespace FastRoute\Dispatcher;

use FastRoute\Dispatcher;

abstract class RegexBasedAbstract implements Dispatcher {
    protected \$staticRouteMap;
    protected \$variableRouteData;

    protected abstract function dispatchVariableRoute(\$routeData, \$uri);

    public function dispatch(\$httpMethod, \$uri) {
        if (isset(\$this->staticRouteMap[\$httpMethod][\$uri])) {
            \$handler = \$this->staticRouteMap[\$httpMethod][\$uri];
            /*
             * My custom patch:
             * Need to return the unserialized closure, i.e. the \$handler
             */
            \$serializer = new \SuperClosure\Serializer();

            // Unserialize the handler
            \$unserializedHandler = \$serializer->unserialize(\$handler);

            // Use the unserialized
            return [self::FOUND, \$unserializedHandler, []];
        }

        \$varRouteData = \$this->variableRouteData;
        if (isset(\$varRouteData[\$httpMethod])) {
            \$result = \$this->dispatchVariableRoute(\$varRouteData[\$httpMethod], \$uri);
            if (\$result[0] === self::FOUND) {
                return \$result;
            }
        }

        // For HEAD requests, attempt fallback to GET
        if (\$httpMethod === 'HEAD') {
            if (isset(\$this->staticRouteMap['GET'][\$uri])) {
                \$handler = \$this->staticRouteMap['GET'][\$uri];
                return [self::FOUND, \$handler, []];
            }
            if (isset(\$varRouteData['GET'])) {
                \$result = \$this->dispatchVariableRoute(\$varRouteData['GET'], \$uri);
                if (\$result[0] === self::FOUND) {
                    return \$result;
                }
            }
        }

        // If nothing else matches, try fallback routes
        if (isset(\$this->staticRouteMap['*'][\$uri])) {
            \$handler = \$this->staticRouteMap['*'][\$uri];
            return [self::FOUND, \$handler, []];
        }
        if (isset(\$varRouteData['*'])) {
            \$result = \$this->dispatchVariableRoute(\$varRouteData['*'], \$uri);
            if (\$result[0] === self::FOUND) {
                return \$result;
            }
        }

        // Find allowed methods for this URI by matching against all other HTTP methods as well
        \$allowedMethods = [];

        foreach (\$this->staticRouteMap as \$method => \$uriMap) {
            if (\$method !== \$httpMethod && isset(\$uriMap[\$uri])) {
                \$allowedMethods[] = \$method;
            }
        }

        foreach (\$varRouteData as \$method => \$routeData) {
            if (\$method === \$httpMethod) {
                continue;
            }

            \$result = \$this->dispatchVariableRoute(\$routeData, \$uri);
            if (\$result[0] === self::FOUND) {
                \$allowedMethods[] = \$method;
            }
        }

        // If there are no allowed methods the route simply does not exist
        if (\$allowedMethods) {
            return [self::METHOD_NOT_ALLOWED, \$allowedMethods];
        } else {
            return [self::NOT_FOUND];
        }
    }
}

REGEXBASEDABSTRACT;

$SerializableClosure_php = <<<SERIALIZABLECLOSURE
<?php namespace SuperClosure;

use Closure;
use SuperClosure\Exception\ClosureUnserializationException;

require_once  __DIR__ . "/../../../../src/lib/Core/Utility/Response.php";

/**
 * This class acts as a wrapper for a closure, and allows it to be serialized.
 *
 * With the combined power of the Reflection API, code parsing, and the infamous
 * `eval()` function, you can serialize a closure, unserialize it somewhere
 * else (even a different PHP process), and execute it.
 */
class SerializableClosure implements \Serializable
{
    /**
     * The closure being wrapped for serialization.
     *
     * @var Closure
     */
    private \$closure;

    /**
     * The serializer doing the serialization work.
     *
     * @var SerializerInterface
     */
    private \$serializer;

    /**
     * The data from unserialization.
     *
     * @var array
     */
    private \$data;

    /**
     * Create a new serializable closure instance.
     *
     * @param Closure                  \$closure
     * @param SerializerInterface|null \$serializer
     */
    public function __construct(
        \Closure \$closure,
        SerializerInterface \$serializer = null
    ) {
        \$this->closure = \$closure;
        \$this->serializer = \$serializer ?: new Serializer;
    }

    /**
     * Return the original closure object.
     *
     * @return Closure
     */
    public function getClosure()
    {
        return \$this->closure;
    }

    /**
     * Delegates the closure invocation to the actual closure object.
     *
     * Important Notes:
     *
     * - `ReflectionFunction::invokeArgs()` should not be used here, because it
     *   does not work with closure bindings.
     * - Args passed-by-reference lose their references when proxied through
     *   `__invoke()`. This is an unfortunate, but understandable, limitation
     *   of PHP that will probably never change.
     *
     * @return mixed
     */
    public function __invoke()
    {
        return call_user_func_array(\$this->closure, func_get_args());
    }

    /**
     * Clones the SerializableClosure with a new bound object and class scope.
     *
     * The method is essentially a wrapped proxy to the Closure::bindTo method.
     *
     * @param mixed \$newthis  The object to which the closure should be bound,
     *                        or NULL for the closure to be unbound.
     * @param mixed \$newscope The class scope to which the closure is to be
     *                        associated, or 'static' to keep the current one.
     *                        If an object is given, the type of the object will
     *                        be used instead. This determines the visibility of
     *                        protected and private methods of the bound object.
     *
     * @return SerializableClosure
     * @link http://www.php.net/manual/en/closure.bindto.php
     */
    public function bindTo(\$newthis, \$newscope = 'static')
    {
        return new self(
            \$this->closure->bindTo(\$newthis, \$newscope),
            \$this->serializer
        );
    }

    /**
     * Serializes the code, context, and binding of the closure.
     *
     * @return string|null
     * @link http://php.net/manual/en/serializable.serialize.php
     */
    public function serialize()
    {
        try {
            \$this->data = \$this->data ?: \$this->serializer->getData(\$this->closure, true);
            return serialize(\$this->data);
        } catch (\Exception \$e) {
            trigger_error(
                'Serialization of closure failed: ' . \$e->getMessage(),
                E_USER_NOTICE
            );
            // Note: The serialize() method of Serializable must return a string
            // or null and cannot throw exceptions.
            return null;
        }
    }

    /**
     * Unserializes the closure.
     *
     * Unserializes the closure's data and recreates the closure using a
     * simulation of its original context. The used variables (context) are
     * extracted into a fresh scope prior to redefining the closure. The
     * closure is also rebound to its former object and scope.
     *
     * @param string \$serialized
     *
     * @throws ClosureUnserializationException
     * @link http://php.net/manual/en/serializable.unserialize.php
     */
    public function unserialize(\$serialized)
    {
        // Unserialize the closure data and reconstruct the closure object.
        \$this->data = unserialize(\$serialized);
        \$this->closure = __reconstruct_closure(\$this->data);

        // Throw an exception if the closure could not be reconstructed.
        if (!\$this->closure instanceof Closure) {
            throw new ClosureUnserializationException(
                'The closure is corrupted and cannot be unserialized.'
            );
        }

        // Rebind the closure to its former binding and scope.
        if (\$this->data['binding'] || \$this->data['isStatic']) {
            \$this->closure = \$this->closure->bindTo(
                \$this->data['binding'],
                \$this->data['scope']
            );
        }
    }

    /**
     * Returns closure data for `var_dump()`.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return \$this->data ?: \$this->serializer->getData(\$this->closure, true);
    }
}

/**
 * Reconstruct a closure.
 *
 * HERE BE DRAGONS!
 *
 * The infamous `eval()` is used in this method, along with the error
 * suppression operator, and variable variables (i.e., double dollar signs) to
 * perform the unserialization logic. I'm sorry, world!
 *
 * This is also done inside a plain function instead of a method so that the
 * binding and scope of the closure are null.
 *
 * @param array \$__data Unserialized closure data.
 *
 * @return Closure|null
 * @internal
 */
function __reconstruct_closure(array \$__data)
{
    // Simulate the original context the closure was created in.
    foreach (\$__data['context'] as \$__var_name => &\$__value) {
        if (\$__value instanceof SerializableClosure) {
            // Unbox any SerializableClosures in the context.
            \$__value = \$__value->getClosure();
        } elseif (\$__value === Serializer::RECURSION) {
            // Track recursive references (there should only be one).
            \$__recursive_reference = \$__var_name;
        }

        // Import the variable into this scope.
        \${\$__var_name} = \$__value;
    }

    // Evaluate the code to recreate the closure.
    try {
        if (isset(\$__recursive_reference)) {
            // Special handling for recursive closures.
            @eval("\${\$__recursive_reference} = {\$__data['code']};");
            \$__closure = \${\$__recursive_reference};
        } else {
            @eval("\$__closure = {\$__data['code']};");
        }
    } catch (\ParseError \$e) {
        // Discard the parse error.
    }

    return isset(\$__closure) ? \$__closure : null;
}

SERIALIZABLECLOSURE;

echo "This executable needs composer installed globally!\n";

system("cd .. && composer install");

// Replace the files

if(file_exists(__DIR__ . "/../vendor/nikic/fast-route/src/Dispatcher.php")) {
    file_put_contents(__DIR__ . "/../vendor/nikic/fast-route/src/RouteCollector.php", $RouteCollector_php);
    file_put_contents(__DIR__ . "/../vendor/nikic/fast-route/src/Dispatcher/RegexBasedAbstract.php", $RegexBasedAbstract_php);
    file_put_contents(__DIR__ . "/../vendor/jeremeamia/SuperClosure/src/SerializableClosure.php", $SerializableClosure_php);
} else {
    exit("YOU SUCK AND YOUR COMPUTER SUCKS.\nI may suck as well, but the probability is less...");
}

