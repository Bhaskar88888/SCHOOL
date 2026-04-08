/**
 * Pagination Helper Functions - Prisma Compatible
 */

/**
 * Calculate pagination data
 * @param {number} page - Current page (1-indexed)
 * @param {number} limit - Items per page
 * @param {number} total - Total number of items
 * @returns {Object} Pagination data
 */
function getPaginationData(page, limit, total) {
  const totalPages = Math.ceil(total / limit);
  const hasPrevPage = page > 1;
  const hasNextPage = page < totalPages;
  const prevPage = hasPrevPage ? page - 1 : null;
  const nextPage = hasNextPage ? page + 1 : null;

  return {
    currentPage: page,
    totalPages,
    totalItems: total,
    itemsPerPage: limit,
    hasPrevPage,
    hasNextPage,
    prevPage,
    nextPage,
    startIndex: (page - 1) * limit + 1,
    endIndex: Math.min(page * limit, total)
  };
}

/**
 * Apply pagination to Prisma query
 * @param {Object} model - Prisma model
 * @param {Object} where - Where clause
 * @param {number} page - Current page
 * @param {number} limit - Items per page
 * @param {Object} options - Additional options (orderBy, include, select)
 * @returns {Promise<Object>} Paginated results
 */
async function paginate(model, where = {}, page = 1, limit = 20, options = {}) {
  // Validate page and limit
  page = Math.max(1, parseInt(page) || 1);
  limit = Math.min(100, Math.max(1, parseInt(limit) || 20));

  const skip = (page - 1) * limit;
  const { orderBy = { createdAt: 'desc' }, include = null, select = null } = options;

  // Get total count
  const total = await model.count({ where });

  // Execute query with pagination
  const query = {
    where,
    skip,
    take: limit,
    orderBy: Array.isArray(orderBy) ? orderBy : [{ [Object.keys(orderBy)[0]]: orderBy[Object.keys(orderBy)[0]] }],
  };

  // Apply includes
  if (include) {
    query.include = include;
  }

  // Apply field selection
  if (select) {
    query.select = select;
  }

  const data = await model.findMany(query);
  const pagination = getPaginationData(page, limit, total);

  return {
    data,
    pagination,
    meta: {
      query: {
        page,
        limit,
        orderBy,
      }
    }
  };
}

/**
 * Parse pagination query parameters
 * @param {Object} queryParams - Express query params
 * @returns {Object} Parsed pagination options
 */
function parsePaginationParams(queryParams) {
  const page = Math.max(1, parseInt(queryParams.page) || 1);
  const limit = Math.min(100, Math.max(1, parseInt(queryParams.limit) || 20));
  const orderBy = queryParams.orderBy ? { [queryParams.orderBy]: queryParams.orderDir === 'asc' ? 'asc' : 'desc' } : { createdAt: 'desc' };
  const search = queryParams.search || '';

  return { page, limit, orderBy, search };
}

module.exports = {
  getPaginationData,
  paginate,
  parsePaginationParams
};
