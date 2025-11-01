<?php

namespace NodeGraph\Controller;

use Error;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\JsonModel;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\Api\Representation\PropertyRepresentation;

class AjaxController extends AbstractActionController
{
    private function escapeHtml($s)
    {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }


    public function popupExtraAction()
    {

        $id        = (int) $this->params()->fromQuery('id', 0);
        $wantMeta  = (bool) $this->params()->fromQuery('metadata', false);
        $wantRel   = (bool) $this->params()->fromQuery('relationships', false);

        if (!$id) {
            return new JsonModel(['ok' => false]);
        }

        $serviceManager  = $this->getEvent()->getApplication()->getServiceManager();
        $api = $serviceManager->get('Omeka\ApiManager');

        try {
            $item = $api->read('items', $id)->getContent();
        } catch (\Throwable $e) {
            return new JsonModel(['ok' => false]);
        }

        // Metadata: all values except media and original id
        $metadataHtml = '';
        if ($wantMeta) {
            $itemValues = $item->values();

            foreach ($itemValues as $term => $propData) {

                $propertyData = null;
                $valueDatas = [];

                if (is_array($propData) && array_key_exists('values', $propData)) {
                    $valueDatas = is_array($propData['values']) ? $propData['values'] : [];
                    if (isset($propData['property']) && $propData['property'] instanceof PropertyRepresentation) {
                        $propertyData = $propData['property'];
                    }
                } else {
                    continue;
                }

                // Get label, use alternate label if available
                $label = isset($propData['alternate_label']) ? $propData['alternate_label'] : ($propertyData instanceof PropertyRepresentation ? $propertyData->label() : preg_replace('~^.+:~', '', (string) $term));

                $valueLines = [];
                foreach ($valueDatas as $valueData) {

                    // Skip resource links to media
                    $type = $valueData->type();
                    if ($type === 'resource:media') {
                        continue;
                    }

                    // Deal with link to another item
                    if ($type === 'resource' || $type === 'resource:item') {
                        $vr = $valueData->valueResource();
                        if (!$vr instanceof ItemRepresentation) {
                            continue;
                        }
                        $title = $vr->displayTitle();
                        $url   = $vr->siteUrl('omaa');
                        $valueLines[] = '<a href="' . $url . '" target="_blank" rel="noopener">' . $this->escapeHtml($title) . '</a>';
                        continue;
                    }


                    $text = (string) $valueData->value();
                    if ($text === '' || $label == 'originalId' || trim($label) == 'Original ID') {
                        continue;
                    }

                    //  Deal with html. no escape
                    if ($type == 'html') {
                        $valueLines[] = $text;
                        continue;
                    }

                    // Link url values
                    $rendered = filter_var($text, FILTER_VALIDATE_URL)
                        ? '<a href="' . $this->escapeHtml($text) . '" target="_blank" rel="noopener">' . $this->escapeHtml($text) . '</a>'
                        : $this->escapeHtml($text);
                    $valueLines[] = $rendered;
                }

                if ($valueLines) {
                    $metadataHtml .= '<p style="font-size:10px"><strong>'
                        . $this->escapeHtml(($label)) . ':</strong><br>' . implode('<br>', $valueLines) . '</p>';
                }
            }
        }

        //Relationships: find ALL items that link to this item (any property) via API query
        $relationshipsHtml = '';
        if ($wantRel) {
            try {
                $relatedItems = $api->search('items', [
                    'property' => [[
                        'joiner'   => 'and',
                        'property' => 0,        // any property
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
                    if (!$relatedItem instanceof ItemRepresentation) continue;
                    $relatedItemID = $relatedItem->id();
                    if ($relatedItemID === $item->id() || isset($seen[$relatedItemID])) continue;
                    $seen[$relatedItemID] = true;

                    $relatedItemTitle = $relatedItem->displayTitle();

                    $relatedItemUrl = (string) $relatedItem->siteUrl('omaa');

                    $links[] = $relatedItemUrl
                        ? '<a href="' . $this->escapeHtml($relatedItemUrl) . '" target="_blank" rel="noopener">' . $this->escapeHtml($relatedItemTitle) . '</a>'
                        : $this->escapeHtml($relatedItemTitle);
                }

                if ($links) {
                    $relationshipsHtml = '<p style="font-size:10px"><strong>'
                        . $this->escapeHtml('Related items') . ':</strong><br>' . implode('<br>', $links) . '</p>';
                }
            } catch (\Throwable $ignored) {
            }
        }

        return new JsonModel([
            'ok'                => true,
            'html'      => $metadataHtml . $relationshipsHtml,
        ]);
    }
}
