<?php

use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ResourceClassRepresentation;
use Omeka\Api\Representation\ResourceTemplateRepresentation;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\Api\Representation\PropertyRepresentation;


/**
 * Deterministic fallback color from any key (stable across renders).
 */
function sigmaColorFromKey(string $key): string
{
    $h = md5($key);
    return '#' . substr($h, 0, 6);
}

/**
 * Compute the grouping key for an item.
 * - resource_class: class ID as string
 * - resource_template: template ID as string
 * - property_value: first literal value of the selected property (or '')
 */
function sigmaGetItemGroupKey(ItemRepresentation $item, string $groupBy, ?string $propTerm): string
{
    if ($groupBy === 'resource_class') {
        /** @var ?ResourceClassRepresentation $rc */
        $rc = $item->resourceClass();
        return $rc ? (string) $rc->id() : '';
    }

    if ($groupBy === 'resource_template') {
        /** @var ?ResourceTemplateRepresentation $rt */
        $rt = $item->resourceTemplate();
        return $rt ? (string) $rt->id() : '';
    }

    if ($groupBy === 'property_value' && $propTerm) {
        $val = $item->value($propTerm);
        return $val ? (string) $val : '';
    }

    return '';
}

/**
 * Derive a human label for a group key from an item that matches it.
 */
function sigmaResolveGroupLabelFromItem(ItemRepresentation $item, string $groupBy, ?string $propTerm): string
{
    if ($groupBy === 'resource_class') {
        $rc = $item->resourceClass();
        return $rc ? (string) $rc->label() : '';
    }
    if ($groupBy === 'resource_template') {
        $rt = $item->resourceTemplate();
        return $rt ? (string) $rt->label() : '';
    }
    if ($groupBy === 'property_value' && $propTerm) {
        $val = $item->value($propTerm);
        return $val ? (string) $val : '';
    }
    return '';
}

/**
 * Normalize rows array from settings 
 */
function sigmaNormalizeRows($maybeRows): array
{
    if (is_array($maybeRows)) {
        if (array_key_exists('rows', $maybeRows) && is_array($maybeRows['rows'])) {
            return $maybeRows['rows'];
        }
        return $maybeRows;
    }
    return [];
}

function sigmaUcfirst(string $s): string
{
    if ($s === '') return $s;
    $first = mb_substr($s, 0, 1, 'UTF-8');
    $rest  = mb_substr($s, 1, null, 'UTF-8');
    return mb_strtoupper($first, 'UTF-8') . $rest;
}

/**
 * Build color map for current groupBy, filling gaps with deterministic colors.
 * @return array<string,string> key => hex color
 */
function sigmaBuildColorMap(array $items, string $groupBy, ?string $propTerm, array $nodeColors): array
{
    $rows = sigmaNormalizeRows($nodeColors);
    $map  = [];

    foreach ($rows as $row) {
        $key = '';
        if ($groupBy === 'resource_class')       $key = (string) ($row['resource_class'] ?? '');
        elseif ($groupBy === 'resource_template') $key = (string) ($row['resource_template'] ?? '');
        elseif ($groupBy === 'property_value')    $key = (string) ($row['property_value'] ?? '');
        if ($key === '') continue;

        $color = trim((string) ($row['color'] ?? ''));
        $map[$key] = $color !== '' ? $color : sigmaColorFromKey($key);
    }

    // Ensure every observed group has a color
    foreach ($items as $item) {
        if (!$item instanceof ItemRepresentation) continue;
        $key = sigmaGetItemGroupKey($item, $groupBy, $propTerm);
        if ($key === '') continue;
        if (!isset($map[$key])) $map[$key] = sigmaColorFromKey($key);
    }

    return $map;
}


/**
 * Safe thumbnail URL (best-effort).
 */
function sigmaGetItemThumbnailUrl(ItemRepresentation $item): ?string
{
    if (method_exists($item, 'thumbnail') && $item->thumbnail()) {
        // Omeka S >= 3 has media thumbs; assetUrl works on ThumbnailRepresentation
        $thumb = $item->thumbnail();
        if ($thumb && method_exists($thumb, 'assetUrl')) return $thumb->assetUrl();
    }
    if (method_exists($item, 'thumbnailDisplayUrl')) {
        return $item->thumbnailDisplayUrl('square');
    }
    return null;
}


