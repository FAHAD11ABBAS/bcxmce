<?php

namespace WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ContentStats;

use WPML\Core\Component\ReportContentStats\Application\Service\ContentStatsService;
use WPML\Core\SharedKernel\Component\Installer\Application\Query\WpmlSiteKeyQueryInterface;
use WPML\Core\SharedKernel\Component\Site\Application\Query\SiteMigrationLockQueryInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptDataProviderInterface;
use WPML\UserInterface\Web\Core\Port\Script\ScriptPrerequisitesInterface;
use WPML\UserInterface\Web\Core\SharedKernel\Config\Endpoint\Endpoint;
use WPML\UserInterface\Web\Infrastructure\CompositionRoot\Config\ApiInterface;

class Controller implements
  ScriptPrerequisitesInterface,
  ScriptDataProviderInterface {

  /** @var Endpoint|null */
  private $endpoint;

  /** @var ApiInterface */
  private $api;

  /** @var ContentStatsService */
  private $contentStatsService;

  /** @var SiteMigrationLockQueryInterface */
  private $siteMigrationLockQuery;

  /** @var WpmlSiteKeyQueryInterface */
  private $siteKeyQuery;


  public function __construct(
    ApiInterface $api,
    ContentStatsService $contentStatsService,
    SiteMigrationLockQueryInterface $siteMigrationLockQuery,
    WpmlSiteKeyQueryInterface $siteKeyQuery
  ) {
    $this->api                    = $api;
    $this->contentStatsService    = $contentStatsService;
    $this->siteMigrationLockQuery = $siteMigrationLockQuery;
    $this->siteKeyQuery           = $siteKeyQuery;
  }


  public function scriptPrerequisitesMet(): bool {
    return ! $this->siteMigrationLockQuery->isLocked() &&
           $this->siteKeyQuery->get() &&
           $this->contentStatsService->canProcess();
  }


  public function jsWindowKey(): string {
    return 'wpmlContentStats';
  }


  public function initialScriptData(): array {
    return [
      'route' => $this->api->getFullUrl( $this->getEndpoint() ),
      'nonce' => $this->api->nonce(),
    ];
  }


  private function getEndpoint(): Endpoint {
    if ( $this->endpoint === null ) {
      $this->endpoint = new Endpoint( EndpointDataProvider::ID, EndpointDataProvider::PATH );
      $this->endpoint->setMethod( EndpointDataProvider::METHOD );
    }

    return $this->endpoint;
  }


}
