const I18nManager = {
    t: (key, params = []) => {
        if (!window.TRANSLATIONS) {
            return key;
        }
        
        const keys = key.split('.');
        let value = window.TRANSLATIONS;

        for (const k of keys) {
            if (value && value[k]) {
                value = value[k];
            } else {
                return key; 
            }
        }
        
        let text = (typeof value === 'string') ? value : key;

        if (params && params.length > 0) {
            params.forEach(param => {
                text = text.replace('%s', param);
            });
        }

        return text;
    }
};

export { I18nManager };