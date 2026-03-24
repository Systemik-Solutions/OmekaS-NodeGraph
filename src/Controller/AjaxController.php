<?php

namespace NodeGraph\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\PropertyRepresentation;

/**
 * AJAX controller for loading additional popup content (metadata, relationships)
 * on demand when a user clicks a graph node.
 *
 * Access control note: item data is retrieved through the Omeka API which
 * enforces visibility rules based on the current user's permissions. Only
 * public items are returned for unauthenticated requests.
 */
class AjaxController extends AbstractActionController
{
    /** Labels to exclude from metadata display. */
    private const EXCLUDED_LABELS = ['originalId', 'Original ID'];

    /**
     * Escape a string for safe HTML output.
     *
     * @param mixed $s
     * @return string
     */
    private function escapeHtml($s): string
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Return popup HTML (metadata and/or relationships) for a single item.
     *
     * Query params:
     *   - id (int): item ID
     *   - metadata (bool): include metadata HTML
     *   - relationships (bool): include relationships HTML
     *
     * @return JsonModel
     */
    public function popupExtraAction(): JsonModel
    {
        $id       = (int) $this->params()->fromQuery('id', 0);
        $wantMeta = (bool) $this->params()->fromQuery('metadata', false);
        $wantRel  = (bool) $this->params()->fromQuery('relationships', false);

        if (!$id) {
            return new JsonModel(['ok' => false]);
        }

        $serviceManager = $this->getEvent()->getApplication()->getServiceManager();
        $api    = $serviceManager->get('Omeka\ApiManager');
        $logger = $serviceManager->get('Omeka\Logger');

        try {
            $item = $api->read('items', $id)->getContent();
        } catch (\Throwable $e) {
            $logger->err(sprintf('NodeGraph: failed to read item #%d: %s', $id, $e->getMessage()));
            return new JsonModel(['ok' => false]);
        }

        $metadataHtml      = $wantMeta ? $this->buildMetadataHtml($item, $serviceManager) : '';
        $relationshipsHtml = $wantRel ? $this->buildRelationshipsHtml($item, $api, $logger) : '';

        return new JsonModel([
            'ok'   => true,
            'html' => $metadataHtml . $relationshipsHtml,
        ]);
    }

    /**
     * Build HTML for all metadata values of an item.
     *
     * @param ItemRepresentation $item
     * @param \Laminas\ServiceManager\ServiceLocatorInterface $serviceManager
     * @return string
     */
    private function buildMetadataHtml(ItemRepresentation $item, $serviceManager): string
    {
        $htmlPurifier = $serviceManager->get('Omeka\HtmlPurifier');
        $metadataHtml = '';
        $itemValues   = $item->values();

        foreach ($itemValues as $term => $propData) {
            $propertyData = null;
            $valueDatas   = [];

            if (is_array($propData) && array_key_exists('values', $propData)) {
                $valueDatas = is_array($propData['values']) ? $propData['values'] : [];
                if (isset($propData['property']) && $propData['property'] instanceof PropertyRepresentation) {
                    $propertyData = $propData['property'];
                }
            } else {
                continue;
            }

            $label = isset($propData['alternate_label'])
                ? $propData['alternate_label']
                : ($propertyData instanceof PropertyRepresentation
                    ? $propertyData->label()
                    : preg_replace('~^.+:~', '', (string) $term));

            $valueLines = [];
            foreach ($valueDatas as $valueData) {
                $type = $valueData->type();

                if ($type === 'resource:media') {
                    continue;
                }

                if ($type === 'resource' || $type === 'resource:item') {
                    $vr = $valueData->valueResource();
                    if (!$vr instanceof ItemRepresentation) {
                        continue;
                    }
                    $title = $vr->displayTitle();
                    $url   = $vr>url();
                    $valueLines[] = '<a href="' . $this->escapeHtml($url) . '" target="_blank" rel="noopener">'
                        . $this->escapeHtml($title) . '</a>';
                    continue;
                }

                $text = (string) $valueData->value();
                if ($text === '' || in_array(trim($label), self::EXCLUDED_LABELS, true)) {
                    continue;
                }

                // Sanitize HTML values through Omeka's HTML purifier
                if ($type === 'html') {
                    $valueLines[] = $htmlPurifier->purify($text);
                    continue;
                }

                $rendered = filter_var($text, FILTER_VALIDATE_URL)
                    ? '<a href="' . $this->escapeHtml($text) . '" target="_blank" rel="noopener">'
                        . $this->escapeHtml($text) . '</a>'
                    : $this->escapeHtml($text);
                $valueLines[] = $rendered;
            }

            if ($valueLines) {
                $metadataHtml .= '<p style="font-size:10px"><strong>'
                    . $this->escapeHtml($label) . ':</strong><br>'
                    . implode('<br>', $valueLines) . '</p>';
            }
        }

        return $metadataHtml;
    }

    /**
     * Build HTML for items that link to the given item.
     *
     * @param ItemRepresentation $item
     * @param \Omeka\Api\Manager $api
     * @param \Laminas\Log\Logger $logger
     * @return string
     */
    private function buildRelationshipsHtml(ItemRepresentation $item, $api, $logger): string
    {
        try {
            $relatedItems = $api->search('items', [
                'property' => [[
                    'joiner'   => 'and',
                    'property' => 0,
                    'type'     => 'res',
                    'text'     => $item->id(),
                ]],
                'per_page'  => 999,
                'page'      => 1,
                'is_public' => 1,
            ])->getContent();

            $links = [];
            $seen  = [];

            foreach ($relatedItems as $relatedItem) {
                if (!$relatedItem instanceof ItemRepresentation) {
                    continue;
                }
                $relatedItemID = $relatedItem->id();
                if ($relatedItemID === $item->id() || isset($seen[$relatedItemID])) {
                    continue;
                }
                $seen[$relatedItemID] = true;

                $relatedItemTitle = $relatedItem->displayTitle();
                $relatedItemUrl   = (string) $relatedItem>url();

                $links[] = $relatedItemUrl
                    ? '<a href="' . $this->escapeHtml($relatedItemUrl) . '" target="_blank" rel="noopener">'
                        . $this->escapeHtml($relatedItemTitle) . '</a>'
                    : $this->escapeHtml($relatedItemTitle);
            }

            if ($links) {
                return '<p style="font-size:10px"><strong>'
                    . $this->escapeHtml('Related items') . ':</strong><br>'
                    . implode('<br>', $links) . '</p>';
            }
        } catch (\Throwable $e) {
            $logger->err(sprintf(
                'NodeGraph: failed to load relationships for item #%d: %s',
                $item->id(),
                $e->getMessage(),
            ));
        }

        return '';
    }
}
