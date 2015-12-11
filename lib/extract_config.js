/*jslint vars: true, indent: 2, maxlen: 120, evil: true */
/*global console */
/*global process */
/*global require */

var requirejs = {};
/**
 * Replace requirejs.config with a function that prints the configuration in proper JSON format.
 */
requirejs.config = function (config) {
  "use strict";

  console.log(JSON.stringify(config, null, 4));
};

/**
 * Evals a main requirejs configuration file.
 *
 * @param err
 * @param data
 * @returns {number}
 */
var eval_config = function (err, data) {
  "use strict";

  if (err) {
    return console.log(err);
  }

  eval(data);
};

// Load the fs module.
var fs = require('fs');

// Replace require with a stub that does nothing to prevent side effects in eval_config due to calls to require in the
// main requirejs configuration file.
var require = function () {
  "use strict";
  // Nothing to do.
};

// Parse the main configuration file.
fs.readFile(process.argv[2], 'utf-8', eval_config);

// @todo test for arguments and exits status.