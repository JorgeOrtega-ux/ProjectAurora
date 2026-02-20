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
                throw new Error(`Error HTTP: ${response.status}`);
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
                throw new Error(`Error HTTP: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`[ApiService] Falló GET a ${url}:`, error);
            throw error;
        }
    }

    // NUEVO MÉTODO PARA SUBIR ARCHIVOS
    static async postFormData(url, formData) {
        try {
            // No pasamos 'Content-Type', el navegador lo establece a 'multipart/form-data' automáticamente
            const response = await fetch(url, {
                method: 'POST',
                body: formData 
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            return await response.json();
        } catch (error) {
            console.error(`[ApiService] Falló POST FormData a ${url}:`, error);
            throw error;
        }
    }
}