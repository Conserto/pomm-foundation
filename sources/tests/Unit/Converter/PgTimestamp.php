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

use PommProject\Foundation\Exception\FoundationException;

class PgTimestamp extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $this->datetime($this->newTestedInstance()->fromPg('2014-09-27 18:51:35.678406+00', 'timestamptz', $session))
            ->hasDateAndTime(2014, 9, 27, 18, 51, 35.678406)
            ->variable($this->newTestedInstance()->fromPg(null, 'timestamptz', $session))
            ->isNull();
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $session = $this->buildSession();
        $this->string($this->newTestedInstance()->toPg(new \DateTime('2014-09-27 18:51:35.678406+00'), 'timestamptz', $session))
            ->isEqualTo("timestamptz '2014-09-27 18:51:35.678406+00:00'")
            ->string($this->newTestedInstance()
                ->toPg(new \DateTimeImmutable('2014-09-27 18:51:35.678406+00'), 'timestamptz', $session))
            ->isEqualTo("timestamptz '2014-09-27 18:51:35.678406+00:00'")
            ->string($this->newTestedInstance()->toPg(null, 'timestamptz', $session))
            ->isEqualTo("NULL::timestamptz");
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $session = $this->buildSession();
        $dateTime = new \DateTime('2014-09-27 18:51:35.678406+00');
        $dateTimeImmutable = new \DateTimeImmutable('2014-09-27 18:51:35.678406+00');
        $this->string($this->newTestedInstance()->toPgStandardFormat($dateTime, 'timestamptz', $session))
            ->isEqualTo('2014-09-27 18:51:35.678406+00:00')
            ->string($this->newTestedInstance()->toPgStandardFormat($dateTimeImmutable, 'timestamptz', $session))
            ->isEqualTo('2014-09-27 18:51:35.678406+00:00')
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'timestamptz', $session))
            ->isNull()
            ->object($this->sendToPostgres($dateTime, 'timestamptz', $session))
            ->isInstanceof(\DateTime::class)
            ->isEqualTo($dateTime)
            ->object($this->sendToPostgres($dateTimeImmutable, 'timestamptz', $session))
            ->isInstanceof(\DateTime::class)
            ->isEqualTo($dateTimeImmutable);
    }
}
