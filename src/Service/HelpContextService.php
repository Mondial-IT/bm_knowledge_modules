<?php

declare(strict_types=1);

namespace Drupal\bm_help_ai\Service;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\user\PermissionHandlerInterface;

/**
 * Provides current-request context for relevance evaluation.
 */
class HelpContextService {

  public function __construct(
    protected RouteMatchInterface $routeMatch,
    protected AccountProxyInterface $currentUser,
    protected ModuleHandlerInterface $moduleHandler,
    protected PermissionHandlerInterface $permissionHandler,
  ) {}

  /**
   * Builds a simple context array.
   *
   * @return array<string, mixed>
   *   Context data: route, parameters, roles, permissions, enabled modules.
   */
  public function getContext(): array {
    return [
      'route_name' => $this->getRouteName(),
      'route_parameters' => $this->getRouteParameters(),
      'user_roles' => $this->getUserRoles(),
      'user_permissions' => $this->getUserPermissions(),
      'enabled_modules' => $this->getEnabledModules(),
    ];
  }

  public function getRouteName(): string {
    return (string) $this->routeMatch->getRouteName();
  }

  /**
   * @return array<string, mixed>
   *   Route parameter names and values.
   */
  public function getRouteParameters(): array {
    return $this->routeMatch->getParameters()->all();
  }

  /**
   * @return string[]
   *   Current user role IDs.
   */
  public function getUserRoles(): array {
    return $this->currentUser->getRoles();
  }

  /**
   * @return string[]
   *   Granted permissions for the current user.
   */
  public function getUserPermissions(): array {
    $granted = [];
    $definitions = $this->permissionHandler->getPermissions();

    foreach ($definitions as $permission => $info) {
      if ($this->currentUser->hasPermission($permission)) {
        $granted[] = $permission;
      }
    }

    return $granted;
  }

  /**
   * @return string[]
   *   Machine names of enabled modules.
   */
  public function getEnabledModules(): array {
    return array_keys($this->moduleHandler->getModuleList());
  }

}
