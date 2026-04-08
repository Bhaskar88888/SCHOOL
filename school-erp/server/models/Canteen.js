const createModelAdapter = require('./_prismaModel');

module.exports = {
  CanteenItem: createModelAdapter('CanteenItem'),
  CanteenSale: createModelAdapter('CanteenSale'),
};
