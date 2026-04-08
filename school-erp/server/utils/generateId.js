/**
 * Automatic ID Generation Utility
 * Re-exports from idGenerator.js - do not duplicate logic here
 * to prevent ID sequence divergence and collisions.
 */
const idGenerator = require('./idGenerator');

// Re-export all functions from idGenerator.js
// Any new ID generation logic should be added to idGenerator.js only
module.exports = idGenerator;
