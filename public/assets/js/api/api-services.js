// public/assets/js/api-services.js
export class ApiService {
    static async post(url, data = {}) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                console.error(`[Backend Error POST] ${url}:`, errorData);
                throw new Error(`Error HTTP: ${response.status} - ${errorData.message || 'Error del servidor'}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`[ApiService] Falló POST a ${url}:`, error);
            throw error;
        }
    }

    static async get(url) {
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                console.error(`[Backend Error GET] ${url}:`, errorData);
                throw new Error(`Error HTTP: ${response.status} - ${errorData.message || 'Error del servidor'}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`[ApiService] Falló GET a ${url}:`, error);
            throw error;
        }
    }

    static async postFormData(url, formData) {
        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData 
            });
            
            if (!response.ok) {
                const errorData = await response.json().catch(() => ({}));
                console.error(`[Backend Error FormData] ${url}:`, errorData);
                throw new Error(`Error HTTP: ${response.status} - ${errorData.message || 'Error del servidor'}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`[ApiService] Falló POST FormData a ${url}:`, error);
            throw error;
        }
    }
}