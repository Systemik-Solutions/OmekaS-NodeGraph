<?php

namespace NodeGraph;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Entity\SitePage;
use Omeka\Module\AbstractModule;

/**
 * Node Graph module for Omeka S.
 *
 * Provides interactive sigma.js-based graph visualizations as site page blocks.
 */
class Module extends AbstractModule
{
    /**
     * @return array
     */
    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    /**
     * Attach event listeners for cache invalidation on page save.
     *
     * @param SharedEventManagerInterface $sem
     * @return void
     */
    public function attachListeners(SharedEventManagerInterface $sem): void
    {
        $sem->attach('*', 'api.create.post', [$this, 'onPageSaved']);
        $sem->attach('*', 'api.update.post', [$this, 'onPageSaved']);
    }

    /**
     * Handle page save events to rebuild graph cache when needed.
     *
     * @param Event $event
     * @return void
     */
    public function onPageSaved(Event $event): void
    {
        $services = $this->getServiceLocator();
        $response = $event->getParam('response');
        $content  = $response ? $response->getContent() : null;

        $pages = is_array($content) ? $content : ($content ? [$content] : []);
        $adapter = $services->get('Omeka\ApiAdapterManager')->get('site_pages');
        $dispatcher = $services->get(\Omeka\Job\Dispatcher::class);
        $conn       = $services->get('Omeka\Connection');

        foreach ($pages as $page) {
            if ($page instanceof SitePage) {
                $page = $adapter->getRepresentation($page);
            }
            if (!$page instanceof SitePageRepresentation) {
                continue;
            }

            foreach ($page->blocks() as $blockRep) {
                if ($blockRep->layout() !== 'Node Graph') {
                    continue;
                }

                $data         = $blockRep->data();
                $cacheEnabled = !empty($data['cache_result']);
                if (!$cacheEnabled) {
                    continue;
                }

                $hash    = sha1(json_encode($data));
                $blockId = (int) $blockRep->id();

                $conn->executeStatement(
                    'DELETE FROM nodegraph_cache WHERE block_id = ? AND hash = ?',
                    [$blockId, $hash],
                );

                $dispatcher->dispatch(\NodeGraph\Job\BuildGraph::class, [
                    'block_id' => $blockId,
                    'hash'     => $hash,
                    'page_id'  => $page->id(),
                ]);
            }
        }
    }

    /**
     * Create the module's database schema.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return void
     */
    public function install(ServiceLocatorInterface $serviceLocator): void
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->executeStatement('
            CREATE TABLE IF NOT EXISTS nodegraph_cache (
              block_id INT NOT NULL PRIMARY KEY,
              hash     VARCHAR(40) NOT NULL,
              payload  LONGTEXT NOT NULL,
              updated  DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ');
    }

    /**
     * Remove the module's database schema.
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @return void
     */
    public function uninstall(ServiceLocatorInterface $serviceLocator): void
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->executeStatement('DROP TABLE IF EXISTS nodegraph_cache;');
    }

    /**
     * Handle version upgrades with any required schema migrations.
     *
     * @param string $oldVersion
     * @param string $newVersion
     * @param ServiceLocatorInterface $serviceLocator
     * @return void
     */
    public function upgrade($oldVersion, $newVersion, ServiceLocatorInterface $serviceLocator): void
    {
        // Future migrations go here, keyed by version comparison.
        // Example:
        // if (version_compare($oldVersion, '1.1.0', '<')) {
        //     $conn = $serviceLocator->get('Omeka\Connection');
        //     $conn->executeStatement('ALTER TABLE ...');
        // }
    }
}
