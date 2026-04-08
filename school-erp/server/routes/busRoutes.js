const express = require('express');
const router = express.Router();
const prisma = require('../config/prisma');
const auth = require('../middleware/auth');
const roleCheck = require('../middleware/roleCheck');
const { withLegacyId, toLegacyUser } = require('../utils/prismaCompat');
const { parsePaginationParams, getPaginationData } = require('../utils/pagination');

function toLegacyBusStop(stop) {
  return stop ? withLegacyId(stop) : null;
}

function toLegacyBusRoute(route) {
  if (!route) return null;
  return withLegacyId({
    ...route,
    vehicleId: route.vehicle ? withLegacyId(route.vehicle) : route.vehicleId,
    driverId: route.driver ? toLegacyUser(route.driver) : route.driverId,
    conductorId: route.conductor ? toLegacyUser(route.conductor) : route.conductorId,
    stops: Array.isArray(route.stops) ? route.stops.map(toLegacyBusStop) : [],
  });
}

// POST /api/bus-routes - Create bus route
router.post('/', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const {
      routeName,
      routeCode,
      routeNumber,
      vehicleId,
      driverId,
      conductorId,
      stops,
      totalDistance,
      totalDuration,
      departureTime,
      returnTime,
      vehicleType,
      capacity,
      feePerStudent,
      description,
      notes,
    } = req.body;

    const route = await prisma.busRoute.create({
      data: {
        routeName,
        routeCode,
        routeNumber,
        vehicleId: vehicleId || null,
        driverId: driverId || null,
        conductorId: conductorId || null,
        totalDistance: Number(totalDistance || 0),
        totalDuration: Number(totalDuration || 0),
        departureTime,
        returnTime: returnTime || null,
        vehicleType: vehicleType || 'Non-AC Bus',
        capacity: Number(capacity || 50),
        feePerStudent: Number(feePerStudent || 0),
        description: description || '',
        notes: notes || '',
        stops: stops && stops.length ? {
          create: stops.map((stop, index) => ({
            stopName: stop.stopName,
            stopCode: stop.stopCode || null,
            sequence: stop.sequence || index + 1,
            arrivalTime: stop.arrivalTime || null,
            departureTime: stop.departureTime || null,
            distance: Number(stop.distance || 0),
            latitude: stop.latitude || null,
            longitude: stop.longitude || null,
            landmark: stop.landmark || null,
            isPickup: stop.isPickup !== false,
            isDrop: stop.isDrop !== false,
          })),
        } : undefined,
      },
      include: {
        vehicle: { select: { id: true, busNumber: true } },
        driver: { select: { id: true, name: true, phone: true } },
        conductor: { select: { id: true, name: true, phone: true } },
        stops: { orderBy: { sequence: 'asc' } },
      },
    });

    res.status(201).json({ msg: 'Bus route created successfully', route: toLegacyBusRoute(route) });
  } catch (err) {
    console.error('Route creation error:', err);
    res.status(500).json({ msg: 'Failed to create bus route', error: err.message });
  }
});

// GET /api/bus-routes - Get all bus routes
router.get('/', auth, async (req, res) => {
  try {
    const { isActive, vehicleType } = req.query;
    const where = {};
    if (isActive !== undefined) where.isActive = isActive === 'true';
    if (vehicleType) where.vehicleType = vehicleType;

    const { page, limit } = parsePaginationParams(req.query);
    const skip = (page - 1) * limit;

    const [total, routes] = await Promise.all([
      prisma.busRoute.count({ where }),
      prisma.busRoute.findMany({
        where,
        include: {
          vehicle: { select: { id: true, busNumber: true, capacity: true } },
          driver: { select: { id: true, name: true, phone: true } },
          conductor: { select: { id: true, name: true, phone: true } },
          stops: { orderBy: { sequence: 'asc' } },
        },
        orderBy: { routeNumber: 'asc' },
        skip,
        take: limit,
      }),
    ]);

    res.json({
      data: routes.map(toLegacyBusRoute),
      pagination: getPaginationData(page, limit, total),
      meta: { query: { page, limit, sort: 'routeNumber' } },
    });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to fetch routes', error: err.message });
  }
});

