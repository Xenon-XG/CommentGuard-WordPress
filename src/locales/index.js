/**
 * Locale registry
 *
 * To add a new language:
 * 1. Create a new file in this directory (e.g. ja.js, ko.js, fr.js)
 * 2. Copy all keys from zh.js or en.js and translate the values
 * 3. Import it below and add an entry to `locales`
 */
import zh from './zh';
import en from './en';

const locales = {
    zh: { label: '中文', translations: zh },
    en: { label: 'English', translations: en },
    // ja: { label: '日本語', translations: ja },
    // ko: { label: '한국어', translations: ko },
};

export default locales;
