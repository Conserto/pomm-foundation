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

use PommProject\Foundation\Converter\Type\Box;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Test\Unit\Converter\BaseConverter;

class PgBox extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->object($this->newTestedInstance()->fromPg('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session))
            ->isInstanceOf(Box::class)
            ->variable($this->newTestedInstance()->fromPg(null, 'box', $session))
            ->isNull();

        $box = $this->newTestedInstance()->fromPg('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session);
        $this->float($box->topX)
            ->isEqualTo(1.2345)
            ->float($box->topY)
            ->isEqualTo(-9.87654)
            ->float($box->bottomX)
            ->isEqualTo(0.1234)
            ->float($box->bottomY)
            ->isEqualTo(-10.98765);
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $box = new Box('( (1.2345, -9.87654 ), (0.1234, -10.98765 ) )');
        $this->string($this->newTestedInstance()->toPg($box, 'box', $session))
            ->isEqualTo('box((1.2345,-9.87654),(0.1234,-10.98765))')
            ->string($this->newTestedInstance()->toPg('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session))
            ->isEqualTo('box((1.2345,-9.87654),(0.1234,-10.98765))')
            ->exception(fn() => $this->newTestedInstance()->toPg('azsdf', 'box', $session))
            ->isInstanceOf(ConverterException::class)
            ->string($this->newTestedInstance()->toPg(null, 'subbox', $session))
            ->isEqualTo('NULL::subbox');
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $box = new Box('((1.2345, -9.87654), (0.1234, -10.98765))');
        $this->string($this->newTestedInstance()->toPgStandardFormat($box, 'box', $session))
            ->isEqualTo('((1.2345,-9.87654),(0.1234,-10.98765))')
            ->string($this->newTestedInstance()->toPgStandardFormat('((1.2345,-9.87654),(0.1234,-10.98765))', 'box', $session))
            ->isEqualTo('((1.2345,-9.87654),(0.1234,-10.98765))')
            ->exception(fn() => $this->newTestedInstance()->toPgStandardFormat('azsdf', 'box', $session))
            ->isInstanceOf(ConverterException::class)
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'subbox', $session))
            ->isNull();
    }
}
