<?php

declare(strict_types=1);

namespace Drupal\Tests\bm_help_ai\Unit;

use Drupal\bm_help_ai\Service\HelpContextService;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\PermissionHandlerInterface;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @coversDefaultClass \Drupal\bm_help_ai\Service\HelpContextService
 */
class HelpContextServiceTest extends UnitTestCase {
  use ProphecyTrait;

  /**
   * @covers ::getContext
   */
  public function testContextCapture(): void {
    $route_match = $this->prophesize(RouteMatchInterface::class);
    $route_match->getRouteName()->willReturn('test.route');
    $route_match->getParameters()->willReturn(new ParameterBag(['node' => 99]));

    $current_user = $this->prophesize(AccountProxyInterface::class);
    $current_user->getRoles()->willReturn(['authenticated', 'site_admin']);
    $current_user->hasPermission('access content')->willReturn(TRUE);
    $current_user->hasPermission('administer site configuration')->willReturn(FALSE);

    $permission_handler = $this->prophesize(PermissionHandlerInterface::class);
    $permission_handler->getPermissions()->willReturn([
      'access content' => ['title' => 'Access content'],
      'administer site configuration' => ['title' => 'Administer site configuration'],
    ]);

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $example_extension = new Extension('', 'module', '/modules/example', 'example.info.yml');
    $example_extension->info = ['name' => 'Example'];
    $module_handler->getModuleList()->willReturn(['example' => $example_extension]);

    $request = new Request([], [], [], [], [], ['QUERY_STRING' => 'tid=9']);
    $request->query->set('tid', 9);
    $request_stack = $this->prophesize(RequestStack::class);
    $request_stack->getCurrentRequest()->willReturn($request);

    $service = new HelpContextService(
      $route_match->reveal(),
      $current_user->reveal(),
      $module_handler->reveal(),
      $permission_handler->reveal(),
      $request_stack->reveal(),
    );

    $context = $service->getContext();

    $this->assertSame('test.route', $context['route_name']);
    $this->assertSame(['node' => 99], $context['route_parameters']);
    $this->assertSame(['authenticated', 'site_admin'], $context['user_roles']);
    $this->assertSame(['access content'], $context['user_permissions']);
    $this->assertSame(['example'], $context['enabled_modules']);
    $this->assertSame(9, $context['selected_term_id']);
  }

}
