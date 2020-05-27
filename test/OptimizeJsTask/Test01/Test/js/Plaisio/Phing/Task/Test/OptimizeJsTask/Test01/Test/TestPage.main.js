/*global require */
/*global requirejs */

//----------------------------------------------------------------------------------------------------------------------
requirejs.config({
  baseUrl: '/js',
  paths: {
    'jquery': 'jquery/jquery'
  },
});

require(["Plaisio/Phing/Task/Test/OptimizeJsTask/Test01/Test/TestPage"]);

//----------------------------------------------------------------------------------------------------------------------
