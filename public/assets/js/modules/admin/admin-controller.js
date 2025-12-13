/**
 * AdminController.js
 * Orquestador central para la sección de administración.
 * Gestiona la carga dinámica (Lazy Loading) y el ciclo de vida (Init/Destroy) de los submódulos.
 */

let activeController = null;

export const AdminController = {
    
    /**
     * Carga la lógica específica para una sub-sección de admin.
     * @param {string} section - La ruta completa, ej: 'admin/users'
     */
    loadSection: async (section) => {
        // 1. Limpieza de memoria (Destroy anterior)
        if (activeController) {
            // Si el controlador anterior tiene método destroy, lo ejecutamos para limpiar eventos
            if (typeof activeController.destroy === 'function') {
                console.log('AdminController: Destruyendo controlador previo...');
                activeController.destroy();
            }
            activeController = null;
        }

        // 2. Identificar qué módulo cargar
        const subSection = section.replace('admin/', '');
        let modulePath = null;

        switch (subSection) {
            case 'users':
                modulePath = './users-controller.js';
                break;
            case 'server':
                modulePath = './server-controller.js';
                break;
            case 'dashboard':
                // El dashboard por ahora es solo visual, no requiere JS complejo
                console.log('AdminController: Dashboard cargado (Sin lógica específica).');
                return;
            default:
                console.warn(`AdminController: No hay controlador definido para '${subSection}'`);
                return;
        }

        // 3. Importación Dinámica y Ejecución
        if (modulePath) {
            try {
                console.log(`AdminController: Cargando módulo ${modulePath}...`);
                
                // Importamos el archivo JS bajo demanda
                const module = await import(modulePath);
                
                // Instanciamos o asignamos el controlador
                activeController = module.default || module;
                
                // Iniciamos la lógica
                if (activeController && typeof activeController.init === 'function') {
                    activeController.init();
                    console.log(`AdminController: ${subSection} inicializado correctamente.`);
                }

            } catch (error) {
                console.error(`AdminController: Error cargando el módulo ${subSection}`, error);
                // Aquí podrías mostrar un Toast de error si lo deseas
            }
        }
    }
};