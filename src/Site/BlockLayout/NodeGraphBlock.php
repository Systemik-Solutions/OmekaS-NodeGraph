<?php

namespace NodeGraph\Site\BlockLayout;

use Laminas\Form\Element;
use Laminas\Form\Element\MultiCheckbox;
use Laminas\Form\Factory as FormFactory;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;
use NodeGraph\Form\Fieldset\GroupByFieldset;
use NodeGraph\Form\Fieldset\NodeColorsFieldset;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Form\Element\Query as QueryElement;
use Omeka\Site\BlockLayout\AbstractBlockLayout;

/**
 * Block layout for rendering an interactive node graph on an Omeka S site page.
 */
class NodeGraphBlock extends AbstractBlockLayout
{
    /**
     * @return string
     */
    public function getLabel(): string
    {
        return 'Node Graph';
    }

    /**
     * Build the admin-side configuration form for the block.
     *
     * @param PhpRenderer $view
     * @param mixed $site
     * @param mixed $page
     * @param mixed $block
     * @return string
     */
    public function form($view, $site, $page = null, $block = null)
    {
        $services = $view->getHelperPluginManager()->getServiceLocator();

        $form = new Form('node-graph');
        $form->add([
            'type' => QueryElement::class,
            'name' => 'o:block[__blockIndex__][o:data][query]',
            'options' => [
                'label' => $view->translate('Search Query'),
                'info'  => $view->translate('Attach items using this query. No query means all items.'),
                'resource_type' => 'items',
            ],
        ]);

        $formElementManager = $services->get('FormElementManager');

        $groupByFieldSet = $formElementManager->get(GroupByFieldset::class);
        $groupByFieldSet->setFormFactory(new FormFactory($formElementManager));
        $groupByFieldSet->setName('o:block[__blockIndex__][o:data][group_by_control]');
        $form->add($groupByFieldSet);

        $nodeColorsFs = $formElementManager->get(NodeColorsFieldset::class);
        $nodeColorsFs->setFormFactory(new FormFactory($formElementManager));
        $nodeColorsFs->setName('o:block[__blockIndex__][o:data][node_colors]');
        $form->add($nodeColorsFs);

        $relProps = $formElementManager->get(\Omeka\Form\Element\PropertySelect::class);
        $relProps->setName('o:block[__blockIndex__][o:data][relationships_properties]');
        $relProps->setOptions([
            'label'              => $view->translate('Selection of relationships (by property)'),
            'empty_option'       => '',
            'term_as_value'      => true,
            'use_hidden_element' => true,
        ]);
        $relProps->setAttributes([
            'multiple'         => true,
            'class'            => 'ng-relprops chosen-select',
            'data-placeholder' => $view->translate('Select one or more properties…'),
        ]);
        $form->add($relProps);

        $form->add([
            'type' => Element\Checkbox::class,
            'name' => 'o:block[__blockIndex__][o:data][exclude_without_relationships]',
            'options' => [
                'label'              => $view->translate('Exclude items without relationships'),
                'use_hidden_element' => true,
                'checked_value'      => '1',
                'unchecked_value'    => '0',
            ],
        ]);

        $form->add([
            'type' => Element\Checkbox::class,
            'name' => 'o:block[__blockIndex__][o:data][cache_result]',
            'options' => [
                'label'              => $view->translate('Cache result? (recommended for large datasets)'),
                'use_hidden_element' => true,
                'checked_value'      => '1',
                'unchecked_value'    => '0',
            ],
        ]);

        $form->add([
            'type' => MultiCheckbox::class,
            'name' => 'o:block[__blockIndex__][o:data][popup_content]',
            'options' => [
                'label' => $view->translate('Popup Content'),
                'value_options' => [
                    'title'         => $view->translate('Title'),
                    'thumbnail'     => $view->translate('Thumbnail'),
                    'metadata'      => $view->translate('Metadata'),
                    'relationships' => $view->translate('Relationships'),
                ],
            ],
            'attributes' => [],
        ]);

        $form->add([
            'type' => Element\Text::class,
            'name' => 'o:block[__blockIndex__][o:data][graph_width]',
            'options' => [
                'label' => $view->translate('Width'),
            ],
        ]);

        $form->add([
            'type' => Element\Text::class,
            'name' => 'o:block[__blockIndex__][o:data][graph_height]',
            'options' => [
                'label' => $view->translate('Height'),
            ],
        ]);

        $groupByData = (array) ($block ? $block->dataValue('group_by_control') : []);
        $nodeColors  = (array) ($block ? $block->dataValue('node_colors') : []);
        $form->setData([
            'o:block[__blockIndex__][o:data][query]'            => $block ? ($block->dataValue('query') ?? '') : '',
            'o:block[__blockIndex__][o:data][group_by_control]' => [
                'group-by-select'          => $groupByData['group-by-select'] ?? null,
                'group-by-property-select' => $groupByData['group-by-property-select'] ?? null,
            ],
            'o:block[__blockIndex__][o:data][node_colors]' => [
                'rows' => $nodeColors['rows'] ?? [],
            ],
            'o:block[__blockIndex__][o:data][relationships_properties]'      => $block ? (array) ($block->dataValue('relationships_properties') ?? []) : [],
            'o:block[__blockIndex__][o:data][exclude_without_relationships]' => $block ? ($block->dataValue('exclude_without_relationships') ?? '0') : '0',
            'o:block[__blockIndex__][o:data][cache_result]'  => $block ? ($block->dataValue('cache_result') ?? '0') : '0',
            'o:block[__blockIndex__][o:data][popup_content]' => $block
                ? (array) ($block->dataValue('popup_content') ?? ['title'])
                : ['title'],
            'o:block[__blockIndex__][o:data][graph_width]'  => $block
                ? ($block->dataValue('graph_width') ?? '100%')
                : '100%',
            'o:block[__blockIndex__][o:data][graph_height]' => $block
                ? ($block->dataValue('graph_height') ?? '600px')
                : '600px',
        ]);
        $form->prepare();

        return $view->formCollection($form)
            . $view->partial('node-graph/group-by-js')
            . $view->partial('node-graph/node-colors-js')
            . $view->partial('node-graph/node-relationships');
    }

