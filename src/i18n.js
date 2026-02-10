import { createContext, useContext, useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import locales from './locales';

const LangContext = createContext();
const localeKeys = Object.keys(locales);

export function LangProvider({ children }) {
    const [lang, setLang] = useState(() => {
        try {
            const saved = localStorage.getItem('acm_lang');
            return saved && locales[saved] ? saved : 'zh';
        } catch {
            return 'zh';
        }
    });

    const changeLang = useCallback((newLang) => {
        if (locales[newLang]) {
            setLang(newLang);
            try { localStorage.setItem('acm_lang', newLang); } catch { }
            // Sync to backend so the AI responds in this language
            apiFetch({
                path: '/ai-moderator/v1/settings',
                method: 'POST',
                data: { ui_language: newLang, ui_language_name: locales[newLang].label },
            }).catch(() => { }); // fire-and-forget
        }
    }, []);

    const t = useCallback((key) => {
        const table = locales[lang]?.translations;
        return table?.[key] || key;
    }, [lang]);

    const langOptions = localeKeys.map(k => ({ value: k, label: locales[k].label }));

    return (
        <LangContext.Provider value={{ lang, changeLang, langOptions, t }}>
            {children}
        </LangContext.Provider>
    );
}

export function useLang() {
    return useContext(LangContext);
}
