/**
 * Insurance CRM Service Worker
 * Handles offline caching, push notifications, and background sync
 * 
 * @version 2.0.0
 */

const CACHE_NAME = 'insurance-crm-v2.0.0';
const STATIC_CACHE = 'insurance-crm-static-v2.0.0';
const DYNAMIC_CACHE = 'insurance-crm-dynamic-v2.0.0';

// Static assets to cache
const STATIC_ASSETS = [
    '/wp-content/plugins/insurance-crm/assets/css/admin-optimized.css',
    '/wp-content/plugins/insurance-crm/assets/css/realtime-announcements.css',
    '/wp-content/plugins/insurance-crm/assets/js/realtime-announcements.js',
    '/wp-content/plugins/insurance-crm/assets/js/module-loader.js',
    '/wp-content/plugins/insurance-crm/assets/images/icon-192x192.png',
    '/wp-content/plugins/insurance-crm/assets/images/icon-512x512.png'
];

// Routes to cache dynamically
const CACHE_ROUTES = [
    '/wp-admin/admin.php?page=insurance-crm',
    '/wp-admin/admin-ajax.php'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('Service Worker installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Caching static assets...');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Failed to cache static assets:', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker activated');
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache with network fallback
self.addEventListener('fetch', event => {
    const request = event.request;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Skip external requests
    if (!url.origin.includes(self.location.origin)) {
        return;
    }
    
    // Handle different types of requests
    if (isStaticAsset(request.url)) {
        event.respondWith(cacheFirst(request));
    } else if (isAPIRequest(request.url)) {
        event.respondWith(networkFirst(request));
    } else if (isCRMPage(request.url)) {
        event.respondWith(networkFirst(request));
    }
});

// Push notification handler
self.addEventListener('push', event => {
    console.log('Push notification received:', event);
    
    let notificationData = {
        title: 'Insurance CRM',
        body: 'Yeni bir duyuru var.',
        icon: '/wp-content/plugins/insurance-crm/assets/images/icon-192x192.png',
        badge: '/wp-content/plugins/insurance-crm/assets/images/badge-72x72.png',
        data: {
            url: '/wp-admin/admin.php?page=insurance-crm-announcements'
        }
    };
    
    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = { ...notificationData, ...data };
        } catch (e) {
            console.error('Error parsing push data:', e);
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            data: notificationData.data,
            requireInteraction: notificationData.urgent || false,
            actions: [
                {
                    action: 'view',
                    title: 'Görüntüle'
                },
                {
                    action: 'dismiss',
                    title: 'Kapat'
                }
            ]
        })
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    console.log('Notification clicked:', event);
    
    event.notification.close();
    
    if (event.action === 'view' || !event.action) {
        const urlToOpen = event.notification.data?.url || '/wp-admin/admin.php?page=insurance-crm';
        
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then(clientList => {
                    // Check if a window with the URL is already open
                    for (const client of clientList) {
                        if (client.url.includes('insurance-crm') && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    
                    // Open new window
                    if (clients.openWindow) {
                        return clients.openWindow(urlToOpen);
                    }
                })
        );
    }
});

// Background sync handler
self.addEventListener('sync', event => {
    console.log('Background sync:', event.tag);
    
    if (event.tag === 'insurance-crm-sync') {
        event.waitUntil(syncCRMData());
    }
});

// Caching strategies
function cacheFirst(request) {
    return caches.match(request)
        .then(response => {
            if (response) {
                return response;
            }
            
            return fetch(request)
                .then(response => {
                    if (response.status === 200) {
                        const responseClone = response.clone();
                        caches.open(STATIC_CACHE)
                            .then(cache => cache.put(request, responseClone));
                    }
                    return response;
                })
                .catch(() => {
                    // Return offline fallback if available
                    return caches.match('/wp-content/plugins/insurance-crm/offline.html');
                });
        });
}

function networkFirst(request) {
    return fetch(request)
        .then(response => {
            if (response.status === 200) {
                const responseClone = response.clone();
                caches.open(DYNAMIC_CACHE)
                    .then(cache => cache.put(request, responseClone));
            }
            return response;
        })
        .catch(() => {
            return caches.match(request)
                .then(response => {
                    if (response) {
                        return response;
                    }
                    
                    // Return offline fallback
                    if (isCRMPage(request.url)) {
                        return caches.match('/wp-content/plugins/insurance-crm/offline.html');
                    }
                    
                    throw new Error('No cache match found');
                });
        });
}

// Utility functions
function isStaticAsset(url) {
    return url.includes('/assets/') && 
           (url.endsWith('.css') || url.endsWith('.js') || url.endsWith('.png') || 
            url.endsWith('.jpg') || url.endsWith('.svg') || url.endsWith('.woff2'));
}

function isAPIRequest(url) {
    return url.includes('admin-ajax.php') || url.includes('/wp-json/');
}

function isCRMPage(url) {
    return url.includes('insurance-crm') || url.includes('temsilci-paneli');
}

// Sync CRM data in background
async function syncCRMData() {
    try {
        // Get pending changes from IndexedDB
        const db = await openDB();
        const pendingChanges = await getAllPendingChanges(db);
        
        for (const change of pendingChanges) {
            try {
                await syncChange(change);
                await markChangeSynced(db, change.id);
            } catch (error) {
                console.error('Failed to sync change:', change, error);
            }
        }
        
        console.log('Background sync completed');
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

// IndexedDB helpers
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('insurance-crm-offline', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('pendingChanges')) {
                const store = db.createObjectStore('pendingChanges', { keyPath: 'id', autoIncrement: true });
                store.createIndex('timestamp', 'timestamp');
                store.createIndex('type', 'type');
            }
        };
    });
}

function getAllPendingChanges(db) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pendingChanges'], 'readonly');
        const store = transaction.objectStore('pendingChanges');
        const request = store.getAll();
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
    });
}

function markChangeSynced(db, changeId) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(['pendingChanges'], 'readwrite');
        const store = transaction.objectStore('pendingChanges');
        const request = store.delete(changeId);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve();
    });
}

async function syncChange(change) {
    const response = await fetch('/wp-admin/admin-ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'insurance_crm_sync_change',
            ...change.data
        })
    });
    
    if (!response.ok) {
        throw new Error(`Sync failed: ${response.status}`);
    }
    
    return response.json();
}