<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\Foundation\Test\Unit\Converter;

use PommProject\Foundation\Exception\FoundationException;

class PgBoolean extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->boolean($this->newTestedInstance()->fromPg('t', 'bool', $session))
            ->isTrue()
            ->boolean($this->newTestedInstance()->fromPg('f', 'bool', $session))
            ->isFalse()
            ->exception(function () use ($session) {
                $this->newTestedInstance()->fromPg('whatever', 'bool', $session);
            })
            ->isInstanceOf(\PommProject\Foundation\Exception\ConverterException::class)
            ->message->contains('Unknown bool data')
            ->variable($this->newTestedInstance()->fromPg(null, 'bool', $session))
            ->isNull();
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPg(true, 'bool', $session))
            ->isEqualTo("bool 'true'")
            ->string($this->newTestedInstance()->toPg(false, 'bool', $session))
            ->isEqualTo("bool 'false'")
            ->string($this->newTestedInstance()->toPg(null, 'bool', $session))
            ->isEqualTo("NULL::bool");
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPgStandardFormat(true, 'bool', $session))
            ->isEqualTo("t")
            ->string($this->newTestedInstance()->toPgStandardFormat(false, 'bool', $session))
            ->isEqualTo("f")
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'bool', $session))
            ->isNull();
    }
}
