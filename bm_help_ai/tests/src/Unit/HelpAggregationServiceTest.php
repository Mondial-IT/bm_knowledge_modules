<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_help_ai\Unit;

use Drupal\bm_help_ai\Service\HelpAggregationService;
use Drupal\bm_knowledge_ai\Model\KnowledgeItem;
use Drupal\bm_knowledge_ai\Service\KnowledgeAdapterManager;
use Drupal\Core\Url;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\bm_help_ai\Service\HelpAggregationService
 */
class HelpAggregationServiceTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * @covers ::getHelpTopics
   * @covers ::getModuleHelp
   * @covers ::getModuleOverviews
   * @covers ::getSituationCandidates
   */
  public function testAggregationAndNormalization(): void {
    $adapter_manager = $this->prophesize(KnowledgeAdapterManager::class);
    $items = [
      new KnowledgeItem(
        id: 'bm_help_ai.overview',
        sourceType: 'help',
        sourceId: 'bm_help_ai.overview',
        title: 'Overview',
        bodyMarkdown: 'Rendered body content',
        summary: 'Rendered body content',
        language: 'en',
        taxonomyTerms: [],
        primaryTerm: NULL,
        contextHints: [],
        authorityLevel: 'canonical',
        updatedAt: NULL,
        extra: [
          'source' => 'help_topic',
          'module' => 'bm_help_ai',
          'description' => 'Rendered body content',
          'is_overview' => TRUE,
          'link' => Url::fromRoute('help.help_topic', ['id' => 'bm_help_ai.overview']),
          'help_topic_type' => 'help_topic',
          'help_topic_status' => 'current',
        ],
      ),
      new KnowledgeItem(
        id: 'hook_help.example',
        sourceType: 'help',
        sourceId: 'hook_help.example',
        title: 'Example',
        bodyMarkdown: 'Module overview help.',
        summary: 'Module overview help.',
        language: 'en',
        taxonomyTerms: [],
        primaryTerm: NULL,
        contextHints: [],
        authorityLevel: 'canonical',
        updatedAt: NULL,
        extra: [
          'source' => 'hook_help',
          'module' => 'example',
          'description' => 'Module overview help.',
          'help_topic_type' => '.module',
          'link' => Url::fromUri('internal:/admin/help/example'),
        ],
      ),
    ];
    $adapter_manager->getItems('help')->willReturn($items);

    $service = new HelpAggregationService($adapter_manager->reveal());

    $topics = $service->getHelpTopics();
    $this->assertCount(1, $topics);
    $this->assertSame('bm_help_ai.overview', $topics[0]['id']);
    $this->assertSame('Overview', $topics[0]['title']);
    $this->assertTrue($topics[0]['is_overview']);
    $this->assertInstanceOf(Url::class, $topics[0]['link']);
    $this->assertSame('Rendered body content', $topics[0]['description']);

    $module_help = $service->getModuleHelp();
    $this->assertCount(1, $module_help);
    $this->assertSame('hook_help.example', $module_help[0]['id']);
    $this->assertSame('Example', $module_help[0]['title']);
    $this->assertSame('hook_help', $module_help[0]['source']);
    $this->assertSame('Module overview help.', $module_help[0]['description']);

    $overviews = $service->getModuleOverviews();
    $this->assertCount(2, $overviews);

    $candidates = $service->getSituationCandidates([]);
    $this->assertCount(2, $candidates);
  }

}
