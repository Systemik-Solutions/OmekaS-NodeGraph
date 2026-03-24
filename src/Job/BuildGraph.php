<?php

namespace NodeGraph\Job;

use NodeGraph\Service\GraphHelper;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Job\AbstractJob;

/**
 * Background job that pre-computes graph data and stores it in the cache table.
 *
 * Dispatched when a page containing a Node Graph block with caching enabled
 * is saved.
 */
class BuildGraph extends AbstractJob
{
    /**
     * Build the graph payload for a single block and upsert it into the cache.
     *
     * @return void
     */
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $api      = $services->get('Omeka\ApiManager');

        $blockId  = (int) $this->getArg('block_id');
        $pageId   = (int) $this->getArg('page_id');
        $hash     = (string) $this->getArg('hash');

        $page = $api->read('site_pages', $pageId)->getContent();

        /** @var SitePageBlockRepresentation|null $blockRep */
        $blockRep = null;
        foreach ($page->blocks() as $b) {
            if ((int) $b->id() === $blockId) {
                $blockRep = $b;
                break;
            }
        }

        if (!$blockRep) {
            $services->get('Omeka\Logger')->err("NodeGraph: block #$blockId not found on page #$pageId");
            return;
        }

        $data = $blockRep->data();

        $query   = $data['query'] ?? '';
        $group   = $data['group_by_control'] ?? [];
        $colors  = $data['node_colors']['rows'] ?? [];
        $rels    = (array) ($data['relationships_properties'] ?? []);
        $exclude = !empty($data['exclude_without_relationships']);
        $popup   = (array) ($data['popup_content'] ?? []);

        parse_str((string) $query, $q);
        $items      = $api->search('items', $q)->getContent();
        $sigmaGraph = GraphHelper::generateGraph($items, [
            'groupBy'    => $group['group-by-select'] ?? 'resource_class',
            'propTerm'   => ($group['group-by-select'] ?? null) === 'property_value'
                ? ($group['group-by-property-select'] ?? null) : null,
            'nodeColors' => $colors,
            'relationshipProperties' => $rels,
            'excludeWithoutRelationships' => $exclude,
            'popupConfig' => $popup,
            'sizeMin' => 3,
            'sizeMax' => 18,
        ]);

        $conn = $services->get('Omeka\Connection');
        $conn->executeStatement(
            'INSERT INTO nodegraph_cache (block_id, hash, payload, updated)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE hash = VALUES(hash), payload = VALUES(payload), updated = NOW()',
            [$blockId, $hash, json_encode($sigmaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
        );
    }
}
