/**
 * Real-time Announcements JavaScript Module
 * Handles real-time notifications, SSE, and push notifications
 * 
 * @version 2.0.0
 * @since 1.9.8
 */

(function($, window, document) {
    'use strict';

    class InsuranceCRMRealtimeAnnouncements {
        constructor(config) {
            this.config = {
                ajaxUrl: '',
                nonce: '',
                userId: 0,
                pollInterval: 30000,
                enableSSE: true,
                enablePush: true,
                vapidPublicKey: '',
                sounds: {
                    notification: '',
                    urgent: ''
                },
                ...config
            };

            this.isConnected = false;
            this.eventSource = null;
            this.pollTimer = null;
            this.lastTimestamp = new Date().toISOString();
            this.unreadCount = 0;
            this.notifications = [];
            this.pushSubscription = null;

            // Audio elements for notifications
            this.audioElements = {};

            this.init();
        }

        /**
         * Initialize the real-time system
         */
        init() {
            this.setupUI();
            this.loadAudioElements();
            this.checkBrowserSupport();
            this.requestNotificationPermission();
            
            if (this.config.enableSSE && this.supportsSSE()) {
                this.initSSE();
            } else {
                this.initPolling();
            }

            if (this.config.enablePush && this.supportsPush()) {
                this.initPushNotifications();
            }

            this.bindEvents();
            this.loadInitialAnnouncements();
        }

        /**
         * Set up the notification UI
         */
        setupUI() {
            // Create notification container if it doesn't exist
            if (!$('#insurance-crm-notifications-container').length) {
                $('body').append(`
                    <div id="insurance-crm-notifications-container" class="insurance-crm-notifications-container">
                        <div class="notifications-header">
                            <h3>Duyurular</h3>
                            <span class="unread-count" id="notifications-unread-count">0</span>
                            <button class="notifications-close">&times;</button>
                        </div>
                        <div class="notifications-content" id="notifications-content">
                            <div class="loading">Yükleniyor...</div>
                        </div>
                        <div class="notifications-footer">
                            <button class="mark-all-read">Tümünü Okundu İşaretle</button>
                        </div>
                    </div>
                `);
            }

            // Create notification bell in admin bar
            if (!$('#insurance-crm-notification-bell').length && $('#wp-admin-bar-root-default').length) {
                $('#wp-admin-bar-root-default').append(`
                    <li id="wp-admin-bar-insurance-crm-notifications">
                        <a class="ab-item" href="#" id="insurance-crm-notification-bell">
                            <span class="ab-icon dashicons-bell"></span>
                            <span class="ab-label">Duyurular</span>
                            <span class="notification-count" id="notification-bell-count">0</span>
                        </a>
                    </li>
                `);
            }

            // Create toast container
            if (!$('#insurance-crm-toast-container').length) {
                $('body').append('<div id="insurance-crm-toast-container" class="insurance-crm-toast-container"></div>');
            }
        }

        /**
         * Load audio elements for notifications
         */
        loadAudioElements() {
            Object.keys(this.config.sounds).forEach(soundType => {
                if (this.config.sounds[soundType]) {
                    const audio = new Audio(this.config.sounds[soundType]);
                    audio.preload = 'auto';
                    this.audioElements[soundType] = audio;
                }
            });
        }

        /**
         * Check browser support for modern features
         */
        checkBrowserSupport() {
            this.features = {
                sse: typeof EventSource !== 'undefined',
                push: 'serviceWorker' in navigator && 'PushManager' in window,
                notifications: 'Notification' in window,
                audio: typeof Audio !== 'undefined'
            };

            console.log('Insurance CRM: Browser features:', this.features);
        }

        /**
         * Request notification permission
         */
        async requestNotificationPermission() {
            if (!this.features.notifications) return;

            if (Notification.permission === 'default') {
                const permission = await Notification.requestPermission();
                console.log('Notification permission:', permission);
            }
        }

        /**
         * Initialize Server-Sent Events
         */
        initSSE() {
            if (!this.features.sse) {
                console.warn('SSE not supported, falling back to polling');
                this.initPolling();
                return;
            }

            const sseUrl = `${this.config.ajaxUrl}?action=insurance_crm_sse_stream&nonce=${this.config.nonce}`;
            
            this.eventSource = new EventSource(sseUrl);

            this.eventSource.onopen = () => {
                console.log('SSE connection opened');
                this.isConnected = true;
                this.updateConnectionStatus(true);
            };

            this.eventSource.onmessage = (event) => {
                console.log('SSE message received:', event.data);
            };

            this.eventSource.addEventListener('connected', (event) => {
                const data = JSON.parse(event.data);
                console.log('Connected to announcement stream:', data);
                this.updateConnectionStatus(true);
            });

            this.eventSource.addEventListener('announcement', (event) => {
                const announcement = JSON.parse(event.data);
                this.handleNewAnnouncement(announcement);
            });

            this.eventSource.addEventListener('heartbeat', (event) => {
                const data = JSON.parse(event.data);
                this.updateUnreadCount(data.unread_count);
            });

            this.eventSource.onerror = (error) => {
                console.error('SSE error:', error);
                this.isConnected = false;
                this.updateConnectionStatus(false);
                
                // Fallback to polling if SSE fails
                setTimeout(() => {
                    if (!this.isConnected) {
                        this.eventSource.close();
                        this.initPolling();
                    }
                }, 5000);
            };
        }

        /**
         * Initialize polling fallback
         */
        initPolling() {
            this.pollTimer = setInterval(() => {
                this.pollForAnnouncements();
            }, this.config.pollInterval);

            // Initial poll
            this.pollForAnnouncements();
        }

        /**
         * Poll for new announcements
         */
        async pollForAnnouncements() {
            try {
                const response = await $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'insurance_crm_poll_announcements',
                        nonce: this.config.nonce,
                        last_timestamp: this.lastTimestamp
                    }
                });

                if (response.success) {
                    const { new_announcements, total_unread, timestamp } = response.data;
                    
                    this.lastTimestamp = timestamp;
                    this.updateUnreadCount(total_unread);
                    
                    new_announcements.forEach(announcement => {
                        this.handleNewAnnouncement(announcement);
                    });
                }
            } catch (error) {
                console.error('Error polling announcements:', error);
            }
        }

        /**
         * Initialize push notifications
         */
        async initPushNotifications() {
            if (!this.features.push || !this.config.vapidPublicKey) {
                console.warn('Push notifications not supported or not configured');
                return;
            }

            try {
                // Register service worker
                const registration = await navigator.serviceWorker.register('/wp-content/plugins/insurance-crm/assets/js/sw.js');
                console.log('Service Worker registered:', registration);

                // Subscribe to push notifications
                const subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.config.vapidPublicKey)
                });

                // Send subscription to server
                await this.registerPushSubscription(subscription);
                this.pushSubscription = subscription;

            } catch (error) {
                console.error('Error setting up push notifications:', error);
            }
        }

        /**
         * Register push subscription with server
         */
        async registerPushSubscription(subscription) {
            try {
                const response = await $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'insurance_crm_register_push',
                        nonce: this.config.nonce,
                        subscription: JSON.stringify(subscription)
                    }
                });

                if (response.success) {
                    console.log('Push subscription registered successfully');
                } else {
                    console.error('Failed to register push subscription:', response.data);
                }
            } catch (error) {
                console.error('Error registering push subscription:', error);
            }
        }

        /**
         * Handle new announcement
         */
        handleNewAnnouncement(announcement) {
            console.log('New announcement received:', announcement);
            
            // Add to notifications array
            this.notifications.unshift(announcement);
            
            // Show toast notification
            this.showToast(announcement);
            
            // Play sound
            this.playNotificationSound(announcement);
            
            // Show browser notification
            this.showBrowserNotification(announcement);
            
            // Update UI
            this.updateNotificationsList();
        }

        /**
         * Show toast notification
         */
        showToast(announcement) {
            const toastId = `toast-${Date.now()}`;
            const priorityClass = announcement.category === 'urgent' ? 'urgent' : 'normal';
            
            const toast = $(`
                <div class="insurance-crm-toast ${priorityClass}" id="${toastId}">
                    <div class="toast-header">
                        <strong>${this.escapeHtml(announcement.title)}</strong>
                        <button class="toast-close">&times;</button>
                    </div>
                    <div class="toast-body">
                        ${this.escapeHtml(announcement.message)}
                    </div>
                    <div class="toast-actions">
                        <button class="toast-mark-read" data-id="${announcement.id}">Okundu İşaretle</button>
                        <button class="toast-view" data-id="${announcement.id}">Görüntüle</button>
                    </div>
                </div>
            `);

            $('#insurance-crm-toast-container').append(toast);

            // Auto-remove after 10 seconds
            setTimeout(() => {
                toast.fadeOut(() => toast.remove());
            }, 10000);

            // Close button
            toast.find('.toast-close').on('click', () => {
                toast.fadeOut(() => toast.remove());
            });

            // Mark as read button
            toast.find('.toast-mark-read').on('click', (e) => {
                const id = $(e.target).data('id');
                this.markAsRead([id]);
                toast.fadeOut(() => toast.remove());
            });

            // View button
            toast.find('.toast-view').on('click', (e) => {
                const id = $(e.target).data('id');
                this.viewAnnouncement(id);
                toast.fadeOut(() => toast.remove());
            });
        }

        /**
         * Play notification sound
         */
        playNotificationSound(announcement) {
            if (!this.features.audio) return;

            const soundType = announcement.category === 'urgent' ? 'urgent' : 'notification';
            const audio = this.audioElements[soundType];
            
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(error => {
                    console.warn('Could not play notification sound:', error);
                });
            }
        }

        /**
         * Show browser notification
         */
        showBrowserNotification(announcement) {
            if (!this.features.notifications || Notification.permission !== 'granted') {
                return;
            }

            const notification = new Notification(announcement.title, {
                body: announcement.message,
                icon: '/wp-content/plugins/insurance-crm/assets/images/icon-192x192.png',
                badge: '/wp-content/plugins/insurance-crm/assets/images/badge-72x72.png',
                tag: `announcement-${announcement.id}`,
                requireInteraction: announcement.category === 'urgent',
                actions: [
                    { action: 'view', title: 'Görüntüle' },
                    { action: 'dismiss', title: 'Kapat' }
                ]
            });

            notification.onclick = () => {
                this.viewAnnouncement(announcement.id);
                notification.close();
            };

            // Auto-close after 5 seconds (unless urgent)
            if (announcement.category !== 'urgent') {
                setTimeout(() => {
                    notification.close();
                }, 5000);
            }
        }

        /**
         * Update unread count in UI
         */
        updateUnreadCount(count) {
            this.unreadCount = count;
            
            $('#notifications-unread-count').text(count);
            $('#notification-bell-count').text(count);
            
            // Show/hide count based on value
            if (count > 0) {
                $('#notifications-unread-count, #notification-bell-count').show();
            } else {
                $('#notifications-unread-count, #notification-bell-count').hide();
            }
        }

        /**
         * Update connection status indicator
         */
        updateConnectionStatus(connected) {
            const indicator = $('#insurance-crm-connection-status');
            if (indicator.length) {
                indicator.removeClass('connected disconnected')
                         .addClass(connected ? 'connected' : 'disconnected')
                         .attr('title', connected ? 'Bağlı' : 'Bağlantı kesildi');
            }
        }

        /**
         * Load initial announcements
         */
        async loadInitialAnnouncements() {
            try {
                const response = await $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'insurance_crm_get_announcements',
                        nonce: this.config.nonce,
                        limit: 50
                    }
                });

                if (response.success) {
                    this.notifications = response.data.announcements;
                    this.updateNotificationsList();
                    this.updateUnreadCount(response.data.announcements.filter(a => !a.is_read).length);
                }
            } catch (error) {
                console.error('Error loading initial announcements:', error);
            }
        }

        /**
         * Update notifications list in UI
         */
        updateNotificationsList() {
            const container = $('#notifications-content');
            
            if (this.notifications.length === 0) {
                container.html('<div class="no-notifications">Duyuru bulunmuyor</div>');
                return;
            }

            const html = this.notifications.map(announcement => `
                <div class="notification-item ${announcement.is_read ? 'read' : 'unread'}" data-id="${announcement.id}">
                    <div class="notification-header">
                        <strong>${this.escapeHtml(announcement.title)}</strong>
                        <span class="notification-time">${this.formatTime(announcement.created_at)}</span>
                    </div>
                    <div class="notification-content">
                        ${this.escapeHtml(announcement.message)}
                    </div>
                    <div class="notification-actions">
                        ${!announcement.is_read ? '<button class="mark-read" data-id="' + announcement.id + '">Okundu İşaretle</button>' : ''}
                        <button class="view-announcement" data-id="${announcement.id}">Görüntüle</button>
                    </div>
                </div>
            `).join('');

            container.html(html);
        }

        /**
         * Mark announcements as read
         */
        async markAsRead(announcementIds) {
            try {
                const response = await $.ajax({
                    url: this.config.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'insurance_crm_mark_read',
                        nonce: this.config.nonce,
                        announcement_ids: announcementIds
                    }
                });

                if (response.success) {
                    // Update local state
                    this.notifications.forEach(notification => {
                        if (announcementIds.includes(notification.id)) {
                            notification.is_read = 1;
                        }
                    });
                    
                    this.updateNotificationsList();
                    this.updateUnreadCount(this.unreadCount - announcementIds.length);
                }
            } catch (error) {
                console.error('Error marking announcements as read:', error);
            }
        }

        /**
         * View announcement details
         */
        viewAnnouncement(announcementId) {
            // Navigate to announcement page or show modal
            window.location.href = `${window.location.origin}/wp-admin/admin.php?page=insurance-crm-announcements&id=${announcementId}`;
        }

        /**
         * Bind UI events
         */
        bindEvents() {
            // Notification bell click
            $(document).on('click', '#insurance-crm-notification-bell', (e) => {
                e.preventDefault();
                $('#insurance-crm-notifications-container').toggleClass('active');
            });

            // Close notifications panel
            $(document).on('click', '.notifications-close', () => {
                $('#insurance-crm-notifications-container').removeClass('active');
            });

            // Mark all as read
            $(document).on('click', '.mark-all-read', () => {
                const unreadIds = this.notifications.filter(n => !n.is_read).map(n => n.id);
                if (unreadIds.length > 0) {
                    this.markAsRead(unreadIds);
                }
            });

            // Individual notification actions
            $(document).on('click', '.mark-read', (e) => {
                const id = parseInt($(e.target).data('id'));
                this.markAsRead([id]);
            });

            $(document).on('click', '.view-announcement', (e) => {
                const id = parseInt($(e.target).data('id'));
                this.viewAnnouncement(id);
            });

            // Close panel when clicking outside
            $(document).on('click', (e) => {
                if (!$(e.target).closest('#insurance-crm-notifications-container, #insurance-crm-notification-bell').length) {
                    $('#insurance-crm-notifications-container').removeClass('active');
                }
            });
        }

        /**
         * Utility methods
         */
        supportsSSE() {
            return this.features.sse;
        }

        supportsPush() {
            return this.features.push;
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Az önce';
            if (diff < 3600000) return Math.floor(diff / 60000) + ' dk önce';
            if (diff < 86400000) return Math.floor(diff / 3600000) + ' sa önce';
            
            return date.toLocaleDateString('tr-TR');
        }

        urlBase64ToUint8Array(base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/-/g, '+')
                .replace(/_/g, '/');
            
            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);
            
            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        }

        /**
         * Cleanup method
         */
        destroy() {
            if (this.eventSource) {
                this.eventSource.close();
            }
            
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
            }
        }
    }

    // Initialize when config is available
    $(document).ready(() => {
        if (typeof insuranceCrmRealtime !== 'undefined') {
            window.insuranceCrmRealtimeAnnouncements = new InsuranceCRMRealtimeAnnouncements(insuranceCrmRealtime);
        }
    });

    // Global access
    window.InsuranceCRMRealtimeAnnouncements = InsuranceCRMRealtimeAnnouncements;

})(jQuery, window, document);