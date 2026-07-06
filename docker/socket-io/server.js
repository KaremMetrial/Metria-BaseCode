require('dotenv').config();
const http = require('http');
const express = require('express');
const { Server } = require('socket.io');
const Redis = require('ioredis');
const axios = require('axios');

const app = express();
const server = http.createServer(app);

const PORT = process.env.PORT || 6001;
const REDIS_HOST = process.env.REDIS_HOST || '127.0.0.1';
const REDIS_PORT = process.env.REDIS_PORT || 6379;
const REDIS_PASSWORD = process.env.REDIS_PASSWORD || null;
const LARAVEL_API_URL = process.env.LARAVEL_API_URL || 'http://127.0.0.1:8000';

app.use(express.json());

// Health check endpoint for Docker & load balancers
app.get('/health', (req, res) => {
    res.status(200).json({ status: 'healthy', service: 'metrial-socket-io-server', timestamp: new Date() });
});

const io = new Server(server, {
    cors: {
        origin: process.env.CORS_ALLOWED_ORIGINS ? process.env.CORS_ALLOWED_ORIGINS.split(',') : '*',
        methods: ['GET', 'POST'],
        credentials: true
    }
});

// Redis Subscriber Connection
const redisOptions = {
    host: REDIS_HOST,
    port: REDIS_PORT,
    password: REDIS_PASSWORD || undefined,
    retryStrategy: (times) => Math.min(times * 50, 2000)
};

const redisSubscriber = new Redis(redisOptions);

redisSubscriber.on('connect', () => {
    console.log(`[Redis] Connected successfully to ${REDIS_HOST}:${REDIS_PORT}`);
    redisSubscriber.psubscribe('*', (err, count) => {
        if (err) {
            console.error('[Redis] Failed to psubscribe:', err);
        } else {
            console.log(`[Redis] Subscribed to ${count} pattern(s). Ready to broadcast events.`);
        }
    });
});

redisSubscriber.on('pmessage', (pattern, channel, message) => {
    try {
        const payload = JSON.parse(message);
        const eventName = payload.event;
        const eventData = payload.data;

        // Laravel Redis broadcaster formats channels with prefix if configured
        console.log(`[Broadcast] Channel: ${channel} | Event: ${eventName}`);

        // Emit to Socket.IO room matching the channel name
        io.to(channel).emit(eventName, eventData);
    } catch (error) {
        console.error('[Broadcast] Error parsing Redis message:', error.message);
    }
});

// Socket.IO Connection & Channel Authorization Handling
io.on('connection', (socket) => {
    console.log(`[Socket] Client connected: ${socket.id}`);

    // Handle channel subscription requests from clients
    socket.on('subscribe', async (data, callback) => {
        const channelName = typeof data === 'string' ? data : data.channel;
        const authToken = socket.handshake.auth?.token || socket.handshake.headers?.authorization;

        const isGuarded = channelName.startsWith('private-') || 
                          channelName.startsWith('presence-') || 
                          channelName.startsWith('couriers.') || 
                          channelName.startsWith('support.');

        if (isGuarded) {
            if (!authToken) {
                console.warn(`[Auth] Rejected subscription to ${channelName} for ${socket.id} - Missing token`);
                if (typeof callback === 'function') callback({ error: 'Unauthenticated. Bearer token required.' });
                return;
            }

            try {
                // Authorize against Laravel Sanctum broadcast endpoint
                const authResponse = await axios.post(`${LARAVEL_API_URL}/api/v1/broadcasting/auth`, {
                    channel_name: channelName,
                    socket_id: socket.id
                }, {
                    headers: {
                        'Authorization': authToken.startsWith('Bearer ') ? authToken : `Bearer ${authToken}`,
                        'Accept': 'application/json'
                    }
                });

                socket.join(channelName);
                console.log(`[Auth] Authorized & joined ${channelName} for client ${socket.id}`);

                if (typeof callback === 'function') {
                    callback({ success: true, channel_data: authResponse.data?.channel_data || null });
                }
            } catch (authError) {
                const status = authError.response?.status || 500;
                console.warn(`[Auth] Failed authorization for ${channelName} (Status: ${status})`);
                if (typeof callback === 'function') {
                    callback({ error: 'Unauthorized channel access.', status });
                }
            }
        } else {
            // Public channel - join immediately
            socket.join(channelName);
            console.log(`[Socket] Joined public channel ${channelName} for client ${socket.id}`);
            if (typeof callback === 'function') callback({ success: true });
        }
    });

    socket.on('unsubscribe', (channelName) => {
        socket.leave(channelName);
        console.log(`[Socket] Left channel ${channelName} for client ${socket.id}`);
    });

    socket.on('disconnect', () => {
        console.log(`[Socket] Client disconnected: ${socket.id}`);
    });
});

server.listen(PORT, () => {
    console.log(`====================================================`);
    console.log(`  Metrial Enterprise Socket.IO Server Running`);
    console.log(`  Port: ${PORT} | Redis: ${REDIS_HOST}:${REDIS_PORT}`);
    console.log(`====================================================`);
});
