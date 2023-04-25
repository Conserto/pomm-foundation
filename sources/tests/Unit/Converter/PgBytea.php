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

class PgBytea extends BaseConverter
{
    /** @throws FoundationException */
    public function testFromPg(): void
    {
        $session = $this->buildSession();
        $binary = chr(0) . chr(27) . chr(92) . chr(39) . chr(32) . chr(13);
        $output = $this->newTestedInstance()->fromPg('\x001b5c27200d', 'bytea', $session);
        $this->string($output)
            ->string(base64_encode($output))
            ->isEqualTo(base64_encode($binary))
            ->variable($this->newTestedInstance()->fromPg(null, 'bytea', $session))
            ->isNull();
    }

    /** @throws FoundationException */
    public function testToPg(): void
    {
        $binary = chr(0) . chr(27) . chr(92) . chr(39) . chr(32) . chr(13);
        $output = '\x001b5c27200d';
        $session = $this->buildSession();

        $this->string($this->newTestedInstance()->toPg($binary, 'bytea', $session))
            ->isEqualTo(sprintf("bytea '%s'", $output))
            ->string($this->newTestedInstance()->toPg(null, 'bytea', $session))
            ->isEqualTo('NULL::bytea');
    }

    /** @throws FoundationException */
    public function testToPgStandardFormat(): void
    {
        $binary = chr(0) . chr(27) . chr(92) . chr(39) . chr(32) . chr(13);
        $output = '\x001b5c27200d';
        $session = $this->buildSession();

        $this->string($this->newTestedInstance()->toPgStandardFormat($binary, 'bytea', $session))
            ->isEqualTo(sprintf('%s', $output))
            ->variable($this->newTestedInstance()->toPgStandardFormat(null, 'bytea', $session))
            ->isNull()
            ->string($this->sendToPostgres($binary, 'bytea', $session))
            ->isEqualTo($binary);
    }
}
