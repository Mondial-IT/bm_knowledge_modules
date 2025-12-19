<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_knowledge_ai\Unit;

use Drupal\bm_knowledge_ai\Adapter\NodeKnowledgeAdapter;
use Drupal\bm_knowledge_ai\Model\KnowledgeItem;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;
use Drupal\Tests\UnitTestCase;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @coversDefaultClass \Drupal\bm_knowledge_ai\Adapter\NodeKnowledgeAdapter
 */
class NodeKnowledgeAdapterTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * @covers ::discover
   * @covers ::normalize
   */
  public function testDiscoveryAndNormalization(): void {
    $config = new Config('bm_knowledge_ai.node_adapter', []);
    $config->set('enabled', TRUE);
    $config->set('bundles', ['article']);
    $config->set('fields', ['body' => 'body']);
    $config->set('language_behavior', 'per_translation');
    $config->set('authority_level', 'canonical');

    $config_factory = $this->prophesize(ConfigFactoryInterface::class);
    $config_factory->get('bm_knowledge_ai.node_adapter')->willReturn($config);

    $node = $this->prophesize(NodeInterface::class);
    $node->id()->willReturn(1);
    $node->bundle()->willReturn('article');
    $node->label()->willReturn('Example node');
    $node->getChangedTime()->willReturn(1700000000);
    $node->hasField('body')->willReturn(TRUE);
    $node->get('body')->willReturn($this->createFieldValueProphecy('Sample <strong>body</strong> text'));
    $node->access('view', \Prophecy\Argument::any())->willReturn(TRUE);
    $node->getTranslationLanguages()->willReturn([
      'en' => $this->createLanguage('en'),
    ]);
    $node->hasTranslation('en')->willReturn(TRUE);
    $node->getTranslation('en')->willReturn($node->reveal());
    $node->language()->willReturn($this->createLanguage('en'));

    $query = $this->prophesize(QueryInterface::class);
    $query->condition('status', 1)->willReturn($query->reveal());
    $query->condition('type', ['article'], 'IN')->willReturn($query->reveal());
    $query->accessCheck(TRUE)->willReturn($query->reveal());
    $query->execute()->willReturn([1]);

    $storage = $this->prophesize(EntityStorageInterface::class);
    $storage->getQuery()->willReturn($query->reveal());
    $storage->loadMultiple([1])->willReturn([$node->reveal()]);

    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager->getStorage('node')->willReturn($storage->reveal());

    $language_manager = $this->prophesize(LanguageManagerInterface::class);
    $language_manager->getDefaultLanguage()->willReturn($this->createLanguage('en'));

    $current_user = $this->prophesize(AccountProxyInterface::class);

    $adapter = new NodeKnowledgeAdapter(
      $entity_type_manager->reveal(),
      $config_factory->reveal(),
      $language_manager->reveal(),
      $current_user->reveal(),
    );

    $items = iterator_to_array($adapter->discover());
    $this->assertCount(1, $items);
    $item = $adapter->normalize($items[0]);
    $this->assertInstanceOf(KnowledgeItem::class, $item);
    $this->assertSame('node:1:en', $item->id);
    $this->assertSame('node:1', $item->sourceId);
    $this->assertSame('node', $item->sourceType);
    $this->assertSame('Example node', $item->title);
    $this->assertSame('Sample body text', $item->bodyMarkdown);
    $this->assertSame('canonical', $item->authorityLevel);
    $this->assertSame('en', $item->language);
  }

  protected function createFieldValueProphecy(string $value) {
    $field_item_list = $this->prophesize(\Drupal\Core\Field\FieldItemListInterface::class);
    $field_item_list->isEmpty()->willReturn(FALSE);
    $field_item = $this->prophesize(\Drupal\Core\Field\FieldItemInterface::class);
    $field_item->value = $value;
    $field_item_list->first()->willReturn($field_item->reveal());
    return $field_item_list->reveal();
  }

  protected function createLanguage(string $langcode): LanguageInterface {
    $language = $this->prophesize(LanguageInterface::class);
    $language->getId()->willReturn($langcode);
    return $language->reveal();
  }

}
