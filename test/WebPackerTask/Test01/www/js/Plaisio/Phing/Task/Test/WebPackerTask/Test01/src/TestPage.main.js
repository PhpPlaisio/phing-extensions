/* ID: TestPage.main.js */
/*global require */
/*global requirejs */

//----------------------------------------------------------------------------------------------------------------------
requirejs.config({
  baseUrl: '/js',
  paths: {
    'jquery': 'jquery/jquery'
  },
});

require(["Plaisio/Phing/Task/Test/WebPackerTask/Test01/Test/TestPage"]);

//----------------------------------------------------------------------------------------------------------------------