// GET /api/bus-routes/stats/summary - Get route statistics
router.get('/stats/summary', auth, async (_req, res) => {
  try {
    const [
      totalRoutes,
      activeRoutes,
      totalStops,
      totalDistanceAgg,
      routesByType,
    ] = await Promise.all([
      prisma.busRoute.count(),
      prisma.busRoute.count({ where: { isActive: true } }),
      prisma.busStop.count(),
      prisma.busRoute.aggregate({ _sum: { totalDistance: true } }),
      prisma.busRoute.groupBy({
        by: ['vehicleType'],
        _count: { _all: true },
      }),
    ]);

    res.json({
      totalRoutes,
      activeRoutes,
      inactiveRoutes: totalRoutes - activeRoutes,
      totalStops,
      totalDistance: totalDistanceAgg._sum.totalDistance || 0,
      routesByType: routesByType.map(item => ({ _id: item.vehicleType, count: item._count._all })),
    });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to get statistics', error: err.message });
  }
});

// GET /api/bus-routes/map/:id - Get route map data
router.get('/map/:id', auth, async (req, res) => {
  try {
    const route = await prisma.busRoute.findUnique({
      where: { id: req.params.id },
      include: { stops: { orderBy: { sequence: 'asc' } } },
    });

    if (!route) {
      return res.status(404).json({ msg: 'Route not found' });
    }

    const mapData = {
      routeName: route.routeName,
      routeCode: route.routeCode,
      stops: route.stops.map(stop => ({
        name: stop.stopName,
        sequence: stop.sequence,
        arrivalTime: stop.arrivalTime,
        departureTime: stop.departureTime,
        landmark: stop.landmark,
        coordinates: stop.latitude && stop.longitude ? {
          lat: stop.latitude,
          lng: stop.longitude,
        } : null,
      })),
    };

    res.json(mapData);
  } catch (err) {
    res.status(500).json({ msg: 'Failed to get map data', error: err.message });
  }
});

// GET /api/bus-routes/:id - Get single bus route with details
router.get('/:id', auth, async (req, res) => {
  try {
    const route = await prisma.busRoute.findUnique({
      where: { id: req.params.id },
      include: {
        vehicle: { select: { id: true, busNumber: true, capacity: true, vehicleType: true } },
        driver: { select: { id: true, name: true, phone: true, email: true } },
        conductor: { select: { id: true, name: true, phone: true } },
        stops: { orderBy: { sequence: 'asc' } },
      },
    });

    if (!route) {
      return res.status(404).json({ msg: 'Route not found' });
    }

    const studentsOnRoute = route.activeStudents || 0;

    res.json({
      route: toLegacyBusRoute(route),
      studentsOnRoute,
    });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to fetch route', error: err.message });
  }
});

// PUT /api/bus-routes/:id - Update bus route
router.put('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const route = await prisma.busRoute.update({
      where: { id: req.params.id },
      data: req.body,
      include: {
        vehicle: { select: { id: true, busNumber: true } },
        driver: { select: { id: true, name: true, phone: true } },
        conductor: { select: { id: true, name: true, phone: true } },
        stops: { orderBy: { sequence: 'asc' } },
      },
    });

    res.json({ msg: 'Bus route updated successfully', route: toLegacyBusRoute(route) });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to update route', error: err.message });
  }
});

// DELETE /api/bus-routes/:id - Delete bus route
router.delete('/:id', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    await prisma.busRoute.delete({ where: { id: req.params.id } });
    res.json({ msg: 'Bus route deleted successfully' });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to delete route', error: err.message });
  }
});

