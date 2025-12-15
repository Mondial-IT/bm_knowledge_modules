<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_help_ai\Unit;

use Drupal\bm_help_ai\Service\HelpClassificationService;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Tests\Core\Config\ConfigFactoryStub;
use Drupal\Tests\UnitTestCase;
use Drupal\taxonomy\TermInterface;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\bm_help_ai\Service\HelpClassificationService
 */
class HelpClassificationServiceTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * @covers ::filterByTerm
   * @covers ::getDescendantTermIds
   */
  public function testFilterByTerm(): void {
    $term_storage = $this->prophesize(EntityStorageInterface::class);
    $root = $this->prophesize(TermInterface::class);
    $root->bundle()->willReturn('bm_help_ai_help_topics');
    $root->id()->willReturn(1);

    $child = $this->prophesize(TermInterface::class);
    $child->id()->willReturn(2);

    $term_storage->load(1)->willReturn($root->reveal());
    $term_storage->loadTree('bm_help_ai_help_topics', 1, NULL, TRUE)->willReturn([$child->reveal()]);
    $term_storage->loadTree('bm_help_ai_help_topics')->willReturn([]);
    $term_storage->loadByProperties(['vid' => 'help_topics_metadata'])->willReturn([]);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('taxonomy_term')->willReturn($term_storage->reveal());

    $config_factory = new ConfigFactoryStub([
      'bm_help_ai.classification' => [
        'items' => [
          'help.one' => ['terms' => [1]],
          'help.two' => ['terms' => [2]],
        ],
      ],
    ]);

    $service = new HelpClassificationService(
      $entity_type_manager->reveal(),
      $config_factory,
    );

    $items = [
      ['id' => 'help.one', 'terms' => [1]],
      ['id' => 'help.two', 'terms' => [2]],
    ];

    $filtered = $service->filterByTerm($items, 1);
    $this->assertCount(2, $filtered, 'Parent term includes descendants.');

    $filtered_child = $service->filterByTerm($items, 2);
    $this->assertCount(1, $filtered_child);
    $this->assertSame('help.two', $filtered_child[0]['id']);
  }

  /**
   * @covers ::getTermTree
   */
  public function testTermTree(): void {
    $term_storage = $this->prophesize(EntityStorageInterface::class);
    $root = new \stdClass();
    $root->tid = 1;
    $root->name = 'Root';
    $root->depth = 0;
    $root->parents = [0];

    $child = new \stdClass();
    $child->tid = 2;
    $child->name = 'Child';
    $child->depth = 1;
    $child->parents = [1];

    $term_storage->loadTree('bm_help_ai_help_topics')->willReturn([$root, $child]);
    $term_storage->loadByProperties(['vid' => 'help_topics_metadata'])->willReturn([]);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('taxonomy_term')->willReturn($term_storage->reveal());

    $config_factory = new ConfigFactoryStub([
      'bm_help_ai.classification' => [
        'items' => [
          'help.one' => ['terms' => [1]],
          'help.two' => ['terms' => [2]],
        ],
      ],
    ]);

    $service = new HelpClassificationService(
      $entity_type_manager->reveal(),
      $config_factory,
    );

    $tree = $service->getTermTree();
    $this->assertCount(1, $tree);
    $this->assertSame(1, $tree[0]['tid']);
    $this->assertCount(1, $tree[0]['children']);
    $this->assertSame(2, $tree[0]['children'][0]['tid']);
    $this->assertEquals(2, $tree[0]['children'][0]['count']);
  }

}
