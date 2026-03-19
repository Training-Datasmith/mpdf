<?php

declare(strict_types=1);

namespace Mpdf\Language;

class LanguageToFont implements \Mpdf\Language\LanguageToFontInterface
{
    public function getLanguageOptions($llcc, $adobeCJK)
    {
        $tags = explode('-', $llcc);
        $lang = strtolower($tags[0]);
        $country = '';
        $script = '';
        if (!empty($tags[1])) {
            if (strlen($tags[1]) === 4) {
                $script = strtolower($tags[1]);
            } else {
                $country = strtolower($tags[1]);
            }
        }
        if (!empty($tags[2])) {
            $country = strtolower($tags[2]);
        }

        $unifont = '';
        $coreSuitable = false;

        switch ($lang) {
            /* European */
            case 'en':
            case 'eng': // English		// LATIN
            case 'eu':
            case 'eus': // Basque
            case 'br':
            case 'bre': // Breton
            case 'ca':
            case 'cat': // Catalan
            case 'co':
            case 'cos': // Corsican
            case 'kw':
            case 'cor': // Cornish
            case 'cy':
            case 'cym': // Welsh
            case 'cs':
            case 'ces': // Czech
            case 'da':
            case 'dan': // Danish
            case 'nl':
            case 'nld': // Dutch
            case 'et':
            case 'est': // Estonian
            case 'fo':
            case 'fao': // Faroese
            case 'fi':
            case 'fin': // Finnish
            case 'fr':
            case 'fra': // French
            case 'gl':
            case 'glg': // Galician
            case 'de':
            case 'deu': // German
            case 'ht':
            case 'hat': // Haitian; Haitian Creole
            case 'hu':
            case 'hun': // Hungarian
            case 'ga':
            case 'gle': // Irish
            case 'is':
            case 'isl': // Icelandic
            case 'it':
            case 'ita': // Italian
            case 'la':
            case 'lat': // Latin
            case 'lb':
            case 'ltz': // Luxembourgish
            case 'li':
            case 'lim': // Limburgish
            case 'lt':
            case 'lit': // Lithuanian
            case 'lv':
            case 'lav': // Latvian
            case 'gv':
            case 'glv': // Manx
            case 'no':
            case 'nor': // Norwegian
            case 'nn':
            case 'nno': // Norwegian Nynorsk
            case 'nb':
            case 'nob': // Norwegian Bokmål
            case 'pl':
            case 'pol': // Polish
            case 'pt':
            case 'por': // Portuguese
            case 'ro':
            case 'ron': // Romanian
            case 'gd':
            case 'gla': // Scottish Gaelic
            case 'es':
            case 'spa': // Spanish
            case 'sv':
            case 'swe': // Swedish
            case 'sl':
            case 'slv': // Slovene
            case 'sk':
            case 'slk': // Slovak
                $coreSuitable = true;
                break;

            case 'ru':
            case 'rus': // Russian	// CYRILLIC
            case 'ab':
            case 'abk': // Abkhaz
            case 'av':
            case 'ava': // Avaric
            case 'ba':
            case 'bak': // Bashkir
            case 'be':
            case 'bel': // Belarusian
            case 'bg':
            case 'bul': // Bulgarian
            case 'ce':
            case 'che': // Chechen
            case 'cv':
            case 'chv': // Chuvash
            case 'kk':
            case 'kaz': // Kazakh
            case 'kv':
            case 'kom': // Komi
            case 'ky':
            case 'kir': // Kyrgyz
            case 'mk':
            case 'mkd': // Macedonian
            case 'cu':
            case 'chu': // Old Church Slavonic
            case 'os':
            case 'oss': // Ossetian
            case 'sr':
            case 'srp': // Serbian
            case 'tg':
            case 'tgk': // Tajik
            case 'tt':
            case 'tat': // Tatar
            case 'tk':
            case 'tuk': // Turkmen
            case 'uk':
            case 'ukr':
            case 'el':
            case 'ell':
                // VIETNAMESE
            case 'vi':
            case 'vie': // Ukrainian
                $unifont = 'dejavusanscondensed';
                /* freeserif best coverage for supplements etc. */
                break;

            case 'hy':
            case 'hye':
            case 'ka':
            case 'kat':
                /* African */
            case 'nqo':  // ARMENIAN
                $unifont = 'dejavusans';
                break;
            case 'cop':
                /* Phillipine */
            case 'bku':
            case 'hnn':
            case 'tl':
            case 'tbw':
            case 'lis':  // COPTIC
                $unifont = 'quivira';
                break;

            case 'got':
                //CASE 'mn':  CASE 'mon':	// MONGOLIAN	(Vertical script)
                //CASE 'ug':  CASE 'uig':	// Uyghur
                //CASE 'uz':  CASE 'uzb':	// Uzbek
                //CASE 'az':  CASE 'azb':	// South Azerbaijani
                /* South Asian */
            case 'as':
            case 'asm':
            case 'bn':
            case 'ben':
            case 'ks':
            case 'kas':
            case 'hi':
            case 'hin':
                // Hindi	DEVANAGARI
            case 'bh':
            case 'bih':
                // Bihari (Bhojpuri, Magahi, and Maithili)
            case 'sa':
            case 'san':
            case 'gu':
            case 'guj':
            case 'pa':
            case 'pan':
            case 'mr':
            case 'mar':
            case 'ml':
            case 'mal':
            case 'ne':
            case 'nep':
            case 'or':
            case 'ori':
            case 'ta':
            case 'tam':
                //CASE 'dgo':	// TAKRI
            case 'dv':
            case 'div':
                //CASE 'ms':  CASE 'msa':	// Malay
                //CASE 'ban':	// BALINESE
                //CASE 'bya':	// BATAK
            case 'bug':  // GOTHIC
                $unifont = 'freeserif';
                break;
                //CASE 'bax':	// BAMUM
                //CASE 'ha':  CASE 'hau':	// Hausa
            case 'vai':  // VAI
                $unifont = 'freesans';
                break;
            case 'am':
            case 'amh': // Amharic ETHIOPIC
            case 'ti':
            case 'tir': // Tigrinya ETHIOPIC
                $unifont = 'abyssinicasil';
                break;

                /* Middle Eastern */
            case 'ar':
            case 'ara':
            case 'fa':
            case 'fas':
            case 'ps':
            case 'pus':
            case 'ku':
            case 'kur':
            case 'ur':
            case 'urd': // Arabic	NB Arabic text identified by Autofont will be marked as und-Arab
                $unifont = 'xbriyaz';
                break;
            case 'he':
            case 'heb': // HEBREW
            case 'yi':
            case 'yid': // Yiddish
                $unifont = 'taameydavidclm'; // dejavusans,dejavusanscondensed,freeserif are fine if you do not need cantillation marks
                break;

            case 'syr':  // SYRIAC
                $unifont = 'estrangeloedessa';
                break;

                //CASE 'arc':	// IMPERIAL_ARAMAIC
                //CASE ''ae:	// AVESTAN
            case 'xcr':
            case 'xlc':
            case 'xld':
                //CASE 'mid':	// MANDAIC
                //CASE 'peo':	// OLD_PERSIAN
            case 'phn':
                //CASE 'smp':	// SAMARITAN
            case 'uga':  // CARIAN
                $unifont = 'aegean';
                break;

                /* Central Asian */
            case 'bo':
            case 'bod': // TIBETAN
            case 'dz':
            case 'dzo': // Dzongkha
                $unifont = 'jomolhari';
                break;
            case 'kn':
            case 'kan': // Kannada
                $unifont = 'lohitkannada';
                break;
            case 'si':
            case 'sin': // SINHALA
                $unifont = 'kaputaunicode';
                break;
            case 'te':
            case 'tel': // TELUGU
                $unifont = 'pothana2000';
                break;

                // Sindhi (Arabic or Devanagari)
            case 'sd':
            case 'snd': // Sindhi
                $unifont = 'lateef';
                if ($country === 'in') {
                    $unifont = 'freeserif';
                }
                break;

                //CASE 'ccp':	// CHAKMA
                //CASE 'lep':	// LEPCHA
            case 'lif':  // LIMBU
                $unifont = 'sun-exta';
                break;
                //CASE 'sat':	// OL_CHIKI
                //CASE 'saz':	// SAURASHTRA
            case 'syl':  // SYLOTI_NAGRI
                $unifont = 'mph2bdamase';
                break;

                /* South East Asian */
            case 'km':
            case 'khm': // KHMER
                $unifont = 'khmeros';
                break;
            case 'lo':
            case 'lao': // LAO
                $unifont = 'dhyana';
                break;
            case 'my':
            case 'mya':
            case 'tdd':  // MYANMAR Burmese
                $unifont = 'tharlon';
                // zawgyi-one is non-unicode compliant but in wide usage
                // ayar is also not strictly compliant
                // padaukbook is unicode compliant
                break;
            case 'th':
            case 'tha': // THAI
                $unifont = 'garuda';
                break;
                //CASE 'cjm':	// CHAM
                //CASE 'jv':	// JAVANESE
            case 'su':  // SUNDANESE
                $unifont = 'sundaneseunicode';
                break;
            case 'blt':  // TAI_VIET
                $unifont = 'taiheritagepro';
                break;

                /* East Asian */
            case 'zh':
            case 'zho': // Chinese
                $unifont = 'sun-exta';
                if ($adobeCJK) {
                    $unifont = 'gb';
                    if ($country === 'hk' || $country === 'tw') {
                        $unifont = 'big5';
                    }
                }
                break;
            case 'ko':
            case 'kor': // HANGUL Korean
                $unifont = 'unbatang';
                if ($adobeCJK) {
                    $unifont = 'uhc';
                }
                break;
            case 'ja':
            case 'jpn': // Japanese HIRAGANA KATAKANA
                $unifont = 'sun-exta';
                if ($adobeCJK) {
                    $unifont = 'sjis';
                }
                break;
            case 'ii':
            case 'iii': // Nuosu; Yi
                $unifont = 'sun-exta';
                if ($adobeCJK) {
                    $unifont = 'gb';
                }
                break;

                /* American */
            case 'chr':  // CHEROKEE
            case 'oj':
            case 'oji': // Ojibwe; Chippewa
            case 'cr':
            case 'cre': // Cree CANADIAN_ABORIGINAL
            case 'iu':
            case 'iku': // Inuktitut
                $unifont = 'aboriginalsans';
                break;

                /* Undetermined language - script used */
            case 'und':
                $unifont = $this->fontByScript($script, $adobeCJK);
                break;
        }

        return [$coreSuitable, $unifont];
    }

