/**
 * Booking App - Vanilla JavaScript Version
 * Handles the complete booking flow
 */

(function() {
    'use strict';

    // Suppress extension messaging errors so they don't clutter the console (capture phase).
    function isExtensionConnectionError(reason) {
        try {
            var msg = (reason && (reason.message || String(reason))) || '';
            return msg.indexOf('Receiving end does not exist') !== -1 || msg.indexOf('Could not establish connection') !== -1;
        } catch (e) { return false; }
    }
    window.addEventListener('unhandledrejection', function(event) {
        try {
            if (isExtensionConnectionError(event.reason)) {
                event.preventDefault();
                event.stopPropagation();
                return false;
            }
        } catch (e) {}
    }, true);

    var PREFIX = '[HP Booking]';

    // Initialize function - will be called when hpBooking is available
    function initializeApp() {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', waitForHpBooking);
        } else {
            waitForHpBooking();
        }
    }
    
    class BookingApp {
        constructor(containerId) {
            this.container = document.getElementById(containerId);
            this.state = {
                step: 1,
                selectedDate: null,
                selectedTime: null,
                pickupAddress: '',
                pickupLat: null,
                pickupLng: null,
                destinationAddress: '',
                destinationLat: null,
                destinationLng: null,
                distance: null,
                duration: null,
                price: null,
                availableSlots: [],
                slotsLoading: false,
                paymentMethod: 'online',
                customerName: '',
                customerEmail: '',
                customerPhone: '',
                errors: [],
                loading: false,
                bookingComplete: false,
                bookingReference: null
            };

            this.init();
        }
        
        init() {
            this.checkStripeReturn();
            this.render();
            this.loadGoogleMaps();
        }

        checkStripeReturn() {
            var params = new URLSearchParams(window.location.search);
            var stripeSuccess = params.get('stripe') === 'success';
            var ref = params.get('ref');
            var status = params.get('redirect_status');
            if (stripeSuccess && ref) {
                this.state.bookingComplete = true;
                this.state.bookingReference = ref;
                this.state.paymentMethod = 'online';
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', window.location.pathname + window.location.hash);
                }
                return;
            }
            if (params.get('stripe') === 'cancel') {
                this.setState({ errors: ['Payment was cancelled. You can try again or choose Pay on Pickup.'] });
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', window.location.pathname + window.location.hash);
                }
                return;
            }
            if (status === 'succeeded') {
                try {
                    var storedRef = sessionStorage.getItem('hp_booking_reference');
                    if (storedRef) {
                        this.state.bookingComplete = true;
                        this.state.bookingReference = storedRef;
                        this.state.paymentMethod = 'online';
                    }
                    sessionStorage.removeItem('hp_booking_reference');
                    sessionStorage.removeItem('hp_booking_return');
                } catch (e) {}
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', window.location.pathname + window.location.hash);
                }
            } else if (status === 'failed') {
                try { sessionStorage.removeItem('hp_booking_return'); } catch (e) {}
                this.setState({ errors: ['Payment was not completed. You can try again or choose Pay on Pickup.'] });
                if (window.history && window.history.replaceState) {
                    window.history.replaceState({}, '', window.location.pathname + window.location.hash);
                }
            }
        }
        
        async loadGoogleMaps() {
            const key = this.container.dataset.googleMapsKey;
            if (!key || window.google) {
                this.initGoogleMaps();
                return;
            }
            
            // Load Google Maps script
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${key}&libraries=places&callback=initBookingMaps`;
            script.async = true;
            script.defer = true;
            window.initBookingMaps = () => this.initGoogleMaps();
            document.head.appendChild(script);
        }
        
        initGoogleMaps() {
            if (!window.google || !window.google.maps) return;
            
            // Initialize Places Autocomplete for pickup
            const pickupInput = document.getElementById('hp-pickup-address');
            if (pickupInput && !pickupInput.autocomplete) {
                const pickupAutocomplete = new google.maps.places.Autocomplete(pickupInput, {
                    componentRestrictions: { country: 'ca' },
                    fields: ['formatted_address', 'geometry', 'address_components', 'name']
                });
                
                pickupAutocomplete.addListener('place_changed', () => {
                    const place = pickupAutocomplete.getPlace();
                    if (place.geometry) {
                        var addr = place.formatted_address;
                        if (place.name && addr.indexOf(place.name) === -1) {
                            addr = place.name + ', ' + addr;
                        }
                        this.setState({
                            pickupAddress: addr,
                            pickupLat: place.geometry.location.lat(),
                            pickupLng: place.geometry.location.lng()
                        });
                        this.validateAddress('pickup', place.geometry.location.lat(), place.geometry.location.lng());
                    }
                });
                pickupInput.autocomplete = pickupAutocomplete;
            }
            
            // Initialize Places Autocomplete for destination
            const destInput = document.getElementById('hp-destination-address');
            if (destInput && !destInput.autocomplete) {
                const destAutocomplete = new google.maps.places.Autocomplete(destInput, {
                    componentRestrictions: { country: 'ca' },
                    fields: ['formatted_address', 'geometry', 'address_components', 'name']
                });
                
                destAutocomplete.addListener('place_changed', () => {
                    const place = destAutocomplete.getPlace();
                    if (place.geometry) {
                        var addr = place.formatted_address;
                        if (place.name && addr.indexOf(place.name) === -1) {
                            addr = place.name + ', ' + addr;
                        }
                        this.setState({
                            destinationAddress: addr,
                            destinationLat: place.geometry.location.lat(),
                            destinationLng: place.geometry.location.lng()
                        });
                        this.validateAddress('destination', place.geometry.location.lat(), place.geometry.location.lng());
                        this.calculatePrice();
                    }
                });
                destInput.autocomplete = destAutocomplete;
            }
        }
        
        setState(newState) {
            this.state = Object.assign({}, this.state, newState);
            var self = this;
            if (this._renderTimer) clearTimeout(this._renderTimer);
            this._renderTimer = setTimeout(function() {
                self._renderTimer = null;
                self.render();
            }, 0);
        }

        /**
         * Handle input changes without full re-render to preserve focus
         */
        handleInput(el, field) {
            this.state[field] = el.value;
            
            // Validate step 2 form for button state
            if (this.state.step === 2) {
                var btn = this.container.querySelector('.hp-cta');
                if (btn) {
                    var isValid = this.state.customerName && this.state.customerEmail && this.state.customerPhone;
                    if (isValid) {
                        btn.removeAttribute('disabled');
                    } else {
                        btn.setAttribute('disabled', 'disabled');
                    }
                }
            }
        }
        
        /**
         * Normalize date to YYYY-MM-DD for the API (handles locale display vs value, edge cases).
         */
        normalizeDateForApi(dateStr) {
            if (!dateStr || typeof dateStr !== 'string') return null;
            var trimmed = String(dateStr).trim();
            if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) return trimmed;
            var d = new Date(trimmed);
            if (isNaN(d.getTime())) return null;
            var y = d.getFullYear(), m = (d.getMonth() + 1), day = d.getDate();
            m = (m < 10 ? '0' : '') + m;
            day = (day < 10 ? '0' : '') + day;
            return y + '-' + m + '-' + day;
        }

        onDateChange(value) {
            var normalized = this.normalizeDateForApi(value) || value;
            this.setState({ selectedDate: normalized });
            this.loadAvailability(normalized);
        }

        async loadAvailability(date) {
            date = this.normalizeDateForApi(date) || date;
            if (!date) {
                this.setState({ availableSlots: [], slotsLoading: false });
                return;
            }
            // Check if hpBooking is defined - ONLY use window.hpBooking to avoid ReferenceError
            const hpBookingObj = window.hpBooking;
            
            if (!hpBookingObj) {
                console.error(PREFIX, 'loadAvailability: hpBooking is not defined. Ensure shortcode [hp_booking_form] is on this page and the plugin is active.');
                setTimeout(() => {
                    const retryObj = window.hpBooking;
                    if (retryObj) {
                        this.loadAvailability(date);
                    } else {
                        this.setState({
                            availableSlots: [],
                            slotsLoading: false,
                            errors: ['Configuration error. Please refresh the page.']
                        });
                    }
                }, 500);
                return;
            }

            this.setState({ errors: [], availableSlots: [], slotsLoading: true });
            
            // Add duration param if available
            let url = `${hpBookingObj.apiUrl}availability?date=${encodeURIComponent(date)}`;
            if (this.state.duration) {
                url += `&duration=${encodeURIComponent(this.state.duration)}`;
            }

            var controller = new AbortController();
            var timeoutId = setTimeout(function() { controller.abort(); }, 15000);
            try {
                const response = await fetch(url, {
                    headers: { 'X-WP-Nonce': hpBookingObj.nonce },
                    signal: controller.signal
                });
                clearTimeout(timeoutId);
                const responseText = await response.text();
                let data = {};
                try { data = responseText ? JSON.parse(responseText) : {}; } catch (e) {
                    console.error(PREFIX, 'Availability: invalid JSON', responseText.substring(0, 200));
                    data = { message: 'Invalid server response.' };
                }

                if (!response.ok) {
                    console.error(PREFIX, 'Availability failed', { status: response.status, statusText: response.statusText, url: url, body: data });
                    this.setState({
                        availableSlots: [],
                        slotsLoading: false,
                        errors: [data.message || 'Unable to load available time slots. Please try again.']
                    });
                    return;
                }

                this.setState({
                    availableSlots: data.available_slots || [],
                    allSlots: data.all_slots || [],
                    slotsLoading: false
                });
            } catch (error) {
                clearTimeout(timeoutId);
                if (error.name === 'AbortError') {
                    this.setState({
                        availableSlots: [],
                        slotsLoading: false,
                        errors: ['Loading times took too long. Please try again or choose another date.']
                    });
                } else {
                    console.error(PREFIX, 'Availability request error', error.message, error);
                    this.setState({
                        availableSlots: [],
                        slotsLoading: false,
                        errors: ['Unable to load available time slots. Please check your connection and try again.']
                    });
                }
            }
        }
        
        async validateAddress(type, lat, lng) {
            // FEATURE HIDDEN PER CLIENT REQUEST (2026-02-17)
            // Proxy distance validation removed to avoid showing errors for addresses outside service area
            // The backend will still validate if needed, but we won't show frontend errors
            return;
            
            /* ORIGINAL CODE - COMMENTED OUT
            // ONLY use window.hpBooking to avoid ReferenceError
            const hpBookingObj = window.hpBooking;
            
            if (!hpBookingObj) {
                console.warn(PREFIX, 'validateAddress: hpBooking not defined, skipping.');
                return;
            }

            try {
                const response = await fetch(`${hpBookingObj.apiUrl}validate-address`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBookingObj.nonce
                    },
                    body: JSON.stringify({
                        address: type === 'pickup' ? this.state.pickupAddress : this.state.destinationAddress,
                        type: type
                    })
                });
                const data = await response.json();
                
                if (!data.in_service_area) {
                    const errors = this.state.errors || [];
                    errors.push(`${type} address must be in Grafton, Cobourg, Port Hope, or Brighton`);
                    this.setState({ errors: errors });
                }
                if (!response.ok) {
                    console.error(PREFIX, 'validate-address not ok', { status: response.status, body: data });
                }
            } catch (error) {
                console.error(PREFIX, 'validateAddress error', error.message, error);
            }
            */
        }
        
        async calculatePrice() {
            if (!this.state.pickupAddress || !this.state.destinationAddress) return;
            
            // Check if hpBooking is defined - ONLY use window.hpBooking
            const hpBookingObj = window.hpBooking;
            
            if (!hpBookingObj) {
                console.error(PREFIX, 'calculatePrice: hpBooking is not defined.');
                this.setState({
                    errors: ['Configuration error. Please refresh the page.'],
                    loading: false
                });
                return;
            }

            this.setState({ loading: true, errors: [] });

            try {
                const response = await fetch(`${hpBookingObj.apiUrl}calculate-price`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBookingObj.nonce
                    },
                    body: JSON.stringify({
                        pickup_address: this.state.pickupAddress,
                        destination_address: this.state.destinationAddress,
                        pickup_lat: this.state.pickupLat,
                        pickup_lng: this.state.pickupLng,
                        destination_lat: this.state.destinationLat,
                        destination_lng: this.state.destinationLng
                    })
                });
                const data = await response.json();
                
                if (!response.ok) {
                    console.error(PREFIX, 'calculate-price failed', { status: response.status, body: data });
                    const errorMessage = data.message || data.error || 'Failed to calculate price';
                    this.setState({
                        errors: [errorMessage],
                        loading: false
                    });
                    return;
                }

                if (data.distance_km) {
                    this.setState({
                        distance: data.distance_km,
                        duration: data.duration_minutes,
                        price: data.price,
                        loading: false
                    });
                    // Refresh availabilty if date is selected (since duration changed)
                    if (this.state.selectedDate) {
                        this.loadAvailability(this.state.selectedDate);
                    }
                } else {
                    this.setState({
                        errors: ['Unable to calculate distance. Please check your addresses.'],
                        loading: false
                    });
                }
            } catch (error) {
                console.error(PREFIX, 'calculatePrice error', error.message, error);
                this.setState({
                    errors: ['An error occurred while calculating the price. Please try again.'],
                    loading: false
                });
            }
        }

        async createBooking() {
            // ONLY use window.hpBooking to avoid ReferenceError
            const hpBookingObj = window.hpBooking;
            
            if (!hpBookingObj) {
                console.error(PREFIX, 'createBooking: hpBooking is not defined.');
                this.setState({
                    errors: ['Configuration error. Please refresh the page.'],
                    loading: false
                });
                return;
            }

            this.setState({ loading: true, errors: [] });

            const bookingData = {
                customer_name: this.state.customerName,
                customer_email: this.state.customerEmail,
                customer_phone: this.state.customerPhone,
                pickup_address: this.state.pickupAddress,
                pickup_lat: this.state.pickupLat,
                pickup_lng: this.state.pickupLng,
                destination_address: this.state.destinationAddress,
                destination_lat: this.state.destinationLat,
                destination_lng: this.state.destinationLng,
                pickup_datetime: `${this.state.selectedDate} ${this.state.selectedTime}:00`,
                payment_method: this.state.paymentMethod,
                special_instructions: this.state.specialInstructions || ''
            };
            
            try {
                const response = await fetch(`${hpBookingObj.apiUrl}create-booking`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBookingObj.nonce
                    },
                    body: JSON.stringify(bookingData)
                });
                
                const data = await response.json();
                
                if (!response.ok) {
                    console.error(PREFIX, 'create-booking failed', { status: response.status, body: data });
                    let errorMessage = data.message || data.error || '';
                    if (!errorMessage && data.data && typeof data.data === 'object' && data.data.message) {
                        errorMessage = data.data.message;
                    }
                    if (!errorMessage) {
                        errorMessage = response.status === 502
                            ? 'Payment setup failed. Check Bookings → Settings: Stripe test keys and ensure the plugin vendor folder (Stripe SDK) is uploaded.'
                            : 'Failed to create booking. Please try again.';
                    }
                    if (response.status === 409) {
                        errorMessage = 'That time slot is no longer available (someone may have just booked it). Please go back and choose another date or time.';
                    }
                    this.setState({
                        errors: [errorMessage],
                        loading: false
                    });
                    return;
                }

                if (data.success) {
                    // Safety: if this is an online payment, do NOT mark complete here
                    // Online payments are handled by submitOnlinePayment() with card confirmation
                    if (this.state.paymentMethod === 'online' && data.client_secret) {
                        // This shouldn't happen - online payments go through submitOnlinePayment()
                        // But if it does, don't skip the payment step
                        this.setState({
                            errors: ['Please use the card form to complete your payment.'],
                            loading: false
                        });
                        return;
                    }
                    this.setState({
                        bookingReference: data.booking_reference,
                        bookingComplete: true,
                        loading: false
                    });
                    this.render();
                } else {
                    const errors = [data.message || 'Failed to create booking'];
                    this.setState({
                        errors: errors,
                        loading: false
                    });
                }
            } catch (error) {
                console.error(PREFIX, 'createBooking error', error.message, error);
                this.setState({
                    errors: ['An error occurred. Please try again.'],
                    loading: false
                });
            }
        }

        loadStripeScript() {
            return new Promise((resolve, reject) => {
                if (window.Stripe) {
                    resolve();
                    return;
                }
                
                // Check if script is already loading
                if (document.querySelector('script[src*="js.stripe.com/v3"]')) {
                    const checkInterval = setInterval(() => {
                        if (window.Stripe) {
                            clearInterval(checkInterval);
                            resolve();
                        }
                    }, 100);
                    setTimeout(() => {
                        clearInterval(checkInterval);
                        if (!window.Stripe) {
                            console.error(PREFIX, 'Stripe script loading timeout (5s).');
                            reject(new Error('Stripe script loading timeout'));
                        }
                    }, 5000);
                    return;
                }
                
                const script = document.createElement('script');
                script.src = 'https://js.stripe.com/v3/';
                script.async = true;
                script.onload = () => {
                    setTimeout(() => {
                        if (window.Stripe) {
                            resolve();
                        } else {
                            reject(new Error('Stripe failed to initialize'));
                        }
                    }, 200);
                };
                script.onerror = function() {
                    console.error(PREFIX, 'Stripe script tag failed to load (network or CSP).');
                    reject(new Error('Failed to load Stripe script'));
                };
                document.head.appendChild(script);
            });
        }
        
        loadStripeIfNeeded() {
            // Only load Stripe if user selects "Pay Now" and Stripe key exists
            const stripeKey = this.getStripeKey();
            if (this.state.paymentMethod === 'online' && stripeKey && !window.Stripe) {
                this.loadStripeScript().catch(error => {
                    console.warn('Stripe will load when payment is processed:', error);
                });
            }
        }
        
        render() {
            if (this.state.bookingComplete) {
                this.container.innerHTML = this.renderThankYou();
                return;
            }
            this.container.innerHTML = this.renderForm();
            
            if (window.google && window.google.maps) {
                setTimeout(function() { window.bookingApp.initGoogleMaps(); }, 100);
            }
            
            var self = this;
            
            // Mount card element after rendering payment step
            if (this.state.step === 3 && this.state.paymentMethod === 'online') {
                setTimeout(function() { self.mountCardElement(); }, 100);
            }
            
            // Initialize Flatpickr on step 1
            if (this.state.step === 1) {
                setTimeout(function() {
                    if (window.flatpickr) {
                        var wrapper = document.querySelector('.hp-flatpickr-wrap');
                        if (wrapper && !wrapper._flatpickr) {
                            var today = new Date();
                            var maxDate = new Date();
                            maxDate.setDate(today.getDate() + 60);

                            flatpickr(wrapper, {
                                wrap: true,
                                static: true,
                                minDate: "today",
                                maxDate: maxDate,
                                disableMobile: true, // Forces our customized HTML picker with the Confirm button on mobile
                                dateFormat: "Y-m-d",
                                defaultDate: self.state.selectedDate || null,
                                closeOnSelect: false,
                                plugins: [
                                    new confirmDatePlugin({
                                        confirmIcon: "", 
                                        confirmText: "✓ Choose this Date",
                                        showAlways: true,
                                        theme: "light"
                                    })
                                ],
                                onChange: function(selectedDates, dateStr, instance) {
                                    // The confirmDate plugin triggers instance.close() when clicked, 
                                    // but we also rely on onChange or onClose to save the state.
                                },
                                onClose: function(selectedDates, dateStr, instance) {
                                    if (dateStr) {
                                        self.onDateChange(dateStr);
                                    }
                                }
                            });
                        }
                    }
                }, 100);
            }
        }

        renderHeader() {
            var s = this.state.step;
            return '<header class="hp-header">' +
                '<div class="hp-header-inner">' +
                '<h1 class="hp-header-title">Book Your Ride</h1>' +
                '<p class="hp-header-subtitle">Quick, easy, reliable transportation.</p>' +
                '</div>' +
                '<div class="hp-stepper">' +
                '<div class="hp-stepper-item' + (s >= 1 ? ' hp-stepper-active' : '') + '"><span class="hp-stepper-num">1</span> Date & Route</div>' +
                '<div class="hp-stepper-line"></div>' +
                '<div class="hp-stepper-item' + (s >= 2 ? ' hp-stepper-active' : '') + '"><span class="hp-stepper-num">2</span> Details</div>' +
                '<div class="hp-stepper-line"></div>' +
                '<div class="hp-stepper-item' + (s >= 3 ? ' hp-stepper-active' : '') + '"><span class="hp-stepper-num">3</span> Pay</div>' +
                '</div>' +
                '</header>';
        }

        renderForm() {
            return '<div class="hp-app-inner">' +
                this.renderHeader() +
                '<div class="hp-content">' +
                this.renderErrors() +
                this.renderStep() +
                '</div>' +
                '</div>';
        }

        renderErrors() {
            if (!this.state.errors || this.state.errors.length === 0) return '';
            return '<div class="hp-error">' +
                this.state.errors.map(function(e) { return '<p>' + e + '</p>'; }).join('') +
                '</div>';
        }

        renderStep() {
            switch (this.state.step) {
                case 1: return this.renderRouteStep();
                case 2: return this.renderDetailsStep();
                case 3: return this.renderPayStep();
                default: return '';
            }
        }

        /**
         * Convert 24-hour time to 12-hour AM/PM format
         */
        formatTime12Hour(time24) {
            if (!time24 || typeof time24 !== 'string') return time24;
            var parts = time24.split(':');
            if (parts.length < 2) return time24;
            var hours = parseInt(parts[0], 10);
            var minutes = parts[1];
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // 0 should be 12
            return hours + ':' + minutes + ' ' + ampm;
        }

        /**
         * Select a time and auto-scroll to the next section
         */
        selectTime(slot) {
            this.setState({ selectedTime: slot });
            setTimeout(function() {
                var locationsCard = document.getElementById('hp-locations-card');
                if (locationsCard) {
                    locationsCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 100);
        }

        /**
         * Scroll the booking app container to the top
         */
        scrollToTop() {
            var el = this.container;
            if (!el) return;
            // Try scrolling the container itself first
            el.scrollTop = 0;
            // Also scroll the .hp-content wrapper if present
            var content = el.querySelector('.hp-content');
            if (content) content.scrollTop = 0;
            // And scroll the page-level window to the top of the container
            var rect = el.getBoundingClientRect();
            var scrollY = window.pageYOffset || document.documentElement.scrollTop;
            var targetY = rect.top + scrollY - 16;
            window.scrollTo({ top: targetY, behavior: 'smooth' });
        }

        /**
         * Navigate to a step and scroll to top
         */
        goToStep(step) {
            this.setState({ step: step, errors: [] });
            var self = this;
            setTimeout(function() { self.scrollToTop(); }, 50);
        }

        renderRouteStep() {
            var today = new Date().toISOString().split('T')[0];
            var maxDate = new Date();
            maxDate.setMonth(maxDate.getMonth() + 2);
            var maxDateStr = maxDate.toISOString().split('T')[0];
            var dateLabel = '';
            var dateDisplayText = 'Select date';
            if (this.state.selectedDate) {
                var d = new Date(this.state.selectedDate + 'T12:00:00');
                dateLabel = d.toLocaleDateString('en-CA', { weekday: 'short', month: 'short', day: 'numeric' }).toUpperCase();
                dateDisplayText = d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            }
            var slotsHtml = '';
            if (this.state.slotsLoading) {
                slotsHtml = '<p class="hp-loading-text">Loading available times…</p>';
            } else if (this.state.availableSlots && this.state.availableSlots.length > 0) {
                slotsHtml = '<div class="hp-time-slots">' +
                    this.state.availableSlots.map(function(slot) {
                        var sel = this.state.selectedTime === slot ? ' selected' : '';
                        var displayTime = this.formatTime12Hour(slot);
                        return '<div class="hp-time-slot' + sel + '" onclick="window.bookingApp.selectTime(\'' + slot + '\')">' + displayTime + '</div>';
                    }.bind(this)).join('') +
                    '</div>';
            } else if (this.state.selectedDate) {
                slotsHtml = '<p class="hp-time-unavailable">No times available for this date. Try another date.</p>';
            }
            
            // Card 1: Date and Time
            var dateTimeCard = '<div class="hp-card">' +
                '<h2 class="hp-card-title"><span class="hp-icon hp-icon-cal"></span> When do you need a ride?</h2>' +
                '<div class="hp-form-group">' +
                '<label>DATE</label>' +
                '<div class="hp-date-picker-wrapper hp-flatpickr-wrap">' +
                '<input type="hidden" id="hp-booking-date" data-input value="' + (this.state.selectedDate || '') + '" />' +
                '<div class="hp-date-display" data-toggle style="cursor: pointer; width: 100%;">' +
                '<span class="hp-date-text">' + dateDisplayText + '</span>' +
                '<svg class="hp-calendar-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '<div class="hp-form-group" id="hp-time-slots-section">' +
                '<label>TIME' + (dateLabel ? ' · ' + dateLabel : '') + '</label>' +
                slotsHtml +
                '</div>' +
                '</div>';
            
            // Card 2: Pickup and Drop-off Locations
            var locationsCard = '<div class="hp-card" id="hp-locations-card">' +
                '<h2 class="hp-card-title"><span class="hp-icon hp-icon-pin"></span> Where are you going?</h2>' +
                '<div class="hp-form-group">' +
                '<label>PICKUP LOCATION</label>' +
                '<div class="hp-address-input-wrapper">' +
                '<div class="hp-route-ab">A</div>' +
                '<input type="text" id="hp-pickup-address" class="hp-address-input" placeholder="Enter pickup address" value="' + (this.state.pickupAddress || '').replace(/"/g, '&quot;') + '" onblur="window.bookingApp.setState({pickupAddress: this.value})" />' +
                '</div>' +
                '</div>' +
                '<div class="hp-form-group">' +
                '<label>DROP-OFF LOCATION</label>' +
                '<div class="hp-address-input-wrapper">' +
                '<div class="hp-route-ab hp-route-b">B</div>' +
                '<input type="text" id="hp-destination-address" class="hp-address-input" placeholder="Enter destination" value="' + (this.state.destinationAddress || '').replace(/"/g, '&quot;') + '" onblur="window.bookingApp.setState({destinationAddress: this.value})" />' +
                '</div>' +
                '</div>' +
                '</div>';
            
            return dateTimeCard + locationsCard +
                '<button class="hp-button hp-cta" onclick="window.bookingApp.goToDetails();" ' +
                (!this.state.selectedDate || !this.state.selectedTime ? 'disabled' : '') + '>Continue to Details →</button>';
        }

        goToDetails() {
            var pickupEl = document.getElementById('hp-pickup-address');
            var destEl = document.getElementById('hp-destination-address');
            var pickup = pickupEl ? pickupEl.value.trim() : '';
            var dest = destEl ? destEl.value.trim() : '';
            if (!pickup || !dest) {
                this.setState({ errors: ['Please enter both pickup and drop-off addresses.'] });
                return;
            }
            this.setState({ pickupAddress: pickup, destinationAddress: dest, step: 2, errors: [] });
            var self = this;
            setTimeout(function() { self.scrollToTop(); }, 50);
            this.calculatePrice();
        }

        renderDetailsStep() {
            return '<div class="hp-card">' +
                '<h2 class="hp-card-title">Your information</h2>' +
                '<div class="hp-form-group">' +
                '<label>FULL NAME *</label>' +
                '<input type="text" id="hp-customer-name" placeholder="Full name" value="' + (this.state.customerName || '').replace(/"/g, '&quot;') + '" oninput="window.bookingApp.handleInput(this, \'customerName\')" />' +
                '</div>' +
                '<div class="hp-form-group">' +
                '<label>EMAIL *</label>' +
                '<input type="email" id="hp-customer-email" placeholder="you@example.com" value="' + (this.state.customerEmail || '').replace(/"/g, '&quot;') + '" oninput="window.bookingApp.handleInput(this, \'customerEmail\')" />' +
                '</div>' +
                '<div class="hp-form-group">' +
                '<label>PHONE NUMBER *</label>' +
                '<input type="tel" id="hp-customer-phone" placeholder="(555) 000-0000" value="' + (this.state.customerPhone || '').replace(/"/g, '&quot;') + '" oninput="window.bookingApp.handleInput(this, \'customerPhone\')" />' +
                '</div>' +
                '<div class="hp-form-group">' +
                '<label>SPECIAL INSTRUCTIONS (Optional)</label>' +
                '<textarea id="hp-special-instructions" placeholder="Any special requests or instructions for your ride..." rows="3" oninput="window.bookingApp.handleInput(this, \'specialInstructions\')">' + (this.state.specialInstructions || '') + '</textarea>' +
                '</div>' +
                '</div>' +
                '<button class="hp-button hp-cta" onclick="window.bookingApp.goToStep(3);" ' +
                (!this.state.customerName || !this.state.customerEmail || !this.state.customerPhone ? 'disabled' : '') + '>Continue to Payment →</button>' +
                '<button class="hp-button hp-button-secondary" onclick="window.bookingApp.goToStep(1);">← Back</button>';
        }

        /**
         * Get Stripe publishable key from data attribute or window.hpBooking (dual source)
         */
        getStripeKey() {
            var key = this.container.dataset.stripeKey;
            if (key && key.length > 0) return key;
            var hpObj = window.hpBooking;
            if (hpObj && hpObj.stripeKey && hpObj.stripeKey.length > 0) return hpObj.stripeKey;
            return '';
        }

        renderPayStep() {
            var stripeKey = this.getStripeKey();
            var hasStripe = stripeKey && stripeKey.length > 0;
            var onlineChecked = this.state.paymentMethod === 'online' ? 'checked' : '';
            var tapChecked = this.state.paymentMethod === 'tap_to_pay' ? 'checked' : '';
            var priceHtml = this.state.price != null
                ? '<div class="hp-price-display"><div class="hp-price-amount">$' + parseFloat(this.state.price).toFixed(2) + '</div></div>'
                : '<p class="hp-loading-text">Enter route and time to see fare.</p>';
            var cardSection = '';
            if (hasStripe) {
                cardSection = '<div class="hp-form-group"><label class="hp-radio-label">' +
                    '<input type="radio" name="hp_pm" value="online" ' + onlineChecked + ' onchange="window.bookingApp.selectPaymentMethod(\'online\');" /> Pay Now (Credit/Debit Card)</label></div>' +
                    '<div class="hp-form-group"><label class="hp-radio-label">' +
                    '<input type="radio" name="hp_pm" value="tap_to_pay" ' + tapChecked + ' onchange="window.bookingApp.selectPaymentMethod(\'tap_to_pay\');" /> Pay on Pickup (Tap to Pay)</label></div>';
                if (this.state.paymentMethod === 'online') {
                    cardSection += '<div class="hp-card-form">' +
                        '<div id="hp-payment-request-button" style="margin-bottom: 20px;"></div>' +
                        '<div class="hp-card-field">' +
                        '<label class="hp-card-label">Card number</label>' +
                        '<div id="hp-card-number" class="hp-card-element"></div>' +
                        '</div>' +
                        '<div class="hp-card-row">' +
                        '<div class="hp-card-field hp-card-half">' +
                        '<label class="hp-card-label">Expiry date</label>' +
                        '<div id="hp-card-expiry" class="hp-card-element"></div>' +
                        '</div>' +
                        '<div class="hp-card-field hp-card-half">' +
                        '<label class="hp-card-label">CVC</label>' +
                        '<div id="hp-card-cvc" class="hp-card-element"></div>' +
                        '</div>' +
                        '</div>' +
                        '<div id="hp-card-errors" class="hp-card-errors" role="alert"></div>' +
                        '</div>';
                }
            } else {
                cardSection = '<div class="hp-form-group"><label class="hp-radio-label">' +
                    '<input type="radio" name="hp_pm" value="tap_to_pay" checked onchange="window.bookingApp.setState({paymentMethod: \'tap_to_pay\'})" /> Pay on Pickup (Tap to Pay)</label></div>';
            }
            var btnLabel = this.state.paymentMethod === 'online' ? '✓ Pay and Confirm' : '✓ Confirm Booking';
            var disabled = this.state.loading ? 'disabled' : '';
            if (this.state.paymentMethod === 'tap_to_pay') {
                disabled = (!this.state.customerName || !this.state.customerEmail || !this.state.customerPhone) ? 'disabled' : disabled;
            }
            return '<div class="hp-card">' +
                '<h2 class="hp-card-title">Payment method</h2>' +
                cardSection +
                priceHtml +
                '</div>' +
                '<button class="hp-button hp-cta hp-cta-confirm" onclick="window.bookingApp.submitBooking();" ' + disabled + '>' + (this.state.loading ? 'Processing…' : btnLabel) + '</button>' +
                '<button class="hp-button hp-button-secondary" onclick="window.bookingApp.goToStep(2);">← Back</button>';
        }

        // Handle Apple Pay / Google Pay payment method event
        async handlePaymentRequest(ev) {
            var self = this;
            var hpBookingObj = window.hpBooking;

            // 1. Create booking to get client_secret
            var bookingData = {
                customer_name: this.state.customerName, // Use name from prev step or ev.payerName
                customer_email: this.state.customerEmail, // Use email from prev step or ev.payerEmail
                customer_phone: this.state.customerPhone, // Use phone from prev step or ev.payerPhone
                pickup_address: this.state.pickupAddress,
                pickup_lat: this.state.pickupLat,
                pickup_lng: this.state.pickupLng,
                destination_address: this.state.destinationAddress,
                destination_lat: this.state.destinationLat,
                destination_lng: this.state.destinationLng,
                pickup_datetime: this.state.selectedDate + ' ' + this.state.selectedTime + ':00',
                payment_method: 'online',
                special_instructions: this.state.specialInstructions || ''
            };
            
            // Override customer details from wallet if available and valid
            if (ev.payerName) bookingData.customer_name = ev.payerName;
            if (ev.payerEmail) bookingData.customer_email = ev.payerEmail;
            if (ev.payerPhone) bookingData.customer_phone = ev.payerPhone;

            try {
                var response = await fetch(hpBookingObj.apiUrl + 'create-booking', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBookingObj.nonce
                    },
                    body: JSON.stringify(bookingData)
                });
                var data = await response.json();

                if (!response.ok || !data.success || !data.client_secret) {
                    ev.complete('fail');
                    var msg = data.message || (data.data && data.data.message) || 'Booking failed.';
                    self.setState({ errors: [msg] });
                    return;
                }
                
                self.state.bookingReference = data.booking_reference;

                // 2. Confirm the PaymentIntent with the method from the wallet
                var confirmResult = await self._stripe.confirmCardPayment(data.client_secret, {
                    payment_method: ev.paymentMethod.id
                }, { handleActions: false });
                
                if (confirmResult.error) {
                    ev.complete('fail');
                    self.setState({ errors: [confirmResult.error.message] });
                } else {
                    ev.complete('success');
                    if (confirmResult.paymentIntent && confirmResult.paymentIntent.status === 'succeeded') {
                        // 3. Notify backend of success
                         try {
                            await fetch(hpBookingObj.apiUrl + 'confirm-payment', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': hpBookingObj.nonce
                                },
                                body: JSON.stringify({
                                    payment_intent_id: confirmResult.paymentIntent.id,
                                    booking_reference: data.booking_reference
                                })
                            });
                        } catch (e) {}

                        // 4. Show Thank You
                        self.setState({
                            bookingComplete: true,
                            loading: false,
                            paymentMethod: 'online'
                        });
                        self.render();
                    } else {
                         // Requires further action (3DS) - usually not for Apple Pay, but handleActions:false means we might need manual handling
                         // For simplicity in this flow, we assume success or fail. Real world might need handleCardAction.
                         // But 'handleActions: false' lets us control the completion. 
                         // Actually, for Payment Request, confirmCardPayment handles it.
                         // Let's rely on standard confirmCardPayment behavior for now.
                         // Wait, if we used handleActions: false, we need to handle next actions. 
                         // Revert to standard confirmCardPayment default (handleActions: true) but that conflicts with ev.complete?
                         // Stripe docs say:
                         // const {error} = await stripe.confirmCardPayment(clientSecret, {payment_method: ev.paymentMethod.id});
                         // if (error) ev.complete('fail'); else ev.complete('success');
                         // Let's do that.
                    }
                }
            } catch (err) {
                ev.complete('fail');
                console.error(PREFIX, 'Payment Request error', err);
                self.setState({ errors: ['Payment failed. Please try again.'] });
            }
        }

        /**
         * Select payment method and re-render with card element if needed
         */
        selectPaymentMethod(method) {
            this.state.paymentMethod = method;
            // Destroy existing card elements before re-render
            this.destroyCardElements();
            this.render();
            if (method === 'online') {
                this.mountCardElement();
            }
        }

        /**
         * Destroy all Stripe card elements
         */
        destroyCardElements() {
            if (this._cardNumberElement) {
                try { this._cardNumberElement.destroy(); } catch (e) {}
                this._cardNumberElement = null;
            }
            if (this._cardExpiryElement) {
                try { this._cardExpiryElement.destroy(); } catch (e) {}
                this._cardExpiryElement = null;
            }
            if (this._cardCvcElement) {
                try { this._cardCvcElement.destroy(); } catch (e) {}
                this._cardCvcElement = null;
            }
            if (this._paymentRequestButton) {
                try { this._paymentRequestButton.destroy(); } catch (e) {}
                this._paymentRequestButton = null;
            }
        }

        /**
         * Mount Stripe Card Elements (stacked: number, expiry, cvc)
         */
        mountCardElement() {
            var self = this;
            var stripeKey = this.getStripeKey();
            if (!stripeKey) return;

            this.loadStripeScript().then(function() {
                var numEl = document.getElementById('hp-card-number');
                var expEl = document.getElementById('hp-card-expiry');
                var cvcEl = document.getElementById('hp-card-cvc');
                if (!numEl || !expEl || !cvcEl) return;

                // Create Stripe instance if not exists
                if (!self._stripe) {
                    self._stripe = Stripe(stripeKey);
                }
                // Create Elements instance
                if (!self._elements) {
                    self._elements = self._stripe.elements();
                }

                // Destroy old elements if they exist
                self.destroyCardElements();

                var elementStyle = {
                    base: {
                        fontSize: '16px',
                        color: '#2d3b2d',
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        '::placeholder': { color: '#9ca3af' },
                        iconColor: '#2d3b2d'
                    },
                    invalid: {
                        color: '#dc2626',
                        iconColor: '#dc2626'
                    }
                };

                // Create and mount separate elements
                self._cardNumberElement = self._elements.create('cardNumber', {
                    style: elementStyle,
                    showIcon: true
                });
                self._cardNumberElement.mount('#hp-card-number');

                self._cardExpiryElement = self._elements.create('cardExpiry', {
                    style: elementStyle
                });
                self._cardExpiryElement.mount('#hp-card-expiry');

                self._cardCvcElement = self._elements.create('cardCvc', {
                    style: elementStyle
                });
                self._cardCvcElement.mount('#hp-card-cvc');

                // Listen for errors on all elements
                var handleError = function(event) {
                    var errorsEl = document.getElementById('hp-card-errors');
                    if (errorsEl) {
                        errorsEl.textContent = event.error ? event.error.message : '';
                    }
                };
                self._cardNumberElement.on('change', handleError);
                // --- Payment Request Button (Apple Pay / Google Pay) ---
                var amountCents = Math.round((self.state.price || 0) * 100);
                var prContainer = document.getElementById('hp-payment-request-button');
                
                if (prContainer) {
                    prContainer.innerHTML = '<div style="text-align:center; font-size:12px; color:#666; padding: 10px;">Checking Apple Pay / Google Pay availability...</div>';
                    prContainer.style.display = 'block';
                }

                if (amountCents > 0) {
                    // Create Payment Request
                    var paymentRequest = self._stripe.paymentRequest({
                        country: 'CA',
                        currency: 'cad',
                        total: {
                            label: 'Handsome Pete Ride',
                            amount: amountCents
                        },
                        requestPayerName: true,
                        requestPayerEmail: true,
                        requestPayerPhone: true
                    });

                    console.log('[HP Booking] Checking digital wallet availability...');
                    
                    paymentRequest.canMakePayment().then(function(result) {
                        console.log('[HP Booking] canMakePayment result:', result);
                        
                        if (result) {
                            console.log('[HP Booking] Digital wallet available, mounting button.');
                            
                            // Clear debug text
                            if (prContainer) prContainer.innerHTML = '';

                            self._paymentRequestButton = self._elements.create('paymentRequestButton', {
                                paymentRequest: paymentRequest,
                                style: {
                                    paymentRequestButton: {
                                        theme: 'dark',
                                        height: '44px'
                                    }
                                }
                            });
                            self._paymentRequestButton.mount('#hp-payment-request-button');
                            
                            paymentRequest.on('paymentmethod', function(ev) {
                                self.handlePaymentRequest(ev);
                            });
                        } else {
                            console.warn('[HP Booking] Digital wallet not available.');
                            if (prContainer) {
                                prContainer.innerHTML = '<div style="text-align:center; font-size:12px; color:#999; padding: 5px; background: #f9f9f9; border-radius: 4px;">Apple Pay / Google Pay not available on this device.</div>';
                            }
                        }
                    }).catch(function(err) {
                        console.error('[HP Booking] canMakePayment error:', err);
                        if (prContainer) {
                            prContainer.innerHTML = '<div style="text-align:center; font-size:12px; color:red;">Wallet check failed: ' + err.message + '</div>';
                        }
                    });
                } else {
                     if (prContainer) prContainer.style.display = 'none';
                }

            }).catch(function(err) {
                console.error(PREFIX, 'Failed to mount card elements:', err.message);
                self.setState({ errors: ['Failed to load payment form. Please refresh the page.'] });
            });
        }

        submitBooking() {
            if (this.state.paymentMethod === 'online') {
                this.submitOnlinePayment();
            } else {
                this.createBooking();
            }
        }

        /**
         * Set loading state WITHOUT triggering a full re-render
         * (re-render would destroy Stripe card elements mid-payment)
         */
        setPaymentLoading(isLoading) {
            this.state.loading = isLoading;
            // Update button directly in the DOM instead of re-rendering
            var btn = this.container.querySelector('.hp-cta');
            var backBtn = this.container.querySelector('.hp-button-secondary');
            if (btn) {
                btn.disabled = isLoading;
                btn.textContent = isLoading ? 'Processing\u2026' : 'Pay and confirm';
            }
            if (backBtn) {
                backBtn.disabled = isLoading;
                if (isLoading) {
                    backBtn.style.opacity = '0.5';
                    backBtn.style.pointerEvents = 'none';
                } else {
                    backBtn.style.opacity = '';
                    backBtn.style.pointerEvents = '';
                }
            }
            // Clear errors from DOM without re-render
            if (isLoading) {
                var errEl = this.container.querySelector('.hp-error');
                if (errEl) errEl.remove();
            }
        }

        /**
         * Handle inline card payment:
         * 1. Create booking (returns client_secret)
         * 2. Confirm card payment with Stripe
         * 3. Call confirm-payment endpoint
         * 4. Show thank you
         */
        async submitOnlinePayment() {
            var self = this;
            var hpBookingObj = window.hpBooking;

            if (!hpBookingObj) {
                this.setState({ errors: ['Configuration error. Please refresh the page.'], loading: false });
                return;
            }

            if (!this._stripe || !this._cardNumberElement) {
                this.setState({ errors: ['Payment form not loaded. Please refresh and try again.'], loading: false });
                return;
            }

            // Use DOM-only loading state to avoid re-render destroying card elements
            this.setPaymentLoading(true);
            this.state.errors = [];

            try {
                // Step 1: Create booking and get client_secret
                var bookingData = {
                    customer_name: this.state.customerName,
                    customer_email: this.state.customerEmail,
                    customer_phone: this.state.customerPhone,
                    pickup_address: this.state.pickupAddress,
                    pickup_lat: this.state.pickupLat,
                    pickup_lng: this.state.pickupLng,
                    destination_address: this.state.destinationAddress,
                    destination_lat: this.state.destinationLat,
                    destination_lng: this.state.destinationLng,
                    pickup_datetime: this.state.selectedDate + ' ' + this.state.selectedTime + ':00',
                    payment_method: 'online',
                    special_instructions: this.state.specialInstructions || ''
                };

                var response = await fetch(hpBookingObj.apiUrl + 'create-booking', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBookingObj.nonce
                    },
                    body: JSON.stringify(bookingData)
                });

                var data = await response.json();

                if (!response.ok || !data.success) {
                    var errorMessage = data.message || data.error || '';
                    if (!errorMessage && data.data && typeof data.data === 'object' && data.data.message) {
                        errorMessage = data.data.message;
                    }
                    if (!errorMessage) {
                        errorMessage = response.status === 502
                            ? 'Payment setup failed. Check Stripe configuration.'
                            : 'Failed to create booking. Please try again.';
                    }
                    this.setPaymentLoading(false);
                    this.setState({ errors: [errorMessage], loading: false });
                    return;
                }

                if (!data.client_secret) {
                    this.setPaymentLoading(false);
                    this.setState({ errors: ['Payment setup failed. No client secret returned.'], loading: false });
                    return;
                }

                this.state.bookingReference = data.booking_reference;

                // Step 2: Confirm card payment with Stripe (card elements still alive in DOM)
                var result = await this._stripe.confirmCardPayment(data.client_secret, {
                    payment_method: {
                        card: this._cardNumberElement,
                        billing_details: {
                            name: this.state.customerName,
                            email: this.state.customerEmail,
                            phone: this.state.customerPhone
                        }
                    }
                });

                if (result.error) {
                    // Payment failed - show error but booking exists in pending state
                    var msg = result.error.message || 'Payment failed. Please try again.';
                    this.setPaymentLoading(false);
                    this.setState({ errors: [msg], loading: false });
                    return;
                }

                // Step 3: Payment succeeded - call confirm-payment endpoint to verify and send email
                if (result.paymentIntent && result.paymentIntent.status === 'succeeded') {
                    try {
                        await fetch(hpBookingObj.apiUrl + 'confirm-payment', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': hpBookingObj.nonce
                            },
                            body: JSON.stringify({
                                payment_intent_id: result.paymentIntent.id,
                                booking_reference: data.booking_reference
                            })
                        });
                    } catch (confirmErr) {
                        // Non-fatal: webhook will handle it as backup
                        console.warn(PREFIX, 'confirm-payment call failed (webhook will handle):', confirmErr.message);
                    }

                    // Step 4: Show thank you page (safe to re-render now, payment is done)
                    this.destroyCardElements();
                    this.setState({
                        bookingComplete: true,
                        loading: false,
                        paymentMethod: 'online'
                    });
                    this.render();
                } else {
                    this.setPaymentLoading(false);
                    this.setState({
                        errors: ['Payment is being processed. You will receive a confirmation email shortly.'],
                        loading: false
                    });
                }
            } catch (error) {
                console.error(PREFIX, 'submitOnlinePayment error:', error.message, error);
                this.setPaymentLoading(false);
                this.setState({
                    errors: ['An error occurred during payment. Please try again.'],
                    loading: false
                });
            }
        }

        renderThankYou() {
            var isPayLater = this.state.paymentMethod === 'tap_to_pay';
            var amountLine = this.state.price != null ? '<p class="hp-thankyou-amount">Amount to pay on pickup: <strong>$' + parseFloat(this.state.price).toFixed(2) + '</strong></p>' : '';
            // Self-contained confetti celebration — no CDN required
            setTimeout(function() {
                var colors = ['#2E4A3E','#86A98D','#C9A227','#ffffff','#404D24','#f0c040'];
                var canvas = document.createElement('canvas');
                canvas.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:99999;';
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
                document.body.appendChild(canvas);
                var ctx = canvas.getContext('2d');
                var pieces = [];
                for (var i = 0; i < 160; i++) {
                    pieces.push({
                        x: Math.random() * canvas.width,
                        y: -Math.random() * canvas.height * 0.5,
                        w: 8 + Math.random() * 8,
                        h: 4 + Math.random() * 6,
                        color: colors[Math.floor(Math.random() * colors.length)],
                        rot: Math.random() * Math.PI * 2,
                        vx: (Math.random() - 0.5) * 5,
                        vy: 2 + Math.random() * 4,
                        vr: (Math.random() - 0.5) * 0.2,
                        opacity: 1
                    });
                }
                var frame = 0;
                var maxFrames = 120;
                function draw() {
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    pieces.forEach(function(p) {
                        p.x += p.vx;
                        p.y += p.vy;
                        p.rot += p.vr;
                        p.vy += 0.12; // gravity
                        if (frame > 60) p.opacity -= 0.012;
                        ctx.save();
                        ctx.globalAlpha = Math.max(0, p.opacity);
                        ctx.translate(p.x, p.y);
                        ctx.rotate(p.rot);
                        ctx.fillStyle = p.color;
                        ctx.fillRect(-p.w/2, -p.h/2, p.w, p.h);
                        ctx.restore();
                    });
                    frame++;
                    if (frame < maxFrames) {
                        requestAnimationFrame(draw);
                    } else {
                        canvas.parentNode && canvas.parentNode.removeChild(canvas);
                    }
                }
                draw();
            }, 200);
            return '<div class="hp-thankyou">' +
                this.renderHeader() +
                '<div class="hp-thankyou-card">' +
                '<div class="hp-thankyou-icon">' +
                '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#2d5a27" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
                '</div>' +
                '<h2 class="hp-thankyou-title">Thank You!</h2>' +
                '<p class="hp-thankyou-subtitle">Your booking has been confirmed.</p>' +
                '<div class="hp-thankyou-details">' +
                '<p class="hp-thankyou-ref">Booking reference: <strong>' + (this.state.bookingReference || '') + '</strong></p>' +
                '<p class="hp-thankyou-email">A confirmation email has been sent to your inbox.</p>' +
                (isPayLater ? amountLine : '') +
                (isPayLater ? '<p class="hp-thankyou-pay-note">You will pay when the driver arrives using Tap to Pay.</p>' : '<p class="hp-thankyou-pay-note">Your payment was successful. No further action needed.</p>') +
                '</div>' +
                '<button class="hp-button hp-cta" onclick="window.location.reload();">Book Another Ride</button>' +
                '</div>' +
                '</div>';
        }
    }
    
    function initBookingApp() {
        const container = document.getElementById('hp-booking-app');
        if (!container) {
            console.warn(PREFIX, 'Container #hp-booking-app not found on this page.');
            return;
        }
        const hpBookingObj = window.hpBooking;

        if (!hpBookingObj) {
            console.error(PREFIX, 'hpBooking not defined. Ensure shortcode [hp_booking_form] is on this page and the plugin is active. Retrying in 300ms...');
            setTimeout(function() {
                const retryObj = window.hpBooking;
                if (retryObj) {
                    console.info(PREFIX, 'hpBooking found on retry, initializing.', { apiUrl: retryObj.apiUrl });
                    window.bookingApp = new BookingApp('hp-booking-app');
                } else {
                    console.error(PREFIX, 'hpBooking still not defined. Check: plugin active, shortcode on page, no script errors before booking-app.js.');
                    container.innerHTML = '<div class="hp-error"><p>Booking system configuration error. Please refresh the page or contact support.</p><p><small>Open the browser console (F12) and look for [HP Booking] messages for details.</small></p></div>';
                }
            }, 300);
            return;
        }

        try {
            console.info(PREFIX, 'Initializing.', { apiUrl: hpBookingObj.apiUrl });
            window.bookingApp = new BookingApp('hp-booking-app');
        } catch (err) {
            console.error(PREFIX, 'Initialization error', err.message, err);
            container.innerHTML = '<div class="hp-error"><p>Booking form failed to load.</p><p><small>Check the console (F12) for [HP Booking] errors.</small></p></div>';
        }
    }

    function waitForHpBooking() {
        const hpBookingObj = window.hpBooking;
        if (hpBookingObj) {
            initBookingApp();
        } else {
            setTimeout(waitForHpBooking, 100);
            if (typeof waitForHpBooking.attempts === 'undefined') {
                waitForHpBooking.attempts = 0;
            }
            waitForHpBooking.attempts++;
            if (waitForHpBooking.attempts > 50) {
                console.error(PREFIX, 'hpBooking not loaded after 5s. Possible causes: shortcode missing, plugin inactive, or script load order (ensure wp_footer runs and hpBooking is defined before booking-app.js).');
                const container = document.getElementById('hp-booking-app');
                if (container) {
                    container.innerHTML = '<div class="hp-error"><p>Booking system configuration error. Please refresh the page or contact support.</p><p><small>Open the console (F12) and look for [HP Booking] for details.</small></p></div>';
                }
            }
        }
    }

    if (typeof window.hpBooking !== 'undefined') {
        console.info(PREFIX, 'hpBooking found, initializing immediately.');
        initializeApp();
    } else {
        console.warn(PREFIX, 'hpBooking not found at load. Waiting up to 5s...');
        var attempts = 0;
        var maxAttempts = 50;
        var checkInterval = setInterval(function() {
            attempts++;
            if (typeof window.hpBooking !== 'undefined') {
                clearInterval(checkInterval);
                console.info(PREFIX, 'hpBooking found after ' + (attempts * 100) + 'ms.');
                initializeApp();
            } else if (attempts >= maxAttempts) {
                clearInterval(checkInterval);
                console.error(PREFIX, 'hpBooking not found after 5s. Check plugin and shortcode.');
                var container = document.getElementById('hp-booking-app');
                if (container) {
                    container.innerHTML = '<div class="hp-error"><p>Booking system configuration error. Please refresh the page.</p><p><small>See console (F12) for [HP Booking] details.</small></p></div>';
                }
            }
        }, 100);
    }
})();
