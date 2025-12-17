/**
 *
 *       the tools-for-your-hobby framework
 *       ----------------------------------
 *       https://www.tfyh.org
 *
 * Copyright  2018-2024  Martin Glade
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * A utility class to define the layut in containers. A container is a div,
 * using up the size of width/height + padding + margin + border (0px in all
 * containers). Containers are just boxes holding other boxes, their padding and
 * margin is always 0px. If a container hold no children boxes, it becomes a
 * tile, with a padding and margin are the same size - always and for all tiles
 * of the layout. Children containers build either a row or a column within the
 * parent container. Layouts shall make use of prer nesting. Note that margins
 * are not set via CSS as absolute positioning will anway overrule any margin
 * setting.
 */

class TfyhContainer
{

	static #profileName;
	static #layoutRootCfg;
	static #layoutRootContainer;
	static #containerIndex;
	static #buildCompleted = false;
	static #layoutType = "";
	static #uiSettingsCfg;
	
    #cfg;
    #ownId;
    #parentId;
    #horizontal;
    #isTile;
    #isRoot;
    #childrenWeights;
    #childrenWeightsSorted;
    #pPerWeight;
    
    #provider;
    #providerCfg;
    
    #mpP; // the margin + padding all around in pixels. Same in all
			// containers.

    #id; // the id of the container
    
    #topP;
    #leftP; // the top left corner in pixels of the container
    #weight; // the relative weight in units of the container
    
    #owP;
    #ohP; // the outer height and width in pixels of the container
    
