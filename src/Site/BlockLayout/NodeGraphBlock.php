<?php

namespace NodeGraph\Site\BlockLayout;


use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Form\Form;
use Omeka\Form\Element\Query as QueryElement;
use Laminas\Form\Factory as FormFactory;
use NodeGraph\Form\Fieldset\GroupByFieldset;
use NodeGraph\Form\Fieldset\NodeColorsFieldset;
use NodeGraph\Form\Fieldset\NodeIconsFieldset;

use Omeka\Form\Element\PropertySelect;
use Laminas\Form\Element;

use Laminas\Form\Element\MultiCheckbox;


class NodeGraphBlock extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Node Graph';
    }


    public function form($view, $site, $page = null, $block = null)
    {
        $services = $view->getHelperPluginManager()->getServiceLocator();

        // Query Filter
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

        $formElementManager      = $services->get('FormElementManager');

        // Group by Filter
        $groupByFieldSet = $formElementManager->get(GroupByFieldset::class);
        $groupByFieldSet->setFormFactory(new FormFactory($formElementManager));
        $groupByFieldSet->setName('o:block[__blockIndex__][o:data][group_by_control]');
        $form->add($groupByFieldSet);

        // Node Colors
        $nodeColorsFs = $formElementManager->get(NodeColorsFieldset::class);
        $nodeColorsFs->setFormFactory(new FormFactory($formElementManager));
        $nodeColorsFs->setName('o:block[__blockIndex__][o:data][node_colors]');
        $form->add($nodeColorsFs);

        // Node icons
        $nodeIconsFs = $formElementManager->get(NodeIconsFieldset::class);
        $nodeIconsFs->setFormFactory(new FormFactory($formElementManager));
        $nodeIconsFs->setName('o:block[__blockIndex__][o:data][node_icons]');
        $form->add($nodeIconsFs);

        // Selection of relationships (by property) — multiple PropertySelect
        $relProps = $formElementManager->get(\Omeka\Form\Element\PropertySelect::class);
        $relProps->setName('o:block[__blockIndex__][o:data][relationships_properties]');
        $relProps->setOptions([
            'label'              => $view->translate('Selection of relationships (by property)'),
            'empty_option'       => '',
            'term_as_value'      => true,
            'use_hidden_element' => true, // fine to keep
        ]);
        $relProps->setAttributes([
            'multiple'         => true,
            'class'            => 'ng-relprops chosen-select',
            'data-placeholder' => $view->translate('Select one or more properties…'),
        ]);
        $form->add($relProps);

        // Exclude items without relationships — checkbox (default unchecked)
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

        // Cache result - checkbox (default unchecked)
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

        // Popup Content (multi-checkbox)
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

        // Graph width / height (CSS values)
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

        // Preset form data
        $groupByData = (array) ($block ? $block->dataValue('group_by_control') : []);
        $nodeColors = (array) ($block ? $block->dataValue('node_colors') : []);
        $nodeIcons = (array) ($block ? $block->dataValue('node_icons') : []);
        $form->setData([
            'o:block[__blockIndex__][o:data][query]'            => $block ? ($block->dataValue('query') ?? '') : '',
            'o:block[__blockIndex__][o:data][group_by_control]' => [
                'group-by-select'          => $groupByData['group-by-select'] ?? null,
                'group-by-property-select' => $groupByData['group-by-property-select'] ?? null,
            ],
            'o:block[__blockIndex__][o:data][node_colors]' => [
                'rows' => $nodeColors['rows'] ?? [],
            ],
            'o:block[__blockIndex__][o:data][node_icons]' => [
                'rows' => $nodeIcons['rows'] ?? [],
            ],
            'o:block[__blockIndex__][o:data][relationships_properties]'       => $block ? (array) ($block->dataValue('relationships_properties') ?? []) : [],
            'o:block[__blockIndex__][o:data][exclude_without_relationships]'  => $block ? ($block->dataValue('exclude_without_relationships') ?? '0') : '0',
            'o:block[__blockIndex__][o:data][cache_result]' =>  $block ? ($block->dataValue('cache_result') ?? '0') : '0',
            'o:block[__blockIndex__][o:data][popup_content]' => $block
                ? (array) ($block->dataValue('popup_content') ?? ['title']) // default: Title checked
                : ['title'],

            'o:block[__blockIndex__][o:data][graph_width]'  => $block
                ? ($block->dataValue('graph_width')  ?? '100%')
                : '100%',

            'o:block[__blockIndex__][o:data][graph_height]' => $block
                ? ($block->dataValue('graph_height') ?? '600px')
                : '600px',
        ]);
        $form->prepare();

        return $view->formCollection($form)
            . $view->partial('node-graph/group-by-js')
            . $view->partial('node-graph/node-colors-js')
            . $view->partial('node-graph/node-icons-js')
            . $view->partial('node-graph/node-relationships');
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {

        $services = $view->getHelperPluginManager()->getServiceLocator();
        $data = $block->data();

        $cacheEnabled = !empty($data['cache_result']);
        if (!$cacheEnabled) {
            return $view->partial('common/block/NodeGraphView', [
                'query' => $block->dataValue('query'),
                'group_by_control' => $block->dataValue('group_by_control'),
                'node_colors' => $block->dataValue('node_colors')['rows'],
                'node_icons' => $block->dataValue('node_icons')['rows'],
                'relationships_properties' => $block->dataValue('relationships_properties'),
                'exclude_without_relationships' => $block->dataValue('exclude_without_relationships'),
                'popup_content' => $block->dataValue('popup_content'),
                'height' => $block->dataValue('graph_height'),
                'width' => $block->dataValue('graph_width'),
            ]);
        }

        $conn     = $services->get('Omeka\Connection');
        $hash = sha1(json_encode($data));

        $row  = $conn->fetchAssociative(
            'SELECT payload FROM nodegraph_cache WHERE block_id = ? AND hash = ?',
            [$block->id(), $hash]
        );

        if ($row) {
            // Cached
            $sigmaGraph = json_decode($row['payload'], true) ?: ['nodes' => [], 'edges' => [], 'legendMap' => []];
            return $view->partial('common/block/NodeGraphView', [
                'sigmaGraph' => $sigmaGraph,
                'width'  => $data['graph_width']  ?? '100%',
                'height' => $data['graph_height'] ?? '600px',
            ]);
        }

        return '<div class="node-graph--building">'
            . $view->translate('Building network… please refresh in a moment.')
            . '</div>';
    }
}
