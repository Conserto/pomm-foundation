<?php

declare(strict_types=1);

/*
 * This file is part of the Pomm package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Internal\PgTypeName;
use PommProject\Foundation\Session\Session;

/**
 * Ltree converter.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgLtree extends ArrayTypeConverter
{
    /**
     * @see ConverterInterface
     *
     * @return array<int, string>|bool|null
     */
    public function fromPg(?string $data, string $type, Session $session): array|bool|null
    {
        if (null === $data) {
            return null;
        }
        $data = trim($data);

        if ($data === '') {
            return null;
        }

        return preg_split('/\./', $data);
    }

    /**
     * The labels are escaped by the connection: they used to be written between quotes as is, so a quote in a label
     * broke the statement or injected SQL.
     *
     * @throws ConnectionException|ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        return
            $data !== null
            ? sprintf(
                '%s %s',
                PgTypeName::quote($type),
                // @phpstan-ignore argument.type (implode() converts each user value itself, as it always did)
                $session->getConnection()->escapeLiteral(implode('.', $this->checkArray($data)))
            )
            : sprintf("NULL::%s", PgTypeName::quote($type));
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return
            // @phpstan-ignore argument.type (implode() converts each user value itself, as it always did)
            $data !== null ? implode('.', $this->checkArray($data)) : null;
    }
}
