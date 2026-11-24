<?php

declare(strict_types=1);

/*
 * This file is part of Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\StringCoercion;

/**
 * Converter wrapper as Session's client.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       Client
 */
class ConverterClient extends Client
{
    /** Wrap the given converter. */
    public function __construct(protected string $name, protected ConverterInterface $converter)
    {
    }

    /** @see ClientInterface */
    public function getClientType(): string
    {
        return 'converter';
    }

    /** @see ClientInterface */
    public function getClientIdentifier(): string
    {
        return $this->name;
    }

    /**
     * Trigger converter's toPg conversion method.
     *
     * @throws FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $value, ?string $type = null): string
    {
        return $this->converter->toPg(
            $value,
            $type ?? $this->getClientIdentifier(),
            $this->getSession()
        );
    }

    /**
     * Trigger converter's fromPg conversion method.
     *
     * @throws FoundationException
     * @see ConverterInterface
     */
    public function fromPg(mixed $value, ?string $type = null): mixed
    {
        // Converted as PHP's coercive mode converted it for fromPg()'s ?string parameter; it raised a TypeError otherwise.
        $data = StringCoercion::toNullableStringOr($value, fn (): ConverterException => new ConverterException(sprintf(
            "The '%s' converter expects a string from PostgreSQL, '%s' given.",
            $type ?? $this->getClientIdentifier(),
            get_debug_type($value)
        )));

        return $this->converter->fromPg(
            $data,
            $type ?? $this->getClientIdentifier(),
            $this->getSession()
        );
    }

    /**
     * Export data as CSV representation
     *
     * @throws FoundationException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $value, ?string $type = null): string
    {
        $result = $this->converter->toPgStandardFormat(
            $value,
            $type ?? $this->getClientIdentifier(),
            $this->getSession()
        );

        if ($result === null) {
            // The return type cannot be null; this used to end in a TypeError.
            throw new ConverterException(sprintf(
                "The '%s' converter returned no standard format (null) for this value: pass null values as is.",
                $type ?? $this->getClientIdentifier()
            ));
        }

        return $result;
    }

    /** Return the embedded converter. */
    public function getConverter(): ConverterInterface
    {
        return $this->converter;
    }
}
