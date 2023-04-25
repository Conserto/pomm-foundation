<?php
/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Converter;

use PommProject\Foundation\Converter\Type\BaseRange;
use PommProject\Foundation\Converter\Type\NumRange;
use PommProject\Foundation\Exception\FoundationException;

class PgNumRange extends BaseConverter
{
    /**
     * @throws FoundationException
     * @param array $data
     * @dataProvider fromPgDataProvider
     */
    public function testExtraFromPg(array $data): void
    {
        $session = $this->buildSession();
        $this->assert($data['assert'])
            ->given($instance = $this->newTestedInstance()->fromPg($data['text_range'], 'tstzrange', $session))
            ->object($instance)
            ->isInstanceOf(NumRange::class)
            ->variable($instance->startLimit)
            ->isEqualTo($data['expected_start_limit'])
            ->variable($instance->endLimit)
            ->isEqualTo($data['expected_end_limit'])
            ->variable($instance->startIncl)
            ->isEqualTo($data['expected_start_incl'])
            ->variable($instance->endIncl)
            ->isEqualTo($data['expected_end_incl']);
    }

    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->object($this->newTestedInstance()->fromPg('[1,3)', 'int4range', $session))
            ->isInstanceOf(NumRange::class)
            ->variable($this->newTestedInstance()->fromPg(null, 'point', $session))
            ->isNull();
        $range = $this->newTestedInstance()->fromPg('[1,3)', 'int4range', $session);
        $this->integer($range->startLimit)
            ->isEqualTo(1)
            ->integer($range->endLimit)
            ->isEqualTo(3)
            ->boolean($range->startIncl)
            ->isTrue()
            ->boolean($range->endIncl)
            ->isFalse();
        $range = $this->newTestedInstance()->fromPg('(-3.1415, -1.6180]', 'numrange', $session);
        $this->float($range->startLimit)
            ->isEqualTo(-3.1415)
            ->float($range->endLimit)
            ->isEqualTo(-1.618)
            ->boolean($range->startIncl)
            ->isFalse()
            ->boolean($range->endIncl)
            ->isTrue();
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPg(new NumRange('[1,3)'), 'myrange', $session))
            ->isEqualTo("myrange('[1,3)')")
            ->string($this->newTestedInstance()->toPg(null, 'myrange', $session))
            ->isEqualTo('NULL::myrange');
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $this->string(
            $this->newTestedInstance()->toPgStandardFormat(new NumRange('[1,3)'), 'myrange', $session)
        )
            ->isEqualTo("[1,3)")
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'myrange', $session))
            ->isNull();
    }

    protected function fromPgDataProvider(): array
    {
        return [
            [
                [
                    'assert' => 'Test with value and null',
                    'text_range' => '(-10,)',
                    'expected_start_limit' => -10,
                    'expected_end_limit' => null,
                    'expected_start_incl' => false,
                    'expected_end_incl' => false,
                ],
            ],
            [
                [
                    'assert' => 'Test with null and null',
                    'text_range' => '[,]',
                    'expected_start_limit' => null,
                    'expected_end_limit' => null,
                    'expected_start_incl' => true,
                    'expected_end_incl' => true,
                ],
            ],
            [
                [
                    'assert' => 'Test with null and value',
                    'text_range' => '(, 99)',
                    'expected_start_limit' => null,
                    'expected_end_limit' => 99,
                    'expected_start_incl' => false,
                    'expected_end_incl' => false,
                ],
            ],
            [
                [
                    'assert' => 'Test with empty',
                    'text_range' => 'empty',
                    'expected_start_limit' => BaseRange::EMPTY_RANGE,
                    'expected_end_limit' => BaseRange::EMPTY_RANGE,
                    'expected_start_incl' => null,
                    'expected_end_incl' => null,
                ],
            ],
        ];
    }
}
