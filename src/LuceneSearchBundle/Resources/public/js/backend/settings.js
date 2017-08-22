pimcore.registerNS('pimcore.plugin.luceneSearch.settings');
pimcore.plugin.luceneSearch.settings = Class.create({

    panel : false,

    task : null,

    loadMask : null,

    initialize: function () {

        this.getData();
    },

    getTabPanel: function () {

        var _ = this;

        this.loadMask = pimcore.globalmanager.get('loadingmask');

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
                        hideLabel: !this.getCrawlerState('enabled'),
                        hidden: !this.getCrawlerState('enabled'),
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
                                            url: '/admin/lucene-search/settings/crawler/start',
                                            method: 'get',
                                            success : function() {

                                                Ext.Ajax.request({
                                                    url: '/admin/lucene-search/settings/get/state',
                                                    method: 'get',
                                                    success: function (transport) {
                                                        var res = Ext.decode(transport.responseText);
                                                        Ext.getCmp('stateMessage').setValue(_.parseState(res.state));
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
                                            url:'/admin/lucene-search/settings/crawler/stop',
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
                                                                    url: '/admin/lucene-search/settings/crawler/stop?force=true',
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

                                                    url: '/admin/lucene-search/settings/get/state',
                                                    method: 'get',
                                                    success: function (transport) {

                                                        var res = Ext.decode(transport.responseText);
                                                        Ext.getCmp('stateMessage').setValue(_.parseState(res.state));
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
                buttons: [],
                items: [
                    {
                        xtype:'fieldset',
                        id: 'log_settings',
                        title:t('lucenesearch_log'),
                        collapsible: false,
                        autoHeight:true,
                        labelWidth: 100,
                        items :[
                            {
                                xtype:'textarea',
                                id: 'lucenesearch_log_data',
                                collapsible: false,
                                autoHeight:false,
                                submitValue : false,
                                height:400,
                                width:'100%',
                                value:''
                            }
                        ]
                    }
                ]
            });

            this.container.add([this.statusLayout,this.layout]);

            pimcore.layout.refresh();

            this.task = Ext.TaskManager.start({
                run: this.updateCrawlerState.bind(_),
                interval: 10000
            });

            Ext.Ajax.request({
                url: '/admin/lucene-search/settings/logs/get',
                success: function(response){
                    var data = Ext.decode(response.responseText);
                    Ext.getCmp('lucenesearch_log_data').setValue(data.logData);
                }
            });

        }

        return this.panel;
    },

    updateCrawlerState : function() {

        var _ = this;

        Ext.Ajax.request({
            url: '/admin/lucene-search/settings/get/state',
            method: 'get',
            success: function (response) {
                var res = Ext.decode(response.responseText);
                if(Ext.getCmp('stateMessage') !== undefined) {
                    Ext.getCmp('stateMessage').setValue(_.parseState(res.state));
                    Ext.getCmp('startFrontendCrawler').setDisabled(!res.canStart);
                    Ext.getCmp('stopFrontendCrawler').setDisabled(!res.canStop);
                }
            }
        });

    },

    getData: function () {

        Ext.Ajax.request({
            url: '/admin/lucene-search/settings/get/state',
            success: function (response) {

                this.data = Ext.decode(response.responseText);
                this.getTabPanel();

            }.bind(this)
        });

    },

    getCrawlerState: function(key) {
        var val = this.data[ key ];
        return this.parseState(val);
    },

    parseState: function(val) {
        return Ext.isArray(val) ? val.join('<br>') : val;
    }

});