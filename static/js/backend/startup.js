pimcore.registerNS("pimcore.layout.toolbar");
pimcore.registerNS("pimcore.plugin.luceneSearch");

pimcore.plugin.luceneSearch = Class.create(pimcore.plugin.admin,{

    isInitialized : false,

    getClassName: function (){
        return "pimcore.plugin.luceneSearch";
    },

    initialize: function() {
        pimcore.plugin.broker.registerPlugin(this);
    },

    uninstall: function(){
    },

    pimcoreReady: function (params, broker) {

        var user = pimcore.globalmanager.get("user");

        if(user.isAllowed("plugins")) {

            var luceneMenu = new Ext.Action({
                id: "lucenesearch",
                text: t('lucenesearch'),
                iconCls: "lucenesearch_icon",
                handler:this.openSettings
            });

            layoutToolbar.settingsMenu.add(luceneMenu);

        }

    },

    openSettings : function()
    {
        try {
            pimcore.globalmanager.get("luceneSearch_settings").activate();
        }
        catch (e) {
            pimcore.globalmanager.add("luceneSearch_settings", new pimcore.plugin.luceneSearch.settings());
        }
    }

});

new pimcore.plugin.luceneSearch();