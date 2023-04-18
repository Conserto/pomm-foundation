<?php
/*
 * This file is part of the Pomm package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Converter;

use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;

/**
 * Composite type converter.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ArrayTypeConverter
 */
class PgComposite extends ArrayTypeConverter
{
    /**
     * Takes the composite type structure as parameter. The structure is $name => $type.
     *
     * @param array<string,string> $structure structure definition.
     */
    public function __construct(protected array $structure)
    {
    }

    /**
     * @throws FoundationException
     * @see ConverterInterface
     *
     * @param string $type
     * @param Session $session
     * @param string|null $data
     * @return array<string, mixed>|null
     */
    public function fromPg(?string $data, string $type, Session $session): ?array
    {
        if ($data === null) {
            return null;
        }

        $values = str_getcsv(stripcslashes(trim($data, '()')));

        return $this->convertArray(array_combine(array_keys($this->structure), $values), $session, 'fromPg');
    }

    /**
     * @throws ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        $this->checkArray($data);

        return sprintf(
            "ROW(%s)::%s",
            join(',', $this->convertArray($data, $session, 'toPg')),
            $type
        );
    }

    /**
     * @throws ConverterException|FoundationException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        if ($data === null) {
            return null;
        }

        $this->checkArray($data);

        return
            sprintf("(%s)",
                join(',', array_map(function ($val) {
                    $returned = $val;

                    if ($val === null) {
                        $returned = '';
                    } elseif ($val === '') {
                        $returned = '""';
                    } elseif (preg_match('/[,\s()]/', $val)) {
                        $returned = sprintf('"%s"', str_replace('"', '""', $val));
                    }

                    return $returned;
                }, $this->convertArray($data, $session, 'toPgStandardFormat')
                ))
            );
    }

    /**
     * Convert the given array of values.
     *
     * @throws FoundationException
     *
     * @param array<string,mixed> $data
     * @param Session $session
     * @param string $method
     * @return array<string,mixed>
     */
    private function convertArray(array $data, Session $session, string $method): array
    {
        $values = [];

        foreach ($this->structure as $name => $subtype) {
            $values[$name] = isset($data[$name])
                ? $this->getSubtypeConverter($subtype, $session)->$method($data[$name], $subtype, $session)
                : $this->getSubtypeConverter($subtype, $session)->$method(null, $subtype, $session);
        }

        return $values;
    }
}