    /**
	 * Bootstrap built of the layout according to the configuration. Calls the
	 * "callback" after completion
	 */
    static buildLayout(uiSettingsCfg) {
    	if (uiSettingsCfg) 
        	TfyhContainer.#uiSettingsCfg = uiSettingsCfg;
    	if (!TfyhContainer.#uiSettingsCfg)
    		return;
    	uiSettingsCfg = TfyhContainer.#uiSettingsCfg;
    	if (!uiSettingsCfg.get_child("preferences").has_child("profile")) {
    		alert("Missing UI profile entry in user preferences");
    		return;
    	}
    	this.#profileName = uiSettingsCfg.get_child("preferences").get_child("profile").get_value();
    	if (!uiSettingsCfg.get_child("profiles").has_child(this.#profileName)) {
    		alert("Missing UI profile '%1'", this.#profileName);
    		return;
    	}
    	let profileCfg = uiSettingsCfg.get_child("profiles").get_child(this.#profileName);
    	TfyhContainer.#layoutType = TfyhContainer.#getLayoutType();
    	if (!profileCfg.has_child(TfyhContainer.#layoutType)) {
    		alert("Missing layout type '%1' in UI profile", TfyhContainer.#layoutType);
    		return;
    	}
    	let layoutName = profileCfg.get_child(TfyhContainer.#layoutType).get_value();
    	if (!uiSettingsCfg.get_child("layouts").has_child(layoutName)) {
    		alert("Missing layout '%1' in UI profile", layoutName);
    		return;
    	}
     	// initialize the container index. It will be needed for resizing.
    	TfyhContainer.#containerIndex = {};
    	TfyhContainer.#layoutRootCfg = uiSettingsCfg.get_child("layouts").get_child(layoutName);
    	// create the root container
    	TfyhContainer.#layoutRootContainer = new TfyhContainer(TfyhContainer.#layoutRootCfg, null);
    	// Clear the root container contents to rebuild it.
    	$('#' + TfyhContainer.#layoutRootContainer.#ownId).html("");
    	// set the class of the root container to ensure absolute positioning.
    	$('#' + TfyhContainer.#layoutRootContainer.#ownId).addClass("tfyh-container");
    	// set the global margin an padding in pixels
    	let mpP = 2 + Math.floor((window.innerWidth + window.innerHeight) / 400);
    	// set its dimensions. It will not use the full size, but let a single
		// mpP spacer width all around
    	TfyhContainer.#layoutRootContainer.#setDimensions(window.innerWidth - 2 * mpP, 
    			window.innerHeight - 2 * mpP, mpP, mpP, mpP);
    	// create the layout itself via children drilldown
    	TfyhContainer.#layoutRootContainer.#createOrResizeChildren(true);
    	// add all contents providers
    	for (let containerId in TfyhContainer.#containerIndex) {
    		let container = TfyhContainer.#containerIndex[containerId];
    		container.#provider = (container.#providerCfg) ? new DilboUIprovider(container, container.#providerCfg) : null;
    	}
    	TfyhContainer.#buildCompleted = true;
    	return TfyhContainer.#layoutRootContainer;
    }
    
    /**
	 * Redo the full layout because of a screen resizing.
	 */
    static onResize() {
    	// no resize prior to initialization
    	if (!TfyhContainer.#buildCompleted)
    		return;
    	let newLayoutType = TfyhContainer.#getLayoutType();
    	if (newLayoutType != this.#layoutType) {
    		/*
			 * Due to the viewport size constraint (see <meta name="viewport"
			 * content="width=device-width, initial-scale=1">) the change of
			 * orientation needs to rigger a complete reload. If not a change
			 * from landscape to portrait will shrink the text size, or from
			 * portrait to landscape enlarge it.
			 */ 
    		window.location.reload();
    	}
    	// set the global margin an padding in pixels
    	let mpP = 2 + Math.floor((window.innerWidth + window.innerHeight) / 400);
    	// set its dimensions. It will not use the full size, but let a single
		// mpP spacer width all around
    	TfyhContainer.#layoutRootContainer.#setDimensions(window.innerWidth - 2 * mpP, 
    			window.innerHeight - 2 * mpP, mpP, mpP, mpP);
    	TfyhContainer.#layoutRootContainer.#createOrResizeChildren(false);
    }
    
    /**
	 * Set the layout type to be chosen based on the current window size.
	 */
    static #getLayoutType() {
    	if (window.innerWidth > window.innerHeight)
    		return "landscape";
    	else 
    		return "portrait";
    }
    
    /**
	 * Constructor. Shall never be called, use TfyhContainer.buildLayout()
	 * instead.
	 */
    constructor (containerCfg, parentId)
    {
    	this.#cfg = containerCfg;
    	// compile layout objects for children
    	this.#horizontal = this.#cfg.get_child("horizontal").get_value();
    	this.#getChildrenWeightObjects();
    	this.#isTile = (this.#childrenWeightsSorted.length == 0);
    	this.#isRoot = (parentId == null);
    	// identify the html ids for the own and parent container
   		this.#ownId = (this.#isRoot) ? "tfyhMain" : "tfyh-" + this.#cfg.get_uid();
   		TfyhContainer.#containerIndex[this.#ownId] = this;
   		this.#parentId = parentId;
   		// check the provider
   		let providerName = this.#cfg.get_child("provider").get_value();
   		this.#providerCfg = this.#cfg.get_by_path(".uiSettings.providers." + providerName);
    }
    
    /**
	 * simple getter
	 */
    getId() {
    	return this.#ownId;
    }
    
    /**
	 * simple getter
	 */
    getHorizontal() {
    	return this.#horizontal;
    }
    
    /**
	 * Create the div DOM element to represent the container.
	 */
    #createDiv() {
    	let div = $("#" + this.#ownId);
    	if (div.length == 0) {
			let parentHtmlSoFar = $("#" + this.#parentId).html();
			let htmlClass = (this.#isTile) ? "tfyh-tile" : "tfyh-container";
			let innerHtml = (this.#isTile) ? this.#cfg.get_uid() : "";
			$("#" + this.#parentId).html(parentHtmlSoFar 
					+ "<div class='" + htmlClass + "' id='" + this.#ownId + "'></div>");
    	}
    }

    /**
	 * Create or resize the children, size them and create their div
	 * DOM-elements. Builds the entire layout by recursive calling. Set create =
	 * false for resizing.
	 */
    #createOrResizeChildren(create) {
    	// set top left corner of first child. Always relative
    	let childTopP = 0;
    	let childLeftP = 0;
    	if (!create) {
    		// reset the sizeP property of all weightObjects to unhide on screen
			// enlargement
    		this.#getChildrenWeightObjects();
    		this.#setChildrenSizes();
    	}
    	// iterate through children
    	for (let weightObject of this.#childrenWeights) {
    		// always create the container, whether it will be displayed or not.
    		let childCfg = this.#cfg.get_child(weightObject.name);
    		let childContainer;
    		if (create) {
    			childContainer = new TfyhContainer(childCfg, this.#ownId);
    			childContainer.#createDiv();
    		} else 
    			childContainer = TfyhContainer.#containerIndex[weightObject.id];
    		let childOwP = (this.#horizontal) ? weightObject.sizeP : this.#owP;
    		let childOhP = (this.#horizontal) ? this.#ohP : weightObject.sizeP;
    		childContainer.#setDimensions(childOwP, childOhP, childTopP, childLeftP, this.#mpP);
    		// set scroll
   			$('#' + childContainer.#ownId).addClass((childContainer.#horizontal) ? "scrollh" : "scrollv");
    		// hide or unhide the container
    		let childDisplay = (((childOwP * childOhP) == 0) ? 'none' : 'block');
   			$('#' + childContainer.#ownId).css({ display : childDisplay });
    		// increment the top left corner
    		if (this.#horizontal) 
    			childLeftP += weightObject.sizeP;
    		else
    			childTopP += weightObject.sizeP;
    		// drilldown. Use the childContainer children to detect the
			// drilldown, because the childCfg children contain also properties
    		if (childContainer.#childrenWeights.length > 0)
    			childContainer.#createOrResizeChildren(create);
    	}
    }
    
    /**
	 * Create the this.#childrenWeightsSorted array which contains weight
	 * objects { name, weight, display } sorted by ascending weight.
	 */
    #getChildrenWeightObjects() {
    	// create all weight objects in the sequence as configured
    	this.#childrenWeights = [];
    	for (let cname in this.#cfg.get_children()) {
    		let child = this.#cfg.get_child(cname);
    		if (child.get_type().startsWith(".")) 
    			this.#childrenWeights.push( { 
    				name : cname, 
    				id : "tfyh-" + child.get_uid(), 
    				weight : child.get_child("weight").get_value(), 
    				sizeMinP : child.get_child("size_min").get_value(), 
    				sizeP : 1 } );  // any sizeP > 0 will do to ensure sizing.
    	}
    	// create a copy of the pointer array and sort it according to the
		// weight for later size-minimum violation filtering.
    	this.#childrenWeightsSorted = [];
    	for (let weightObject of this.#childrenWeights)
    		this.#childrenWeightsSorted.push(weightObject);
    	this.#childrenWeightsSorted.sort(function(a, b) {
    		return a.weight - b.weight;
    	});
    }
    
    /**
	 * Sum up all weights of the weight objects with display = true;
	 */
    #weightSum() {
    	let sumW = 0;
    	for (let weightObject of this.#childrenWeightsSorted)
    		if (weightObject.sizeP > 0) // count only those which will be
										// displayed
    			sumW += weightObject.weight;
    	return sumW;
    }
    
    /**
	 * iterate through all weightObjects and find the first one, which will have
	 * a size in pixel > 0, but smaller than its minimum size per pixel. For
	 * that weightObject set the size in pixel to 0 and return true. If no such
	 * object was found, return false.
	 */
    #weightFilter() {
    	for (let weightObject of this.#childrenWeightsSorted) {
    		if (weightObject.sizeP > 0) {
    			weightObject.sizeP = Math.floor(weightObject.weight * this.#pPerWeight);
    			if (weightObject.sizeP < weightObject.sizeMinP) {
    				weightObject.sizeP = 0;
    				return true;
    			}
    		}
    	}
    	return false;
    }
    
    /**
	 * Compute the dimensions for all children
	 */
    #setChildrenSizes() {
    	let sumP = (this.#horizontal) ? this.#owP : this.#ohP;
    	let sumW = this.#weightSum();
    	this.#pPerWeight = sumP / ((sumW == 0) ? 1 : sumW);
    	let filtered = true;
    	while (filtered) {
        	this.#pPerWeight = sumP / this.#weightSum();
        	filtered = this.#weightFilter();
    	}
    }
    
    /**
	 * Set the own dimensions and children sizes.
	 */
    #setDimensions(owP, ohP, topP, leftP, mpP) {
		this.#owP = owP;
		this.#ohP = ohP;
		this.#topP = topP;
		this.#leftP = leftP;
		this.#mpP = mpP;
		if (this.#isTile)
			$('#' + this.#ownId).css({
				// set origin
				top : (topP + mpP) + 'px',
				left : (leftP + mpP) + 'px',
				// set the padding
				padding : mpP + "px",
				// set width and height, based on the available space (the outer
				// width and height). This excludes the margin at both sides
				width : owP - (4 * mpP) + 'px',
				height : ohP - (4 * mpP) + 'px'
			});
		else 
			$('#' + this.#ownId).css({
				// set origin
				top : topP + 'px',
				left : leftP + 'px',
				// set the padding
				padding : "0px",
				// set width and height, based on the available space (the outer
				// width and height). This excludes the margin at both sides
				width : owP + 'px',
				height : ohP + 'px'
			});
    	this.#setChildrenSizes();
    }
    
    /*
	 * When the openFullscreen() function is executed, open the video in
	 * fullscreen. Note that we must include prefixes for different browsers, as
	 * they don't support the requestFullscreen method yet
	 */
    #requestFullscreen() {
    	const elem = document.getElementById(this.#ownId);
        if (elem.requestFullscreen) {
            elem.requestFullscreen();
        } else if (elem.mozRequestFullScreen) { /* Mozilla */
            elem.mozRequestFullScreen();
        } else if (elem.webkitRequestFullscreen) { /* Safari */
            elem.webkitRequestFullscreen();
        } else if (elem.msRequestFullscreen) { /* IE11 */
            elem.msRequestFullscreen();
        }
    }
}
