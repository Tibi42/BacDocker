<?php

namespace App\Tests\Unit;

use App\Entity\BoardGame;
use App\Entity\LoanLog;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class LoanLogTest extends TestCase
{
    public function testSettersAndLoanedAtLifecycle(): void
    {
        $boardGame = new BoardGame();
        $user = new User();

        $loanLog = new LoanLog();
        $loanLog->setBoardGame($boardGame);
        $loanLog->setUser($user);

        $this->assertSame($boardGame, $loanLog->getBoardGame());
        $this->assertSame($user, $loanLog->getUser());

        $this->assertNull($loanLog->getLoanedAt());
        $loanLog->setLoanedAtValue();
        $this->assertInstanceOf(\DateTimeImmutable::class, $loanLog->getLoanedAt());
    }
}
