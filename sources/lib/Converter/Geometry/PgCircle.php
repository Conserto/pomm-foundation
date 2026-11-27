<?php

declare(strict_types=1);

/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter\Geometry;

use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Converter\ConverterInterface;
use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Converter\TypeConverter;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

/**
 * Converter for PostgreSQL Circle type.
 *
 * @copyright 2014 Grégoire HUBERT
 * @author Grégoire HUBERT
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see ConverterInterface
 */
class PgCircle extends TypeConverter
{
    protected ?ConverterClient $pointConverter = null;

    public function __construct(?string $className = null)
    {
        if (new \ReflectionMethod($this, 'getPointConverter')->getDeclaringClass()->getName() !== self::class) {
            // Silenced like Symfony's deprecations: reported by debug tools and deprecation handlers, not in production.
            @trigger_error(
                sprintf(
                    'Since 5.0, "%s::getPointConverter()" is deprecated and will be removed in 6.0, and toPg() no longer'
                    . ' calls it: "%s" overrides it. To change how the center is written, override Point::__toString() or'
                    . ' toPg().',
                    self::class,
                    static::class
                ),
                E_USER_DEPRECATED
            );
        }

        parent::__construct($className);
    }

    /**
     * Circle class name
     *
     * @see TypeConverter
     */
    protected function getTypeClassName(): string
    {
        return Circle::class;
    }

    /**
     * Cache the point converter.
     *
     * @deprecated since 5.0, no longer used by PgCircle, will be removed in 6.0
     * @throws FoundationException
     */
    protected function getPointConverter(Session $session): ConverterClient
    {
        @trigger_error(
            sprintf('Since 5.0, "%s::getPointConverter()" is deprecated and will be removed in 6.0.', self::class),
            E_USER_DEPRECATED
        );

        if (!$this->pointConverter instanceof ConverterClient) {
            /** @var ConverterClient $converterClient */
            $converterClient = $session->getClientUsingPooler('converter', 'point');
            $this->pointConverter = $converterClient;
        }

        return $this->pointConverter;
    }
}
