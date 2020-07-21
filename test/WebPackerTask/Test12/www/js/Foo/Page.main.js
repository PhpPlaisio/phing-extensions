requirejs.config({
  baseUrl: '/js',
  paths: {
    'ace': 'ace/ace',
    'jquery': 'jquery/jquery',
    'js-cookie': 'js-cookie/js.cookie',
    'jquery-ui': 'jquery-ui/jquery-ui',
    'tinyMCE': 'tinymce/tinymce.min'
  },
  shim: {
    tinyMCE: {
      exports: 'tinyMCE',
      init: function () {
        'use strict';
        this.tinyMCE.DOM.events.domLoaded = true;
        return this.tinyMCE;
      }
    }
  }
});

require(['Foo/Page'], function (page) {
  page.init();
});

/* ID: Page.main.js */
