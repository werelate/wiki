/*==================================================
 *  Exhibit.TemplateView
 *==================================================
 */

Exhibit.TemplateView = function(containerElmt, uiContext) {
    this._div = containerElmt;
    this._uiContext = uiContext;
    
    this._settings = { tempate: null, lenses: null };

    var view = this;
    this._listener = { 
        onItemsChanged: function() {
            view._reconstruct(); 
        }
    };
    uiContext.getCollection().addListener(this._listener);
};

Exhibit.TemplateView._settingSpecs = {
    "showToolbox": { type: "boolean", defaultValue: true },
    "showSummary": { type: "boolean", defaultValue: true },
    "slotIDPrefix": { type: "text", defaultValue: '' },
    "slotKey":     { type: "text", defaultValue: null },
    "slotClass":   { type: "text", defaultValue: null }
};

Exhibit.TemplateView.create = function(configuration, containerElmt, uiContext) {
    var view = new Exhibit.TemplateView(
        containerElmt,
        Exhibit.UIContext.create(configuration, uiContext)
    );
    Exhibit.TemplateView._configure(view, configuration);
    
    view._initializeUI();
    return view;
};

Exhibit.TemplateView.createFromDOM = function(configElmt, containerElmt, uiContext) {
    var configuration = Exhibit.getConfigurationFromDOM(configElmt);
    
    uiContext = Exhibit.UIContext.createFromDOM(configElmt, uiContext);
    
    var view = new Exhibit.TemplateView(
        containerElmt != null ? containerElmt : configElmt, 
        uiContext
    );
    
    Exhibit.SettingsUtilities.collectSettingsFromDOM(configElmt, Exhibit.TemplateView._settingSpecs, view._settings);
    var s = Exhibit.getAttribute(configElmt, "template");
    if (s != null && s.length > 0) {
        var o = eval(s);
        if (typeof o == "object") {
            view._settings.template = o;
        }
    }
    s = Exhibit.getAttribute(configElmt, "lenses");
    if (s != null && s.length > 0) {
        o = eval(s);
        if (typeof o == "object") {
            view._settings.lenses = {};
            for (var lensType in o) {
            	var template = o[lensType];
            	var templateResult = SimileAjax.DOM.createDOMFromTemplate(template);
	            view._settings.lenses[lensType] = Exhibit.Lens.compileTemplate(templateResult.elmt, false, uiContext);
            }
        }
    }
    
    Exhibit.TemplateView._configure(view, configuration);
    view._initializeUI();
    return view;
};

Exhibit.TemplateView._configure = function(view, configuration) {
    Exhibit.SettingsUtilities.collectSettings(configuration, Exhibit.TemplateView._settingSpecs, view._settings);
    
    if ("template" in configuration) {
        view._settings.template = configuration.template;
    }
    if ("lenses" in configuration) {
        view._settings.lenses = configuration.lenses;
    }
};

Exhibit.TemplateView.prototype.dispose = function() {
    this._uiContext.getCollection().removeListener(this._listener);
    
    if (this._toolboxWidget) {
        this._toolboxWidget.dispose();
        this._toolboxWidget = null;
    }
    
    this._collectionSummaryWidget.dispose();
    this._collectionSummaryWidget = null;
    
    this._uiContext.dispose();
    this._uiContext = null;
    
    this._div.innerHTML = "";
    
    this._dom = null;
    this._div = null;
};

Exhibit.TemplateView.prototype._initializeUI = function() {
    var self = this;
    
    this._div.innerHTML = "";
    this._dom = Exhibit.TemplateView.createDom(this._div, this._settings.template);
    this._collectionSummaryWidget = Exhibit.CollectionSummaryWidget.create(
        {}, 
        this._dom.collectionSummaryDiv, 
        this._uiContext
    );
    if (this._settings.showToolbox) {
        this._toolboxWidget = Exhibit.ToolboxWidget.createFromDOM(this._div, this._div, this._uiContext);
        this._toolboxWidget.getGeneratedHTML = function() {
            return self._dom.bodyDiv.innerHTML;
        };
    }
    
    if (!this._settings.showSummary) {
        this._dom.collectionSummaryDiv.style.display = "none";
    }
    
    this._reconstruct();
};

Exhibit.TemplateView.prototype._reconstruct = function() {
    var self = this;
    var collection = this._uiContext.getCollection();
    var database = this._uiContext.getDatabase();
    
    var bodyDiv = this._dom.bodyDiv;

	 /*
	  * Clear the slots
	  */
	 $('.'+this._settings.slotClass).html('');
	 
    /*
     *  Get the current collection and check if it's empty
     */
    if (collection.countRestrictedItems() > 0) {
        var currentSet = collection.getRestrictedItems();
        /*
         *  Create item rows
         */
        currentSet.visit(function(itemID) {
            var slotKey = database.getObject(itemID, self._settings.slotKey);
				var node = document.getElementById(self._settings.slotIDPrefix+slotKey);
				var lensTemplate = self._settings.lenses[slotKey];
				if (!lensTemplate) {
					lensTemplate = self._settings.lenses['*'];
				}
				if (node != null && lensTemplate != null) {
					Exhibit.Lens.constructFromLensTemplate(itemID, lensTemplate, node, self._uiContext);
				}
         });
    }
};

Exhibit.TemplateView.createDom = function(div, template) {
    var l10n = Exhibit.TemplateView.l10n;
    var templateResult = SimileAjax.DOM.createDOMFromTemplate(template)
    var headerTemplate = {
        elmt:       div,
        className:  "exhibit-collectionView-header",
        children: [
            {   tag:    "div",
                field:  "collectionSummaryDiv"
            },
            {   elmt:    templateResult.elmt,
                field:  "bodyDiv"
            }
        ]
    };
    return SimileAjax.DOM.createDOMFromTemplate(headerTemplate);
};
