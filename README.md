# Node Graph

Node Graph is a high-performance Omeka S module for creating interactive node graph visualizations with flexible 
configuration for item selection, grouping, styling, and relationship mapping.

The Node Graph module leverages [sigma.js](https://www.sigmajs.org/) with WebGL rendering, offering significantly better 
performance than the D3-based [Data Visualization](https://omeka.org/s/modules/Datavis/) module when handling large-scale 
graphs. While D3 relies on SVG or Canvas, which can become sluggish with hundreds of nodes and edges, sigma.js offloads 
rendering to the GPU via WebGL, enabling smooth interaction and real-time updates even with thousands of elements. This 
makes Node Graph ideal for visualizing dense, complex networks in Omeka S without compromising responsiveness or user 
experience.

## Installation

- Download a ZIP package from one of the [releases](https://github.com/Systemik-Solutions/OmekaS-NodeGraph/releases).
- Extract the ZIP into the modules directory of your Omeka S installation.
- Rename the folder to 'NodeGraph'
- In the Omeka S admin panel, go to Modules and click Install next to “Node Graph”.

For more details, refer to the [Omeka S module installation guide](https://omeka.org/s/docs/user-manual/modules/).

## Usage

This module provides the "Node Graph" block which can be added to any site page. The following configuration options are 
available:

- Search Query: Specify an Omeka S search query to select items to include in the graph.
- Group by: Choose from "Resource Class", "Resource Template", or "Property value" to group nodes. Nodes in different groups 
  will have distinct colors. If not specified, all nodes will share the same color.
- Node colors: Select the node color for each group. If not specified, default colors will be used.
- Selection of relationships: Choose which properties to use for creating edges between nodes. You can select multiple 
properties. If no properties are selected, all relationship properties will be used.
- Exclude items without relationships: Optionally exclude items that do not have any relationships.
- Cache result: When it's enabled, a background job will be created to precompute the graph data for faster loading. This is 
  recommended for large graphs.
- Popup Content: Define the content to display in a popup when clicking on a node.
- Width: Set the width of the graph block (e.g., "100%", "600px").
- Height: Set the height of the graph block (e.g., "400px").

## Credits

Inspired by the [Data Visualization](https://omeka.org/s/modules/Datavis/) module for Omeka S.