    protected function fontByScript($script, $adobeCJK)
    {
        switch ($script) {
            /* European */
            case 'latn':
            case 'cyrl': // LATIN
                return 'dejavusanscondensed'; /* freeserif best coverage for supplements etc. */
            case 'cprt':
            case 'linb':
            case 'ital': // CYPRIOT
                return 'aegean';
            case 'glag':
            case 'shaw':
                //CASE 'merc':	// MEROITIC_CURSIVE
                //CASE 'mero':	// MEROITIC_HIEROGLYPHS
            case 'osma':
                //CASE 'sarb':	// OLD_SOUTH_ARABIAN
                //CASE 'prti':	// INSCRIPTIONAL_PARTHIAN
                //CASE 'phli':	// INSCRIPTIONAL_PAHLAVI
                /* Central Asian */
                //CASE 'orkh':	// OLD_TURKIC
                //CASE 'phag':	// PHAGS_PA		(Vertical script)
                /* South Asian */
                //CASE 'brah':	// BRAHMI
                //CASE 'kthi':	// KAITHI
            case 'khar':
                /* American */
            case 'dsrt': // GLAGOLITIC
                return 'mph2bdamase';
            case 'ogam':
            case 'tfng':
                /* Other */
            case 'brai': // OGHAM
                return 'dejavusans';
            case 'runr':
            case 'bopo':
                //CASE 'plrd':	// MIAO
            case 'yiii': // RUNIC
                return 'sun-exta';
                /* African */
            case 'egyp': // EGYPTIAN_HIEROGLYPHS
                return 'aegyptus';
            case 'ethi': // ETHIOPIC
                return 'abyssinicasil';

                /* Middle Eastern */
            case 'arab':  // ARABIC
                return 'xbriyaz';
            case 'xsux': // CUNEIFORM
                return 'akkadian';
            case 'mtei': // MEETEI_MAYEK
                return 'eeyekunicode';
                //CASE 'shrd':	// SHARADA
                //CASE 'sora':	// SORA_SOMPENG

                /* South East Asian */
            case 'kali': // KAYAH_LI
                return 'freemono';
                //CASE 'rjng':	// REJANG
            case 'lana': // TAI_THAM
                return 'lannaalif';
            case 'talu': // NEW_TAI_LUE
                return 'daibannasilbook';

                /* East Asian */
            case 'hans': // HAN (SIMPLIFIED)
                if ($adobeCJK) {
                    return 'gb';
                }
                return 'sun-exta';
        }

        return null;
    }

}
