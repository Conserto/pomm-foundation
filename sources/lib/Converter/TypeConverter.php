<?php
/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Converter\Type\BaseRange;
use PommProject\Foundation\Session\Session;

/**
 * Abstract class for converter that use object types like point, circle, numrange etc.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @abstract
 */
abstract class TypeConverter implements ConverterInterface
{
    protected string $className;

    /** Return the type class name */
    abstract protected function getTypeClassName(): string;

    /** Set the type class name. */
    public function __construct(?string $className = null)
    {
        $this->className = $className ?? $this->getTypeClassName();
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): ?object
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data);

        return $data !== '' ? $this->createObjectFrom($data) : null;
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        } else {
            $dataObject = $this->checkData($data);

            if ($dataObject instanceof \Stringable) {
                return sprintf("%s('%s')", $type, $dataObject);
            } else {
                throw new ConverterException(
                    sprintf("Unable to transform a '%s' instance to string.", get_class($dataObject)),
                    0
                );
            }
        }
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return
            $data !== null
                ? sprintf("%s", str_replace('"', '""', (string) $this->checkData($data)))
                : null;
    }

    /**
     * Check if data is suitable for Pg conversion. If not an attempt is made  to build the object from the given
     * definition.
     *
     * @throws ConverterException
     */
    public function checkData(mixed $data): object
    {
        $className = $this->getTypeClassName();

        if (!$data instanceof $className) {
            $data = $this->createObjectFrom($data);
        }

        return $data;
    }

    /**
     * Create a range object from a given definition. If the object creation fails, an exception is thrown.
     *
     * @throws ConverterException
     */
    protected function createObjectFrom(mixed $data): object
    {
        $className = $this->className;

        try {
            return new $className($data);
        } catch (\InvalidArgumentException $e) {
            throw new ConverterException(
                sprintf("Unable to create a '%s' instance.", $className),
                0,
                $e
            );
        }
    }
}
