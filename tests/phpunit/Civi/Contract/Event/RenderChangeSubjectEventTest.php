<?php

declare(strict_types = 1);

namespace Civi\Contract\Event;

use Civi\Contract\Support\AbstractSetupHeadless;

/**
 * @covers \Civi\Contract\Event\RenderChangeSubjectEvent
 * @group headless
 */
final class RenderChangeSubjectEventTest extends AbstractSetupHeadless {

  public function testGetMembershipIncreaseAmount_WithFloatValues_CalculatesDiff(): void {
    $event = new RenderChangeSubjectEvent(
      'update',
      ['membership_payment.membership_annual' => 77379.0],
      ['membership_payment.membership_annual' => 77500.0]
    );

    self::assertEqualsWithDelta(121.0, $event->getMembershipIncreaseAmount(), 0.001);
  }

  public function testGetMembershipIncreaseAmount_WithFloatValues_CalculatesReduction(): void {
    $event = new RenderChangeSubjectEvent(
      'update',
      ['membership_payment.membership_annual' => 120.0],
      ['membership_payment.membership_annual' => 60.0]
    );

    self::assertEqualsWithDelta(-60.0, $event->getMembershipIncreaseAmount(), 0.001);
  }

  public function testGetMembershipIncreaseAmount_WithFormattedStrings_StripsThousandSeparator(): void {
    $event = new RenderChangeSubjectEvent(
      'update',
      ['membership_payment.membership_annual' => '1.200,00'],
      ['membership_payment.membership_annual' => '1.500,00']
    );

    self::assertEqualsWithDelta(300.0, $event->getMembershipIncreaseAmount(), 0.001);
  }

  public function testGetMembershipIncreaseAmount_WithoutContractData_ReturnsZero(): void {
    $event = new RenderChangeSubjectEvent('update', [], []);

    self::assertEqualsWithDelta(0.0, $event->getMembershipIncreaseAmount(), 0.001);
  }

}
