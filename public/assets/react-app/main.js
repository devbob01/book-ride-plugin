// This file will be the compiled output from main.jsx
// For now, we'll use the same code but ensure it's JavaScript-compatible
// In production, compile main.jsx with Babel/Webpack

// Include the JSX code directly (for simplicity in this implementation)
// Production should use a build process

(function() {
    'use strict';
    
    // Copy the code from main.jsx here or load it dynamically
    // For now, we'll create a simple version that works without React
    
    const script = document.createElement('script');
    script.src = hpBooking.pluginUrl + 'public/assets/react-app/main.jsx';
    script.type = 'module';
    document.head.appendChild(script);
    
    // Fallback: Load the inline version
    setTimeout(() => {
        if (!window.bookingApp) {
            // Load inline version as fallback
            const inlineScript = document.createElement('script');
            inlineScript.innerHTML = document.getElementById('hp-booking-app').dataset.inlineScript || '';
            document.head.appendChild(inlineScript);
        }
    }, 1000);
})();
