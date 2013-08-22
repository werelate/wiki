/*==================================================
 *  Simile Exhibit Template Extension
 *==================================================
 */

Exhibit.TemplateExtension = {
    params: {
        bundle:     true
    } 
};

(function() {
    var javascriptFiles = [
        "template-view.js"
    ];
    var cssFiles = [
        "template-view.css"
    ];
        
    var url = SimileAjax.findScript(document, "/template-extension.js");
    if (url == null) {
        SimileAjax.Debug.exception(new Error("Failed to derive URL prefix for Simile Exhibit Template Extension code files"));
        return;
    }
    Exhibit.TemplateExtension.urlPrefix = url.substr(0, url.indexOf("template-extension.js"));
        
    var paramTypes = { bundle: Boolean };
    SimileAjax.parseURLParameters(url, Exhibit.TemplateExtension.params, paramTypes);
        
    var scriptURLs = [];
    var cssURLs = [];
        
    if (Exhibit.TemplateExtension.params.bundle) {
        scriptURLs.push(Exhibit.TemplateExtension.urlPrefix + "template-extension-bundle.js");
        cssURLs.push(Exhibit.TemplateExtension.urlPrefix + "template-extension-bundle.css");
    } else {
        SimileAjax.prefixURLs(scriptURLs, Exhibit.TemplateExtension.urlPrefix + "scripts/", javascriptFiles);
        SimileAjax.prefixURLs(cssURLs, Exhibit.TemplateExtension.urlPrefix + "styles/", cssFiles);
    }
    
    for (var i = 0; i < Exhibit.locales.length; i++) {
        scriptURLs.push(Exhibit.TemplateExtension.urlPrefix + "locales/" + Exhibit.locales[i] + "/template-locale.js");
    };
    
    SimileAjax.includeJavascriptFiles(document, "", scriptURLs);
    SimileAjax.includeCssFiles(document, "", cssURLs);
})();
