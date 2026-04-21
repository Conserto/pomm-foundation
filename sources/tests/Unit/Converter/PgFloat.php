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
use PommProject\Foundation\Test\Unit\Enum\PureEnum;

class PgFloat extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->variable($this->newTestedInstance()->fromPg(null, 'int4', $session))
            ->isNull()
            ->float($this->newTestedInstance()->fromPg('0', 'int4', $session))
            ->isEqualTo(0)
            ->float($this->newTestedInstance()->fromPg('2015', 'int4', $session))
            ->isEqualTo(2015)
            ->float($this->newTestedInstance()->fromPg('3.141596', 'float4', $session))
            ->isEqualTo(3.141596);
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPg(2014, 'float8', $session))
            ->isEqualTo("float8 '2014'")
            ->string($this->newTestedInstance()->toPg(1.6180339887499, 'float8', $session))
            ->isEqualTo("float8 '1.6180339887499'")
            ->string($this->newTestedInstance()->toPg(null, 'float8', $session))
            ->isEqualTo("NULL::float8")
            ->string($this->newTestedInstance()->toPg(0, 'float8', $session))
            ->isEqualTo("float8 '0'")
            ->string($this->newTestedInstance()->toPg(IntBackedEnum::TWO, 'float8', $session))
            ->isEqualTo("float8 '2'")
            ->string($this->newTestedInstance()->toPg(BackedEnum::NUMERIC, 'float8', $session))
            ->isEqualTo("float8 '42.5'")
            ->exception(fn() => $this->newTestedInstance()->toPg(BackedEnum::A, 'float8', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'float8'.",
                BackedEnum::class
            ))
            ->exception(fn() => $this->newTestedInstance()->toPg(PureEnum::Active, 'float8', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'float8'.",
                PureEnum::class
            ));
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPgStandardFormat(2014, 'float8', $session))
            ->isEqualTo("2014")
            ->string($this->newTestedInstance()->toPgStandardFormat(1.6180339887499, 'float8', $session))
            ->isEqualTo("1.6180339887499")
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'float8', $session))
            ->isNull()
            ->string($this->newTestedInstance()->toPgStandardFormat(IntBackedEnum::TWO, 'float8', $session))
            ->isEqualTo('2')
            ->string($this->newTestedInstance()->toPgStandardFormat(BackedEnum::NUMERIC, 'float8', $session))
            ->isEqualTo('42.5')
            ->exception(fn() => $this->newTestedInstance()->toPgStandardFormat(BackedEnum::A, 'float8', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'float8'.",
                BackedEnum::class
            ))
            ->exception(fn() => $this->newTestedInstance()->toPgStandardFormat(PureEnum::Active, 'float8', $session))
            ->hasMessage(sprintf(
                "Enum '%s' is not compatible with Pg type 'float8'.",
                PureEnum::class
            ));
    }
}
