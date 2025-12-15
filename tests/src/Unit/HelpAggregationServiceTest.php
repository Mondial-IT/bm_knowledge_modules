<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_help_ai\Unit;

use Drupal\bm_help_ai\Service\HelpAggregationService;
use Drupal\bm_help_ai\Service\HelpClassificationService;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\help\HelpTopicPluginInterface;
use Drupal\help\HelpTopicPluginManagerInterface;
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
    $help_topic_manager = $this->prophesize(HelpTopicPluginManagerInterface::class);
    $help_topic_manager->getDefinitions()->willReturn([
      'bm_help_ai.overview' => [
        'label' => 'Overview',
        'provider' => 'bm_help_ai',
        'bm_help_ai_overview' => TRUE,
      ],
    ]);
    $help_topic_manager->createInstance('bm_help_ai.overview')->willReturn(
      $this->createHelpTopicStub('bm_help_ai.overview', ['#markup' => 'Rendered body content'])
    );

    $renderer = $this->prophesize(RendererInterface::class);
    $renderer->renderPlain(['#markup' => 'Rendered body content'])->willReturn('Rendered body content');

    $route_match = $this->prophesize(RouteMatchInterface::class);

    $example_extension = new Extension('', 'module', '/modules/example', 'example.info.yml');
    $example_extension->info = ['name' => 'Example'];

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->getModuleList()->willReturn(['example' => $example_extension]);
    $module_handler->invoke('example', 'help', ['help.page.example', $route_match->reveal()])->willReturn('<p>Module overview help.</p>');

    $classification = $this->prophesize(HelpClassificationService::class);
    $classification->attachClassification(\Prophecy\Argument::any())->willReturnArgument(0);

    $service = new HelpAggregationService(
      $help_topic_manager->reveal(),
      $module_handler->reveal(),
      $route_match->reveal(),
      $renderer->reveal(),
      $classification->reveal(),
    );

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

  protected function createHelpTopicStub(string $id, array $body): HelpTopicPluginInterface {
    return new class($id, $body) implements HelpTopicPluginInterface {

      public function __construct(private string $id, private array $body) {}

      public function getLabel(): string {
        return 'Overview';
      }

      public function getBody(): array {
        return $this->body;
      }

      public function isTopLevel(): bool {
        return TRUE;
      }

      public function getRelated(): array {
        return [];
      }

      public function toUrl(array $options = []): Url {
        return Url::fromRoute('help.help_topic', ['id' => $this->id], $options);
      }

      public function toLink($text = NULL, array $options = []) {
        return Link::fromTextAndUrl($text ?? $this->getLabel(), $this->toUrl($options));
      }

      public function getPluginId() {
        return $this->id;
      }

      public function getBaseId() {
        return $this->id;
      }

      public function getDerivativeId() {
        return NULL;
      }

      public function getCacheContexts() {
        return [];
      }

      public function getCacheTags() {
        return [];
      }

      public function getCacheMaxAge() {
        return 0;
      }

    };
  }

}
