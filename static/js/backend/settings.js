pimcore.registerNS('pimcore.plugin.luceneSearch.settings');
pimcore.plugin.luceneSearch.settings = Class.create({

    panel : false,

    task : null,

    loadMask : null,

    initialize: function () {

        this.getData();
    },

    getTabPanel: function () {

        this.loadMask = pimcore.globalmanager.get("loadingmask");

        if (!this.panel) {

            this.panel = Ext.create('Ext.panel.Panel', {

                id: 'lucenesearch_settings',
                title: t('lucenesearch_settings'),
                iconCls: 'lucenesearch_icon_settings',
                border: false,
                layout: 'fit',
                closable:true

            });

            var tabPanel = Ext.getCmp('pimcore_panel_tabs');
            tabPanel.add(this.panel);
            tabPanel.setActiveItem('lucenesearch_settings');

            this.panel.on('destroy', function () {

                pimcore.globalmanager.remove('lucenesearch_settings');
                Ext.TaskManager.destroy(this.task);

            }.bind(this));

            this.categoriesStore = new Ext.data.Store({
                fields: ['category'],
                data : this.getValue('frontend.categories')
            });

            this.tagStore = new Ext.data.JsonStore({
                fields: ['url'],
                data : this.getValue('frontend.urls')
            });

            this.allowedStore = new Ext.data.JsonStore({
                fields: ['regex'],
                data : this.getValue('frontend.validLinkRegexes')
            });

            this.forbiddenStore = new Ext.data.JsonStore({
                fields: ['regex'],
                data : this.getValue('frontend.invalidLinkRegexesEditable')

            });

            this.container = Ext.create('Ext.Container', {

                autoScroll: true,
                scrollable: true,
                layout: {
                    type: 'vbox',
                    align: 'stretch'
                }
            });

            this.panel.add(this.container);

            this.statusLayout = Ext.create('Ext.form.Panel', {

                id: 'LSstatusFormPanel',
                border: false,
                flex: 2,
                bodyStyle: 'background-color: #F7F7F7; padding:5px 10px;',
                autoScroll: false,

                items:[
                    {
                        xtype:'displayfield',
                        id : 'stateMessage',
                        fieldLabel: t('lucenesearch_status'),
                        value: this.getCrawlerState('state')
                    },
                    {   xtype: 'buttongroup',
                        fieldLabel: t('lucenesearch_frontend_crawler'),
                        hideLabel: !this.getValue('frontend.enabled'),
                        hidden: !this.getValue('frontend.enabled'),
                        columns:2,
                        bodyStyle: 'background-color: #fff;',
                        bodyBorder:false,
                        border: false,
                        frame:false,
                        items: [
                            {
                                xtype:'button',
                                hideLabel: true,
                                text: t('lucenesearch_start_crawler'),
                                id: 'startFrontendCrawler',
                                iconCls: 'pimcore_icon_apply',
                                disabled: !this.getCrawlerState('canStart'),
                                listeners:
                                {
                                    click: function(button, event)
                                    {
                                        button.setDisabled(true);

                                        Ext.Ajax.request({
                                            url: '/plugin/LuceneSearch/admin_Plugin/start-frontend-crawler',
                                            method: 'get',
                                            success : function() {

                                                Ext.Ajax.request({
                                                    url: '/plugin/LuceneSearch/admin_Plugin/get-state',
                                                    method: 'get',
                                                    success: function (transport) {
                                                        var res = Ext.decode(transport.responseText);
                                                        Ext.getCmp('stateMessage').setValue(res.message);
                                                    }
                                                });

                                            }
                                        });

                                    }
                                }

                            },
                            {
                                xtype:'button',
                                style: 'margin: 0 0 0 5px',
                                hideLabel: true,
                                text: t('lucenesearch_stop_crawler'),
                                id: 'stopFrontendCrawler',
                                iconCls: 'pimcore_icon_cancel',
                                disabled: !this.getCrawlerState('canStop'),
                                listeners:
                                {
                                    click: function(button, event)
                                    {
                                        var _self = this;

                                        _self.loadMask.show();
                                        button.setDisabled(true);

                                        Ext.Ajax.request({
                                            url:'/plugin/LuceneSearch/admin_Plugin/stop-frontend-crawler',
                                            method: 'get',
                                            success: function(transport){

                                                var res = Ext.decode(transport.responseText);

                                                if( res.success != true) {

                                                    Ext.MessageBox.show({
                                                        title: t('lucenesearch_frontend_crawler_stop_failed'),
                                                        msg: t('lucenesearch_frontend_crawler_stop_failed_description'),
                                                        buttons: Ext.Msg.OKCANCEL,
                                                        icon: Ext.MessageBox.QUESTION,
                                                        fn: function(v,s,o){
                                                            if(o[0]=='ok'){
                                                                Ext.Ajax.request({
                                                                    url: '/plugin/LuceneSearch/admin_Plugin/stop-frontend-crawler?force=true',
                                                                    method: 'get'
                                                                } );
                                                            }
                                                        }
                                                    });

                                                } else {

                                                    button.setDisabled(false);
                                                    _self.loadMask.hide();
                                                }

                                                Ext.Ajax.request({

                                                    url: '/plugin/LuceneSearch/admin_Plugin/get-state',
                                                    method: 'get',
                                                    success: function (transport) {

                                                        var res = Ext.decode(transport.responseText);
                                                        Ext.getCmp('stateMessage').setValue(res.message);
                                                        _self.loadMask.hide();

                                                    }
                                                });
                                            }
                                        });

                                    }.bind(this)
                                }

                            }
                        ]
                    }

                ]
            });

            this.layout = Ext.create('Ext.form.Panel', {

                bodyStyle:'padding:20px 5px 20px 5px;',
                border: false,
                flex: 5,
                id:'LSsettingsFormPanel',
                autoScroll: true,
                fieldDefaults: {
                    labelWidth: 250
                },
                buttons: [
                    {
                        text: t('lucenesearch_settings_save'),
                        handler: this.save.bind(this),
                        iconCls: 'pimcore_icon_apply'
                    }
                ],
                items: [
                    {
                        xtype:'fieldset',
                        id: 'basic_settings',
                        title:t('lucenesearch_basic'),
                        collapsible: false,
                        autoHeight:true,
                        labelWidth: 100,
                        items :[
                            {
                                xtype:'displayfield',
                                value: t('lucenesearch_frontend_enabled_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'checkbox',
                                autoHeight:true,
                                boxLabel: t('lucenesearch_frontend_enabled'),
                                name: 'search.frontend.enabled',
                                checked: this.getValue('frontend.enabled'),
                                inputValue: '1',
                                ctCls: 'x-form-item',
                                listeners:{
                                    change: function(checkbox, checked) {
                                        if (checked) {
                                            Ext.getCmp('ls_frontend_settings').show();
                                        } else {
                                            Ext.getCmp('ls_frontend_settings').hide();

                                        }
                                    }
                                }
                            }
                        ]
                    },
                    {
                        xtype:'fieldset',
                        id: 'ls_frontend_settings',
                        title: t('lucenesearch_frontend_settings'),
                        collapsible: false,
                        autoHeight:true,
                        labelWidth: 100,
                        hidden: !this.getValue('frontend.enabled'),
                        defaultType: 'combobox',
                        defaults: {
                            allowBlank:true,
                            msgTarget: 'under',
                            allowAddNewData: true,
                            queryDelay: 0,
                            triggerAction: 'all',
                            extraItemCls: 'x-tag',
                            resizable: true,
                            mode: 'remote',
                            anchor:'100%',
                            queryValuesDelimiter:'__#--#__',
                            minChars: 2
                        },
                        items :[
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_ignoreLanguage_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'checkbox',
                                fieldLabel: t('language'),
                                autoHeight:true,
                                boxLabel: t('lucenesearch_frontend_ignoreLanguage'),
                                name: 'frontend.ignoreLanguage',
                                checked: this.getValue('frontend.ignoreLanguage'),
                                inputValue: '1'
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_ignoreCountry_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'checkbox',
                                fieldLabel: t('country'),
                                autoHeight:true,
                                boxLabel: t('lucenesearch_frontend_ignoreCountry'),
                                name: 'frontend.ignoreCountry',
                                checked: this.getValue('frontend.ignoreCountry'),
                                inputValue: '1'
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_ignoreRestriction_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'checkbox',
                                fieldLabel: t('lucenesearch_restriction'),
                                autoHeight:true,
                                boxLabel: t('lucenesearch_frontend_ignoreRestriction'),
                                name: 'frontend.ignoreRestriction',
                                checked: this.getValue('frontend.ignoreRestriction'),
                                inputValue: '1',
                                listeners:{
                                    change: function(checkbox, checked) {
                                        if (checked) {
                                            Ext.getCmp('lucenesearch_auth').hide();
                                        } else {
                                            Ext.getCmp('lucenesearch_auth').show();

                                        }
                                    }
                                }
                            },

                            {
                                xtype:'fieldset',
                                id: 'lucenesearch_auth',
                                title:t('lucenesearch_restriction_settings'),
                                collapsible: false,
                                autoHeight:true,
                                labelWidth: 100,
                                items :[
                                    {
                                        xtype:'displayfield',
                                        value: t('lucenesearch_use_auth_enabled_description'),
                                        cls: 'description'
                                    },

                                    {
                                        xtype:'textfield',
                                        id:'lucenesearch_restriction_static_class',
                                        fieldLabel: t('lucenesearch_restriction_static_class'),
                                        name: 'frontend.restriction.class',
                                        collapsible: false,
                                        autoHeight:true,
                                        value: this.getValue('frontend.restriction.class')
                                    },
                                    {
                                        xtype:'textfield',
                                        id:'lucenesearch_restriction_method',
                                        fieldLabel: t('lucenesearch_restriction_method'),
                                        name: 'frontend.restriction.method',
                                        collapsible: false,
                                        autoHeight:true,
                                        value: this.getValue('frontend.restriction.method')
                                    },

                                    {
                                        xtype:'checkbox',
                                        autoHeight:true,
                                        boxLabel: t('lucenesearch_use_auth'),
                                        name: 'frontend.auth.useAuth',
                                        checked: this.getValue('frontend.auth.useAuth'),
                                        inputValue: '1',
                                        ctCls: 'x-form-item',
                                        listeners:{
                                            change: function(checkbox, checked) {
                                                if (checked) {
                                                    Ext.getCmp('lucenesearch_auth_username').enable();
                                                    Ext.getCmp('lucenesearch_auth_password').enable();
                                                } else {
                                                    Ext.getCmp('lucenesearch_auth_username').disable();
                                                    Ext.getCmp('lucenesearch_auth_password').disable();

                                                }
                                            }
                                        }
                                    },
                                    {
                                        xtype:'textfield',
                                        id:'lucenesearch_auth_username',
                                        fieldLabel: t('lucenesearch_auth_username'),
                                        name: 'frontend.auth.username',
                                        collapsible: false,
                                        autoHeight:true,
                                        value: this.getValue('frontend.auth.username')
                                    },
                                    {
                                        xtype:'textfield',
                                        id:'lucenesearch_auth_password',
                                        fieldLabel: t('lucenesearch_auth_password'),
                                        name: 'frontend.auth.password',
                                        collapsible: false,
                                        autoHeight:true,
                                        value: this.getValue('frontend.auth.password')
                                    }

                                ]
                            },

                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_fuzzysearch_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'checkbox',
                                fieldLabel: t('lucenesearch_frontend_fuzzysearch'),
                                autoHeight:true,
                                boxLabel: t('lucenesearch_search_suggestions'),
                                name: 'frontend.fuzzySearch',
                                checked: this.getValue('frontend.fuzzySearch'),
                                inputValue: '1'
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_ownhostonly_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'checkbox',
                                fieldLabel: t('lucenesearch_subdomains'),
                                autoHeight:true,
                                boxLabel: t('lucenesearch_frontend_ownhostonly'),
                                name: 'frontend.ownHostOnly',
                                checked:this.getValue('frontend.ownHostOnly'),
                                inputValue: '1'
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_crawler_maxlinkdepth_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'textfield',
                                fieldLabel: t('lucenesearch_frontend_crawler_maxlinkdepth'),
                                name: 'frontend.crawler.maxLinkDepth',
                                collapsible: false,
                                autoHeight:true,
                                value:this.getValue('frontend.crawler.maxLinkDepth')
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_crawler_maxdownloadlimit_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'textfield',
                                fieldLabel: t('lucenesearch_frontend_crawler_maxdownloadlimit'),
                                name: 'frontend.crawler.maxDownloadLimit',
                                collapsible: false,
                                autoHeight:true,
                                value:this.getValue('frontend.crawler.maxDownloadLimit')
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_content_indicator_description'),
                                cls: 'description'
                            },
                            {
                                xtype:'textfield',
                                fieldLabel: t('lucenesearch_frontend_content_start_indicator'),
                                name: 'frontend.crawler.contentStartIndicator',
                                collapsible: false,
                                autoHeight:true,
                                value: this.getValue('frontend.crawler.contentStartIndicator')
                            },
                            {
                                xtype:'textfield',
                                fieldLabel: t('lucenesearch_frontend_content_end_indicator'),
                                name: 'frontend.crawler.contentEndIndicator',
                                collapsible: false,
                                autoHeight:true,
                                value: this.getValue('frontend.crawler.contentEndIndicator')
                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_allowedschemes_description'),
                                cls: 'description'
                            },
                            {
                                xtype: 'tagfield',
                                fieldLabel: t('lucenesearch_frontend_allowedschemes'),
                                name: 'frontend.allowedSchemes',

                                store: this.categoriesStore,
                                value : this.getValue('frontend.allowedSchemes'),
                                valueField: 'allowedschemes',
                                displayField: 'allowedschemes',
                                stacked : true,
                                hideTrigger: true,
                                expand: Ext.emptyFn,
                                forceSelection: false,
                                createNewOnEnter: true,
                                queryMode: 'local',
                                componentCls: 'superselect-no-drop-down'

                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_categories_description'),
                                cls: 'description'
                            },
                            {
                                xtype: 'tagfield',
                                fieldLabel: t('lucenesearch_frontend_categories'),
                                //emptyText: t('lucenesearch_frontend_categories_empty_text'),
                                name: 'frontend.categories',

                                store: this.categoriesStore,
                                value : this.getValue('frontend.categories'),
                                valueField: 'category',
                                displayField: 'category',
                                stacked : true,
                                hideTrigger: true,
                                expand: Ext.emptyFn,
                                forceSelection: false,
                                createNewOnEnter: true,
                                queryMode: 'local',
                                componentCls: 'superselect-no-drop-down'

                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_settings_urls_description'),
                                cls: 'description'
                            },
                            {
                                xtype: 'tagfield',
                                fieldLabel: t('lucenesearch_frontend_settings_urls') + ' *',
                                //emptyText: t('lucenesearch_frontend_settings_empty_text'),
                                name: 'frontend.urls',
                                value: this.getValue('frontend.urls'),
                                store: this.tagStore,
                                displayField: 'url',
                                valueField: 'url',
                                stacked : true,
                                hideTrigger: true,
                                expand: Ext.emptyFn,
                                forceSelection: false,
                                createNewOnEnter: true,
                                queryMode: 'local',
                                componentCls: 'superselect-no-drop-down'

                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_allowed_description'),
                                cls: 'description'
                            },
                            {
                                xtype: 'tagfield',
                                fieldLabel: t('lucenesearch_frontend_allowed') + ' *',
                                //emptyText: t('lucenesearch_frontend_allowed_empty_text'),
                                name: 'frontend.validLinkRegexes',
                                store: this.allowedStore,
                                displayField: 'regex',
                                valueField: 'regex',
                                value: this.getValue('frontend.validLinkRegexes'),
                                stacked : true,
                                hideTrigger: true,
                                expand: Ext.emptyFn,
                                forceSelection: false,
                                createNewOnEnter: true,
                                queryMode: 'local',
                                componentCls: 'superselect-no-drop-down'

                            },
                            {
                                xtype:'displayfield',
                                value:t('lucenesearch_frontend_forbidden_description'),
                                cls: 'description'
                            },
                            {
                                xtype: 'tagfield',
                                fieldLabel: t('lucenesearch_frontend_forbidden'),
                                //emptyText: t('lucenesearch_frontend_forbidden_empty_text'),
                                name: 'frontend.invalidLinkRegexesEditable',
                                store: this.forbiddenStore,
                                displayField: 'regex',
                                valueField: 'regex',
                                value: this.getValue('frontend.invalidLinkRegexesEditable'),
                                stacked : true,
                                hideTrigger: true,
                                expand: Ext.emptyFn,
                                forceSelection: false,
                                createNewOnEnter: true,
                                queryMode: 'local',
                                componentCls: 'superselect-no-drop-down'

                            },
                            {
                                xtype:'displayfield',
                                value:'*) ' + t('lucenesearch_frontend_mandatory_fields'),
                                cls: 'mandatory_hint'
                            }
                        ]}
                ]
            });

            this.container.add([this.statusLayout,this.layout]);

            pimcore.layout.refresh();

            this.task = Ext.TaskManager.start({
                run: this.updateCrawlerState,
                interval: 10000
            });

        }

        return this.panel;
    },

    updateCrawlerState : function() {

        Ext.Ajax.request({
            url: '/plugin/LuceneSearch/admin_Plugin/get-state',
            method: 'get',
            success: function (response) {

                var res = Ext.decode( response.responseText);
                Ext.getCmp('stateMessage').setValue(res.message);
                Ext.getCmp('startFrontendCrawler').setDisabled(res.frontendButtonDisabled);
                Ext.getCmp('stopFrontendCrawler').setDisabled(res.frontendStopButtonDisabled);

            }
        });

    },

    activate: function () {

        var tabPanel = Ext.getCmp('pimcore_panel_tabs');
        tabPanel.setActiveItem('lucenesearch_settings')

    },

    getData: function () {

        Ext.Ajax.request({
            url: '/plugin/LuceneSearch/admin_plugin/get-settings',
            success: function (response) {

                this.data = Ext.decode(response.responseText);
                this.getTabPanel();

            }.bind(this)
        });

    },

    getValue: function (key) {

        var current = null;

        if(this.data.values.hasOwnProperty(key)) {
            current = this.data.values[key];
        }

        if (typeof current != 'function') {
            return current;
        }

        return null;
    },

    getCrawlerState: function (key) {

        var current = null;

        if(this.data.crawler.hasOwnProperty(key)) {
            current = this.data.crawler[key];
        }

        return current;
    },

    save: function () {

        var values = this.layout.getForm().getFieldValues();

        Ext.Ajax.request({
            url: '/plugin/LuceneSearch/admin_plugin/set-setting',
            method: 'post',
            params: {
                data: Ext.encode(values)
            },
            success: function (response) {
                try {
                    var res = Ext.decode(response.responseText);
                    if (res.success) {
                        pimcore.helpers.showNotification(t('success'), t('lucenesearch_settings_save_success'), 'success');
                    } else {
                        pimcore.helpers.showNotification(t('error'), t('lucenesearch_settings_save_error'),
                            'error', t(res.message));
                    }
                } catch(e) {
                    pimcore.helpers.showNotification(t('error'), t('lucenesearch_settings_save_error'), 'error');
                }
            }
        });
    }
});