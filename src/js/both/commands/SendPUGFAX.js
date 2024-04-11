Ext.define('Tualo.cmp.mail.commands.SendPUGFax', {
    statics:{
      glyph: 'fax',
      title: 'FAX senden',
      tooltip: 'FAX senden'
    },
    extend: 'Ext.panel.Panel',
    alias: 'widget.sendpugfax',
    layout: 'fit',
    viewModel: {
        data: {
            messagetitle: '',
            messagetext: '',
        },
        stores: {
            attachments: {
                type: 'array',
                data: [],
                fields: [
                    {name: 'filename', type: 'string'},
                    {name: 'title', type: 'string'},
                    {name: 'size', type: 'string'},
                    {name: 'contenttype', type: 'string'},
                ]
            }
        }
    },
    items: [
      {
        xtype: 'form',
        itemId: 'faxform',
        bodyPadding: '25px',
        scrollable: 'y',
        defaults: {
            labelWidth: 150,
            anchor: '100%'
        },
        layout: {
            type: 'vbox',
            align: 'stretch'
        },
        disabled: true,
        items: [
            {
                xtype: 'textfield',
                name: 'from',
                fieldLabel: 'Von',
            },
            {
                xtype: 'textfield',
                name: 'to',
                fieldLabel: 'An'
            },/*
            {
                xtype: 'textfield',
                fieldLabel: 'Betreff',
                name: 'mailsubject'
            },
            {
                fieldLabel: 'Text',
                flex: 1,
                xtype: 'htmleditor',
                name: 'mailbody'
            },*/
            {
                xtype: 'tagfield',
                fieldLabel: 'Anhänge',
                name: 'attachments',
                bind: {
                    store: '{attachments}',
                },
                displayField: 'title',
                valueField: 'filename',
                queryMode: 'local',
                filterPickList: true
            }
        ]
      },{
        hidden: true,
        xtype: 'panel',
        itemId: 'messagepanel',
        layout:{
          type: 'vbox',
          align: 'center'
        },
        items: [
          {
            xtype: 'component',
            style:{
                backrgoundColor: '#8acdeb'
            },
            cls: 'lds-container',
            html: '<div id="container">'+
            '<div class="steam" id="steam1"> </div>'+
            '<div class="steam" id="steam2"> </div>'+
            '<div class="steam" id="steam3"> </div>'+
            '<div class="steam" id="steam4"> </div>'+
            '<div id="cup">'+
              '<div id="cup-body">'+
              '<div id="cup-shade"></div>'+
                '</div>'+
              '<div id="cup-handle"></div>'+
              '</div>'+
            '<div id="saucer"></div>'+
            '<div id="shadow"></div>'+
            '</div>'+
            '<div><h3>{messagetitle}</h3>'+
            '<span>{messagetext}</span></div>'
          }
        ]
      },{
        hidden: true,
        xtype: 'panel',
        itemId: 'waitpanel',
        layout:{
          type: 'vbox',
          align: 'center'
        },
        items: [
          {
            xtype: 'component',
            cls: 'lds-container',
            html: '<div class="lds-grid"><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div><div></div></div>'
            +'<div><h3>Die Mail wird gesendet</h3>'
            +'<span>Einen Moment bitte ...</span></div>'
          }
        ]
      }
    ],
    loadRecord: function(record,records,selectedrecords){
      this.record = record;
      this.records = records;
      this.selectedrecords = selectedrecords;

      console.log('disableNext');
      this.fireEvent ( 'disableNext' ,true);

      if(typeof this.record.get('__sendfax_info')=='undefined'){
        me.getComponent('syncform').hide();
        me.getComponent('waitpanel').hide();
        me.getComponent('messagepanel').show();
        return;
      }
      this.fillform();
    },
    fillform: async function(){
        let res = await fetch('./fax/renderpug',{
            method: 'put',
            body: JSON.stringify(this.record.getData())
        });
        res = await res.json();
        if (res.success){
            this.getViewModel().getStore('attachments').setData(res.attachments);

            this.getComponent('faxform').getForm().setValues(res.data);
            this.getComponent('faxform').enable();
            this.getComponent('waitpanel').hide();

            console.log('enableNext');
            this.fireEvent ( 'enableNext' ,true);
        }else{
          Ext.toast({
              html: res.msg,
              title: 'Fehler',
              align: 't',
              iconCls: 'fa fa-warning'
          });
        }

    },
    getNextText: function(){
      return 'Senden';
    },
    run: async function(){
      let me = this;
      let o = this.getComponent('faxform').getForm().getValues();
      o.mail_record = this.record.getData();
      
      me.getComponent('messagepanel').hide();
      me.getComponent('waitpanel').show();
      let res= await fetch('./fax/sendpug',{
        method: 'POST',
        body: JSON.stringify(o)
      });
      res = await res.json();
      if (res.success !== true){
        Ext.toast({
            html: res.msg,
            title: 'Fehler',
            align: 't',
            iconCls: 'fa fa-warning'
        });
      }
      return res;
    }
  });
