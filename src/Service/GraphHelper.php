<?php

namespace NodeGraph\Service;

use Omeka\Api\Representation\ItemRepresentation;

/**
 * Utility class for building sigma.js graph data structures from Omeka S items.
 *
 * namespaced, static API.
 */
class GraphHelper
{
    /** Maximum display length for node labels before truncation. */
    private const LABEL_MAX_LENGTH = 25;

    /** Horizontal coordinate range for pseudo-random node placement. */
    private const COORD_X_RANGE = 1170;

    /** Horizontal coordinate offset for centering nodes. */
    private const COORD_X_OFFSET = 585;

    /** Vertical coordinate range for pseudo-random node placement. */
    private const COORD_Y_RANGE = 700;

    /** Vertical coordinate offset for centering nodes. */
    private const COORD_Y_OFFSET = 350;

    /** Default color for graph edges. */
    private const DEFAULT_EDGE_COLOR = '#CCCCCC';

    /**
     * Generate a deterministic hex color from any string key (stable across renders).
     *
     * @param string $key
     * @return string Hex color string (e.g. '#a1b2c3')
     */
    public static function colorFromKey(string $key): string
    {
        $h = md5($key);
        return '#' . substr($h, 0, 6);
    }

    /**
     * Compute the grouping key for an item based on the groupBy strategy.
     *
     * @param ItemRepresentation $item
     * @param string $groupBy One of 'resource_class', 'resource_template', 'property_value'
     * @param string|null $propTerm Property term when groupBy is 'property_value'
     * @return string The group key, or empty string if not resolvable
     */
    public static function getItemGroupKey(ItemRepresentation $item, string $groupBy, ?string $propTerm): string
    {
        if ($groupBy === 'resource_class') {
            $rc = $item->resourceClass();
            return $rc ? (string) $rc->id() : '';
        }

        if ($groupBy === 'resource_template') {
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
     * Derive a human-readable label for a group key from an item.
     *
     * @param ItemRepresentation $item
     * @param string $groupBy
     * @param string|null $propTerm
     * @return string
     */
    public static function resolveGroupLabelFromItem(ItemRepresentation $item, string $groupBy, ?string $propTerm): string
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
     * Normalize a rows array from settings, handling both nested and flat formats.
     *
     * @param mixed $maybeRows
     * @return array
     */
    public static function normalizeRows($maybeRows): array
    {
        if (is_array($maybeRows)) {
            if (array_key_exists('rows', $maybeRows) && is_array($maybeRows['rows'])) {
                return $maybeRows['rows'];
            }
            return $maybeRows;
        }
        return [];
    }

    /**
     * Multibyte-safe ucfirst.
     *
     * @param string $s
     * @return string
     */
    public static function mbUcfirst(string $s): string
    {
        if ($s === '') {
            return $s;
        }
        $first = mb_substr($s, 0, 1, 'UTF-8');
        $rest  = mb_substr($s, 1, null, 'UTF-8');
        return mb_strtoupper($first, 'UTF-8') . $rest;
    }

    /**
     * Build a color map for the current groupBy strategy, filling gaps with deterministic colors.
     *
     * @param ItemRepresentation[] $items
     * @param string $groupBy
     * @param string|null $propTerm
     * @param array $nodeColors Raw node color settings
     * @return array<string, string> Map of group key to hex color
     */
    public static function buildColorMap(array $items, string $groupBy, ?string $propTerm, array $nodeColors): array
    {
        $rows = self::normalizeRows($nodeColors);
        $map  = [];

        foreach ($rows as $row) {
            $key = '';
            if ($groupBy === 'resource_class') {
                $key = (string) ($row['resource_class'] ?? '');
            } elseif ($groupBy === 'resource_template') {
                $key = (string) ($row['resource_template'] ?? '');
            } elseif ($groupBy === 'property_value') {
                $key = (string) ($row['property_value'] ?? '');
            }
            if ($key === '') {
                continue;
            }

            $color = trim((string) ($row['color'] ?? ''));
            $map[$key] = $color !== '' ? $color : self::colorFromKey($key);
        }

        foreach ($items as $item) {
            if (!$item instanceof ItemRepresentation) {
                continue;
            }
            $key = self::getItemGroupKey($item, $groupBy, $propTerm);
            if ($key === '') {
                continue;
            }
            if (!isset($map[$key])) {
                $map[$key] = self::colorFromKey($key);
            }
        }

        return $map;
    }

    /**
     * Get the best available thumbnail URL for an item.
     *
     * @param ItemRepresentation $item
     * @return string|null
     */
    public static function getItemThumbnailUrl(ItemRepresentation $item): ?string
    {
        if (method_exists($item, 'thumbnail') && $item->thumbnail()) {
            $thumb = $item->thumbnail();
            if ($thumb && method_exists($thumb, 'assetUrl')) {
                return $thumb->assetUrl();
            }
        }
        if (method_exists($item, 'thumbnailDisplayUrl')) {
            return $item->thumbnailDisplayUrl('square');
        }
        return null;
    }

    /**
     * Build popup HTML content for a node (title and thumbnail only; metadata loaded via AJAX).
     *
     * @param ItemRepresentation $item
     * @param array $popupConfig List of enabled popup sections (e.g. ['title', 'thumbnail'])
     * @return string HTML content
     */
    public static function buildPopupContent(ItemRepresentation $item, array $popupConfig): string
    {
        $popupContent = '';

        $e = function ($s) {
            return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        };

        $itemUrl = (string) $item>url();

        if (in_array('title', $popupConfig, true)) {
            $title = $e($item->displayTitle());
            $popupContent .= $itemUrl
                ? '<p style="font-size:10px"><a href="' . $e($itemUrl) . '" target="_blank" rel="noopener">' . $title . '</a></p>'
                : '<p style="font-size:10px">' . $title . '</p>';
        }

        if (in_array('thumbnail', $popupConfig, true)) {
            $thumbnail = self::getItemThumbnailUrl($item);
            if ($thumbnail) {
                $popupContent .= '<div>'
                    . '<img src="' . $e($thumbnail) . '" alt="" style="width:100%;height:auto;display:block" />'
                    . '</div>';
            }
        }

        return $popupContent;
    }

    /**
     * Build the complete sigma.js graph data structure from a set of items.
     *
     * @param ItemRepresentation[] $items
     * @param array $opts Graph generation options:
     *   - groupBy (string): 'resource_class'|'resource_template'|'property_value'
     *   - propTerm (?string): property term for property_value grouping
     *   - nodeColors (array): color configuration rows
     *   - relationshipProperties (array): property terms to follow for edges
     *   - excludeWithoutRelationships (bool): drop isolated nodes
     *   - popupConfig (array): popup sections to include
     *   - sizeMin (int): minimum node size
     *   - sizeMax (int): maximum node size
     * @return array{nodes: array, edges: array, legendMap: array}
     */
    public static function generateGraph(array $items, array $opts = []): array
    {
        $groupBy   = $opts['groupBy'] ?? 'resource_class';
        $propTerm  = $opts['propTerm'] ?? null;
        $colorsRaw = $opts['nodeColors'] ?? [];
        $relProps  = array_values(array_filter($opts['relationshipProperties'] ?? []));
        $excludeIsolated = !empty($opts['excludeWithoutRelationships']);
        $sizeMin   = isset($opts['sizeMin']) ? (int) $opts['sizeMin'] : 3;
        $sizeMax   = isset($opts['sizeMax']) ? (int) $opts['sizeMax'] : 18;
        $popupConfig = $opts['popupConfig'] ?? ['title'];

        $colorMap = self::buildColorMap($items, $groupBy, $propTerm, $colorsRaw);

        $itemById = [];
        foreach ($items as $it) {
            if ($it instanceof ItemRepresentation) {
                $itemById[$it->id()] = $it;
            }
        }

        $nodeResult  = self::buildNodes($itemById, $groupBy, $propTerm, $colorMap, $sizeMin, $popupConfig);
        $nodes       = $nodeResult['nodes'];
        $groupLabels = $nodeResult['groupLabels'];

        $edgeResult = self::buildEdges($itemById, $relProps);
        $edges      = $edgeResult['edges'];
        $degree     = $edgeResult['degree'];

        if ($excludeIsolated) {
            $nodes = self::filterIsolatedNodes($nodes, $edges);
        }

        $nodes     = self::applyDegreeSizing($nodes, $degree, $sizeMin, $sizeMax);
        $legendMap = self::buildLegendMap($nodes, $groupLabels, $colorMap);

        return [
            'nodes'     => array_values($nodes),
            'edges'     => $edges,
            'legendMap' => $legendMap,
        ];
    }

    /**
     * Build node data from indexed items.
     *
     * @param array<int, ItemRepresentation> $itemById
     * @param string $groupBy
     * @param string|null $propTerm
     * @param array<string, string> $colorMap
     * @param int $sizeMin
     * @param array $popupConfig
     * @return array{nodes: array, groupLabels: array}
     */
    private static function buildNodes(
        array $itemById,
        string $groupBy,
        ?string $propTerm,
        array $colorMap,
        int $sizeMin,
        array $popupConfig
    ): array {
        $nodes       = [];
        $groupLabels = [];

        foreach ($itemById as $id => $item) {
            $key   = self::getItemGroupKey($item, $groupBy, $propTerm);
            $label = $item->displayTitle();
            $short = (mb_strlen($label, 'UTF-8') > self::LABEL_MAX_LENGTH)
                ? (mb_substr($label, 0, self::LABEL_MAX_LENGTH - 3, 'UTF-8') . '…')
                : $label;

            $color = ($key !== '' && isset($colorMap[$key]))
                ? $colorMap[$key]
                : self::colorFromKey((string) $id);

            if ($key !== '' && !isset($groupLabels[$key])) {
                $groupLabels[$key] = self::resolveGroupLabelFromItem($item, $groupBy, $propTerm);
            }

            $h = hexdec(substr(md5((string) $id), 0, 8));
            $x = ($h % self::COORD_X_RANGE) - self::COORD_X_OFFSET;
            $y = ((int) ($h / self::COORD_X_RANGE) % self::COORD_Y_RANGE) - self::COORD_Y_OFFSET;

            $nodes[(string) $id] = [
                'id'            => (string) $id,
                'label'         => $short,
                'title'         => $short,
                'name'          => $short,
                'x'             => $x,
                'y'             => $y,
                'size'          => $sizeMin,
                'color'         => $color,
                'originalColor' => $color,
                'groupKey'      => $key,
                'popupContent'  => self::buildPopupContent($item, $popupConfig),
                'link'          => $item ? $item->url() : null,
            ];
        }

        return ['nodes' => $nodes, 'groupLabels' => $groupLabels];
    }

    /**
     * Build edges and compute degree counts from item relationships.
     *
     * When $relProps is non-empty, only the specified properties are followed
     * (strict resource:item type check). When empty, all property terms on
     * each item are inspected for any link to an ItemRepresentation.
     *
     * @param array<int, ItemRepresentation> $itemById
     * @param array $relProps Selected relationship property terms (empty = all)
     * @return array{edges: array, degree: array<int, int>}
     */
    private static function buildEdges(array $itemById, array $relProps): array
    {
        $edges      = [];
        $degree     = [];
        $edgeSeen   = [];
        $nextEdgeId = 0;
        $useAllTerms = empty($relProps);

        foreach ($itemById as $srcId => $item) {
            $terms = $useAllTerms ? self::getItemPropertyTerms($item) : $relProps;
            if (empty($terms)) {
                continue;
            }

            foreach ($terms as $term) {
                $vals = $item->value($term, ['all' => true, 'default' => []]);
                if (!is_array($vals)) {
                    continue;
                }

                foreach ($vals as $val) {
                    // With specific properties: only follow explicit resource:item links
                    if (!$useAllTerms && ($val->type() !== 'resource:item' || !$val->valueResource())) {
                        continue;
                    }

                    $vr = $val->valueResource();
                    if (!$vr instanceof ItemRepresentation) {
                        continue;
                    }

                    $tgtId = $vr->id();
                    if ($tgtId === $srcId || !isset($itemById[$tgtId])) {
                        continue;
                    }

                    // Undirected de-dup (per property term)
                    $a  = min($srcId, $tgtId);
                    $b  = max($srcId, $tgtId);
                    $ek = $a . '|' . $b . '|' . $term;
                    if (isset($edgeSeen[$ek])) {
                        continue;
                    }
                    $edgeSeen[$ek] = true;

                    $edges[] = [
                        'id'     => (string) $nextEdgeId++,
                        'source' => (string) $srcId,
                        'target' => (string) $tgtId,
                        'size'   => 1,
                        'color'  => self::DEFAULT_EDGE_COLOR,
                        'term'   => $term,
                    ];

                    $degree[$srcId] = ($degree[$srcId] ?? 0) + 1;
                    $degree[$tgtId] = ($degree[$tgtId] ?? 0) + 1;
                }
            }
        }

        return ['edges' => $edges, 'degree' => $degree];
    }

    /**
     * Get all property terms present on an item.
     *
     * @param ItemRepresentation $item
     * @return string[]
     */
    private static function getItemPropertyTerms(ItemRepresentation $item): array
    {
        $terms = [];
        foreach ((array) $item->values() as $block) {
            if (!empty($block['property']) && method_exists($block['property'], 'term')) {
                $terms[] = $block['property']->term();
            }
        }
        return $terms;
    }

    /**
     * Remove nodes that have no edges (isolated nodes).
     *
     * @param array $nodes
     * @param array $edges
     * @return array Filtered nodes
     */
    private static function filterIsolatedNodes(array $nodes, array $edges): array
    {
        $connected = [];
        foreach ($edges as $e) {
            $connected[$e['source']] = true;
            $connected[$e['target']] = true;
        }
        foreach (array_keys($nodes) as $nid) {
            if (!isset($connected[$nid])) {
                unset($nodes[$nid]);
            }
        }
        return $nodes;
    }

    /**
     * Apply degree-based sizing to nodes.
     *
     * @param array $nodes
     * @param array<int, int> $degree
     * @param int $sizeMin
     * @param int $sizeMax
     * @return array
     */
    private static function applyDegreeSizing(array $nodes, array $degree, int $sizeMin, int $sizeMax): array
    {
        if (empty($nodes)) {
            return $nodes;
        }

        $minD = PHP_INT_MAX;
        $maxD = PHP_INT_MIN;
        foreach ($nodes as $nid => $_) {
            $d    = $degree[$nid] ?? 0;
            $minD = min($minD, $d);
            $maxD = max($maxD, $d);
        }

        foreach ($nodes as $nid => &$n) {
            $d = $degree[$nid] ?? 0;
            $n['degree'] = $d;
            if ($maxD === $minD) {
                $n['size'] = $sizeMin;
            } else {
                $n['size'] = (int) round(
                    $sizeMin + ($sizeMax - $sizeMin) * (($d - $minD) / max(1, $maxD - $minD))
                );
            }
        }
        unset($n);

        return $nodes;
    }

    /**
     * Build a legend map from node data keyed by human-readable group labels.
     *
     * @param array $nodes
     * @param array $groupLabels
     * @param array<string, string> $colorMap
     * @return array<string, array{color: string}>
     */
    private static function buildLegendMap(array $nodes, array $groupLabels, array $colorMap): array
    {
        $legendMap = [];
        foreach ($nodes as $n) {
            $key = $n['groupKey'] ?? '';
            if ($key === '' || isset($legendMap[$groupLabels[$key] ?? (string) $key])) {
                continue;
            }
            $legendMap[$groupLabels[$key] ?? (string) $key] = [
                'color' => $colorMap[$key] ?? self::colorFromKey($key),
            ];
        }
        return $legendMap;
    }
}
