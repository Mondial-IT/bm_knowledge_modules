<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_help_ai\Unit;

use Drupal\bm_help_ai\Service\HelpAiRelevanceService;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\bm_help_ai\Service\HelpAiRelevanceService
 */
class HelpAiRelevanceServiceTest extends UnitTestCase {

  /**
   * @covers ::reorder
   */
  public function testReorderReturnsInput(): void {
    $service = new HelpAiRelevanceService();
    $items = [
      ['id' => 'one', 'title' => 'One'],
      ['id' => 'two', 'title' => 'Two'],
    ];
    $context = ['route_name' => 'example'];

    $result = $service->reorder($items, $context);

    $this->assertEquals($items, $result);
  }

}
