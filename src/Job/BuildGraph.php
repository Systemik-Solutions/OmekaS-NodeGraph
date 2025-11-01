<?php

namespace NodeGraph\Job;

require_once dirname(__DIR__, 2) . '/functions.php';

use Omeka\Job\AbstractJob;
use Omeka\Api\Representation\SitePageBlockRepresentation;

class BuildGraph extends AbstractJob
{
    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $api      = $services->get('Omeka\ApiManager');

        $blockId  = (int) $this->getArg('block_id');
        $pageId   = (int) $this->getArg('page_id');
        $hash     = (string) $this->getArg('hash');

        // Read the page, then pick the block by id
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

        $data = $blockRep->data(); // array with your block inputs

        $query  = $data['query'] ?? '';
        $group  = $data['group_by_control'] ?? [];
        $colors = ($data['node_colors']['rows'] ?? []);
        $rels   = (array) ($data['relationships_properties'] ?? []);
        $exclude = !empty($data['exclude_without_relationships']);
        $popup  = (array) ($data['popup_content'] ?? []);


        parse_str((string)$query, $q);
        $items      = $api->search('items', $q)->getContent();
        $sigmaGraph = sigmaGenerateGraph($items, [
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

        $conn     = $services->get('Omeka\Connection');
        // Upsert cache row
        $conn->executeStatement(
            'INSERT INTO nodegraph_cache (block_id, hash, payload, updated)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE hash = VALUES(hash), payload = VALUES(payload), updated = NOW()',
            [$blockId, $hash, json_encode($sigmaGraph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)]
        );
    }
}