// POST /api/bus-routes/:id/stops - Add stop to route
router.post('/:id/stops', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { stops } = req.body;
    const route = await prisma.busRoute.findUnique({
      where: { id: req.params.id },
      include: { stops: { orderBy: { sequence: 'asc' } } },
    });

    if (!route) {
      return res.status(404).json({ msg: 'Route not found' });
    }

    const startIndex = route.stops.length + 1;
    await prisma.busStop.createMany({
      data: (stops || []).map((stop, index) => ({
        routeId: route.id,
        stopName: stop.stopName,
        stopCode: stop.stopCode || null,
        sequence: stop.sequence || startIndex + index,
        arrivalTime: stop.arrivalTime || null,
        departureTime: stop.departureTime || null,
        distance: Number(stop.distance || 0),
        latitude: stop.latitude || null,
        longitude: stop.longitude || null,
        landmark: stop.landmark || null,
        isPickup: stop.isPickup !== false,
        isDrop: stop.isDrop !== false,
      })),
    });

    const totalDistance = route.stops.reduce((sum, stop) => sum + (stop.distance || 0), 0)
      + (stops || []).reduce((sum, stop) => sum + (Number(stop.distance || 0)), 0);

    const updated = await prisma.busRoute.update({
      where: { id: route.id },
      data: { totalDistance },
      include: {
        vehicle: { select: { id: true, busNumber: true } },
        driver: { select: { id: true, name: true, phone: true } },
        stops: { orderBy: { sequence: 'asc' } },
      },
    });

    res.json({ msg: 'Stops added successfully', route: toLegacyBusRoute(updated) });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to add stops', error: err.message });
  }
});

// PUT /api/bus-routes/:id/stops/:stopIndex - Update stop
router.put('/:id/stops/:stopIndex', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { stopIndex } = req.params;
    const route = await prisma.busRoute.findUnique({
      where: { id: req.params.id },
      include: { stops: { orderBy: { sequence: 'asc' } } },
    });

    if (!route) {
      return res.status(404).json({ msg: 'Route not found' });
    }

    const stop = route.stops[Number(stopIndex)];
    if (!stop) {
      return res.status(404).json({ msg: 'Stop not found' });
    }

    await prisma.busStop.update({
      where: { id: stop.id },
      data: req.body,
    });

    const updated = await prisma.busRoute.findUnique({
      where: { id: route.id },
      include: { stops: { orderBy: { sequence: 'asc' } } },
    });

    res.json({ msg: 'Stop updated successfully', route: toLegacyBusRoute(updated) });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to update stop', error: err.message });
  }
});

// DELETE /api/bus-routes/:id/stops/:stopIndex - Delete stop
router.delete('/:id/stops/:stopIndex', auth, roleCheck('superadmin'), async (req, res) => {
  try {
    const { stopIndex } = req.params;

    const route = await prisma.busRoute.findUnique({
      where: { id: req.params.id },
      include: { stops: { orderBy: { sequence: 'asc' } } },
    });

    if (!route) {
      return res.status(404).json({ msg: 'Route not found' });
    }

    const stop = route.stops[Number(stopIndex)];
    if (!stop) {
      return res.status(404).json({ msg: 'Stop not found' });
    }

    await prisma.busStop.delete({ where: { id: stop.id } });

    const remaining = await prisma.busStop.findMany({
      where: { routeId: route.id },
      orderBy: { sequence: 'asc' },
    });

    await prisma.$transaction(
      remaining.map((item, index) => prisma.busStop.update({
        where: { id: item.id },
        data: { sequence: index + 1 },
      }))
    );

    const updated = await prisma.busRoute.findUnique({
      where: { id: route.id },
      include: { stops: { orderBy: { sequence: 'asc' } } },
    });

    res.json({ msg: 'Stop deleted successfully', route: toLegacyBusRoute(updated) });
  } catch (err) {
    res.status(500).json({ msg: 'Failed to delete stop', error: err.message });
  }
});

module.exports = router;
