// safeFetch.js - Utility for handling API requests
export async function safeFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);
        
        // Check for HTTP errors
        if (!response.ok) {
            throw new Error(`HTTP error! Status: ${response.status}`);
        }
        
        // Check if response is JSON
        const contentType = response.headers.get('content-type');
        if (contentType && contentType.includes('application/json')) {
            return await response.json();
        }
        
        // Handle non-JSON responses
        const text = await response.text();
        return {
            success: true,
            data: text
        };
    } catch (error) {
        console.error('API Request Error:', error);
        return {
            success: false,
            error: error.message,
            details: {
                url,
                method: options.method || 'GET',
                timestamp: new Date().toISOString()
            }
        };
    }
}