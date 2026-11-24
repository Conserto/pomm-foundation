<?php

/*
 * This file is part of the Pomm's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\QueryManager;

use PommProject\Foundation\Session\Session;

/**
 * Trait that makes query managers to parse, expand anc convert query parameters.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
trait QueryParameterParserTrait
{
    abstract protected function getSession(): Session;

    /** Transform an ordered parameters list with $1, $2 to $* parameters. preg_* only return null on a regex failure. */
    public function unorderParameters(string $string): string
    {
        return (string) preg_replace('/\$[0-9]+/', '$*', $string);
    }

    /** Transform an unordered parameters list $* to ordered $1, $2 parameters. */
    public function orderParameters(string $string): string
    {
        return (string) preg_replace_callback(
            '/\$\*/',
            function (): string {
                /** @var int $nb */
                static $nb = 0;

                return sprintf("$%d", ++$nb);
            },
            $string
        );
    }

    /**
     * Return an array of the type specified with the parameters if any. It is possible to give the type when passing
     * parameters like « SELECT … WHERE field = $*::timestamptz ». In this case, PostgreSQL will assume the given
     * parameter is a timestamp. Pomm uses these type hints to convert PHP representation to PostgreSQL data value.
     *
     * @param   mixed $string SQL query.
     * @return  array<int, string>
     */
    public function getParametersType(mixed $string): array
    {
        // @phpstan-ignore cast.string (any value is cast to a query string, as it always was)
        preg_match_all('/\$\*(?:::([\w\."]+(?:\[\])?))?/', (string) $string, $matches);

        return str_replace('"', '', $matches[1]);
    }
}
