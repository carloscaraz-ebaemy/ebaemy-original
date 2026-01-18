/**
 * Servidor Socket.IO para Restaurant Module
 * Escucha eventos desde Laravel y hace broadcast a clientes conectados
 * 
 * Uso: node socket-server.js
 * O en desarrollo: npm install -g nodemon && nodemon socket-server.js
 */

const express = require('express');
const http = require('http');
const socketIO = require('socket.io');
const cors = require('cors');

const app = express();
const server = http.createServer(app);
const PORT = process.env.SOCKET_PORT || 8070;

// Middleware
app.use(cors());
app.use(express.json());

// Socket.IO con CORS habilitado
const io = socketIO(server, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST']
  }
});

// Almacenar clientes conectados por tenant/sala
const connectedClients = {};

// Cuando un cliente se conecta
io.on('connection', (socket) => {
  console.log('✓ Cliente conectado:', socket.id);

  // El cliente se subscribe a una sala (ej: tenant_1)
  socket.on('subscribe', (room) => {
    socket.join(room);
    console.log(`✓ Socket ${socket.id} se suscribió a: ${room}`);
    
    if (!connectedClients[room]) {
      connectedClients[room] = [];
    }
    connectedClients[room].push(socket.id);
  });

  // Cuando se desconecta
  socket.on('disconnect', () => {
    console.log('✗ Cliente desconectado:', socket.id);
    
    // Limpiar de connectedClients
    Object.keys(connectedClients).forEach(room => {
      connectedClients[room] = connectedClients[room].filter(id => id !== socket.id);
    });
  });
});

// ============= RUTAS HTTP (para que Laravel notifique al servidor) =============

/**
 * POST /notify/table-updated
 * Laravel llama a este endpoint cuando una mesa se actualiza
 * Body: { room: 'tenant_1', table_id: 5, data: {...} }
 */
app.post('/notify/table-updated', (req, res) => {
  const { room, table_id, data } = req.body;

  if (!room) {
    return res.status(400).json({ error: 'room is required' });
  }

  // Hacer broadcast a todos los clientes de esa sala
  io.to(room).emit('table-updated', {
    table_id,
    data,
    timestamp: new Date().toISOString()
  });

  console.log(`📡 Broadcast "table-updated" a sala: ${room}`);
  res.json({ success: true, room, clients: connectedClients[room]?.length || 0 });
});

/**
 * POST /notify/table-groups-updated
 * Laravel llama a este endpoint cuando grupos de mesas se actualizan
 * Body: { room: 'tenant_1' }
 */
app.post('/notify/table-groups-updated', (req, res) => {
  const { room } = req.body;

  if (!room) {
    return res.status(400).json({ error: 'room is required' });
  }

  io.to(room).emit('table-groups-updated', {
    timestamp: new Date().toISOString()
  });

  console.log(`📡 Broadcast "table-groups-updated" a sala: ${room}`);
  res.json({ success: true, room, clients: connectedClients[room]?.length || 0 });
});

/**
 * POST /notify/order-status-updated
 * Laravel llama cuando cambia el estado de un pedido
 * Body: { room: 'tenant_1', table_id: 5, order_id: 123, status: 2 }
 */
app.post('/notify/order-status-updated', (req, res) => {
  const { room, table_id, order_id, status } = req.body;

  if (!room) {
    return res.status(400).json({ error: 'room is required' });
  }

  io.to(room).emit('order-status-updated', {
    table_id,
    order_id,
    status,
    timestamp: new Date().toISOString()
  });

  console.log(`📡 Broadcast "order-status-updated" a sala: ${room}, status: ${status}`);
  res.json({ success: true, room, clients: connectedClients[room]?.length || 0 });
});

/**
 * GET /health
 * Verificar que el servidor está activo
 */
app.get('/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    uptime: process.uptime(),
    connectedClients: Object.values(connectedClients).reduce((a, b) => a + b.length, 0)
  });
});

// Iniciar servidor
server.listen(PORT, () => {
  console.log(`🚀 Servidor Socket.IO escuchando en puerto ${PORT}`);
  console.log(`🔗 Conectar desde frontend: io('http://localhost:${PORT}')`);
});

// Manejo de errores
process.on('uncaughtException', (err) => {
  console.error('❌ Error no capturado:', err);
});