    /**
     * Render the node graph block on the public site.
     *
     * Loads required JavaScript libraries only when this block is present on
     * the page, avoiding unnecessary script loading on every page.
     *
     * @param PhpRenderer $view
     * @param SitePageBlockRepresentation $block
     * @return string
     */
    public function render(PhpRenderer $view, SitePageBlockRepresentation $block): string
    {
        // Load JS libraries only when the block is actually rendered
        $view->headScript()
            ->appendFile('https://cdnjs.cloudflare.com/ajax/libs/graphology/0.24.0/graphology.umd.min.js')
            ->appendFile('https://cdn.jsdelivr.net/npm/graphology-library@0.8.0/dist/graphology-library.min.js')
            ->appendFile('https://cdnjs.cloudflare.com/ajax/libs/sigma.js/3.0.2/sigma.min.js');

        $services = $view->getHelperPluginManager()->getServiceLocator();
        $data = $block->data();

        $cacheEnabled = !empty($data['cache_result']);
        if (!$cacheEnabled) {
            return $view->partial('common/block/NodeGraphView', [
                'query' => $block->dataValue('query'),
                'group_by_control' => $block->dataValue('group_by_control'),
                'node_colors' => $block->dataValue('node_colors')['rows'],
                'relationships_properties' => $block->dataValue('relationships_properties'),
                'exclude_without_relationships' => $block->dataValue('exclude_without_relationships'),
                'popup_config' => $block->dataValue('popup_content'),
                'height' => $block->dataValue('graph_height'),
                'width' => $block->dataValue('graph_width'),
            ]);
        }

        $conn = $services->get('Omeka\Connection');
        $hash = sha1(json_encode($data));

        $row = $conn->fetchAssociative(
            'SELECT payload FROM nodegraph_cache WHERE block_id = ? AND hash = ?',
            [$block->id(), $hash],
        );

        if ($row) {
            $sigmaGraph = json_decode($row['payload'], true) ?: ['nodes' => [], 'edges' => [], 'legendMap' => []];
            return $view->partial('common/block/NodeGraphView', [
                'sigmaGraph' => $sigmaGraph,
                'width'  => $data['graph_width'] ?? '100%',
                'height' => $data['graph_height'] ?? '600px',
                'popup_config' => $block->dataValue('popup_content'),
            ]);
        }

        return '<div class="node-graph--building">'
            . $view->translate('Building network… please refresh in a moment.')
            . '</div>';
    }
}
