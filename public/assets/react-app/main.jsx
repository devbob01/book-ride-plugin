/**
 * Main React Booking Component
 * Handles the complete booking flow
 */

(function() {
    'use strict';
    
    // Simple React-like component system (or use React if available)
    // For production, this should be compiled with webpack/babel
    
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
            this.render();
            this.loadGoogleMaps();
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
                        let addr = place.formatted_address;
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
                        let addr = place.formatted_address;
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
            this.state = { ...this.state, ...newState };
            this.render();
        }
        
        async loadAvailability(date) {
            try {
                const response = await fetch(`${hpBooking.apiUrl}availability?date=${date}`, {
                    headers: {
                        'X-WP-Nonce': hpBooking.nonce
                    }
                });
                const data = await response.json();
                this.setState({
                    availableSlots: data.available_slots || []
                });
            } catch (error) {
                console.error('Error loading availability:', error);
            }
        }
        
        async validateAddress(type, lat, lng) {
            try {
                const response = await fetch(`${hpBooking.apiUrl}validate-address`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBooking.nonce
                    },
                    body: JSON.stringify({
                        address: type === 'pickup' ? this.state.pickupAddress : this.state.destinationAddress,
                        type: type
                    })
                });
                const data = await response.json();
                
                if (!data.in_service_area) {
                    this.setState({
                        errors: [...(this.state.errors || []), `${type} address must be in Grafton, Cobourg, Port Hope, or Brighton`]
                    });
                }
            } catch (error) {
                console.error('Error validating address:', error);
            }
        }
        
        async calculatePrice() {
            if (!this.state.pickupAddress || !this.state.destinationAddress) return;
            
            this.setState({ loading: true });
            
            try {
                const response = await fetch(`${hpBooking.apiUrl}calculate-price`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBooking.nonce
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
                
                if (data.distance_km) {
                    this.setState({
                        distance: data.distance_km,
                        duration: data.duration_minutes,
                        price: data.price,
                        loading: false
                    });
                }
            } catch (error) {
                console.error('Error calculating price:', error);
                this.setState({ loading: false });
            }
        }
        
        async createBooking() {
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
                payment_method: this.state.paymentMethod
            };
            
            try {
                const response = await fetch(`${hpBooking.apiUrl}create-booking`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': hpBooking.nonce
                    },
                    body: JSON.stringify(bookingData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    this.setState({
                        bookingComplete: true,
                        bookingReference: data.booking_reference,
                        loading: false
                    });
                    
                    // If pay now, process Stripe payment
                    if (data.payment_intent_client_secret && this.state.paymentMethod === 'online') {
                        this.processStripePayment(data.payment_intent_client_secret);
                    }
                } else {
                    this.setState({
                        errors: [data.message || 'Failed to create booking'],
                        loading: false
                    });
                }
            } catch (error) {
                this.setState({
                    errors: ['An error occurred. Please try again.'],
                    loading: false
                });
            }
        }
        
        async processStripePayment(clientSecret) {
            const stripeKey = this.container.dataset.stripeKey;
            if (!stripeKey || !window.Stripe) {
                console.error('Stripe not loaded');
                return;
            }
            
            const stripe = Stripe(stripeKey);
            const { error } = await stripe.confirmPayment({
                clientSecret: clientSecret,
                confirmParams: {
                    return_url: window.location.href
                }
            });
            
            if (error) {
                this.setState({
                    errors: [error.message]
                });
            }
        }
        
        render() {
            if (this.state.bookingComplete) {
                this.container.innerHTML = this.renderConfirmation();
                return;
            }
            
            this.container.innerHTML = this.renderForm();
            
            // Re-initialize Google Maps after render
            if (window.google && window.google.maps) {
                setTimeout(() => this.initGoogleMaps(), 100);
            }
        }
        
        renderForm() {
            return `
                <div class="hp-booking-step">
                    <h2>Book Your Ride</h2>
                    ${this.renderErrors()}
                    ${this.renderStep()}
                </div>
            `;
        }
        
        renderErrors() {
            if (!this.state.errors || this.state.errors.length === 0) return '';
            return `
                <div class="hp-error">
                    ${this.state.errors.map(e => `<p>${e}</p>`).join('')}
                </div>
            `;
        }
        
        renderStep() {
            switch (this.state.step) {
                case 1: return this.renderDateStep();
                case 2: return this.renderTimeStep();
                case 3: return this.renderAddressStep();
                case 4: return this.renderPriceStep();
                case 5: return this.renderPaymentStep();
                case 6: return this.renderCustomerInfoStep();
                default: return '';
            }
        }
        
        renderDateStep() {
            const today = new Date().toISOString().split('T')[0];
            const maxDate = new Date();
            maxDate.setMonth(maxDate.getMonth() + 2);
            const maxDateStr = maxDate.toISOString().split('T')[0];
            
            return `
                <h3>Step 1: Select Date</h3>
                <div class="hp-form-group">
                    <label for="hp-booking-date">Pickup Date</label>
                    <input 
                        type="date" 
                        id="hp-booking-date" 
                        min="${today}" 
                        max="${maxDateStr}"
                        value="${this.state.selectedDate || ''}"
                        onchange="window.bookingApp.setState({selectedDate: this.value}); window.bookingApp.loadAvailability(this.value);"
                    />
                </div>
                <button class="hp-button" onclick="window.bookingApp.setState({step: 2});" ${!this.state.selectedDate ? 'disabled' : ''}>
                    Next: Select Time
                </button>
            `;
        }
        
        renderTimeStep() {
            if (!this.state.availableSlots || this.state.availableSlots.length === 0) {
                return `
                    <h3>Step 2: Select Time</h3>
                    <p>Loading available times...</p>
                    <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 1});">
                        Back
                    </button>
                `;
            }
            
            return `
                <h3>Step 2: Select Time</h3>
                <div class="hp-time-slots">
                    ${this.state.availableSlots.map(slot => `
                        <div 
                            class="hp-time-slot ${this.state.selectedTime === slot ? 'selected' : ''}"
                            onclick="window.bookingApp.setState({selectedTime: '${slot}'})"
                        >
                            ${slot}
                        </div>
                    `).join('')}
                </div>
                <button class="hp-button" onclick="window.bookingApp.setState({step: 3});" ${!this.state.selectedTime ? 'disabled' : ''}>
                    Next: Enter Addresses
                </button>
                <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 1});">
                    Back
                </button>
            `;
        }
        
        renderAddressStep() {
            return `
                <h3>Step 3: Enter Addresses</h3>
                <div class="hp-form-group">
                    <label for="hp-pickup-address">Pickup Address</label>
                    <input 
                        type="text" 
                        id="hp-pickup-address" 
                        placeholder="Enter pickup address"
                        value="${this.state.pickupAddress || ''}"
                        onchange="window.bookingApp.setState({pickupAddress: this.value})"
                    />
                    <small>Service available in Grafton, Cobourg, Port Hope, and Brighton only</small>
                </div>
                <div class="hp-form-group">
                    <label for="hp-destination-address">Destination Address</label>
                    <input 
                        type="text" 
                        id="hp-destination-address" 
                        placeholder="Enter destination address"
                        value="${this.state.destinationAddress || ''}"
                        onchange="window.bookingApp.setState({destinationAddress: this.value})"
                    />
                    <small>Service available in Grafton, Cobourg, Port Hope, and Brighton only</small>
                </div>
                <button class="hp-button" onclick="window.bookingApp.calculatePrice(); window.bookingApp.setState({step: 4});" ${!this.state.pickupAddress || !this.state.destinationAddress ? 'disabled' : ''}>
                    Next: Review Price
                </button>
                <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 2});">
                    Back
                </button>
            `;
        }
        
        renderPriceStep() {
            if (this.state.loading) {
                return `
                    <h3>Step 4: Calculating Price</h3>
                    <p>Please wait...</p>
                `;
            }
            
            if (!this.state.price) {
                return `
                    <h3>Step 4: Review Price</h3>
                    <p>Calculating...</p>
                    <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 3});">
                        Back
                    </button>
                `;
            }
            
            return `
                <h3>Step 4: Review Price</h3>
                <div class="hp-price-display">
                    <div class="hp-price-amount">$${parseFloat(this.state.price).toFixed(2)}</div>
                    <div class="hp-price-breakdown">
                        ${this.state.distance.toFixed(2)} km × $1.75/km
                        <br>
                        Estimated duration: ${this.state.duration} minutes
                    </div>
                </div>
                <button class="hp-button" onclick="window.bookingApp.setState({step: 5});">
                    Next: Payment Options
                </button>
                <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 3});">
                    Back
                </button>
            `;
        }
        
        renderPaymentStep() {
            return `
                <h3>Step 5: Payment Method</h3>
                <div class="hp-form-group">
                    <label>
                        <input 
                            type="radio" 
                            name="payment_method" 
                            value="online" 
                            ${this.state.paymentMethod === 'online' ? 'checked' : ''}
                            onchange="window.bookingApp.setState({paymentMethod: 'online'})"
                        />
                        Pay Now (Credit/Debit Card)
                    </label>
                </div>
                <div class="hp-form-group">
                    <label>
                        <input 
                            type="radio" 
                            name="payment_method" 
                            value="tap_to_pay" 
                            ${this.state.paymentMethod === 'tap_to_pay' ? 'checked' : ''}
                            onchange="window.bookingApp.setState({paymentMethod: 'tap_to_pay'})"
                        />
                        Pay on Pickup (Tap to Pay)
                    </label>
                </div>
                <button class="hp-button" onclick="window.bookingApp.setState({step: 6});">
                    Next: Customer Information
                </button>
                <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 4});">
                    Back
                </button>
            `;
        }
        
        renderCustomerInfoStep() {
            return `
                <h3>Step 6: Your Information</h3>
                <div class="hp-form-group">
                    <label for="hp-customer-name">Full Name *</label>
                    <input 
                        type="text" 
                        id="hp-customer-name" 
                        required
                        value="${this.state.customerName || ''}"
                        onchange="window.bookingApp.setState({customerName: this.value})"
                    />
                </div>
                <div class="hp-form-group">
                    <label for="hp-customer-email">Email *</label>
                    <input 
                        type="email" 
                        id="hp-customer-email" 
                        required
                        value="${this.state.customerEmail || ''}"
                        onchange="window.bookingApp.setState({customerEmail: this.value})"
                    />
                </div>
                <div class="hp-form-group">
                    <label for="hp-customer-phone">Phone Number *</label>
                    <input 
                        type="tel" 
                        id="hp-customer-phone" 
                        required
                        value="${this.state.customerPhone || ''}"
                        onchange="window.bookingApp.setState({customerPhone: this.value})"
                    />
                </div>
                <button 
                    class="hp-button" 
                    onclick="window.bookingApp.createBooking();"
                    ${this.state.loading ? 'disabled' : ''}
                    ${!this.state.customerName || !this.state.customerEmail || !this.state.customerPhone ? 'disabled' : ''}
                >
                    ${this.state.loading ? 'Processing...' : 'Confirm Booking'}
                </button>
                <button class="hp-button hp-button-secondary" onclick="window.bookingApp.setState({step: 5});">
                    Back
                </button>
            `;
        }
        
        renderConfirmation() {
            return `
                <div class="hp-booking-step">
                    <div class="hp-success">
                        <h2>Booking Confirmed!</h2>
                        <p><strong>Booking Reference:</strong> ${this.state.bookingReference}</p>
                        <p>You will receive a confirmation email and SMS shortly.</p>
                        ${this.state.paymentMethod === 'tap_to_pay' ? '<p>You will pay when the driver arrives using Tap to Pay.</p>' : ''}
                    </div>
                </div>
            `;
        }
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initBookingApp);
    } else {
        initBookingApp();
    }
    
    function initBookingApp() {
        const container = document.getElementById('hp-booking-app');
        if (container) {
            window.bookingApp = new BookingApp('hp-booking-app');
        }
    }
})();
