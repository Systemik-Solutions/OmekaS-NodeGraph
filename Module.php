<?php

namespace NodeGraph;

use Omeka\Module\AbstractModule;
use Laminas\Mvc\MvcEvent;
use Laminas\EventManager\SharedEventManagerInterface;
use Omeka\Entity\SitePage;
use Omeka\Api\Representation\SitePageRepresentation;
use Laminas\EventManager\Event;


class Module extends AbstractModule
{

    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }


    public function onBootstrap(MvcEvent $event): void
    {
        // IMPORTANT: ensure base class runs (this is what normally calls attachListeners()).
        parent::onBootstrap($event);

        $services = $event->getApplication()->getServiceManager();
        $viewHelperManager = $services->get('ViewHelperManager');
        $headScript = $viewHelperManager->get('headScript');
        $headLink = $viewHelperManager->get('headLink');

        //Sigma js
        $headScript
            ->appendFile('https://cdnjs.cloudflare.com/ajax/libs/graphology/0.24.0/graphology.umd.min.js')
            ->appendFile('https://cdn.jsdelivr.net/npm/graphology-library@0.8.0/dist/graphology-library.min.js')
            ->appendFile('https://cdnjs.cloudflare.com/ajax/libs/sigma.js/3.0.2/sigma.min.js');
    }

    public function attachListeners(SharedEventManagerInterface $sem): void
    {
        // After API save (create/update) of site pages, queue builds.
        $sem->attach('*', 'api.create.post', [$this, 'onPageSaved']);
        $sem->attach('*', 'api.update.post', [$this, 'onPageSaved']);
    }


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
                // Only Node graph block
                if ($blockRep->layout() !== 'Node Graph') {
                    continue;
                }

                $data         = $blockRep->data();
                $cacheEnabled = !empty($data['cache_result']);
                if (!$cacheEnabled) {
                    continue;
                }

                // If caching is on, compute hash of inputs that affect output
                $hash    = sha1(json_encode($data));
                $blockId = (int) $blockRep->id();

                // If cache already exist, delete and rebuild
                $conn->executeStatement(
                    'DELETE FROM nodegraph_cache WHERE block_id = ? AND hash = ?',
                    [$blockId, $hash]
                );

                // Queue job to (re)build the cache
                $dispatcher->dispatch(\NodeGraph\Job\BuildGraph::class, [
                    'block_id' => $blockId,
                    'hash'     => $hash,
                    'page_id'  => $page->id(),
                ]);
            }
        }
    }

    public function install($serviceLocator)
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

    public function uninstall($serviceLocator)
    {
        $conn = $serviceLocator->get('Omeka\Connection');
        $conn->executeStatement('DROP TABLE IF EXISTS nodegraph_cache;');
    }
}
