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
use PommProject\Foundation\Test\Unit\Enum\BackedEnum;
use PommProject\Foundation\Test\Unit\Enum\IntBackedEnum;
use PommProject\Foundation\Test\Unit\Enum\UnitEnum;

class PgInteger extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->variable($this->newTestedInstance()->fromPg(null, 'int4', $session))
            ->isNull()
            ->integer($this->newTestedInstance()->fromPg('0', 'int4', $session))
            ->isEqualTo(0)
            ->integer($this->newTestedInstance()->fromPg('2015', 'int4', $session))
            ->isEqualTo(2015)
            ->integer($this->newTestedInstance()->fromPg('3.141596', 'int4', $session))
            ->isEqualTo(3);
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPg(2014, 'int4', $session))
            ->isEqualTo("int4 '2014'")
            ->string($this->newTestedInstance()->toPg(1.6180339887499, 'int4', $session))
            ->isEqualTo("int4 '1'")
            ->string($this->newTestedInstance()->toPg(null, 'int4', $session))
            ->isEqualTo("NULL::int4")
            ->string($this->newTestedInstance()->toPg(0, 'int4', $session))
            ->isEqualTo("int4 '0'")
            ->string($this->newTestedInstance()->toPg(-42, 'int4', $session))
            ->isEqualTo("int4 '-42'")
            ->string($this->newTestedInstance()->toPg(IntBackedEnum::TWO, 'int4', $session))
            ->isEqualTo("int4 '2'")
            ->exception(fn () => $this->newTestedInstance()->toPg(BackedEnum::A, 'int4', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'int4'.",
                BackedEnum::class
            ))
            ->exception(fn () => $this->newTestedInstance()->toPg(UnitEnum::Active, 'int4', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'int4'.",
                UnitEnum::class
            ));
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPgStandardFormat(2014, 'int4', $session))
            ->isEqualTo("2014")
            ->string($this->newTestedInstance()->toPgStandardFormat(1.6180339887499, 'int4', $session))
            ->isEqualTo("1")
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'int4', $session))
            ->isNull()
            ->string($this->newTestedInstance()->toPgStandardFormat(-42, 'int4', $session))
            ->isEqualTo('-42')
            ->string($this->newTestedInstance()->toPgStandardFormat(IntBackedEnum::TWO, 'int4', $session))
            ->isEqualTo('2')
            ->exception(fn () => $this->newTestedInstance()->toPgStandardFormat(BackedEnum::A, 'int4', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'int4'.",
                BackedEnum::class
            ))
            ->exception(fn () => $this->newTestedInstance()->toPgStandardFormat(UnitEnum::Active, 'int4', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'int4'.",
                UnitEnum::class
            ));
    }
}
