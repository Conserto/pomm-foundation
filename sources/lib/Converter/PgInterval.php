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
use PommProject\Foundation\Internal\StringCoercion;
use PommProject\Foundation\Session\Session;

/**
 * Convert an ISO8601 interval from/to PHP.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class PgInterval implements ConverterInterface
{
    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): mixed
    {
        if (null === $data) {
            return null;
        }
        if (trim($data) === '') {
            return null;
        }

        $iso = (string) preg_replace('/\.[0-9]+S/', 'S', $data);

        // PostgreSQL signs each negative component (P-1Y-2DT-3H); a DateInterval can only be negative as a whole.
        preg_match_all('/(-?)[0-9]+[YMWDHS]/', $iso, $components);
        $negative = count(array_filter($components[1], static fn (string $sign): bool => $sign === '-'));
        if ($negative > 0 && $negative < count($components[1])) {
            throw new ConverterException(sprintf(
                "Interval '%s' mixes positive and negative components, which a DateInterval cannot represent.",
                $data
            ));
        }

        try {
            $interval = new \DateInterval($negative > 0 ? str_replace('-', '', $iso) : $iso);
        } catch (\Exception $e) {
            throw new ConverterException(sprintf("Data '%s' is not an ISO8601 interval representation.", $data), 0, $e);
        }
        $interval->invert = $negative > 0 ? 1 : 0;

        return $interval;
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        return $data !== null
            ? sprintf("%s '%s'", $type, $this->toPostgresInterval($this->checkData($data)))
            : sprintf("NULL::%s", $type);
    }

    /**
     * @throws ConverterException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        return $data !== null
            ? sprintf('"%s"', $this->toPostgresInterval($this->checkData($data)))
            : null;
    }

    /**
     * Check if Data is a DateInterval. If not, it tries to instantiate a DateInterval with the given data.
     *
     * @throws ConverterException
     * @return \DateInterval $data
     */
    protected function checkData(mixed $data): \DateInterval
    {
        if (!$data instanceof \DateInterval) {
            // Converted as PHP's coercive mode converted it for DateInterval's string parameter (null meant '');
            // it raised a TypeError otherwise.
            $duration = $data === null ? '' : StringCoercion::toStringOr(
                $data,
                fn (): ConverterException => new ConverterException("First argument is not a \DateInterval instance.")
            );

            try {
                $data = new \DateInterval($duration);
            } catch (\Exception $e) {
                throw new ConverterException("First argument is not a \DateInterval instance.", 0, $e);
            }
        }

        return $data;
    }

    /** Negative intervals (invert) used to be written as positive: 'ago' negates the whole interval. */
    private function toPostgresInterval(\DateInterval $interval): string
    {
        return $interval->format('%Y years %M months %D days %H:%i:%S') . ($interval->invert === 1 ? ' ago' : '');
    }
}
