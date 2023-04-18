<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\SessionBuilder as VanillaSessionBuilder;
use PommProject\Foundation\Session\Session as BaseSession;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * The Pomm service manager.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @implements \ArrayAccess<string,BaseSession|VanillaSessionBuilder>
 */
class Pomm implements \ArrayAccess, LoggerAwareInterface
{
    /** @var array<string,VanillaSessionBuilder> */
    protected array $builders = [];

    /** @var array<string,array<int,callable>> */
    protected array $postConfigurations = [];

    /** @var array<string,BaseSession>  */
    protected array $sessions = [];

    protected ?string $default = null;

    use LoggerAwareTrait;

    /**
     * Instantiate a new Pomm Service class. It takes an array of configurations as parameter.
     * Following configuration settings are supported by this service for each configuration:
     *
     * class_name   name of the DatabaseConfiguration class to instantiate.
     *
     * @throws FoundationException
     *
     * @param array<string,mixed> $configurations
     */
    public function __construct(array $configurations = [])
    {
        foreach ($configurations as $name => $configuration) {
            $builderClass = SessionBuilder::class;
            if (isset($configuration['class:session_builder'])) {
                $builderClass = $this->checkSessionBuilderClass($configuration['class:session_builder']);
            }

            $this->addBuilder($name, new $builderClass($configuration));

            if (isset($configuration['pomm:default']) && $configuration['pomm:default'] === true) {
                $this->setDefaultBuilder($name);
            }
        }
    }

    /**
     * Check if the given builder class is valid.
     *
     * @throws  FoundationException if not valid
     */
    private function checkSessionBuilderClass(string $builderClass): string
    {
        try {
            $reflection = new \ReflectionClass($builderClass);

            if (!$reflection->isSubclassOf(VanillaSessionBuilder::class)) {
                throw new FoundationException(
                    sprintf(
                        "Class '%s' is not a subclass of \PommProject\Foundation\Session\SessionBuilder.",
                        $builderClass
                    )
                );
            }
        } catch (\ReflectionException $e) {
            throw new FoundationException(
                sprintf("Could not instantiate class '%s'.", $builderClass),
                0,
                $e
            );
        }

        return $builderClass;
    }

    /**
     * Set the name for the default session builder.
     *
     * @throws FoundationException
     */
    public function setDefaultBuilder(string $name): Pomm
    {
        if (!$this->hasBuilder($name)) {
            throw new FoundationException(sprintf("No such builder '%s'.", $name));
        }

        $this->default = $name;

        return $this;
    }

    /**
     * Return a session built by the default session builder.
     *
     * @throws FoundationException
     */
    public function getDefaultSession(): BaseSession
    {
        if ($this->default === null) {
            throw new FoundationException("No default session builder set.");
        }

        return $this->getSession($this->default);
    }

    /** Check if $name is a default session builder */
    public function isDefaultSession(string $name): bool
    {
        return $this->default == $name;
    }

    /**
     * Add a new session builder. Override any previously existing builder with the same name.
     *
     * @throws FoundationException
     */
    public function addBuilder(string $builderName, VanillaSessionBuilder $builder): Pomm
    {
        $this->builders[$builderName] = $builder;
        $this->postConfigurations[$builderName] = [];

        if ($this->default === null) {
            $this->setDefaultBuilder($builderName);
        }

        return $this;
    }

    /**
     * Add an environment dependent post configuration callable that will be run once after the session creation.
     *
     * @throws FoundationException
     */
    public function addPostConfiguration(string $name, callable $callable): Pomm
    {
        $this->builderMustExist($name)->postConfigurations[$name][] = $callable;

        return $this;
    }

    /** Return if true or false the given builder exists. */
    public function hasBuilder(string $name): bool
    {
        return isset($this->builders[$name]);
    }

    /**
     * Remove the builder with the given name.
     *
     * @throws  FoundationException if name does not exist.
     */
    public function removeBuilder(string $name): Pomm
    {
        unset(
            $this->builderMustExist($name)->builders[$name],
            $this->postConfigurations[$name]
        );

        return $this;
    }

    /**
     * Return the given builder.
     *
     * @throws FoundationException
     */
    public function getBuilder(string $name): VanillaSessionBuilder
    {
        return $this->builderMustExist($name)->builders[$name];
    }

    /**
     * Return a session from the pool. If no session exists, an attempt is made
     * to create one.
     *
     * @throws FoundationException
     */
    public function getSession(string $name): BaseSession
    {
        if (!$this->hasSession($name)) {
            $this->createSession($name);
        }

        return $this->sessions[$name];
    }

    /**
     * Create a new session using a session_builder and set it to the pool. Any previous session for this name is
     * overrided.
     *
     * @throws  FoundationException if builder does not exist.
     */
    public function createSession(string $name): BaseSession
    {
        $this->sessions[$name] = $this
            ->builderMustExist($name)
            ->builders[$name]
            ->buildSession($name);

        $session = $this->sessions[$name];

        foreach ($this->postConfigurations[$name] as $callable) {
            call_user_func($callable, $session);
        }

        if ($this->logger !== null) {
            $session->setLogger($this->logger);
        }

        return $session;
    }

    /** Does a given session exist in the pool ? */
    public function hasSession(string $name): bool
    {
        return isset($this->sessions[$name]);
    }

    /**
     * Remove a session from the pool if it exists.
     *
     * @throws  FoundationException if no builders with that name exist
     */
    public function removeSession(string $name): Pomm
    {
        if ($this->builderMustExist($name)->hasSession($name)) {
            unset($this->sessions[$name]);
        }

        return $this;
    }

    /**
     * Return the builders. This is mainly done for testing purposes.
     *
     * @return VanillaSessionBuilder[]
     */
    public function getSessionBuilders(): array
    {
        return $this->builders;
    }

    /**
     * @throws FoundationException
     * @see ArrayAccess
     */
    public function offsetGet(mixed $offset): BaseSession
    {
        return $this->getSession($offset);
    }

    /**
     * @throws FoundationException
     * @see ArrayAccess
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->addBuilder($offset, $value);
    }

    /**
     * @throws FoundationException
     * @see ArrayAccess
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->removeBuilder($offset);
    }

    /** @see ArrayAccess */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasBuilder($offset);
    }

    /**
     * Shutdown and remove sessions from the service. If no arguments are given, all the instantiated sessions are
     * shutdown. Otherwise, only given sessions are shutdown.
     *
     * @throws FoundationException
     *
     * @param array<int, string> $sessionNames
     * @return $this
     */
    public function shutdown(array $sessionNames = []): Pomm
    {
        if (empty($sessionNames)) {
            $sessions = array_keys($this->sessions);
        } else {
            array_map([ $this, 'builderMustExist' ], $sessionNames);
            $sessions = array_intersect(array_keys($this->sessions), $sessionNames);
        }

        foreach ($sessions as $session_name) {
            $this->getSession($session_name)->shutdown();
            $this->removeSession($session_name);
        }

        return $this;
    }

    /**
     * Throw a FoundationException if the given builder does not exist.
     *
     * @throws  FoundationException
     */
    private function builderMustExist(string $name): Pomm
    {
        if (!$this->hasBuilder($name)) {
            throw new FoundationException(
                sprintf(
                    "No such builder '%s'. Available builders are {%s}.",
                    $name,
                    join(
                        ', ',
                        array_map(
                            fn($val) => sprintf("'%s'", $val),
                            array_keys($this->getSessionBuilders())
                        )
                    )
                )
            );
        }

        return $this;
    }
}
