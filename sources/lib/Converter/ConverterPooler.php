<?php
/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\ClientPooler;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;

/**
 * Pooler for converters.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         ClientPooler
 */
class ConverterPooler extends ClientPooler
{
    /** Instantiate converter pooler. */
    public function __construct(protected ConverterHolder $converterHolder)
    {
    }

    /** @see ClientPoolerInterface */
    public function getPoolerType(): string
    {
        return 'converter';
    }

    /**
     * @throws FoundationException
     * @see ClientPoolerInterface
     */
    public function getClient($identifier): ClientInterface
    {
        $clientIdentifier = $identifier !== PgArray::getSubType($identifier) ? 'array' : $identifier;
        return parent::getClient($clientIdentifier);
    }

    /**
     * Check in the converter holder if the type has an associated converter. If not, an exception is thrown.
     *
     * @see   ClientPooler
     * @throws ConverterException
     */
    public function createClient(string $identifier): ConverterClient
    {
        if (!$this->converterHolder->hasType($identifier)) {
            throw new ConverterException(sprintf("No converter registered for type '%s'.", $identifier));
        }

        return new ConverterClient($identifier, $this->converterHolder->getConverterForType($identifier));
    }

    /** Expose converter holder so one can add new converters on the fly. */
    public function getConverterHolder(): ConverterHolder
    {
        return $this->converterHolder;
    }
}
