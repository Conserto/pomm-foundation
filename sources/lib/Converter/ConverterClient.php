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

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Exception\FoundationException;

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
        return $this->converter->fromPg(
            $value,
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
        return $this->converter->toPgStandardFormat(
            $value,
            $type ?? $this->getClientIdentifier(),
            $this->getSession()
        );
    }

    /** Return the embedded converter. */
    public function getConverter(): ConverterInterface
    {
        return $this->converter;
    }
}
