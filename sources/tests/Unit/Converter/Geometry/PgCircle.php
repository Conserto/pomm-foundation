<?php
/*
 * This file is part of PommProject's Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\Foundation\Test\Unit\Converter\Geometry;

use PommProject\Foundation\Converter\Type\Circle;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Test\Unit\Converter\BaseConverter;

class PgCircle extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->object($this->newTestedInstance()->fromPg('<(1.2345,-9.87654),3.141596>', 'circle', $session))
            ->isInstanceOf(\PommProject\Foundation\Converter\Type\Circle::class)
            ->variable($this->newTestedInstance()->fromPg(null, 'circle', $session))
            ->isNull();

        $circle = $this->newTestedInstance()->fromPg('<(1.2345,-9.87654),3.141596>', 'circle', $session);
        $this->object($circle->center)
            ->isInstanceOf(\PommProject\Foundation\Converter\Type\Point::class)
            ->float($circle->center->x)
            ->isEqualTo(1.2345)
            ->float($circle->radius)
            ->isEqualTo(3.141596);
    }

    public function testToPg(): void
    {
        $session = $this->buildSession();
        $circle = new Circle('<(1.2345,-9.87654),3.141596>');
        $this->string($this->newTestedInstance()->toPg($circle, 'circle', $session))
            ->isEqualTo('circle(point(1.2345,-9.87654),3.141596)')
            ->string($this->newTestedInstance()->toPg('<(1.2345,-9.87654),3.141596>', 'circle', $session))
            ->isEqualTo('circle(point(1.2345,-9.87654),3.141596)')
            ->string($this->newTestedInstance()->toPg(null, 'mycircle', $session))
            ->isEqualTo('NULL::mycircle')
            ->exception(fn() => $this->newTestedInstance()->toPg('azsdf', 'circle', $session))
            ->isInstanceOf(ConverterException::class);
    }

    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $circle = new Circle('<(1.2345,-9.87654),3.141596>');
        $this->string($this->newTestedInstance()->toPgStandardFormat($circle, 'circle', $session))
            ->isEqualTo('<(1.2345,-9.87654),3.141596>')
            ->string($this->newTestedInstance()->toPgStandardFormat('<(1.2345,-9.87654),3.141596>', 'circle', $session))
            ->isEqualTo('<(1.2345,-9.87654),3.141596>')
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'mycircle', $session))
            ->isNull()
            ->exception(fn() => $this->newTestedInstance()->toPgStandardFormat('azsdf', 'circle', $session))
            ->isInstanceOf(ConverterException::class);
    }
}