/**
 * 
 *  Build popup content block
 *  Title, thumbnail , metadata, relationships
 * 
 */
function sigmaBuildPopUpContent(ItemRepresentation $item, array $popupConfig)
{

    $popupContent = '';

    // tiny escaper
    $e = function ($s) {
        return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    };

    $itemUrl = (string) $item->siteUrl();
    // Title
    if (in_array('title', $popupConfig, true)) {
        $title = $e($item->displayTitle());
        $popupContent .= $itemUrl
            ? '<p style="font-size:10px"><a href="' . $e($itemUrl) . '" target="_blank" rel="noopener">' . $title . '</a></p>'
            : '<p style="font-size:10px">' . $title . '</p>';
    }

    // Thumbnail
    if (in_array('thumbnail', $popupConfig, true)) {
        $thumbnail = sigmaGetItemThumbnailUrl($item);
        if ($thumbnail) {
            $popupContent .= '<div>'
                .  '<img src="' . $e($thumbnail) . '" alt="" style="width:100%;height:auto;display:block" />'
                .  '</div>';
        }
    }

    return $popupContent;
}

function sigmaGenerateGraph(array $items, array $opts = []): array
{
    $groupBy   = $opts['groupBy']   ?? 'resource_class';
    $propTerm  = $opts['propTerm']  ?? null;
    $colorsRaw = $opts['nodeColors'] ?? [];
    $relProps  = array_values(array_filter($opts['relationshipProperties'] ?? []));
    $excludeIsolated = !empty($opts['excludeWithoutRelationships']);
    $sizeMin   = isset($opts['sizeMin']) ? (int) $opts['sizeMin'] : 3;
    $sizeMax   = isset($opts['sizeMax']) ? (int) $opts['sizeMax'] : 18;

    $popupConfig = isset($opts['popupConfig']) ?  $opts['popupConfig'] : ['title'];

    // Build maps
    $colorMap = sigmaBuildColorMap($items, $groupBy, $propTerm, $colorsRaw);

    // Pre-index items by id for quick lookups
    $itemById = [];
    foreach ($items as $it) {
        if ($it instanceof ItemRepresentation) $itemById[$it->id()] = $it;
    }

    // Nodes (initial, all items)
    $nodes = [];
    $groupLabels = []; // key => label
    foreach ($itemById as $id => $item) {
        $key      = sigmaGetItemGroupKey($item, $groupBy, $propTerm);

        $label    = $item->displayTitle();
        $short    = (mb_strlen($label, 'UTF-8') > 25) ? (mb_substr($label, 0, 22, 'UTF-8') . '…') : $label;

        $color    = $key !== '' && isset($colorMap[$key]) ? $colorMap[$key] : sigmaColorFromKey((string) $id);

        if ($key !== '' && !isset($groupLabels[$key])) {
            $groupLabels[$key] = sigmaResolveGroupLabelFromItem($item, $groupBy, $propTerm);
        }

        // Use stable pseudo-random coordinates from id hash
        $h = hexdec(substr(md5((string) $id), 0, 8));
        $x = (($h % 1170) - 585);         // -585..584
        $y = (((int)($h / 1170) % 700) - 350); // -350..349

        $nodes[(string) $id] = [
            'id'        => (string) $id,
            'label'     => $short,
            'title'     => $short,
            'name'     => $short,
            'x'         => $x,
            'y'         => $y,
            'size'      => $sizeMin,
            'color'     => $color,
            'originalColor' => $color,
            'groupKey'  => $key,
            'popupContent' => sigmaBuildPopUpContent($item, $popupConfig),
            'link'      => method_exists($item, 'siteUrl') ? $item->siteUrl() : null,
        ];
    }

    // Edges: only across selected relationship properties
    $edges          = [];
    $degree         = []; // id => count
    $edgeSeen       = []; // dedup (undirected)
    $nextEdgeId     = 0;

    if (!empty($relProps)) {
        foreach ($itemById as $srcId => $item) {
            foreach ($relProps as $term) {

                $vals = $item->value($term, ['all' => true, 'default' => []]);

                foreach ($vals as $val) {
                    // Only resource links
                    if ($val->type() !== 'resource:item' || !$val->valueResource()) continue;

                    $vr = $val->valueResource();

                    $tgtId = $vr->id();
                    if ($tgtId === $srcId) continue;                // no self-loop
                    if (!isset($itemById[$tgtId])) continue;        // keep edges inside current result set

                    // Undirected de-dup (per property term)
                    $a = min($srcId, $tgtId);
                    $b = max($srcId, $tgtId);
                    $ek = $a . '|' . $b . '|' . $term;
                    if (isset($edgeSeen[$ek])) continue;
                    $edgeSeen[$ek] = true;

                    $edges[] = [
                        'id'     => (string) $nextEdgeId++,
                        'source' => (string) $srcId,
                        'target' => (string) $tgtId,
                        'size'   => 1,
                        'color'  => '#CCCCCC',
                        'term'   => $term,
                    ];

                    $degree[$srcId] = ($degree[$srcId] ?? 0) + 1;
                    $degree[$tgtId] = ($degree[$tgtId] ?? 0) + 1;
                }
            }
        }
    } else {
        foreach ($itemById as $srcId => $item) {
            /** @var ItemRepresentation $item */

            // 1) Collect all property terms present on this item
            $terms = [];
            foreach ((array) $item->values() as $block) {
                // $block is an array with keys like 'property' (PropertyRepresentation) and 'values' (ValueRepresentation[])
                if (!empty($block['property']) && method_exists($block['property'], 'term')) {
                    $terms[] = $block['property']->term();
                }
            }
            if (!$terms) {
                continue;
            }

            // 2) For each term, get all ValueRepresentation objects the *safe* way
            foreach ($terms as $term) {
                $vals = $item->value($term, ['all' => true, 'default' => []]); // always ValueRepresentation[]
                if (!is_array($vals)) continue;

                /** @var ValueRepresentation $val */
                foreach ($vals as $val) {
                    // Only resource links to items
                    $vr = $val->valueResource();
                    if (!$vr instanceof ItemRepresentation) {
                        continue;
                    }

                    $tgtId = $vr->id();
                    if ($tgtId === $srcId) continue;          // no self-loop
                    if (!isset($itemById[$tgtId])) continue;  // edge only within current set

                    // Undirected de-dup (per property term)
                    $a = min($srcId, $tgtId);
                    $b = max($srcId, $tgtId);
                    $ek = $a . '|' . $b . '|' . $term;
                    if (isset($edgeSeen[$ek])) continue;
                    $edgeSeen[$ek] = true;

                    $edges[] = [
                        'id'     => (string) $nextEdgeId++,
                        'source' => (string) $srcId,
                        'target' => (string) $tgtId,
                        'size'   => 1,
                        'color'  => '#CCCCCC',
                        'term'   => $term,
                    ];
                    $degree[$srcId] = ($degree[$srcId] ?? 0) + 1;
                    $degree[$tgtId] = ($degree[$tgtId] ?? 0) + 1;
                }
            }
        }
    }

    // Optionally drop isolated nodes (no edges)
    if ($excludeIsolated) {
        $connected = [];
        foreach ($edges as $e) {
            $connected[$e['source']] = true;
            $connected[$e['target']] = true;
        }
        foreach (array_keys($nodes) as $nid) {
            if (!isset($connected[$nid])) unset($nodes[$nid]);
        }
    }

    // Degree-based sizing
    if (!empty($nodes)) {
        $minD = PHP_INT_MAX;
        $maxD = PHP_INT_MIN;
        foreach ($nodes as $nid => $_) {
            $d = $degree[$nid] ?? 0;
            $minD = min($minD, $d);
            $maxD = max($maxD, $d);
        }
        foreach ($nodes as $nid => &$n) {
            $d = $degree[$nid] ?? 0;
            $n['degree'] = $d;
            if ($maxD === $minD) {
                $n['size'] = $sizeMin;
            } else {
                $n['size'] = (int) round($sizeMin + ($sizeMax - $sizeMin) * (($d - $minD) / max(1, $maxD - $minD)));
            }
        }
        unset($n);
    }

    // --- Legend based on observed groups (use names, not ids) ---
    $legendMap = [];
    foreach ($nodes as $n) {
        $key = $n['groupKey'] ?? '';
        if ($key === '' || isset($legendMap[$key])) continue;

        $legendMap[$groupLabels[$key] ?? (string) $key] = [
            'color' => $colorMap[$key] ?? sigmaColorFromKey($key),
        ];
    }

    return [
        'nodes'     => array_values($nodes),
        'edges'     => $edges,
        'legendMap' => $legendMap,
    ];
}
