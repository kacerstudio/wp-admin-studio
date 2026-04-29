<?php
/**
 * Plugin Name: WP Admin Studio
 * Plugin URI: https://kacer.studio/wpadminstudio
 * Description: Professional WordPress customization: admin settings, pages & posts, translations, custom scripts & codes, robots.txt & .htaccess editor
 * Version: 1.9.3
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: KACER STUDIO s.r.o.
 * Author URI: https://kacer.studio
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-admin-studio
 */

if (!defined('ABSPATH')) exit;

// GitHub auto-updater
require_once plugin_dir_path(__FILE__) . 'updater.php';
new WPAdminStudioUpdater(__FILE__, WPAdminStudio::VERSION);

if (!function_exists('wpc_current_year')) {
    function wpc_current_year() {
        return gmdate('Y');
    }
}

class WPAdminStudio {
    const VERSION = '1.9.3';
    const MAX_UPLOAD_SIZE = 5242880; 
    const MAX_FILE_SIZE = 5242880; 
    
    private $option_name = 'wpc_settings';
    private $lang_option = 'wpc_language';
    private $restoring_maps_key = false;
    private $options_cache = null;
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_post_wpc_export_settings', array($this, 'export_settings'));
        add_action('admin_post_wpc_import_settings', array($this, 'import_settings'));
        add_action('wp_ajax_wpc_change_language', array($this, 'ajax_change_language'));
        add_action('wp_ajax_wpc_submit_bug_report', array($this, 'ajax_submit_bug_report'));
        add_action('wp_ajax_wpc_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_wpc_restore_htaccess', array($this, 'ajax_restore_htaccess'));
        add_action('wp_ajax_wpc_export_translations', array($this, 'ajax_export_translations'));
        add_action('admin_post_wpc_import_translations', array($this, 'import_translations'));
        add_action('admin_notices', array($this, 'show_duplicate_notice'));
        add_filter('plugin_row_meta', array($this, 'modify_plugin_links'), 10, 2);
        $this->init_features();
    }
    
    private function get_lang() {
        return get_option($this->lang_option, 'cs');
    }
    
    private function get_user_translation($key) {
        $locale = get_user_locale();
        
        $lang_map = array(
            'cs_CZ' => 'cs',
            'en_US' => 'en',
            'en_GB' => 'en',
            'de_DE' => 'de',
            'de_DE_formal' => 'de',
            'de_CH' => 'de',
            'de_CH_informal' => 'de',
            'sk_SK' => 'sk',
            'pl_PL' => 'pl'
        );
        
        $lang = isset($lang_map[$locale]) ? $lang_map[$locale] : 'en';
        
        $translations = array(
            'cs' => array(
                'duplicate_action' => 'Vytvořit kopii',
                'duplicate_success' => 'Kopie byla vytvořena a otevřena pro editaci.'
            ),
            'en' => array(
                'duplicate_action' => 'Create copy',
                'duplicate_success' => 'Copy has been created and opened for editing.'
            ),
            'de' => array(
                'duplicate_action' => 'Kopie erstellen',
                'duplicate_success' => 'Kopie wurde erstellt und zur Bearbeitung geöffnet.'
            ),
            'sk' => array(
                'duplicate_action' => 'Vytvoriť kópiu',
                'duplicate_success' => 'Kópia bola vytvorená a otvorená na úpravu.'
            ),
            'pl' => array(
                'duplicate_action' => 'Utwórz kopię',
                'duplicate_success' => 'Kopia została utworzona i otwarta do edycji.'
            )
        );
        
        return isset($translations[$lang][$key]) ? $translations[$lang][$key] : $translations['en'][$key];
    }
    
    private function t($key) {
        $translations = array(
            'cs' => array(
                'page_title' => 'WP Admin Studio',
                'version' => 'Verze ' . self::VERSION,
                'author' => 'Vytvořilo <a href="https://kacer.studio" target="_blank">KACER STUDIO s.r.o.</a>',
                'bulk_actions' => 'Hromadné akce',
                'enable_all' => 'Zapnout vše',
                'disable_all' => 'Vypnout vše',
                'import_export' => 'Záloha nastavení',
                'export' => 'Exportovat',
                'import' => 'Importovat',
                'export_desc' => 'Stáhne JSON soubor s kompletním nastavením pluginu.',
                'import_desc' => 'Nahrajte JSON soubor pro obnovení nastavení z dřívější zálohy.',
                'save' => 'Uložit změny',
                'saving' => 'Ukládám',
                'settings_saved' => 'Uloženo!',
                'all_enabled' => 'Všechna nastavení byla zapnuta!',
                'all_disabled' => 'Všechna nastavení byla vypnuta!',
                'import_success' => 'Nastavení bylo úspěšně importováno!',
                'import_error' => 'Chyba: Soubor nebyl nahrán.',
                'import_invalid' => 'Chyba: Neplatný soubor nastavení.',
                'lang_switch' => 'Přepnout jazyk',
                
                'admin_section' => 'Administrace',
                'editor_section' => 'Stránky a příspěvky',
                'frontend' => 'Frontend a výkon',
                'comments' => 'Komentáře',
                'forms' => 'Formuláře',
                'translations' => 'Vlastní překlady',
                'nav_admin' => 'Admin',
                'nav_scripts' => 'Skripty',
                'nav_maintenance' => 'Údržba',
                'nav_login' => 'Přihlášení',
                'nav_editor' => 'Editor',
                'nav_frontend' => 'Frontend',
                'nav_comments' => 'Komentáře',
                'nav_forms' => 'Formuláře',
                'nav_translations' => 'Překlady',
                'nav_system' => 'Systém',
                'nav_backup' => 'Záloha',
                
                'admin_bar' => 'Skrýt položky z horní lišty',
                'admin_bar_tip' => 'Zaškrtněte položky, které chcete odstranit z horního admin baru WordPress.',
                'admin_bar_logo' => 'WordPress logo (včetně submenu s odkazy na dokumentaci)',
                'admin_bar_updates' => 'Aktualizace',
                'admin_bar_comments' => 'Komentáře',
                'admin_bar_new' => 'Akce "Přidat nový" (příspěvek, stránku, médium...)',
                'admin_bar_view' => 'Odkaz "Zobrazit web"',
                'admin_bar_avatar' => 'Ikona avatara u jména uživatele (pouze desktop)',
                
                'login_lang' => 'Skrýt přepínač jazyků',
                'login_lang_desc' => 'Skrýt přepínač jazyků',
                'login_lang_tip' => 'Odstraní dropdown menu pro výběr jazyka z přihlašovací stránky WordPress (wp-login.php).',
                
                'hide_updates' => 'Skrýt aktualizace pro non-admin uživatele',
                'hide_updates_desc' => 'Pouze administrátoři uvidí notifikace o aktualizacích',
                'hide_updates_tip' => 'Skryje oznámení o dostupných aktualizacích pluginů, témat a WordPressu pro všechny uživatele kromě administrátorů. Snižuje zmatek pro editory a přispěvatele.',
                
                
                'disable_auto_update_emails' => 'Zakázat e-maily o automatických aktualizacích',
                'disable_auto_update_emails_desc' => 'Zakázat e-maily o automatických aktualizacích',
                'disable_auto_update_emails_tip' => 'WordPress po každé automatické aktualizaci jádra, pluginů nebo témat posílá e-mail. Tato volba ho utiší.',
                
                'hide_admin_notices' => 'Informační hlášky',
                'hide_admin_notices_desc' => 'Skrýt informační hlášky pro všechny uživatele',
                'hide_admin_notices_tip' => 'Skryje informační lišty pluginů a WordPressu pro všechny přihlášené uživatele. Volitelně lze hlášky ponechat viditelné pro konkrétního administrátora.',
                'show_notices_current_user_desc' => 'Zobrazit hlášky pouze pro: ',
                'hide_howdy' => 'Skrýt "Přihlášený uživatel:" z admin baru',
                'hide_howdy_desc' => 'Zobrazit pouze jméno uživatele',
                'hide_howdy_tip' => 'Odstraní text "Přihlášený uživatel:" z pravého horního rohu admin baru a ponechá pouze jméno přihlášeného uživatele.',
                
                'hide_wp_version' => 'Skrýt verzi WordPressu z admin footeru',
                'hide_wp_version_desc' => 'Odstraní "Děkujeme, že používáte WordPress. Verze X.X.X"',
                'hide_wp_version_tip' => 'Skryje informaci o verzi WordPressu z patičky administrace.',
                
                'hide_dashboard_widgets' => 'Skrýt widgety na nástěnce',
                'hide_dashboard_widgets_tip' => 'Zaškrtněte widgety, které mají být na nástěnce skryty. Seznam widgetů se automaticky aktualizuje při každé návštěvě nástěnky.',
                
                'admin_page_titles' => 'Vlastní názvy stránek v administraci',
                'admin_page_titles_desc' => 'Upravit název v záložce prohlížeče',
                'admin_page_titles_tip' => 'Umožňuje nastavit vlastní formát názvu stránky, který se zobrazí v záložce prohlížeče při práci v administraci WordPress. Můžete použít tagy %page% (název aktuální stránky) a %site_title% (název webu).',
                'admin_page_title_page_tag' => 'název aktuální stránky',
                'admin_page_title_site_tag' => 'název webu',
                'admin_page_title_format' => 'Formát titulku:',
                'admin_page_title_tags' => 'Dostupné tagy:',
                'admin_page_title_tag_page' => '%page%',
                'admin_page_title_tag_site' => '%site_title%',
                'admin_page_title_examples' => 'Příklady:',
                'admin_page_title_example1' => '%page% ‹ %site_title% – Company Name',
                'admin_page_title_example2' => 'Company Name &rsaquo; %page%',
                'admin_page_title_example3' => '%site_title% - %page%',
                
                'disable_gutenberg' => 'Zakázat Gutenberg editor',
                'disable_gutenberg_desc' => 'Použít klasický editor namísto blokového',
                'disable_gutenberg_tip' => 'Přepne WordPress zpět na klasický editor pro všechny příspěvky, stránky i widgety.',
                
                'duplicate_posts' => 'Duplikace stránek a příspěvků',
                'duplicate_posts_desc' => 'Zapnout rychlé kopírování jedním kliknutím',
                'duplicate_posts_tip' => 'Přidá tlačítko "Vytvořit kopii" do akcí u stránek, příspěvků a custom post types. Kopie zahrnuje veškerý obsah, metadata, taxonomie a custom fields. Duplikát se uloží jako koncept a otevře se pro editaci.',
                'duplicate_action' => 'Vytvořit kopii',
                'duplicate_success' => 'Kopie byla vytvořena a otevřena pro editaci.',
                
                'enable_svg_upload' => 'Podpora SVG souborů',
                'enable_svg_upload_desc' => 'Povolit nahrávání SVG souborů',
                'enable_svg_upload_tip' => 'Umožní nahrávání SVG souborů do knihovny médií ve WordPressu. Oprávnění pro nahrávání SVG je uděleno dynamicky přes WordPress filter a nevyžaduje změnu rolí v databázi. Soubory jsou při nahrání automaticky sanitizovány.',

                'enable_media_replace' => 'Nahrazení souborů v médiích',
                'enable_media_replace_desc' => 'Přidat možnost nahradit soubor přímo v knihovně médií',
                'mr_row_action' => 'Nahradit soubor',
                'mr_page_title' => 'Nahradit soubor',
                'mr_back' => 'Zpět do knihovny médií',
                'mr_edit' => 'Upravit médium',
                'mr_cancel' => 'Zrušit',
                'mr_submit' => 'Nahrát a nahradit',
                'mr_uploading' => 'Nahrávám...',
                'mr_badge_new' => 'nový',
                'mr_label_old' => 'Aktuální soubor',
                'mr_label_new' => 'Nový soubor',
                'mr_uploaded' => 'Nahráno',
                'mr_drop_title' => 'Vyberte nebo přetáhněte soubor',
                'mr_drop_sub' => 'Klikněte kamkoliv sem',
                'mr_mode_keep_title' => 'Adresa zůstane stejná',
                'mr_mode_keep_desc' => 'Soubor se vymění, ale jeho adresa na webu se nezmění. Všechna místa kde je použit budou fungovat dál.',
                'mr_mode_new_title' => 'Adresa se změní a aktualizuje všude',
                'mr_mode_new_desc' => 'Soubor dostane nové jméno a jeho nová adresa se automaticky doplní všude, kde je na webu použit.',
                'mr_mode_section_title' => 'Co se stane s adresou souboru',
                'mr_success_keep' => 'Soubor byl úspěšně nahrazen',
                'mr_success_new' => 'Soubor nahrazen a adresy aktualizovány',
                'mr_desc_keep' => 'Nový soubor je aktivní. Adresa zůstala stejná — žádné jiné změny nebyly potřeba.',
                'mr_desc_new' => 'Nový soubor je aktivní. Jeho adresa byla automaticky aktualizována všude na webu.',
                'mr_error_no_file' => 'Žádný soubor nebyl nahrán nebo došlo k chybě při nahrávání.',
                'mr_error_write' => 'Nepodařilo se přepsat soubor. Zkontrolujte oprávnění složky uploads.',
                'mr_error_perm' => 'Nemáte oprávnění k této akci.',
                'mr_error_id' => 'Neplatné ID přílohy.',
                'mr_error_not_found' => 'Příloha nebyla nalezena.',
                'mr_js_no_file' => 'Vyberte prosím soubor pro nahrání.',
                
                'wp_emails' => 'E-maily WordPressu',
                'wp_emails_desc' => 'Změnit odesílatele e-mailů',
                'wp_emails_tip' => 'Změňte jméno odesílatele a e-mailovou adresu pro všechny e-maily odesílané WordPressem (registrace, obnovení hesla, oznámení, atd.). Ovlivní také pluginy, které nespecifikují vlastního odesílatele.',
                'wp_email_from_name' => 'Jméno odesílatele:',
                'wp_email_from_name_placeholder' => 'např. KACER STUDIO',
                'wp_email_from_name_default' => 'Výchozí je',
                'wp_email_from_email' => 'E-mailová adresa:',
                'wp_email_from_email_placeholder' => 'např. info@kacer.studio',
                'wp_email_from_email_default' => 'Výchozí je',
                'wp_email_domain_warning' => 'E-mail musí být ze stejné domény jako web (např.',
                'wp_email_domain_warning_2' => '). Hosting vyžaduje, aby odesílatel byl z domény webu.',
                
                'post_colors' => 'Barevné označení příspěvků podle stavu',
                'post_colors_desc' => 'Zapnout barevné odlišení v administraci',
                'post_colors_tip' => 'V seznamu příspěvků v administraci barevně odliší jednotlivé stavy pro lepší přehlednost.',
                
                'custom_colors' => 'Vlastní barvy pro jednotlivé stavy',
                'color_draft' => 'Koncept',
                'color_pending' => 'Čeká na schválení',
                'color_publish' => 'Publikováno',
                'color_future' => 'Naplánováno',
                'color_private' => 'Soukromé',
                'color_note' => 'Ponechte prázdné pro zachování výchozí barvy WordPress',
                
                'edit_link' => 'Tlačítko "Upravit" na frontendu',
                'edit_link_desc' => 'Zobrazit fixní tlačítko v levém dolním rohu',
                'edit_link_tip' => 'Přidá fixní tlačítko "Upravit" v levém dolním rohu webu pro přihlášené uživatele s oprávněním upravovat příspěvky. Umožňuje rychlý přístup k editaci aktuální stránky.',
                
                'archive_titles' => 'Nadpisy kategorií a štítků',
                'archive_titles_desc' => 'Upravit nadpisy',
                'archive_titles_tip' => 'Na stránkách kategorií se místo "Kategorie: Novinky" zobrazí jen "Novinky".

Funguje na adresách jako /tema/XXX/ nebo /stitek/XXX/',
                'category_prefix' => 'Text před kategorií:',
                'category_prefix_placeholder' => 'Témata:',
                'tag_prefix' => 'Text před štítkem:',
                'tag_prefix_placeholder' => 'Klíčová slova:',
                
                'year_shortcode' => 'Povolit shortcode [year]',
                'year_shortcode_desc' => 'Aktivovat shortcode pro zobrazení aktuálního roku',
                'year_shortcode_tip' => 'Aktivuje shortcode [year] který funguje VŠUDE: v obsahu příspěvků/stránek, widgetech, menu, výňatcích, nastavení šablon (footer copyright text všech populárních témat), a dalších místech. PHP kód funguje pouze v souborech .php.',
                'year_example' => 'Použití: [year] zobrazí aktuální rok (' . gmdate('Y') . ')',
                
                'responsive_images' => 'Vypnout responzivní obrázky',
                'responsive_images_desc' => 'Zakázat automatické generování srcset a sizes',
                'responsive_images_tip' => 'WordPress standardně generuje několik velikostí každého obrázku a přidává srcset/sizes atributy pro responzivní zobrazení. Tato volba to vypne - ideální pokud optimalizujete obrázky vlastním způsobem, používáte CDN nebo lazy loading plugin, který se o responzivitu postará jinak.',
                'responsive_images_note' => 'Zjednoduší HTML kód obrázků. Vhodné pokud používáte vlastní optimalizaci nebo CDN.',
                'disable_big_image_threshold' => 'Vypnout automatické zmenšování velkých obrázků',
                'disable_big_image_threshold_desc' => 'Zakázat automatické zmenšování obrázků nad 2560px',
                'disable_big_image_threshold_tip' => 'WordPress od verze 5.3 automaticky zmenšuje velké obrázky (nad 2560px) při nahrávání. Tato volba to vypne - vhodné pokud potřebujete zachovat původní velikost obrázků.',
                'disable_big_image_threshold_note' => 'Zachová původní rozměry nahraných obrázků.',
                
                'comment_url' => 'Odstranit pole "Webová stránka" z komentářů',
                'comment_url_desc' => 'Skrýt nepovinné pole URL',
                'comment_url_tip' => 'Odstraní nepovinné pole "Webová stránka" (URL) z formuláře pro přidání komentáře. Snižuje spam a zjednodušuje formulář.',
                
                'disable_comments_completely' => 'Kompletně vypnout komentáře',
                'disable_comments_completely_desc' => 'Uzavřít komentáře + skrýt z menu',
                'disable_comments_completely_tip' => 'Uzavře komentáře pro všechny příspěvky a stránky a skryje položku "Komentáře" z admin menu.',
                
                'disable_user_enumeration' => 'Ochrana uživatelských účtů',
                'disable_user_enumeration_desc' => 'Zablokovat výpis uživatelů',
                'disable_user_enumeration_tip' => 'Zablokuje REST API endpointy pro výpis uživatelů (wp/v2/users) a odstraní author archive stránky, aby nebylo možné vylistovat uživatele přes ?author=1.',
                
                'auto_delete_files' => 'Automatické mazání nepotřebných souborů',
                'auto_delete_files_desc' => 'Automaticky mazat nepotřebné soubory',
                'auto_delete_files_tip' => 'Po každé aktualizaci WordPressu automaticky odstraní soubory license.txt, readme.html a wp-config-sample.php z FTP serveru.',
                
                'change_login_url' => 'Změna URL pro přihlášení',
                'change_login_url_desc' => 'Nastavit vlastní přihlašovací URL',
                'change_login_url_tip' => 'Změní standardní wp-login.php na vlastní URL adresu pro zvýšení bezpečnosti. Útočníci nebudou moci najít vaši přihlašovací stránku pomocí standardních URL.',
                'custom_login_slug' => 'Přihlašovací URL',
                'login_url_invalid_slug' => 'Slug může obsahovat pouze malá písmena, čísla a pomlčky.',
                'login_url_reserved_slug' => 'Tento slug je rezervovaný a nelze jej použít.',
                'login_url_current' => 'Vaše aktuální přihlašovací URL:',
                
                'login_page' => 'Přihlašovací stránka',
                'login_customize' => 'Vlastní přihlašovací stránka',
                'login_customize_desc' => 'Přizpůsobit vzhled přihlašovací stránky',
                'login_customize_tip' => 'Upravte logo, pozadí, barvy a odkazy přihlašovací stránky pro profesionální vzhled.',
                'login_logo' => 'Logo',
                'login_logo_upload' => 'Nahrát logo',
                'login_logo_remove' => 'Odstranit logo',
                'login_logo_height' => 'Výška loga',
                'login_logo_url' => 'Odkaz loga',
                'login_logo_url_placeholder' => 'https://vase-stranka.cz',
                'login_logo_url_desc' => 'URL, kam povede klik na logo (výchozí: homepage)',
                'login_bg_color' => 'Barva pozadí',
                'login_bg_image' => 'Obrázek pozadí',
                'login_bg_image_upload' => 'Nahrát obrázek',
                'login_bg_image_remove' => 'Odstranit obrázek',
                'login_bg_size' => 'Pokrytí pozadí',
                'login_bg_size_cover' => 'Pokrýt celou plochu (cover)',
                'login_bg_size_contain' => 'Zobrazit celý obrázek (contain)',
                'login_bg_size_repeat' => 'Opakovat (repeat)',
                'login_primary_color' => 'Primární barva',
                'login_primary_color_desc' => 'Barva pro odkazy a fokus',
                'login_form_radius' => 'Zaoblení rohů formuláře',
                'login_form_bg_color' => 'Barva pozadí formuláře',
                'login_form_text_color' => 'Barva písma formuláře',
                'login_button_bg' => 'Barva tlačítka',
                'login_button_text_color' => 'Barva textu tlačítka',
                'login_button_radius' => 'Zaoblení rohů tlačítka',
                'login_links_color' => 'Barva odkazů',
                'login_hide_lostpassword' => 'Skrýt odkaz "Zapomenuté heslo?"',
                'login_hide_backtoblog' => 'Skrýt odkaz "Zpět na..."',
                'login_hide_rememberme' => 'Skrýt pole "Pamatovat si mě"',
                'login_hide_privacy' => 'Skrýt odkaz Zpracování osobních údajů',
                'login_custom_css' => 'Vlastní CSS',
                'login_custom_css_desc' => 'Pokročilé CSS úpravy',
                
                'wpforms_countries' => 'Omezit předvolby v telefonních polích',
                'wpforms_countries_tip' => 'Omezí výběr předvolby na vybranou skupinu zemí. Funguje automaticky s WPForms, SureForms, Fluent Forms a dalšími pluginy, které používají knihovnu intl-tel-input.',
                'no_form_plugin' => 'Nebyl nalezen žádný aktivní plugin pro formuláře',
                'phone_restrict_off' => 'Vypnuto',
                'phone_restrict_czsk' => 'CZ + SK',
                'phone_restrict_europe' => 'Evropa',
                'phone_restrict_us' => 'USA + Kanada',
                
                'enable_trans' => 'Povolit vlastní překlady textů',
                'enable_trans_desc' => 'Aplikovat níže definované překlady na frontend webu',
                'enable_trans_tip' => 'Nahradí vybrané anglické texty českými ekvivalenty na frontendu webu (nezasahuje do administrace).',
                
                'trans_defs' => 'Definice překladů',
                'trans_note' => 'ℹ️ Upravte nebo přidejte vlastní překlady. Ponechte pole překladu prázdné pro úplné odstranění textu z výstupu. V poli překladu můžete používat HTML tagy.',
                'trans_html_allowed' => 'Můžete použít HTML (např. <a href="">odkaz</a>)',
                'from' => 'Originální text',
                'to' => 'Překlad (česky)',
                'remove' => 'Odebrat',
                'add_trans' => 'Přidat nový překlad',
                'export_trans' => 'Export překladů',
                'import_trans' => 'Import překladů',
                'import_trans_confirm' => 'Import nahradí všechny stávající překlady. Pokračovat?',
                
                'system_info' => 'Systémové informace',
                'site_name' => 'Název webu',
                'site_url' => 'URL adresa',
                'protocol' => 'Protokol',
                'protocol_https' => 'HTTPS',
                'protocol_http' => 'HTTP',
                'wp_version' => 'Verze WordPress',
                'php_version' => 'Verze PHP',
                'php_modern' => 'Moderní',
                'php_outdated' => 'Zastaralá',
                'mysql_version' => 'Verze MySQL',
                'server' => 'Webový server',
                'php_memory' => 'PHP paměťový limit',
                'wp_memory' => 'WP paměťový limit',
                'max_upload' => 'Max. velikost uploadu',

                'maintenance_mode' => 'Web v údržbě',
                'maintenance_enable' => 'Aktivovat režim údržby',
                'maintenance_enable_desc' => 'Zobrazit vlastní stránku údržby návštěvníkům',
                'maintenance_enable_tip' => 'Když je aktivní, návštěvníci webu uvidí stránku údržby místo běžného obsahu. Přihlášení administrátoři (s oprávněním manage_options) vidí normální web.',
                'maintenance_mode_type' => 'Režim úprav',
                'maintenance_mode_simple' => 'Jednoduchý režim',
                'maintenance_mode_advanced' => 'Pokročilý režim (HTML)',
                'maintenance_heading' => 'Nadpis',
                'maintenance_heading_placeholder' => 'Web je momentálně v údržbě',
                'maintenance_message' => 'Text',
                'maintenance_message_placeholder' => 'Pracujeme na vylepšení našich stránek. Omlouváme se za dočasné potíže a brzy se vrátíme online!',
                'maintenance_button_text' => 'Text tlačítka',
                'maintenance_button_text_placeholder' => 'Kontaktujte nás',
                'maintenance_button_url' => 'Odkaz tlačítka',
                'maintenance_button_url_placeholder' => 'https://example.com/kontakt',
                'maintenance_button_show' => 'Zobrazit tlačítko',
                'maintenance_image' => 'Logo / Obrázek',
                'maintenance_image_desc' => 'Nahrajte obrázek, který se zobrazí nad nadpisem (volitelné)',
                'maintenance_image_upload' => 'Nahrát obrázek',
                'maintenance_image_remove' => 'Odstranit obrázek',
                'maintenance_image_max_width' => 'Maximální šířka obrázku',
                'maintenance_bg_color' => 'Barva pozadí',
                'maintenance_text_color' => 'Barva textu',
                'maintenance_button_bg_color' => 'Barva tlačítka',
                'maintenance_button_text_color' => 'Barva textu tlačítka',
                'maintenance_button_radius' => 'Zaoblení rohů tlačítka',
                'maintenance_html_code' => 'HTML kód stránky údržby',
                'maintenance_html_tip' => 'Zadejte kompletní HTML dokument včetně &lt;!DOCTYPE&gt;, &lt;html&gt;, &lt;head&gt; a &lt;body&gt; tagů.',
                'maintenance_show_logged' => 'Zobrazit také pro přihlášené uživatele',
                'maintenance_show_logged_desc' => 'Režim údržby uvidí i přihlášení uživatelé (kromě administrátorů)',

                'custom_scripts' => 'Vlastní skripty a kódy',
                'google_maps_api_key' => 'API klíč Google Maps',
                'google_maps_api_key_desc' => 'Pro použití Google Maps je potřeba vygenerovat API klíč a vložit ho zde. Další informace najdete v',
                'google_maps_api_key_link' => 'oficiální dokumentaci',
                'custom_functions' => 'Vlastní PHP kód (functions.php)',
                'custom_functions_desc' => 'Aktivovat vlastní PHP funkce',
                'custom_functions_tip' => 'Přidá vlastní PHP kód který se spustí při načtení WordPress. Ekvivalent přidání kódu do functions.php vašeho theme. POZOR: Špatný kód může rozbít web!',
                'custom_functions_info' => 'ℹ️ Zadávejte PHP kód <strong>BEZ</strong> otevíracích/uzavíracích &lt;?php ?&gt; tagů.',
                'custom_functions_placeholder' => '

add_filter(\'wp_footer\', function() {
    echo \'<p>Vlastní text</p>\';
});',
                'custom_css' => 'Vlastní CSS',
                'custom_css_desc' => 'Aktivovat vlastní CSS styly',
                'custom_css_tip' => 'Přidá vlastní CSS styly do &lt;head&gt; sekce. Zadávejte kód BEZ &lt;style&gt; tagů - ty se přidají automaticky. Příklad: body { background: #fff; }',
                'custom_css_active_warning' => 'Vlastní CSS je aktivní a aplikuje se na celý web. Deaktivujte, pokud web zobrazuje neočekávané styly.',
                'custom_css_theme' => 'Téma:',
                'custom_css_info' => 'ℹ️ Zadávejte CSS <strong>BEZ</strong> &lt;style&gt; tagů - ty se přidají automaticky.',
                'custom_css_placeholder' => 'body {
  background: #fff;
  font-family: Arial, sans-serif;
}

.my-class {
  color: #333;
}',
                'script_head' => 'Vlastní JavaScript v &lt;head&gt;',
                'script_head_desc' => 'Aktivovat vlastní JavaScript',
                'script_head_tip' => 'Přidá vlastní JavaScript do &lt;head&gt; sekce. Použijte pro: analytics, vlastní JS funkce. Zadávejte s &lt;script&gt; tagem.',
                'script_body_start' => 'Vložit kód na začátek &lt;body&gt;',
                'script_body_start_desc' => 'Aktivovat tracking kódy',
                'script_body_start_tip' => 'Přidá kód ihned za otevírací &lt;body&gt; tag. Ideální pro: Google Tag Manager, Facebook Pixel, tracking kódy, které vyžadují umístění na začátku body.',
                'script_body_end' => 'Vložit kód před &lt;/body&gt;',
                'script_body_end_desc' => 'Kód se vloží před uzavírací značku &lt;/body&gt;',
                'script_body_end_tip' => 'Přidá kód před uzavírací &lt;/body&gt; tag. Doporučeno pro: optimalizaci rychlosti načítání, neesenciální skripty, analytics.',
                'script_placeholder_head' => '<script>
  
  console.log("Hello from head");
</script>',
                'script_placeholder_body' => '<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){...})(window,document,\'script\',\'dataLayer\',\'GTM-XXXXX\');
</script>
<!-- End Google Tag Manager -->',

                'robots_editor' => 'Editor robots.txt',
                'robots_enable' => 'Povolit vlastní robots.txt',
                'robots_enable_desc' => 'Přepsat výchozí WordPress robots.txt',
                'robots_enable_tip' => 'Aktivuje vlastní robots.txt soubor. WordPress má výchozí robots.txt, tato funkce vám umožní ho přepsat vlastním obsahem.',
                'robots_content' => 'Obsah robots.txt',
                'robots_template' => 'Šablona',
                'robots_template_default' => 'Výchozí (WordPress)',
                'robots_template_allow' => 'Povolit vše',
                'robots_template_disallow' => 'Zakázat vše',
                'robots_template_custom' => 'Vlastní',
                'robots_apply' => 'Použít šablonu',
                'robots_tip' => 'Upravte pravidla pro vyhledávače. Soubor robots.txt říká botům (Google, Bing...), které části webu mohou indexovat.',
                'robots_info' => 'ℹ️ Po uložení se obsah <strong>zapíše do fyzického souboru</strong> <code>robots.txt</code> v root složce webu. Pokud soubor existuje, bude přepsán.',

                'htaccess_editor' => 'Editor .htaccess',
                'htaccess_enable' => 'Povolit vlastní .htaccess pravidla',
                'htaccess_enable_desc' => 'Přidat vlastní pravidla do .htaccess',
                'htaccess_enable_tip' => 'Přidá vlastní Apache pravidla do .htaccess souboru. POZOR: Nesprávná konfigurace může způsobit nefunkčnost webu!',
                'htaccess_content' => 'Vlastní .htaccess pravidla',
                'htaccess_warning' => 'VAROVÁNÍ: Nesprávná pravidla mohou způsobit nefunkčnost webu! Plugin automaticky vytvoří zálohu jako <code>.htaccess.wp-admin-studio-backup</code>.',
                'htaccess_info' => 'ℹ️ Pravidla se <strong>přidají NA ZAČÁTEK</strong> .htaccess souboru (nepřepisují celý soubor). Obklopí se komentáři <code># BEGIN WP WP Admin Studio</code> a <code># END WP WP Admin Studio</code>.',
                'htaccess_template' => 'Šablona',
                'htaccess_template_security' => 'Bezpečnostní pravidla',
                'htaccess_template_cache' => 'Cache hlavičky',
                'htaccess_template_redirect' => '301 Redirect',
                'htaccess_template_custom' => 'Vlastní',
                'htaccess_apply' => 'Použít šablonu',
                'htaccess_tip' => 'Přidejte vlastní Apache direktivy. Můžete nastavit: redirecty, cache, bezpečnostní pravidla, GZIP kompresi.',
                'htaccess_backup_success' => 'Záloha uložena jako .htaccess.wp-admin-studio-backup',
                'htaccess_backup_restore' => 'V případě problémů obnovte soubor .htaccess.wp-admin-studio-backup',
                'htaccess_restore_button' => 'Obnovit ze zálohy',
                'htaccess_restore_confirm' => 'Opravdu chcete obnovit .htaccess ze zálohy? Současný .htaccess bude přepsán a vaše WP Admin Studio pravidla budou odstraněna.',
                'htaccess_restore_success' => '.htaccess byl úspěšně obnoven ze zálohy!',
                'htaccess_restore_error' => 'Chyba při obnově: záložní soubor neexistuje nebo není čitelný.',
                'htaccess_no_backup' => 'Záložní soubor nebyl nalezen.',

                'feedback_bug' => 'Nahlásit chybu',

                'bug_report_title' => 'Nahlásit chybu',
                'bug_report_email' => 'Váš email',
                'bug_report_message' => 'Popis chyby',
                'bug_report_message_placeholder' => 'Popište chybu, se kterou jste se setkali...',
                'bug_report_screenshot' => 'Screenshot (volitelné)',
                'bug_report_screenshot_desc' => 'PNG nebo JPG, max 5 MB',
                'bug_report_system_info' => 'Systémové informace',
                'bug_report_url' => 'URL webu',
                'bug_report_consent' => 'Odesláním tohoto hlášení souhlasíte s tím, že výše uvedené systémové informace budou předány autorovi pluginu (KACER STUDIO s.r.o.) výhradně za účelem diagnostiky a vyřešení nahlášeného problému.',
                'bug_report_send' => 'Odeslat',
                'bug_report_success' => 'Děkujeme! Váš report byl úspěšně odeslán.',
                'bug_report_error_empty' => 'Prosím vyplňte popis chyby.',
                'bug_report_error_email' => 'Prosím zadejte platnou e-mailovou adresu.',
                'bug_report_error_send' => 'Chyba při odesílání. Zkuste to prosím znovu.',
                'bug_report_error_security' => 'Bezpečnostní kontrola selhala.',
                'cancel' => 'Zrušit',
                'sending' => 'Odesílání',
                'search_placeholder' => 'Hledat...',
            ),
            'en' => array(
                'page_title' => 'WP Admin Studio',
                'version' => 'Version ' . self::VERSION,
                'author' => 'Created by <a href="https://kacer.studio" target="_blank">KACER STUDIO s.r.o.</a>',
                'bulk_actions' => 'Bulk Actions',
                'enable_all' => 'Enable All',
                'disable_all' => 'Disable All',
                'import_export' => 'Backup Settings',
                'export' => 'Export',
                'import' => 'Import',
                'export_desc' => 'Downloads a JSON file with complete plugin settings.',
                'import_desc' => 'Upload a JSON file to restore settings from a previous backup.',
                'save' => 'Save Changes',
                'saving' => 'Saving',
                'settings_saved' => 'Saved!',
                'all_enabled' => 'All settings have been enabled!',
                'all_disabled' => 'All settings have been disabled!',
                'import_success' => 'Settings successfully imported!',
                'import_error' => 'Error: File was not uploaded.',
                'import_invalid' => 'Error: Invalid settings file.',
                'lang_switch' => 'Switch to Czech',
                
                'admin_section' => 'Administration',
                'editor_section' => 'Pages and Posts',
                'frontend' => 'Frontend and Performance',
                'comments' => 'Comments',
                'forms' => 'Forms',
                'translations' => 'Custom Translations',
                'nav_admin' => 'Admin',
                'nav_scripts' => 'Scripts',
                'nav_maintenance' => 'Maintenance',
                'nav_login' => 'Login',
                'nav_editor' => 'Editor',
                'nav_frontend' => 'Frontend',
                'nav_comments' => 'Comments',
                'nav_forms' => 'Forms',
                'nav_translations' => 'Translations',
                'nav_system' => 'System',
                'nav_backup' => 'Backup',
                'admin_bar_tip' => 'Check items you want to remove from the WordPress admin bar.',
                'admin_bar_logo' => 'WordPress logo (including submenu with documentation links)',
                'admin_bar_updates' => 'Updates',
                'admin_bar_comments' => 'Comments',
                'admin_bar_new' => 'Action "Add New" (post, page, media...)',
                'admin_bar_view' => 'Link "View Site"',
                'admin_bar_avatar' => 'Avatar icon next to username (desktop only)',
                
                'login_lang' => 'Hide language switcher',
                'login_lang_desc' => 'Hide language switcher',
                'login_lang_tip' => 'Removes the language selector dropdown from the WordPress login page (wp-login.php).',
                
                'hide_updates' => 'Hide updates for non-admin users',
                'hide_updates_desc' => 'Only administrators will see update notifications',
                'hide_updates_tip' => 'Hides notifications about available plugin, theme, and WordPress updates for all users except administrators. Reduces confusion for editors and contributors.',
                
                'disable_auto_update_emails' => 'Disable automatic update emails',
                'disable_auto_update_emails_desc' => 'Disable automatic update emails',
                'disable_auto_update_emails_tip' => 'WordPress sends an email after every automatic update of core, plugins or themes. This option silences it.',
                
                'hide_admin_notices' => 'Admin notices',
                'hide_admin_notices_desc' => 'Hide admin notices for all users',
                'hide_admin_notices_tip' => 'Hides plugin and WordPress info bars for all logged-in users. Optionally, notices can remain visible for a specific administrator.',
                'show_notices_current_user_desc' => 'Show notices only for: ',
                
                'hide_howdy' => 'Hide "Howdy" from admin bar',
                'hide_howdy_desc' => 'Display only username',
                'hide_howdy_tip' => 'Removes the "Howdy" text from the top right corner of the admin bar and leaves only the logged-in user\'s name.',
                
                'hide_wp_version' => 'Hide WordPress version from admin footer',
                'hide_wp_version_desc' => 'Remove "Thank you for using WordPress. Version X.X.X"',
                'hide_wp_version_tip' => 'Hides WordPress version information from the admin footer.',
                
                'hide_dashboard_widgets' => 'Hide dashboard widgets',
                'hide_dashboard_widgets_tip' => 'Check the widgets you want to hide on the dashboard. The widget list updates automatically each time the dashboard is visited.',
                
                'admin_page_titles' => 'Custom Admin Page Titles',
                'admin_page_titles_desc' => 'Edit browser tab title',
                'admin_page_titles_tip' => 'Allows you to set a custom page title format that appears in the browser tab when working in WordPress admin. You can use tags %page% (current page name) and %site_title% (site name).',
                'admin_page_title_page_tag' => 'current page name',
                'admin_page_title_site_tag' => 'site name',
                
                'disable_gutenberg' => 'Disable Gutenberg Editor',
                'disable_gutenberg_desc' => 'Use classic editor instead of block editor',
                'disable_gutenberg_tip' => 'Switches WordPress back to the classic editor for all posts, pages, and widgets.',
                
                'duplicate_posts' => 'Duplicate Pages & Posts',
                'duplicate_posts_desc' => 'Enable quick one-click copying',
                'duplicate_posts_tip' => 'Adds "Create copy" button to actions for pages, posts and custom post types. Copy includes all content, metadata, taxonomies and custom fields. Duplicate is saved as draft and opened for editing.',
                'duplicate_action' => 'Create copy',
                'duplicate_success' => 'Copy has been created and opened for editing.',
                
                'enable_svg_upload' => 'SVG File Support',
                'enable_svg_upload_desc' => 'Enable SVG file uploads',
                'enable_svg_upload_tip' => 'Allows uploading SVG files to the WordPress media library. Permission is granted dynamically via a WordPress filter and does not permanently modify user roles. Uploaded files are automatically sanitized.',

                'enable_media_replace' => 'Media File Replacement',
                'enable_media_replace_desc' => 'Add option to replace a file directly in the media library',
                'mr_row_action' => 'Replace file',
                'mr_page_title' => 'Replace file',
                'mr_back' => 'Back to media library',
                'mr_edit' => 'Edit media',
                'mr_cancel' => 'Cancel',
                'mr_submit' => 'Upload and replace',
                'mr_uploading' => 'Uploading...',
                'mr_badge_new' => 'new',
                'mr_label_old' => 'Current file',
                'mr_label_new' => 'New file',
                'mr_uploaded' => 'Uploaded',
                'mr_drop_title' => 'Select or drop a file',
                'mr_drop_sub' => 'Click anywhere here',
                'mr_mode_keep_title' => 'Keep the same URL',
                'mr_mode_keep_desc' => 'The file will be swapped but its URL will stay the same. All places where it is used will continue to work.',
                'mr_mode_new_title' => 'Change URL and update everywhere',
                'mr_mode_new_desc' => 'The file will get a new name and its new URL will automatically be updated everywhere it is used on the site.',
                'mr_mode_section_title' => 'What happens to the file URL',
                'mr_success_keep' => 'File replaced successfully',
                'mr_success_new' => 'File replaced and URLs updated',
                'mr_desc_keep' => 'The new file is active. The URL stayed the same — no other changes were needed.',
                'mr_desc_new' => 'The new file is active. Its URL has been automatically updated everywhere on the site.',
                'mr_error_no_file' => 'No file was uploaded or an upload error occurred.',
                'mr_error_write' => 'Could not overwrite the file. Check the permissions of the uploads folder.',
                'mr_error_perm' => 'You do not have permission to perform this action.',
                'mr_error_id' => 'Invalid attachment ID.',
                'mr_error_not_found' => 'Attachment not found.',
                'mr_js_no_file' => 'Please select a file to upload.',
                
                'wp_emails' => 'WordPress Emails',
                'wp_emails_desc' => 'Change email sender',
                'wp_emails_tip' => 'Change the sender name and email address for all emails sent by WordPress (registration, password reset, notifications, etc.). Also affects plugins that don\'t specify their own sender.',
                'wp_email_from_name' => 'Sender name:',
                'wp_email_from_name_placeholder' => 'e.g. KACER STUDIO',
                'wp_email_from_name_default' => 'Default is',
                'wp_email_from_email' => 'Email address:',
                'wp_email_from_email_placeholder' => 'e.g. info@kacer.studio',
                'wp_email_from_email_default' => 'Default is',
                'wp_email_domain_warning' => 'The email must be from the same domain as the website (e.g.',
                'wp_email_domain_warning_2' => '). Hosting requires the sender to be from the website domain.',
                
                'post_colors' => 'Color-coded post status',
                'post_colors_desc' => 'Enable color differentiation in admin',
                'post_colors_tip' => 'In the admin post list, colors different statuses for better overview.',
                
                'custom_colors' => 'Custom colors for individual statuses',
                'color_draft' => 'Draft',
                'color_pending' => 'Pending Review',
                'color_publish' => 'Published',
                'color_future' => 'Scheduled',
                'color_private' => 'Private',
                'color_note' => 'Leave empty to keep default WordPress color',
                
                'edit_link' => '"Edit" button on frontend',
                'edit_link_desc' => 'Show fixed button in bottom left corner',
                'edit_link_tip' => 'Adds a fixed "Edit" button in the bottom left corner of the site for logged-in users with permission to edit posts. Allows quick access to edit the current page.',
                
                'archive_titles' => 'Category and Tag Headings',
                'archive_titles_desc' => 'Customize headings',
                'archive_titles_tip' => 'On category pages, instead of "Category: News" shows just "News".

Works on URLs like /category/XXX/ or /tag/XXX/',
                'category_prefix' => 'Text before category:',
                'category_prefix_placeholder' => 'Topics:',
                'tag_prefix' => 'Text before tag:',
                'tag_prefix_placeholder' => 'Keywords:',
                
                'year_shortcode' => 'Enable [year] shortcode',
                'year_shortcode_desc' => 'Activate shortcode to display current year',
                'year_shortcode_tip' => 'Activates the [year] shortcode that works EVERYWHERE: in post/page content, widgets, menus, excerpts, theme settings (footer copyright text for all popular themes), and more. PHP code only works in .php files.',
                'year_example' => 'Usage: [year] displays current year (' . gmdate('Y') . ')',
                
                'responsive_images' => 'Disable responsive images',
                'responsive_images_desc' => 'Disable automatic generation of srcset and sizes',
                'responsive_images_tip' => 'WordPress normally generates multiple sizes of each image and adds srcset/sizes attributes for responsive display. This option disables it - ideal if you optimize images your own way, use a CDN, or a lazy loading plugin that handles responsiveness differently.',
                'responsive_images_note' => 'Simplifies image HTML code. Suitable if you use custom optimization or CDN.',
                'disable_big_image_threshold' => 'Disable automatic downsizing of large images',
                'disable_big_image_threshold_desc' => 'Disable automatic downsizing of images over 2560px',
                'disable_big_image_threshold_tip' => 'Since WordPress 5.3, large images (over 2560px) are automatically downsized during upload. This option disables it - useful when you need to preserve original image dimensions.',
                'disable_big_image_threshold_note' => 'Preserves original dimensions of uploaded images.',
                
                'comment_url' => 'Remove "Website" field from comments',
                'comment_url_desc' => 'Hide optional URL field',
                'comment_url_tip' => 'Removes the optional "Website" (URL) field from the comment submission form. Reduces spam and simplifies the form.',
                
                'disable_comments_completely' => 'Completely disable comments',
                'disable_comments_completely_desc' => 'Close comments + hide from menu',
                'disable_comments_completely_tip' => 'Closes comments for all posts and pages and hides "Comments" from admin menu.',
                
                'disable_user_enumeration' => 'Protect user accounts',
                'disable_user_enumeration_desc' => 'Block user enumeration',
                'disable_user_enumeration_tip' => 'Blocks REST API endpoints for user listing (wp/v2/users) and removes author archive pages to prevent user enumeration via ?author=1.',
                
                'auto_delete_files' => 'Automatic deletion of unnecessary files',
                'auto_delete_files_desc' => 'Automatically delete unnecessary files',
                'auto_delete_files_tip' => 'After each WordPress update, automatically removes license.txt, readme.html and wp-config-sample.php files from the FTP server.',
                
                'change_login_url' => 'Change login URL',
                'change_login_url_desc' => 'Set custom login URL',
                'change_login_url_tip' => 'Changes the standard wp-login.php to a custom URL address for increased security. Attackers will not be able to find your login page using standard URLs.',
                'custom_login_slug' => 'Login URL',
                'login_url_invalid_slug' => 'Slug can only contain lowercase letters, numbers and dashes.',
                'login_url_reserved_slug' => 'This slug is reserved and cannot be used.',
                'login_url_current' => 'Your current login URL:',
                
                'login_page' => 'Login Page',
                'login_customize' => 'Custom Login Page',
                'login_customize_desc' => 'Customize login page appearance',
                'login_customize_tip' => 'Customize logo, background, colors, and links of the login page for a professional look.',
                'login_logo' => 'Logo',
                'login_logo_upload' => 'Upload logo',
                'login_logo_remove' => 'Remove logo',
                'login_logo_height' => 'Logo height',
                'login_logo_url' => 'Logo link',
                'login_logo_url_placeholder' => 'https://your-site.com',
                'login_logo_url_desc' => 'URL where logo click leads to (default: homepage)',
                'login_bg_color' => 'Background color',
                'login_bg_image' => 'Background image',
                'login_bg_image_upload' => 'Upload image',
                'login_bg_image_remove' => 'Remove image',
                'login_bg_size' => 'Background coverage',
                'login_bg_size_cover' => 'Cover entire area (cover)',
                'login_bg_size_contain' => 'Show entire image (contain)',
                'login_bg_size_repeat' => 'Repeat (repeat)',
                'login_primary_color' => 'Primary color',
                'login_primary_color_desc' => 'Color for links and focus',
                'login_form_radius' => 'Form border radius',
                'login_form_bg_color' => 'Form background color',
                'login_form_text_color' => 'Form text color',
                'login_button_bg' => 'Button color',
                'login_button_text_color' => 'Button text color',
                'login_button_radius' => 'Button border radius',
                'login_links_color' => 'Links color',
                'login_hide_lostpassword' => 'Hide "Lost your password?" link',
                'login_hide_backtoblog' => 'Hide "Back to..." link',
                'login_hide_rememberme' => 'Hide "Remember Me" checkbox',
                'login_hide_privacy' => 'Hide Privacy Policy link',
                'login_custom_css' => 'Custom CSS',
                'login_custom_css_desc' => 'Advanced CSS customizations',
                
                'wpforms_countries' => 'Restrict phone field prefixes',
                'wpforms_countries_tip' => 'Restricts the prefix selection to the chosen group of countries. Works automatically with WPForms, SureForms, Fluent Forms and other plugins using the intl-tel-input library.',
                'no_form_plugin' => 'No active form plugin found',
                'phone_restrict_off' => 'Disabled',
                'phone_restrict_czsk' => 'CZ + SK',
                'phone_restrict_europe' => 'Europe',
                'phone_restrict_us' => 'USA + Canada',
                
                'enable_trans' => 'Enable custom text translations',
                'enable_trans_desc' => 'Apply translations defined below to frontend',
                'enable_trans_tip' => 'Replaces selected English texts with Czech equivalents on the website frontend (does not affect admin area).',
                
                'trans_defs' => 'Translation Definitions',
                'trans_note' => 'ℹ️ Edit or add custom translations. Leave the translation field empty to completely remove text from output. You can use HTML tags in the translation field.',
                'trans_html_allowed' => 'You can use HTML (e.g. <a href="">link</a>)',
                'from' => 'Original text',
                'to' => 'Translation (Czech)',
                'remove' => 'Remove',
                'add_trans' => 'Add new translation',
                'export_trans' => 'Export translations',
                'import_trans' => 'Import translations',
                'import_trans_confirm' => 'Import will replace all existing translations. Continue?',
                
                'system_info' => 'System Information',
                'site_name' => 'Site Name',
                'site_url' => 'URL Address',
                'protocol' => 'Protocol',
                'protocol_https' => 'HTTPS',
                'protocol_http' => 'HTTP',
                'wp_version' => 'WordPress Version',
                'php_version' => 'PHP Version',
                'php_modern' => 'Modern',
                'php_outdated' => 'Outdated',
                'mysql_version' => 'MySQL Version',
                'server' => 'Web Server',
                'php_memory' => 'PHP Memory Limit',
                'wp_memory' => 'WP Memory Limit',
                'max_upload' => 'Max Upload Size',

                'maintenance_mode' => 'Maintenance Mode',
                'maintenance_enable' => 'Enable maintenance mode',
                'maintenance_enable_desc' => 'Show custom maintenance page to visitors',
                'maintenance_enable_tip' => 'When active, site visitors will see a maintenance page instead of regular content. Logged-in administrators (with manage_options capability) see the normal site.',
                'maintenance_mode_type' => 'Edit mode',
                'maintenance_mode_simple' => 'Simple mode',
                'maintenance_mode_advanced' => 'Advanced mode (HTML)',
                'maintenance_heading' => 'Heading',
                'maintenance_heading_placeholder' => 'Site is currently under maintenance',
                'maintenance_message' => 'Text',
                'maintenance_message_placeholder' => 'We are working on improving our website. We apologize for the temporary inconvenience and will be back online soon!',
                'maintenance_button_text' => 'Button text',
                'maintenance_button_text_placeholder' => 'Contact us',
                'maintenance_button_url' => 'Button link',
                'maintenance_button_url_placeholder' => 'https://example.com/contact',
                'maintenance_button_show' => 'Show button',
                'maintenance_image' => 'Logo / Image',
                'maintenance_image_desc' => 'Upload an image to display above the heading (optional)',
                'maintenance_image_upload' => 'Upload image',
                'maintenance_image_remove' => 'Remove image',
                'maintenance_image_max_width' => 'Maximum image width',
                'maintenance_bg_color' => 'Background color',
                'maintenance_text_color' => 'Text color',
                'maintenance_button_bg_color' => 'Button color',
                'maintenance_button_text_color' => 'Button text color',
                'maintenance_button_radius' => 'Button corner radius',
                'maintenance_html_code' => 'Maintenance page HTML code',
                'maintenance_html_tip' => 'Enter complete HTML document including &lt;!DOCTYPE&gt;, &lt;html&gt;, &lt;head&gt; and &lt;body&gt; tags.',
                'maintenance_show_logged' => 'Also show for logged-in users',
                'maintenance_show_logged_desc' => 'Logged-in users (except administrators) will also see maintenance mode',

                'custom_scripts' => 'Custom Scripts & Codes',
                'google_maps_api_key' => 'Google Maps API Key',
                'google_maps_api_key_desc' => 'In order to use Google Maps, you need to generate an API key and enter it here. Please see the',
                'google_maps_api_key_link' => 'official documentation',
                'custom_functions' => 'Custom PHP Code (functions.php)',
                'custom_functions_desc' => 'Enable custom PHP functions',
                'custom_functions_tip' => 'Adds custom PHP code that runs when WordPress loads. Equivalent to adding code to your theme\'s functions.php. WARNING: Bad code can break your site!',
                'custom_functions_info' => 'ℹ️ Enter PHP code <strong>WITHOUT</strong> opening/closing &lt;?php ?&gt; tags.',
                'custom_functions_placeholder' => '

add_filter(\'wp_footer\', function() {
    echo \'<p>Custom text</p>\';
});',
                'custom_css' => 'Custom CSS',
                'custom_css_desc' => 'Enable custom CSS styles',
                'custom_css_tip' => 'Adds custom CSS styles to the &lt;head&gt; section. Enter code WITHOUT &lt;style&gt; tags - they will be added automatically. Example: body { background: #fff; }',
                'custom_css_active_warning' => 'Custom CSS is active and applied site-wide. Deactivate if the site displays unexpected styles.',
                'custom_css_theme' => 'Theme:',
                'custom_css_info' => 'ℹ️ Enter CSS <strong>WITHOUT</strong> &lt;style&gt; tags - they will be added automatically.',
                'custom_css_placeholder' => 'body {
  background: #fff;
  font-family: Arial, sans-serif;
}

.my-class {
  color: #333;
}',
                'script_head' => 'Custom JavaScript in &lt;head&gt;',
                'script_head_desc' => 'Enable custom JavaScript',
                'script_head_tip' => 'Adds custom JavaScript to the &lt;head&gt; section. Use for: analytics, custom JS functions. Enter with &lt;script&gt; tag.',
                'script_body_start' => 'Insert code at beginning of &lt;body&gt;',
                'script_body_start_desc' => 'Enable tracking codes',
                'script_body_start_tip' => 'Adds code right after the opening &lt;body&gt; tag. Ideal for: Google Tag Manager, Facebook Pixel, tracking codes that require placement at the beginning of body.',
                'script_body_end' => 'Insert code before &lt;/body&gt;',
                'script_body_end_desc' => 'Code will be inserted before the closing &lt;/body&gt; tag',
                'script_body_end_tip' => 'Adds code before the closing &lt;/body&gt; tag. Recommended for: page speed optimization, non-essential scripts, analytics.',
                'script_placeholder_head' => '<script>
  
  console.log("Hello from head");
</script>',
                'script_placeholder_body' => '<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){...})(window,document,\'script\',\'dataLayer\',\'GTM-XXXXX\');
</script>
<!-- End Google Tag Manager -->',

                'robots_editor' => 'Robots.txt Editor',
                'robots_enable' => 'Enable custom robots.txt',
                'robots_enable_desc' => 'Override default WordPress robots.txt',
                'robots_enable_tip' => 'Activates custom robots.txt file. WordPress has a default robots.txt, this feature allows you to override it with your own content.',
                'robots_content' => 'Robots.txt content',
                'robots_template' => 'Template',
                'robots_template_default' => 'Default (WordPress)',
                'robots_template_allow' => 'Allow all',
                'robots_template_disallow' => 'Disallow all',
                'robots_template_custom' => 'Custom',
                'robots_apply' => 'Apply template',
                'robots_tip' => 'Edit rules for search engines. The robots.txt file tells bots (Google, Bing...) which parts of the site they can index.',
                'robots_info' => 'ℹ️ After saving, the content <strong>will be written to the physical file</strong> <code>robots.txt</code> in the site root. If the file exists, it will be overwritten.',

                'htaccess_editor' => '.htaccess Editor',
                'htaccess_enable' => 'Enable custom .htaccess rules',
                'htaccess_enable_desc' => 'Add custom rules to .htaccess',
                'htaccess_enable_tip' => 'Adds custom Apache rules to the .htaccess file. WARNING: Incorrect configuration can break your site!',
                'htaccess_content' => 'Custom .htaccess rules',
                'htaccess_warning' => 'WARNING: Incorrect rules can break your site! Plugin automatically creates backup as <code>.htaccess.wp-admin-studio-backup</code>.',
                'htaccess_info' => 'ℹ️ Rules will be <strong>added TO THE BEGINNING</strong> of the .htaccess file (not overwriting the entire file). They will be wrapped with comments <code># BEGIN WP WP Admin Studio</code> and <code># END WP WP Admin Studio</code>.',
                'htaccess_template' => 'Template',
                'htaccess_template_security' => 'Security rules',
                'htaccess_template_cache' => 'Cache headers',
                'htaccess_template_redirect' => '301 Redirect',
                'htaccess_template_custom' => 'Custom',
                'htaccess_apply' => 'Apply template',
                'htaccess_tip' => 'Add custom Apache directives. You can set: redirects, cache, security rules, GZIP compression.',
                'htaccess_backup_success' => 'Backup saved as .htaccess.wp-admin-studio-backup',
                'htaccess_backup_restore' => 'If problems occur, restore the .htaccess.wp-admin-studio-backup file',
                'htaccess_restore_button' => 'Restore from Backup',
                'htaccess_restore_confirm' => 'Are you sure you want to restore .htaccess from backup? Current .htaccess will be overwritten and your WP Admin Studio rules will be removed.',
                'htaccess_restore_success' => '.htaccess has been successfully restored from backup!',
                'htaccess_restore_error' => 'Restore error: backup file does not exist or is not readable.',
                'htaccess_no_backup' => 'Backup file not found.',

                'feedback_bug' => 'Report a bug',

                'bug_report_title' => 'Report a Bug',
                'bug_report_email' => 'Your email',
                'bug_report_message' => 'Bug description',
                'bug_report_message_placeholder' => 'Describe the bug you encountered...',
                'bug_report_screenshot' => 'Screenshot (optional)',
                'bug_report_screenshot_desc' => 'PNG or JPG, max 5 MB',
                'bug_report_system_info' => 'System Information',
                'bug_report_url' => 'Site URL',
                'bug_report_consent' => 'By sending this report you agree that the above system information will be transmitted to the plugin author (KACER STUDIO s.r.o.) solely for the purpose of diagnosing and resolving the reported issue.',
                'bug_report_send' => 'Send',
                'bug_report_success' => 'Thank you! Your report has been sent successfully.',
                'bug_report_error_empty' => 'Please fill in the bug description.',
                'bug_report_error_email' => 'Please enter a valid email address.',
                'bug_report_error_send' => 'Error sending. Please try again.',
                'bug_report_error_security' => 'Security check failed.',
                'cancel' => 'Cancel',
                'sending' => 'Sending',
                'search_placeholder' => 'Search...',
            ),
            'de' => array(
                'page_title' => 'WP Admin Studio',
                'version' => 'Version ' . self::VERSION,
                'author' => 'Erstellt von <a href="https://kacer.studio" target="_blank">KACER STUDIO s.r.o.</a>',
                'bulk_actions' => 'Massenaktionen',
                'enable_all' => 'Alle aktivieren',
                'disable_all' => 'Alle deaktivieren',
                'import_export' => 'Einstellungen sichern',
                'export' => 'Exportieren',
                'import' => 'Importieren',
                'export_desc' => 'Lädt eine JSON-Datei mit den vollständigen Plugin-Einstellungen herunter.',
                'import_desc' => 'Laden Sie eine JSON-Datei hoch, um Einstellungen aus einem früheren Backup wiederherzustellen.',
                'save' => 'Änderungen speichern',
                'saving' => 'Speichern',
                'settings_saved' => 'Gespeichert!',
                'all_enabled' => 'Alle Einstellungen wurden aktiviert!',
                'all_disabled' => 'Alle Einstellungen wurden deaktiviert!',
                'import_success' => 'Einstellungen erfolgreich importiert!',
                'import_error' => 'Fehler: Datei wurde nicht hochgeladen.',
                'import_invalid' => 'Fehler: Ungültige Einstellungsdatei.',
                'lang_switch' => 'Sprache wechseln',
                
                'admin_section' => 'Administration',
                'editor_section' => 'Seiten und Beiträge',
                'frontend' => 'Frontend und Leistung',
                'comments' => 'Kommentare',
                'forms' => 'Formulare',
                'translations' => 'Benutzerdefinierte Übersetzungen',
                'nav_admin' => 'Admin',
                'nav_scripts' => 'Skripte',
                'nav_maintenance' => 'Wartung',
                'nav_login' => 'Anmeldung',
                'nav_editor' => 'Editor',
                'nav_frontend' => 'Frontend',
                'nav_comments' => 'Kommentare',
                'nav_forms' => 'Formulare',
                'nav_translations' => 'Übersetzungen',
                'nav_system' => 'System',
                'nav_backup' => 'Backup',
                'admin_bar_tip' => 'Markieren Sie die Elemente, die Sie aus der WordPress-Admin-Leiste entfernen möchten.',
                'admin_bar_logo' => 'WordPress-Logo (einschließlich Untermenü mit Links zur Dokumentation)',
                'admin_bar_updates' => 'Aktualisierungen',
                'admin_bar_comments' => 'Kommentare',
                'admin_bar_new' => 'Aktion "Neu hinzufügen" (Beitrag, Seite, Medium...)',
                'admin_bar_view' => 'Link "Website ansehen"',
                
                'login_lang' => 'Sprachumschalter ausblenden',
                'login_lang_desc' => 'Sprachumschalter ausblenden',
                'login_lang_tip' => 'Entfernt das Dropdown-Menü zur Sprachauswahl von der WordPress-Anmeldeseite (wp-login.php).',
                
                'hide_updates' => 'Updates für Nicht-Admin-Benutzer ausblenden',
                'hide_updates_desc' => 'Nur Administratoren sehen Update-Benachrichtigungen',
                'hide_updates_tip' => 'Blendet Benachrichtigungen über verfügbare Plugin-, Theme- und WordPress-Updates für alle Benutzer außer Administratoren aus. Reduziert Verwirrung für Redakteure und Mitwirkende.',
                
                'disable_auto_update_emails' => 'Automatische Update-E-Mails deaktivieren',
                'disable_auto_update_emails_desc' => 'Automatische Update-E-Mails deaktivieren',
                'disable_auto_update_emails_tip' => 'WordPress sendet nach jedem automatischen Update von Core, Plugins oder Themes eine E-Mail. Diese Option schaltet sie stumm.',
                
                'hide_admin_notices' => 'Admin-Hinweise',
                'hide_admin_notices_desc' => 'Admin-Hinweise für alle Benutzer ausblenden',
                'hide_admin_notices_tip' => 'Blendet Plugin- und WordPress-Hinweisbanner für alle angemeldeten Benutzer aus. Optional können Hinweise für einen bestimmten Administrator sichtbar bleiben.',
                'show_notices_current_user_desc' => 'Hinweise nur anzeigen für: ',
                
                'hide_howdy' => '"Angemeldet als:" aus Admin-Leiste ausblenden',
                'hide_howdy_desc' => 'Nur Benutzernamen anzeigen',
                'hide_howdy_tip' => 'Entfernt den Text "Angemeldet als:" aus der rechten oberen Ecke der Admin-Leiste und belässt nur den Namen des angemeldeten Benutzers.',
                
                'hide_wp_version' => 'WordPress-Version aus Admin-Fußzeile ausblenden',
                'hide_wp_version_desc' => 'Entfernt "Danke, dass Sie WordPress verwenden. Version X.X.X"',
                'hide_wp_version_tip' => 'Blendet die WordPress-Versionsinformationen aus der Fußzeile der Administration aus.',
                
                'hide_dashboard_widgets' => 'Dashboard-Widgets ausblenden',
                'hide_dashboard_widgets_tip' => 'Aktivieren Sie die Widgets, die im Dashboard ausgeblendet werden sollen. Die Widget-Liste wird bei jedem Dashboard-Besuch automatisch aktualisiert.',
                
                'admin_page_titles' => 'Benutzerdefinierte Seitentitel in der Administration',
                'admin_page_titles_desc' => 'Browser-Tab-Titel bearbeiten',
                'admin_page_titles_tip' => 'Ermöglicht das Festlegen eines benutzerdefinierten Seitentitelformats, das im Browser-Tab bei der Arbeit in der WordPress-Administration angezeigt wird. Sie können Tags verwenden: %page% (aktueller Seitenname) und %site_title% (Website-Name).',
                'admin_page_title_page_tag' => 'aktueller Seitenname',
                'admin_page_title_site_tag' => 'Website-Name',
                
                'disable_gutenberg' => 'Gutenberg-Editor deaktivieren',
                'disable_gutenberg_desc' => 'Klassischen Editor anstelle des Block-Editors verwenden',
                'disable_gutenberg_tip' => 'Schaltet WordPress auf den klassischen Editor für alle Beiträge, Seiten und Widgets zurück.',
                
                'duplicate_posts' => 'Seiten & Beiträge duplizieren',
                'duplicate_posts_desc' => 'Schnelles Kopieren mit einem Klick aktivieren',
                'duplicate_posts_tip' => 'Fügt "Kopie erstellen" Button zu Aktionen für Seiten, Beiträge und Custom Post Types hinzu. Kopie enthält alle Inhalte, Metadaten, Taxonomien und Custom Fields. Duplikat wird als Entwurf gespeichert und zum Bearbeiten geöffnet.',
                'duplicate_action' => 'Kopie erstellen',
                'duplicate_success' => 'Kopie wurde erstellt und zur Bearbeitung geöffnet.',
                
                'enable_svg_upload' => 'SVG-Dateiunterstützung',
                'enable_svg_upload_desc' => 'SVG-Datei-Uploads aktivieren',
                'enable_svg_upload_tip' => 'Ermöglicht das Hochladen von SVG-Dateien in die WordPress-Mediathek.',

                'enable_media_replace' => 'Mediendateien ersetzen',
                'enable_media_replace_desc' => 'Option zum Ersetzen von Dateien direkt in der Mediathek hinzufügen',
                'mr_row_action' => 'Datei ersetzen',
                'mr_page_title' => 'Datei ersetzen',
                'mr_back' => 'Zurück zur Mediathek',
                'mr_edit' => 'Medium bearbeiten',
                'mr_cancel' => 'Abbrechen',
                'mr_submit' => 'Hochladen und ersetzen',
                'mr_uploading' => 'Wird hochgeladen...',
                'mr_badge_new' => 'neu',
                'mr_label_old' => 'Aktuelle Datei',
                'mr_label_new' => 'Neue Datei',
                'mr_uploaded' => 'Hochgeladen',
                'mr_drop_title' => 'Datei auswählen oder hierher ziehen',
                'mr_drop_sub' => 'Klicken Sie hier',
                'mr_mode_keep_title' => 'URL bleibt gleich',
                'mr_mode_keep_desc' => 'Die Datei wird ausgetauscht, aber ihre URL bleibt unverändert. Alle Stellen, an denen sie verwendet wird, funktionieren weiterhin.',
                'mr_mode_new_title' => 'URL ändern und überall aktualisieren',
                'mr_mode_new_desc' => 'Die Datei erhält einen neuen Namen und ihre neue URL wird automatisch überall auf der Website aktualisiert.',
                'mr_mode_section_title' => 'Was passiert mit der Datei-URL',
                'mr_success_keep' => 'Datei erfolgreich ersetzt',
                'mr_success_new' => 'Datei ersetzt und URLs aktualisiert',
                'mr_desc_keep' => 'Die neue Datei ist aktiv. Die URL blieb gleich — keine weiteren Änderungen waren nötig.',
                'mr_desc_new' => 'Die neue Datei ist aktiv. Ihre URL wurde automatisch überall auf der Website aktualisiert.',
                'mr_error_no_file' => 'Es wurde keine Datei hochgeladen oder ein Upload-Fehler ist aufgetreten.',
                'mr_error_write' => 'Die Datei konnte nicht überschrieben werden. Überprüfen Sie die Berechtigungen des Upload-Ordners.',
                'mr_error_perm' => 'Sie haben keine Berechtigung für diese Aktion.',
                'mr_error_id' => 'Ungültige Anhang-ID.',
                'mr_error_not_found' => 'Anhang nicht gefunden.',
                'mr_js_no_file' => 'Bitte wählen Sie eine Datei zum Hochladen aus.',
                
                'wp_emails' => 'WordPress E-Mails',
                'wp_emails_desc' => 'E-Mail-Absender ändern',
                'wp_emails_tip' => 'Ändern Sie den Absendernamen und die E-Mail-Adresse für alle von WordPress gesendeten E-Mails (Registrierung, Passwort-Reset, Benachrichtigungen usw.). Betrifft auch Plugins, die keinen eigenen Absender angeben.',
                'wp_email_from_name' => 'Absendername:',
                'wp_email_from_name_placeholder' => 'z.B. KACER STUDIO',
                'wp_email_from_name_default' => 'Standard ist',
                'wp_email_from_email' => 'E-Mail-Adresse:',
                'wp_email_from_email_placeholder' => 'z.B. info@kacer.studio',
                'wp_email_from_email_default' => 'Standard ist',
                'wp_email_domain_warning' => 'Die E-Mail muss von derselben Domain wie die Website stammen (z.B.',
                'wp_email_domain_warning_2' => '). Das Hosting erfordert, dass der Absender von der Website-Domain stammt.',
                
                'post_colors' => 'Farbige Kennzeichnung der Beiträge nach Status',
                'post_colors_desc' => 'Farbliche Unterscheidung in der Administration aktivieren',
                'post_colors_tip' => 'In der Beitragsliste in der Administration werden die einzelnen Status farblich unterschieden für bessere Übersichtlichkeit.',
                
                'custom_colors' => 'Benutzerdefinierte Farben für einzelne Status',
                'color_draft' => 'Entwurf',
                'color_pending' => 'Wartet auf Genehmigung',
                'color_publish' => 'Veröffentlicht',
                'color_future' => 'Geplant',
                'color_private' => 'Privat',
                'color_note' => 'Leer lassen, um die Standard-WordPress-Farbe beizubehalten',
                
                'edit_link' => 'Schaltfläche "Bearbeiten" im Frontend',
                'edit_link_desc' => 'Fixierte Schaltfläche in der linken unteren Ecke anzeigen',
                'edit_link_tip' => 'Fügt eine fixierte Schaltfläche "Bearbeiten" in der linken unteren Ecke der Website für angemeldete Benutzer mit Berechtigung zum Bearbeiten von Beiträgen hinzu. Ermöglicht schnellen Zugriff auf die Bearbeitung der aktuellen Seite.',
                
                'archive_titles' => 'Kategorie- und Tag-Überschriften',
                'archive_titles_desc' => 'Überschriften anpassen',
                'archive_titles_tip' => 'Auf Kategorieseiten wird statt "Kategorie: Nachrichten" nur "Nachrichten" angezeigt.

Funktioniert bei URLs wie /category/XXX/ oder /tag/XXX/',
                'category_prefix' => 'Text vor Kategorie:',
                'category_prefix_placeholder' => 'Themen:',
                'tag_prefix' => 'Text vor Tag:',
                'tag_prefix_placeholder' => 'Schlagwörter:',
                
                'year_shortcode' => 'Shortcode [year] aktivieren',
                'year_shortcode_desc' => 'Shortcode zur Anzeige des aktuellen Jahres aktivieren',
                'year_shortcode_tip' => 'Aktiviert den Shortcode [year], der ÜBERALL funktioniert: in Beitrags-/Seiteninhalten, Widgets, Menüs, Auszügen, Theme-Einstellungen (Footer Copyright-Text für alle populären Themes) und mehr. PHP-Code funktioniert nur in .php-Dateien.',
                'year_example' => 'Verwendung: [year] zeigt das aktuelle Jahr (' . gmdate('Y') . ')',
                
                'responsive_images' => 'Responsive Bilder deaktivieren',
                'responsive_images_desc' => 'Automatische Generierung von srcset und sizes deaktivieren',
                'responsive_images_tip' => 'WordPress generiert standardmäßig mehrere Größen jedes Bildes und fügt srcset/sizes-Attribute für responsive Anzeige hinzu. Diese Option deaktiviert dies - ideal, wenn Sie Bilder auf eigene Weise optimieren, ein CDN verwenden oder ein Lazy-Loading-Plugin, das sich anders um Responsivität kümmert.',
                'responsive_images_note' => 'Vereinfacht den HTML-Code der Bilder. Geeignet, wenn Sie eigene Optimierung oder CDN verwenden.',
                'disable_big_image_threshold' => 'Automatische Verkleinerung großer Bilder deaktivieren',
                'disable_big_image_threshold_desc' => 'Automatische Verkleinerung von Bildern über 2560px deaktivieren',
                'disable_big_image_threshold_tip' => 'Seit WordPress 5.3 werden große Bilder (über 2560px) beim Hochladen automatisch verkleinert. Diese Option deaktiviert dies - nützlich, wenn Sie die ursprünglichen Bildabmessungen beibehalten müssen.',
                'disable_big_image_threshold_note' => 'Behält die ursprünglichen Abmessungen der hochgeladenen Bilder bei.',
                
                'comment_url' => 'Feld "Website" aus Kommentaren entfernen',
                'comment_url_desc' => 'Optionales URL-Feld ausblenden',
                'comment_url_tip' => 'Entfernt das optionale Feld "Website" (URL) aus dem Kommentarformular. Reduziert Spam und vereinfacht das Formular.',
                
                'disable_comments_completely' => 'Kommentare komplett deaktivieren',
                'disable_comments_completely_desc' => 'Kommentare schließen + aus Menü ausblenden',
                'disable_comments_completely_tip' => 'Schließt Kommentare für alle Beiträge und Seiten und blendet "Kommentare" aus dem Admin-Menü aus.',
                
                'disable_user_enumeration' => 'Benutzerkonten schützen',
                'disable_user_enumeration_desc' => 'Benutzeraufzählung blockieren',
                'disable_user_enumeration_tip' => 'Blockiert REST-API-Endpunkte für Benutzerlisten (wp/v2/users) und entfernt Autor-Archivseiten, um Benutzeraufzählung über ?author=1 zu verhindern.',
                
                'auto_delete_files' => 'Automatisches Löschen unnötiger Dateien',
                'auto_delete_files_desc' => 'Unnötige Dateien automatisch löschen',
                'auto_delete_files_tip' => 'Entfernt nach jedem WordPress-Update automatisch die Dateien license.txt, readme.html und wp-config-sample.php vom FTP-Server.',
                
                'change_login_url' => 'Änderung der Anmelde-URL',
                'change_login_url_desc' => 'Benutzerdefinierte Anmelde-URL festlegen',
                'change_login_url_tip' => 'Ändert die Standard-wp-login.php zu einer benutzerdefinierten URL-Adresse für erhöhte Sicherheit. Angreifer können Ihre Anmeldeseite nicht über Standard-URLs finden.',
                'custom_login_slug' => 'Anmelde-URL',
                'login_url_invalid_slug' => 'Slug darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
                'login_url_reserved_slug' => 'Dieser Slug ist reserviert und kann nicht verwendet werden.',
                'login_url_current' => 'Ihre aktuelle Anmelde-URL:',
                
                'login_page' => 'Anmeldeseite',
                'login_customize' => 'Benutzerdefinierte Anmeldeseite',
                'login_customize_desc' => 'Erscheinungsbild der Anmeldeseite anpassen',
                'login_customize_tip' => 'Passen Sie Logo, Hintergrund, Farben und Links der Anmeldeseite für ein professionelles Aussehen an.',
                'login_logo' => 'Logo',
                'login_logo_upload' => 'Logo hochladen',
                'login_logo_remove' => 'Logo entfernen',
                'login_logo_height' => 'Logo-Höhe',
                'login_logo_url' => 'Logo-Link',
                'login_logo_url_placeholder' => 'https://ihre-seite.de',
                'login_logo_url_desc' => 'URL, zu der der Logo-Klick führt (Standard: Homepage)',
                'login_bg_color' => 'Hintergrundfarbe',
                'login_bg_image' => 'Hintergrundbild',
                'login_bg_image_upload' => 'Bild hochladen',
                'login_bg_image_remove' => 'Bild entfernen',
                'login_bg_size' => 'Hintergrundabdeckung',
                'login_bg_size_cover' => 'Gesamten Bereich abdecken (cover)',
                'login_bg_size_contain' => 'Gesamtes Bild anzeigen (contain)',
                'login_bg_size_repeat' => 'Wiederholen (repeat)',
                'login_primary_color' => 'Primärfarbe',
                'login_primary_color_desc' => 'Farbe für Links und Fokus',
                'login_form_radius' => 'Formular-Eckenradius',
                'login_form_bg_color' => 'Formular-Hintergrundfarbe',
                'login_form_text_color' => 'Formular-Textfarbe',
                'login_button_bg' => 'Button-Farbe',
                'login_button_text_color' => 'Button-Textfarbe',
                'login_button_radius' => 'Button-Eckenradius',
                'login_links_color' => 'Link-Farbe',
                'login_hide_lostpassword' => 'Link "Passwort vergessen?" ausblenden',
                'login_hide_backtoblog' => 'Link "Zurück zu..." ausblenden',
                'login_hide_rememberme' => 'Checkbox "Angemeldet bleiben" ausblenden',
                'login_hide_privacy' => 'Datenschutzrichtlinie-Link ausblenden',
                'login_custom_css' => 'Benutzerdefiniertes CSS',
                'login_custom_css_desc' => 'Erweiterte CSS-Anpassungen',
                
                'wpforms_countries' => 'Telefonvorwahl-Auswahl einschränken',
                'wpforms_countries_tip' => 'Schränkt die Vorwahlauswahl auf die gewählte Ländergruppe ein. Funktioniert automatisch mit WPForms, SureForms, Fluent Forms und anderen Plugins, die die intl-tel-input-Bibliothek verwenden.',
                'no_form_plugin' => 'Kein aktives Formular-Plugin gefunden',
                'phone_restrict_off' => 'Deaktiviert',
                'phone_restrict_czsk' => 'CZ + SK',
                'phone_restrict_europe' => 'Europa',
                'phone_restrict_us' => 'USA + Kanada',
                
                'enable_trans' => 'Benutzerdefinierte Textübersetzungen aktivieren',
                'enable_trans_desc' => 'Unten definierte Übersetzungen auf das Frontend der Website anwenden',
                'enable_trans_tip' => 'Ersetzt ausgewählte englische Texte durch deutsche Äquivalente im Frontend der Website (greift nicht in die Administration ein).',
                
                'trans_defs' => 'Übersetzungsdefinitionen',
                'trans_note' => 'ℹ️ Bearbeiten oder fügen Sie benutzerdefinierte Übersetzungen hinzu. Lassen Sie das Übersetzungsfeld leer, um den Text vollständig aus der Ausgabe zu entfernen. Sie können HTML-Tags im Übersetzungsfeld verwenden.',
                'trans_html_allowed' => 'Sie können HTML verwenden (z.B. <a href="">Link</a>)',
                'from' => 'Originaltext',
                'to' => 'Übersetzung (Deutsch)',
                'remove' => 'Entfernen',
                'add_trans' => 'Neue Übersetzung hinzufügen',
                'export_trans' => 'Übersetzungen exportieren',
                'import_trans' => 'Übersetzungen importieren',
                'import_trans_confirm' => 'Import ersetzt alle vorhandenen Übersetzungen. Fortfahren?',
                
                'system_info' => 'Systeminformationen',
                'site_name' => 'Website-Name',
                'site_url' => 'URL-Adresse',
                'protocol' => 'Protokoll',
                'protocol_https' => 'HTTPS',
                'protocol_http' => 'HTTP',
                'wp_version' => 'WordPress-Version',
                'php_version' => 'PHP-Version',
                'php_modern' => 'Modern',
                'php_outdated' => 'Veraltet',
                'mysql_version' => 'MySQL-Version',
                'server' => 'Webserver',
                'php_memory' => 'PHP-Speicherlimit',
                'wp_memory' => 'WP-Speicherlimit',
                'max_upload' => 'Max. Upload-Größe',

                'maintenance_mode' => 'Wartungsmodus',
                'maintenance_enable' => 'Wartungsmodus aktivieren',
                'maintenance_enable_desc' => 'Benutzerdefinierte Wartungsseite für Besucher anzeigen',
                'maintenance_enable_tip' => 'Wenn aktiv, sehen Website-Besucher eine Wartungsseite anstelle des regulären Inhalts. Angemeldete Administratoren (mit manage_options Berechtigung) sehen die normale Website.',
                'maintenance_mode_type' => 'Bearbeitungsmodus',
                'maintenance_mode_simple' => 'Einfacher Modus',
                'maintenance_mode_advanced' => 'Erweiterter Modus (HTML)',
                'maintenance_heading' => 'Überschrift',
                'maintenance_heading_placeholder' => 'Website befindet sich derzeit in Wartung',
                'maintenance_message' => 'Text',
                'maintenance_message_placeholder' => 'Wir arbeiten an der Verbesserung unserer Website. Wir entschuldigen uns für die vorübergehende Unannehmlichkeit und werden bald wieder online sein!',
                'maintenance_button_text' => 'Button-Text',
                'maintenance_button_text_placeholder' => 'Kontaktieren Sie uns',
                'maintenance_button_url' => 'Button-Link',
                'maintenance_button_url_placeholder' => 'https://example.com/kontakt',
                'maintenance_button_show' => 'Button anzeigen',
                'maintenance_image' => 'Logo / Bild',
                'maintenance_image_desc' => 'Laden Sie ein Bild hoch, das über der Überschrift angezeigt wird (optional)',
                'maintenance_image_upload' => 'Bild hochladen',
                'maintenance_image_remove' => 'Bild entfernen',
                'maintenance_image_max_width' => 'Maximale Bildbreite',
                'maintenance_bg_color' => 'Hintergrundfarbe',
                'maintenance_text_color' => 'Textfarbe',
                'maintenance_button_bg_color' => 'Button-Farbe',
                'maintenance_button_text_color' => 'Button-Textfarbe',
                'maintenance_button_radius' => 'Button-Eckenradius',
                'maintenance_html_code' => 'HTML-Code der Wartungsseite',
                'maintenance_html_tip' => 'Geben Sie ein vollständiges HTML-Dokument ein, einschließlich &lt;!DOCTYPE&gt;, &lt;html&gt;, &lt;head&gt; und &lt;body&gt; Tags.',
                'maintenance_show_logged' => 'Auch für angemeldete Benutzer anzeigen',
                'maintenance_show_logged_desc' => 'Angemeldete Benutzer (außer Administratoren) sehen ebenfalls den Wartungsmodus',

                'custom_scripts' => 'Eigene Skripte & Codes',
                'google_maps_api_key' => 'Google Maps API-Schlüssel',
                'google_maps_api_key_desc' => 'Um Google Maps zu verwenden, müssen Sie einen API-Schlüssel generieren und hier eingeben. Weitere Informationen finden Sie in der',
                'google_maps_api_key_link' => 'offiziellen Dokumentation',
                'custom_functions' => 'Eigener PHP-Code (functions.php)',
                'custom_functions_desc' => 'Eigene PHP-Funktionen aktivieren',
                'custom_functions_tip' => 'Fügt eigenen PHP-Code hinzu, der beim Laden von WordPress ausgeführt wird. Entspricht dem Hinzufügen von Code zu functions.php Ihres Themes. WARNUNG: Fehlerhafter Code kann Ihre Website beschädigen!',
                'custom_functions_info' => 'ℹ️ PHP-Code <strong>OHNE</strong> öffnende/schließende &lt;?php ?&gt; Tags eingeben.',
                'custom_functions_placeholder' => '

add_filter(\'wp_footer\', function() {
    echo \'<p>Eigener Text</p>\';
});',
                'custom_css' => 'Eigenes CSS',
                'custom_css_desc' => 'Eigenes CSS aktivieren',
                'custom_css_tip' => 'Fügt eigene CSS-Stile zum &lt;head&gt; hinzu. OHNE &lt;style&gt; Tags eingeben.',
                'custom_css_active_warning' => 'Eigenes CSS ist aktiv und wird auf der gesamten Website angewendet. Deaktivieren, falls unerwartete Stile erscheinen.',
                'custom_css_theme' => 'Thema:',
                'custom_css_info' => 'ℹ️ CSS-Code <strong>OHNE</strong> &lt;style&gt; Tags eingeben - diese werden automatisch hinzugefügt.',
                'custom_css_placeholder' => 'body {
  background: #fff;
}',
                'script_head' => 'Eigenes JavaScript in &lt;head&gt;',
                'script_head_desc' => 'Eigenes JavaScript aktivieren',
                'script_head_tip' => 'Fügt JavaScript zum &lt;head&gt; hinzu.',
                'script_body_start' => 'Code am Anfang von &lt;body&gt; einfügen',
                'script_body_start_desc' => 'Tracking-Codes aktivieren',
                'script_body_start_tip' => 'Fügt Code direkt nach dem öffnenden &lt;body&gt;-Tag hinzu. Ideal für: Google Tag Manager, Facebook Pixel, Tracking-Codes, die am Anfang des Body platziert werden müssen.',
                'script_body_end' => 'Code vor &lt;/body&gt; einfügen',
                'script_body_end_desc' => 'Code wird vor dem schließenden &lt;/body&gt;-Tag eingefügt',
                'script_body_end_tip' => 'Fügt Code vor dem schließenden &lt;/body&gt;-Tag hinzu. Empfohlen für: Seitengeschwindigkeitsoptimierung, nicht essentielle Skripte, Analytics.',
                'script_placeholder_head' => '<script>
  console.log("Hello");
</script>',
                'script_placeholder_body' => '<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){...})();
</script>',

                'robots_editor' => 'Robots.txt Editor',
                'robots_enable' => 'Eigene robots.txt aktivieren',
                'robots_enable_desc' => 'Standard WordPress robots.txt überschreiben',
                'robots_enable_tip' => 'Aktiviert eine benutzerdefinierte robots.txt-Datei. WordPress hat eine Standard-robots.txt, diese Funktion ermöglicht es Ihnen, sie mit eigenem Inhalt zu überschreiben.',
                'robots_content' => 'Robots.txt Inhalt',
                'robots_template' => 'Vorlage',
                'robots_template_default' => 'Standard (WordPress)',
                'robots_template_allow' => 'Alles erlauben',
                'robots_template_disallow' => 'Alles verbieten',
                'robots_template_custom' => 'Benutzerdefiniert',
                'robots_apply' => 'Vorlage anwenden',
                'robots_tip' => 'Bearbeiten Sie Regeln für Suchmaschinen. Die robots.txt-Datei teilt Bots (Google, Bing...) mit, welche Teile der Website sie indexieren können.',
                'robots_info' => 'ℹ️ Nach dem Speichern wird der Inhalt <strong>in die physische Datei</strong> <code>robots.txt</code> im Website-Stammverzeichnis geschrieben. Wenn die Datei existiert, wird sie überschrieben.',

                'htaccess_editor' => '.htaccess Editor',
                'htaccess_enable' => 'Eigene .htaccess Regeln aktivieren',
                'htaccess_enable_desc' => 'Eigene Regeln zu .htaccess hinzufügen',
                'htaccess_enable_tip' => 'Fügt benutzerdefinierte Apache-Regeln zur .htaccess-Datei hinzu. WARNUNG: Falsche Konfiguration kann Ihre Website unbrauchbar machen!',
                'htaccess_content' => 'Eigene .htaccess Regeln',
                'htaccess_warning' => 'WARNUNG: Falsche Regeln können Ihre Website unbrauchbar machen! Plugin erstellt automatisch Backup als <code>.htaccess.wp-admin-studio-backup</code>.',
                'htaccess_info' => 'ℹ️ Regeln werden <strong>AM ANFANG</strong> der .htaccess-Datei hinzugefügt (nicht die gesamte Datei überschreibend). Sie werden mit Kommentaren <code># BEGIN WP WP Admin Studio</code> und <code># END WP WP Admin Studio</code> umschlossen.',
                'htaccess_template' => 'Vorlage',
                'htaccess_template_security' => 'Sicherheitsregeln',
                'htaccess_template_cache' => 'Cache-Header',
                'htaccess_template_redirect' => '301 Weiterleitung',
                'htaccess_template_custom' => 'Benutzerdefiniert',
                'htaccess_apply' => 'Vorlage anwenden',
                'htaccess_tip' => 'Fügen Sie benutzerdefinierte Apache-Direktiven hinzu. Sie können festlegen: Weiterleitungen, Cache, Sicherheitsregeln, GZIP-Kompression.',
                'htaccess_backup_success' => 'Backup gespeichert als .htaccess.wp-admin-studio-backup',
                'htaccess_backup_restore' => 'Bei Problemen die Datei .htaccess.wp-admin-studio-backup wiederherstellen',
                'htaccess_restore_button' => 'Aus Backup wiederherstellen',
                'htaccess_restore_confirm' => 'Möchten Sie wirklich .htaccess aus dem Backup wiederherstellen? Die aktuelle .htaccess wird überschrieben und Ihre WP Admin Studio-Regeln werden entfernt.',
                'htaccess_restore_success' => '.htaccess wurde erfolgreich aus dem Backup wiederhergestellt!',
                'htaccess_restore_error' => 'Wiederherstellungsfehler: Backup-Datei existiert nicht oder ist nicht lesbar.',
                'htaccess_no_backup' => 'Backup-Datei nicht gefunden.',

                'feedback_bug' => 'Fehler melden',

                'bug_report_title' => 'Fehler melden',
                'bug_report_email' => 'Ihre E-Mail',
                'bug_report_message' => 'Fehlerbeschreibung',
                'bug_report_message_placeholder' => 'Beschreiben Sie den aufgetretenen Fehler...',
                'bug_report_screenshot' => 'Screenshot (optional)',
                'bug_report_screenshot_desc' => 'PNG, JPG oder GIF, max 5 MB',
                'bug_report_system_info' => 'Systeminformationen',
                'bug_report_url' => 'Website-URL',
                'bug_report_consent' => 'Mit dem Absenden dieses Berichts stimmen Sie zu, dass die oben genannten Systeminformationen an den Plugin-Autor (KACER STUDIO s.r.o.) ausschließlich zum Zweck der Diagnose und Lösung des gemeldeten Problems übermittelt werden.',
                'bug_report_send' => 'Senden',
                'bug_report_success' => 'Vielen Dank! Ihr Bericht wurde erfolgreich gesendet.',
                'bug_report_error_empty' => 'Bitte füllen Sie die Fehlerbeschreibung aus.',
                'bug_report_error_send' => 'Fehler beim Senden. Bitte versuchen Sie es erneut.',
                'bug_report_error_security' => 'Sicherheitsprüfung fehlgeschlagen.',
                'cancel' => 'Abbrechen',
                'sending' => 'Senden',
                'search_placeholder' => 'Suchen...',
            ),
            'sk' => array(
                'page_title' => 'WP Admin Studio',
                'version' => 'Verzia ' . self::VERSION,
                'author' => 'Vytvorilo <a href="https://kacer.studio" target="_blank">KACER STUDIO s.r.o.</a>',
                'bulk_actions' => 'Hromadné akcie',
                'enable_all' => 'Zapnúť všetko',
                'disable_all' => 'Vypnúť všetko',
                'import_export' => 'Záloha nastavení',
                'export' => 'Exportovať',
                'import' => 'Importovať',
                'export_desc' => 'Stiahne JSON súbor s kompletným nastavením pluginu.',
                'import_desc' => 'Nahrajte JSON súbor pre obnovenie nastavení z predchádzajúcej zálohy.',
                'save' => 'Uložiť zmeny',
                'saving' => 'Ukladám',
                'settings_saved' => 'Uložené!',
                'all_enabled' => 'Všetky nastavenia boli zapnuté!',
                'all_disabled' => 'Všetky nastavenia boli vypnuté!',
                'import_success' => 'Nastavenia boli úspešne importované!',
                'import_error' => 'Chyba: Súbor nebol nahraný.',
                'import_invalid' => 'Chyba: Neplatný súbor nastavení.',
                'lang_switch' => 'Prepnúť jazyk',
                
                'admin_section' => 'Administrácia',
                'editor_section' => 'Stránky a príspevky',
                'frontend' => 'Frontend a výkon',
                'comments' => 'Komentáre',
                'forms' => 'Formuláre',
                'translations' => 'Vlastné preklady',
                'nav_admin' => 'Admin',
                'nav_scripts' => 'Skripty',
                'nav_maintenance' => 'Údržba',
                'nav_login' => 'Prihlásenie',
                'nav_editor' => 'Editor',
                'nav_frontend' => 'Frontend',
                'nav_comments' => 'Komentáre',
                'nav_forms' => 'Formuláre',
                'nav_translations' => 'Preklady',
                'nav_system' => 'Systém',
                'nav_backup' => 'Záloha',
                'admin_bar_tip' => 'Označte položky, ktoré chcete odstrániť z horného admin baru WordPress.',
                'admin_bar_logo' => 'WordPress logo (vrátane submenu s odkazmi na dokumentáciu)',
                'admin_bar_updates' => 'Aktualizácie',
                'admin_bar_comments' => 'Komentáre',
                'admin_bar_new' => 'Akcia "Pridať nový" (príspevok, stránku, médium...)',
                'admin_bar_view' => 'Odkaz "Zobraziť web"',
                
                'login_lang' => 'Skryť prepínač jazykov',
                'login_lang_desc' => 'Skryť prepínač jazykov',
                'login_lang_tip' => 'Odstráni dropdown menu pre výber jazyka z prihlasovacej stránky WordPress (wp-login.php).',
                
                'hide_updates' => 'Skryť aktualizácie pre non-admin používateľov',
                'hide_updates_desc' => 'Iba administrátori uvidia notifikácie o aktualizáciách',
                'hide_updates_tip' => 'Skryje oznámenia o dostupných aktualizáciách pluginov, tém a WordPressu pre všetkých používateľov okrem administrátorov. Znižuje zmätok pre editorov a prispievateľov.',
                
                'disable_auto_update_emails' => 'Zakázať e-maily o automatických aktualizáciách',
                'disable_auto_update_emails_desc' => 'Zakázať e-maily o automatických aktualizáciách',
                'disable_auto_update_emails_tip' => 'WordPress po každej automatickej aktualizácii jadra, pluginov alebo tém posiela e-mail. Táto voľba ho utiší.',
                
                'hide_admin_notices' => 'Informačné hlášky',
                'hide_admin_notices_desc' => 'Skryť informačné hlášky pre všetkých používateľov',
                'hide_admin_notices_tip' => 'Skryje informačné lišty pluginov a WordPressu pre všetkých prihlásených používateľov. Voliteľne možno hlášky ponechať viditeľné pre konkrétneho administrátora.',
                'show_notices_current_user_desc' => 'Zobraziť hlášky iba pre: ',
                
                'hide_howdy' => 'Skryť "Prihlásený používateľ:" z admin baru',
                'hide_howdy_desc' => 'Zobraziť len meno používateľa',
                'hide_howdy_tip' => 'Odstráni text "Prihlásený používateľ:" z pravého horného rohu admin baru a ponechá len meno prihláseného používateľa.',
                
                'hide_wp_version' => 'Skryť verziu WordPressu z admin footera',
                'hide_wp_version_desc' => 'Odstráni "Ďakujeme, že používate WordPress. Verzia X.X.X"',
                'hide_wp_version_tip' => 'Skryje informáciu o verzii WordPressu z pätičky administrácie.',
                
                'hide_dashboard_widgets' => 'Skryť widgety na nástenke',
                'hide_dashboard_widgets_tip' => 'Zaškrtnite widgety, ktoré majú byť na nástenke skryté. Zoznam widgetov sa automaticky aktualizuje pri každej návšteve nástenky.',
                
                'admin_page_titles' => 'Vlastné názvy stránok v administrácii',
                'admin_page_titles_desc' => 'Upraviť názov v záložke prehliadača',
                'admin_page_titles_tip' => 'Umožňuje nastaviť vlastný formát názvu stránky, ktorý sa zobrazí v záložke prehliadača pri práci v administrácii WordPress. Môžete použiť tagy %page% (názov aktuálnej stránky) a %site_title% (názov webu).',
                'admin_page_title_page_tag' => 'názov aktuálnej stránky',
                'admin_page_title_site_tag' => 'názov webu',
                
                'disable_gutenberg' => 'Vypnúť Gutenberg editor',
                'disable_gutenberg_desc' => 'Vrátiť klasický editor',
                'disable_gutenberg_tip' => 'Prepne WordPress späť na klasický editor pre všetky príspevky, stránky aj widgety.',
                
                'duplicate_posts' => 'Duplikácia stránok a príspevkov',
                'duplicate_posts_desc' => 'Zapnúť rýchle kopírovanie jedným kliknutím',
                'duplicate_posts_tip' => 'Pridá tlačidlo "Vytvoriť kópiu" do akcií pri stránkach, príspevkoch a custom post types. Kópia zahŕňa všetok obsah, metadáta, taxonómie a custom fields. Duplikát sa uloží ako koncept a otvorí sa na úpravu.',
                'duplicate_action' => 'Vytvoriť kópiu',
                'duplicate_success' => 'Kópia bola vytvorená a otvorená na úpravu.',
                
                'enable_svg_upload' => 'Podpora SVG súborov',
                'enable_svg_upload_desc' => 'Povoliť nahrávanie SVG súborov',
                'enable_svg_upload_tip' => 'Umožní nahrávanie SVG súborov do knižnice médií vo WordPresse.',

                'enable_media_replace' => 'Nahrádzanie súborov v médiách',
                'enable_media_replace_desc' => 'Pridať možnosť nahradiť súbor priamo v knižnici médií',
                'mr_row_action' => 'Nahradiť súbor',
                'mr_page_title' => 'Nahradiť súbor',
                'mr_back' => 'Späť do knižnice médií',
                'mr_edit' => 'Upraviť médium',
                'mr_cancel' => 'Zrušiť',
                'mr_submit' => 'Nahrať a nahradiť',
                'mr_uploading' => 'Nahrávam...',
                'mr_badge_new' => 'nový',
                'mr_label_old' => 'Aktuálny súbor',
                'mr_label_new' => 'Nový súbor',
                'mr_uploaded' => 'Nahraté',
                'mr_drop_title' => 'Vyberte alebo presuňte súbor',
                'mr_drop_sub' => 'Kliknite kdekoľvek sem',
                'mr_mode_keep_title' => 'Adresa zostane rovnaká',
                'mr_mode_keep_desc' => 'Súbor sa vymení, ale jeho adresa na webe sa nezmení. Všetky miesta kde je použitý budú naďalej fungovať.',
                'mr_mode_new_title' => 'Adresa sa zmení a aktualizuje všade',
                'mr_mode_new_desc' => 'Súbor dostane nový názov a jeho nová adresa sa automaticky doplní všade, kde je na webe použitý.',
                'mr_mode_section_title' => 'Čo sa stane s adresou súboru',
                'mr_success_keep' => 'Súbor bol úspešne nahradený',
                'mr_success_new' => 'Súbor nahradený a adresy aktualizované',
                'mr_desc_keep' => 'Nový súbor je aktívny. Adresa zostala rovnaká — žiadne ďalšie zmeny neboli potrebné.',
                'mr_desc_new' => 'Nový súbor je aktívny. Jeho adresa bola automaticky aktualizovaná všade na webe.',
                'mr_error_no_file' => 'Žiadny súbor nebol nahraný alebo nastala chyba pri nahrávaní.',
                'mr_error_write' => 'Nepodarilo sa prepísať súbor. Skontrolujte oprávnenia priečinka uploads.',
                'mr_error_perm' => 'Nemáte oprávnenie na túto akciu.',
                'mr_error_id' => 'Neplatné ID prílohy.',
                'mr_error_not_found' => 'Príloha nebola nájdená.',
                'mr_js_no_file' => 'Vyberte prosím súbor na nahranie.',
                
                'wp_emails' => 'E-maily WordPressu',
                'wp_emails_desc' => 'Zmeniť odosielateľa e-mailov',
                'wp_emails_tip' => 'Zmeňte meno odosielateľa a e-mailovú adresu pre všetky e-maily odosielané WordPressom (registrácia, obnovenie hesla, oznámenia atď.). Ovplyvní aj pluginy, ktoré nešpecifikujú vlastného odosielateľa.',
                'wp_email_from_name' => 'Meno odosielateľa:',
                'wp_email_from_name_placeholder' => 'napr. KACER STUDIO',
                'wp_email_from_name_default' => 'Predvolená je',
                'wp_email_from_email' => 'E-mailová adresa:',
                'wp_email_from_email_placeholder' => 'napr. info@kacer.studio',
                'wp_email_from_email_default' => 'Predvolená je',
                'wp_email_domain_warning' => 'E-mail musí byť z rovnakej domény ako web (napr.',
                'wp_email_domain_warning_2' => '). Hosting vyžaduje, aby odosielateľ bol z domény webu.',
                
                'post_colors' => 'Farebné označenie príspevkov podľa stavu',
                'post_colors_desc' => 'Zapnúť farebné odlíšenie v administrácii',
                'post_colors_tip' => 'V zozname príspevkov v administrácii farebne odlíši jednotlivé stavy pre lepší prehľad.',
                
                'custom_colors' => 'Vlastné farby pre jednotlivé stavy',
                'color_draft' => 'Koncept',
                'color_pending' => 'Čaká na schválenie',
                'color_publish' => 'Publikované',
                'color_future' => 'Naplánované',
                'color_private' => 'Súkromné',
                'color_note' => 'Ponechajte prázdne pre zachovanie predvolenej farby WordPress',
                
                'edit_link' => 'Tlačidlo "Upraviť" na frontende',
                'edit_link_desc' => 'Zobraziť fixné tlačidlo v ľavom dolnom rohu',
                'edit_link_tip' => 'Pridá fixné tlačidlo "Upraviť" v ľavom dolnom rohu webu pre prihlásených používateľov s oprávnením upravovať príspevky. Umožňuje rýchly prístup k editácii aktuálnej stránky.',
                
                'archive_titles' => 'Nadpisy kategórií a štítkov',
                'archive_titles_desc' => 'Upraviť nadpisy',
                'archive_titles_tip' => 'Na stránkach kategórií sa namiesto "Kategória: Novinky" zobrazí len "Novinky".

Funguje na adresách ako /tema/XXX/ alebo /stitok/XXX/',
                'category_prefix' => 'Text pred kategóriou:',
                'category_prefix_placeholder' => 'Témy:',
                'tag_prefix' => 'Text pred štítkom:',
                'tag_prefix_placeholder' => 'Kľúčové slová:',
                
                'year_shortcode' => 'Automatický shortcode [rok]',
                'year_shortcode_desc' => 'Zobrazí aktuálny rok',
                'year_shortcode_tip' => 'Aktivuje shortcode [rok], ktorý funguje VŠADE: v obsahu príspevkov/stránok, widgetoch, menu, výňatkoch, nastavení šablón (footer copyright text všetkých populárnych tém) a ďalších miestach. PHP kód funguje len v .php súboroch.',
                'year_example' => 'Použitie: [rok] zobrazí aktuálny rok (' . gmdate('Y') . ')',
                
                'responsive_images' => 'Responzívne obrázky',
                'responsive_images_desc' => 'Pridať srcset a sizes atribúty',
                'responsive_images_tip' => 'Automaticky pridá srcset a sizes atribúty k obrázkam pre lepšiu responzivitu a rýchlejšie načítanie na mobilných zariadeniach.',
                'responsive_images_note' => 'WordPress automaticky generuje viaceré veľkosti obrázkov pri nahraní.',
                'disable_big_image_threshold' => 'Vypnúť automatické zmenšovanie veľkých obrázkov',
                'disable_big_image_threshold_desc' => 'Zakázať automatické zmenšovanie obrázkov nad 2560px',
                'disable_big_image_threshold_tip' => 'WordPress od verzie 5.3 automaticky zmenšuje veľké obrázky (nad 2560px) pri nahrávaní. Táto voľba to vypne - vhodné, ak potrebujete zachovať pôvodnú veľkosť obrázkov.',
                'disable_big_image_threshold_note' => 'Zachová pôvodné rozmery nahratých obrázkov.',
                
                'remove_comment_url' => 'Odstrániť pole URL z formulára komentárov',
                'remove_comment_url_desc' => 'Skryť pole "Webová stránka"',
                'remove_comment_url_tip' => 'Odstráni pole pre zadanie webovej stránky z formulára komentárov. Znižuje spam a zjednodušuje formulár.',
                
                'comment_url' => 'Odstrániť pole "Webová stránka" z komentárov',
                'comment_url_desc' => 'Skryť nepovinné pole URL',
                'comment_url_tip' => 'Odstráni nepovinné pole "Webová stránka" (URL) z formulára pre pridanie komentára. Znižuje spam a zjednodušuje formulár.',
                
                'disable_comments_completely' => 'Kompletne vypnúť komentáre',
                'disable_comments_completely_desc' => 'Uzavrieť komentáre + skryť z menu',
                'disable_comments_completely_tip' => 'Uzavrie komentáre pre všetky príspevky a stránky a skryje položku "Komentáre" z admin menu.',
                
                'disable_user_enumeration' => 'Ochrana používateľských účtov',
                'disable_user_enumeration_desc' => 'Zablokovať výpis používateľov',
                'disable_user_enumeration_tip' => 'Zablokuje REST API endpointy pre výpis používateľov (wp/v2/users) a odstráni author archive stránky, aby nebolo možné vylistovať používateľov cez ?author=1.',
                
                'auto_delete_files' => 'Automatické mazanie nepotrebných súborov',
                'auto_delete_files_desc' => 'Automaticky mazať nepotrebné súbory',
                'auto_delete_files_tip' => 'Po každej aktualizácii WordPressu automaticky odstráni súbory license.txt, readme.html a wp-config-sample.php z FTP servera.',
                
                'change_login_url' => 'Zmena URL pre prihlásenie',
                'change_login_url_desc' => 'Nastaviť vlastnú prihlasovaciu URL',
                'change_login_url_tip' => 'Zmení štandardnú wp-login.php na vlastnú URL adresu pre zvýšenie bezpečnosti. Útočníci nebudú môcť nájsť vašu prihlasovaciu stránku pomocou štandardných URL.',
                'custom_login_slug' => 'Prihlasovacia URL',
                'login_url_invalid_slug' => 'Slug môže obsahovať iba malé písmená, čísla a pomlčky.',
                'login_url_reserved_slug' => 'Tento slug je rezervovaný a nie je možné ho použiť.',
                'login_url_current' => 'Vaša aktuálna prihlasovacia URL:',
                
                'login_page' => 'Prihlasovacia stránka',
                'login_customize' => 'Vlastná prihlasovacia stránka',
                'login_customize_desc' => 'Prispôsobiť vzhľad prihlasovacej stránky',
                'login_customize_tip' => 'Upravte logo, pozadie, farby a odkazy prihlasovacej stránky pre profesionálny vzhľad.',
                'login_logo' => 'Logo',
                'login_logo_upload' => 'Nahrať logo',
                'login_logo_remove' => 'Odstrániť logo',
                'login_logo_height' => 'Výška loga',
                'login_logo_url' => 'Odkaz loga',
                'login_logo_url_placeholder' => 'https://vasa-stranka.sk',
                'login_logo_url_desc' => 'URL, kam povedie klik na logo (predvolená: homepage)',
                'login_bg_color' => 'Farba pozadia',
                'login_bg_image' => 'Obrázok pozadia',
                'login_bg_image_upload' => 'Nahrať obrázok',
                'login_bg_image_remove' => 'Odstrániť obrázok',
                'login_bg_size' => 'Pokrytie pozadia',
                'login_bg_size_cover' => 'Pokryť celú plochu (cover)',
                'login_bg_size_contain' => 'Zobraziť celý obrázok (contain)',
                'login_bg_size_repeat' => 'Opakovať (repeat)',
                'login_primary_color' => 'Primárna farba',
                'login_primary_color_desc' => 'Farba pre odkazy a fokus',
                'login_form_radius' => 'Zaoblenie rohov formulára',
                'login_form_bg_color' => 'Farba pozadia formulára',
                'login_form_text_color' => 'Farba písma formulára',
                'login_button_bg' => 'Farba tlačidla',
                'login_button_text_color' => 'Farba textu tlačidla',
                'login_button_radius' => 'Zaoblenie rohov tlačidla',
                'login_links_color' => 'Farba odkazov',
                'login_hide_lostpassword' => 'Skryť odkaz "Zabudnuté heslo?"',
                'login_hide_backtoblog' => 'Skryť odkaz "Späť na..."',
                'login_hide_rememberme' => 'Skryť pole "Zapamätať si ma"',
                'login_hide_privacy' => 'Skryť odkaz Ochrana osobných údajov',
                'login_custom_css' => 'Vlastné CSS',
                'login_custom_css_desc' => 'Pokročilé CSS úpravy',
                
                'restrict_wpforms' => 'Obmedziť WPForms na krajiny',
                'restrict_wpforms_desc' => 'Zobraziť len predvoľby CZ a SK',
                'restrict_wpforms_tip' => 'V dropdown menu krajín vo WPForms zobrazí len Česko a Slovensko. Zjednodušuje výber pre lokálne webstránky.',
                
                'wpforms_countries' => 'Obmedziť predvoľby v telefónnych poliach',
                'wpforms_countries_tip' => 'Obmedzí výber predvoľby na vybranú skupinu krajín. Funguje automaticky s WPForms, SureForms, Fluent Forms a ďalšími pluginmi, ktoré používajú knižnicu intl-tel-input.',
                'no_form_plugin' => 'Nebol nájdený žiadny aktívny plugin pre formuláre',
                'phone_restrict_off' => 'Vypnuté',
                'phone_restrict_czsk' => 'CZ + SK',
                'phone_restrict_europe' => 'Európa',
                'phone_restrict_us' => 'USA + Kanada',
                
                'enable_trans' => 'Zapnúť vlastné preklady',
                'enable_trans_desc' => 'Aktivovať nahrádzanie textov',
                'enable_trans_tip' => 'Umožňuje nahradiť ľubovoľné texty v administrácii a na frontende. Užitočné pre prispôsobenie termínov alebo opravu nesprávnych prekladov.',
                
                'trans_defs' => 'Definície prekladov',
                'trans_note' => 'ℹ️ Každý riadok nahradí všetky výskyty textu v ľavom poli textom v pravom poli. V poli prekladu môžete používať HTML tagy.',
                'trans_html_allowed' => 'Môžete použiť HTML (napr. <a href="">odkaz</a>)',
                'from' => 'Nahradiť text',
                'to' => 'Novým textom',
                'add_trans' => 'Pridať preklad',
                'export_trans' => 'Export prekladov',
                'import_trans' => 'Import prekladov',
                'import_trans_confirm' => 'Import nahradí všetky existujúce preklady. Pokračovať?',
                'site_name' => 'Názov stránky',
                'site_url' => 'URL adresa',
                'protocol' => 'Protokol',
                'protocol_https' => 'HTTPS',
                'protocol_http' => 'HTTP',
                'wp_version' => 'Verzia WordPress',
                'php_version' => 'Verzia PHP',
                'php_modern' => 'Moderná',
                'php_outdated' => 'Zastaraná',
                'mysql_version' => 'Verzia MySQL',
                'server' => 'Webový server',
                'php_memory' => 'PHP pamäťový limit',
                'wp_memory' => 'WP pamäťový limit',
                'max_upload' => 'Max. veľkosť uploadu',

                'maintenance_mode' => 'Web v údržbe',
                'maintenance_enable' => 'Aktivovať režim údržby',
                'maintenance_enable_desc' => 'Zobraziť vlastnú stránku údržby návštevníkom',
                'maintenance_enable_tip' => 'Keď je aktívny, návštevníci webu uvidia stránku údržby namiesto bežného obsahu. Prihlásení administrátori (s oprávnením manage_options) vidia normálny web.',
                'maintenance_mode_type' => 'Režim úprav',
                'maintenance_mode_simple' => 'Jednoduchý režim',
                'maintenance_mode_advanced' => 'Pokročilý režim (HTML)',
                'maintenance_heading' => 'Nadpis',
                'maintenance_heading_placeholder' => 'Web je momentálne v údržbe',
                'maintenance_message' => 'Text',
                'maintenance_message_placeholder' => 'Pracujeme na vylepšení našich stránok. Ospravedlňujeme sa za dočasné problémy a čoskoro sa vrátime online!',
                'maintenance_button_text' => 'Text tlačidla',
                'maintenance_button_text_placeholder' => 'Kontaktujte nás',
                'maintenance_button_url' => 'Odkaz tlačidla',
                'maintenance_button_url_placeholder' => 'https://example.com/kontakt',
                'maintenance_button_show' => 'Zobraziť tlačidlo',
                'maintenance_image' => 'Logo / Obrázok',
                'maintenance_image_desc' => 'Nahrajte obrázok, ktorý sa zobrazí nad nadpisom (voliteľné)',
                'maintenance_image_upload' => 'Nahrať obrázok',
                'maintenance_image_remove' => 'Odstrániť obrázok',
                'maintenance_image_max_width' => 'Maximálna šírka obrázku',
                'maintenance_bg_color' => 'Farba pozadia',
                'maintenance_text_color' => 'Farba textu',
                'maintenance_button_bg_color' => 'Farba tlačidla',
                'maintenance_button_text_color' => 'Farba textu tlačidla',
                'maintenance_button_radius' => 'Zaoblenie rohov tlačidla',
                'maintenance_html_code' => 'HTML kód stránky údržby',
                'maintenance_html_tip' => 'Zadajte kompletný HTML dokument vrátane &lt;!DOCTYPE&gt;, &lt;html&gt;, &lt;head&gt; a &lt;body&gt; tagov.',
                'maintenance_show_logged' => 'Zobraziť aj pre prihlásených používateľov',
                'maintenance_show_logged_desc' => 'Režim údržby uvidia aj prihlásení používatelia (okrem administrátorov)',

                'custom_scripts' => 'Vlastné skripty a kódy',
                'google_maps_api_key' => 'API kľúč Google Maps',
                'google_maps_api_key_desc' => 'Pre použitie Google Maps je potrebné vygenerovať API klúč a vložiť ho sem. Ďalšie informácie nájdete v',
                'google_maps_api_key_link' => 'oficiálnej dokumentácii',
                'custom_functions' => 'Vlastný PHP kód (functions.php)',
                'custom_functions_desc' => 'Aktivovať vlastné PHP funkcie',
                'custom_functions_tip' => 'Pridá vlastný PHP kód ktorý sa spustí pri načítaní WordPress. Ekvivalent pridania kódu do functions.php vašej témy. POZOR: Zlý kód môže pokaziť web!',
                'custom_functions_info' => 'ℹ️ Zadávajte PHP kód <strong>BEZ</strong> otváracích/zatváracích &lt;?php ?&gt; tagov.',
                'custom_functions_placeholder' => '

add_filter(\'wp_footer\', function() {
    echo \'<p>Vlastný text</p>\';
});',
                'custom_css' => 'Vlastné CSS',
                'custom_css_desc' => 'Aktivovať vlastné CSS',
                'custom_css_tip' => 'Pridá vlastné CSS štýly do &lt;head&gt;. BEZ &lt;style&gt; tagov.',
                'custom_css_active_warning' => 'Vlastné CSS je aktívne a aplikuje sa na celý web. Deaktivujte, ak web zobrazuje neočakávané štýly.',
                'custom_css_theme' => 'Téma:',
                'custom_css_info' => 'ℹ️ Zadávajte CSS <strong>BEZ</strong> &lt;style&gt; tagov - tie sa pridajú automaticky.',
                'custom_css_placeholder' => 'body {
  background: #fff;
}',
                'script_head' => 'Vlastný JavaScript v &lt;head&gt;',
                'script_head_desc' => 'Aktivovať vlastný JavaScript',
                'script_head_tip' => 'Pridá JavaScript do &lt;head&gt;.',
                'script_body_start' => 'Vložiť kód na začiatok &lt;body&gt;',
                'script_body_start_desc' => 'Aktivovať tracking kódy',
                'script_body_start_tip' => 'Pridá kód hneď za otváraciu &lt;body&gt; značku. Ideálne pre: Google Tag Manager, Facebook Pixel.',
                'script_body_end' => 'Vložiť kód pred &lt;/body&gt;',
                'script_body_end_desc' => 'Kód sa vloží pred uzatváraciu značku &lt;/body&gt;',
                'script_body_end_tip' => 'Pridá kód pred uzatváraciu &lt;/body&gt; značku. Odporúčané pre: optimalizáciu rýchlosti načítania.',
                'script_placeholder_head' => '<script>
  console.log("Hello");
</script>',
                'script_placeholder_body' => '<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){...})();
</script>',

                'robots_editor' => 'Editor robots.txt',
                'robots_enable' => 'Povoliť vlastný robots.txt',
                'robots_enable_desc' => 'Prepísať predvolený WordPress robots.txt',
                'robots_enable_tip' => 'Aktivuje vlastný robots.txt súbor.',
                'robots_content' => 'Obsah robots.txt',
                'robots_template' => 'Šablóna',
                'robots_template_default' => 'Predvolená (WordPress)',
                'robots_template_allow' => 'Povoliť všetko',
                'robots_template_disallow' => 'Zakázať všetko',
                'robots_template_custom' => 'Vlastná',
                'robots_apply' => 'Použiť šablónu',
                'robots_tip' => 'Upravte pravidlá pre vyhľadávače.',
                'robots_info' => 'ℹ️ Po uložení sa obsah <strong>zapíše do fyzického súboru</strong> <code>robots.txt</code> v root priečinku webu. Ak súbor existuje, bude prepísaný.',

                'htaccess_editor' => 'Editor .htaccess',
                'htaccess_enable' => 'Povoliť vlastné .htaccess pravidlá',
                'htaccess_enable_desc' => 'Pridať vlastné pravidlá do .htaccess',
                'htaccess_enable_tip' => 'Pridá vlastné Apache pravidlá do .htaccess súboru. POZOR: Nesprávna konfigurácia môže spôsobiť nefunkčnosť webu!',
                'htaccess_content' => 'Vlastné .htaccess pravidlá',
                'htaccess_warning' => 'VAROVANIE: Nesprávne pravidlá môžu spôsobiť nefunkčnosť webu! Plugin automaticky vytvorí zálohu ako <code>.htaccess.wp-admin-studio-backup</code>.',
                'htaccess_info' => 'ℹ️ Pravidlá sa <strong>pridajú NA ZAČIATOK</strong> .htaccess súboru (neprepíšu celý súbor). Obklopí sa komentármi <code># BEGIN WP WP Admin Studio</code> a <code># END WP WP Admin Studio</code>.',
                'htaccess_template' => 'Šablóna',
                'htaccess_template_security' => 'Bezpečnostné pravidlá',
                'htaccess_template_cache' => 'Cache hlavičky',
                'htaccess_template_redirect' => '301 Presmerovanie',
                'htaccess_template_custom' => 'Vlastná',
                'htaccess_apply' => 'Použiť šablónu',
                'htaccess_tip' => 'Pridajte vlastné Apache direktívy.',
                'htaccess_backup_success' => 'Záloha uložená ako .htaccess.wp-admin-studio-backup',
                'htaccess_backup_restore' => 'V prípade problémov obnovte súbor .htaccess.wp-admin-studio-backup',
                'htaccess_restore_button' => 'Obnoviť zo zálohy',
                'htaccess_restore_confirm' => 'Naozaj chcete obnoviť .htaccess zo zálohy? Súčasný .htaccess bude prepísaný a vaše WP Admin Studio pravidlá budú odstránené.',
                'htaccess_restore_success' => '.htaccess bol úspešne obnovený zo zálohy!',
                'htaccess_restore_error' => 'Chyba pri obnove: záložný súbor neexistuje alebo nie je čitateľný.',
                'htaccess_no_backup' => 'Záložný súbor nebol nájdený.',

                'feedback_bug' => 'Nahlásiť chybu',

                'bug_report_title' => 'Nahlásiť chybu',
                'bug_report_email' => 'Váš email',
                'bug_report_message' => 'Popis chyby',
                'bug_report_message_placeholder' => 'Popíšte chybu, s ktorou ste sa stretli...',
                'bug_report_screenshot' => 'Screenshot (voliteľné)',
                'bug_report_screenshot_desc' => 'PNG, JPG alebo GIF, max 5 MB',
                'bug_report_system_info' => 'Systémové informácie',
                'bug_report_url' => 'URL webu',
                'bug_report_consent' => 'Odoslaním tohto hlásenia súhlasíte s tým, že vyššie uvedené systémové informácie budú odovzdané autorovi pluginu (KACER STUDIO s.r.o.) výhradne na účely diagnostiky a vyriešenia nahláseného problému.',
                'bug_report_send' => 'Odoslať',
                'bug_report_success' => 'Ďakujeme! Váš report bol úspešne odoslaný.',
                'bug_report_error_empty' => 'Prosím vyplňte popis chyby.',
                'bug_report_error_send' => 'Chyba pri odosielaní. Skúste to prosím znovu.',
                'bug_report_error_security' => 'Bezpečnostná kontrola zlyhala.',
                'cancel' => 'Zrušiť',
                'sending' => 'Odosielanie',
                'search_placeholder' => 'Hľadať...',
            ),
            'pl' => array(
                'page_title' => 'WP Admin Studio',
                'version' => 'Wersja ' . self::VERSION,
                'author' => 'Stworzył <a href="https://kacer.studio" target="_blank">KACER STUDIO s.r.o.</a>',
                'bulk_actions' => 'Operacje zbiorcze',
                'enable_all' => 'Włącz wszystko',
                'disable_all' => 'Wyłącz wszystko',
                'import_export' => 'Kopia zapasowa ustawień',
                'export' => 'Eksportuj',
                'import' => 'Importuj',
                'export_desc' => 'Pobierz plik JSON z kompletną konfiguracją wtyczki.',
                'import_desc' => 'Prześlij plik JSON aby przywrócić ustawienia z wcześniejszej kopii zapasowej.',
                'save' => 'Zapisz zmiany',
                'saving' => 'Zapisuję',
                'settings_saved' => 'Zapisano!',
                'all_enabled' => 'Wszystkie ustawienia zostały włączone!',
                'all_disabled' => 'Wszystkie ustawienia zostały wyłączone!',
                'import_success' => 'Ustawienia zostały pomyślnie zaimportowane!',
                'import_error' => 'Błąd: Plik nie został przesłany.',
                'import_invalid' => 'Błąd: Nieprawidłowy plik ustawień.',
                'lang_switch' => 'Przełącz język',
                
                'admin_section' => 'Administracja',
                'editor_section' => 'Strony i wpisy',
                'frontend' => 'Frontend i wydajność',
                'comments' => 'Komentarze',
                'forms' => 'Formularze',
                'translations' => 'Własne tłumaczenia',
                'nav_admin' => 'Admin',
                'nav_scripts' => 'Skrypty',
                'nav_maintenance' => 'Konserwacja',
                'nav_login' => 'Logowanie',
                'nav_editor' => 'Editor',
                'nav_frontend' => 'Frontend',
                'nav_comments' => 'Komentarze',
                'nav_forms' => 'Formularze',
                'nav_translations' => 'Tłumaczenia',
                'nav_system' => 'System',
                'nav_backup' => 'Backup',
                'admin_bar_tip' => 'Zaznacz elementy, które chcesz usunąć z górnego paska administratora WordPress.',
                'admin_bar_logo' => 'Logo WordPress (łącznie z podmenu z linkami do dokumentacji)',
                'admin_bar_updates' => 'Aktualizacje',
                'admin_bar_comments' => 'Komentarze',
                'admin_bar_new' => 'Akcja "Dodaj nowy" (wpis, stronę, media...)',
                'admin_bar_view' => 'Link "Zobacz witrynę"',
                
                'login_lang' => 'Ukryj przełącznik języków',
                'login_lang_desc' => 'Ukryj przełącznik języków',
                'login_lang_tip' => 'Usuwa menu wyboru języka ze strony logowania WordPress (wp-login.php).',
                
                'hide_updates' => 'Ukryj aktualizacje dla użytkowników nie-admin',
                'hide_updates_desc' => 'Tylko administratorzy zobaczą powiadomienia o aktualizacjach',
                'hide_updates_tip' => 'Ukrywa powiadomienia o dostępnych aktualizacjach wtyczek, motywów i WordPressa dla wszystkich użytkowników oprócz administratorów. Zmniejsza zamieszanie dla redaktorów i współpracowników.',
                
                'disable_auto_update_emails' => 'Wyłącz e-maile o automatycznych aktualizacjach',
                'disable_auto_update_emails_desc' => 'Wyłącz e-maile o automatycznych aktualizacjach',
                'disable_auto_update_emails_tip' => 'WordPress wysyła e-mail po każdej automatycznej aktualizacji rdzenia, wtyczek lub motywów. Ta opcja to wyłącza.',
                
                'hide_admin_notices' => 'Powiadomienia administratora',
                'hide_admin_notices_desc' => 'Ukryj powiadomienia dla wszystkich użytkowników',
                'hide_admin_notices_tip' => 'Ukrywa paski informacyjne wtyczek i WordPressa dla wszystkich zalogowanych użytkowników. Opcjonalnie powiadomienia mogą pozostać widoczne dla konkretnego administratora.',
                'show_notices_current_user_desc' => 'Pokaż powiadomienia tylko dla: ',
                
                'hide_howdy' => 'Ukryj "Witaj" z paska administratora',
                'hide_howdy_desc' => 'Wyświetl tylko nazwę użytkownika',
                'hide_howdy_tip' => 'Usuwa tekst "Witaj" z prawego górnego rogu paska administratora i pozostawia tylko nazwę zalogowanego użytkownika.',
                
                'hide_wp_version' => 'Ukryj wersję WordPress ze stopki administratora',
                'hide_wp_version_desc' => 'Usuń "Dziękujemy za korzystanie z WordPress. Wersja X.X.X"',
                'hide_wp_version_tip' => 'Ukrywa informację o wersji WordPress ze stopki administracji.',
                
                'hide_dashboard_widgets' => 'Ukryj widżety na pulpicie',
                'hide_dashboard_widgets_tip' => 'Zaznacz widżety, które mają być ukryte na pulpicie. Lista widżetów jest aktualizowana automatycznie przy każdej wizycie na pulpicie.',
                
                'admin_page_titles' => 'Niestandardowe tytuły stron w administracji',
                'admin_page_titles_desc' => 'Edytuj tytuł karty przeglądarki',
                'admin_page_titles_tip' => 'Umożliwia ustawienie niestandardowego formatu tytułu strony, który będzie wyświetlany w karcie przeglądarki podczas pracy w administracji WordPress. Możesz użyć tagów %page% (nazwa bieżącej strony) i %site_title% (nazwa witryny).',
                'admin_page_title_page_tag' => 'nazwa bieżącej strony',
                'admin_page_title_site_tag' => 'nazwa witryny',
                
                'disable_gutenberg' => 'Wyłącz edytor Gutenberg',
                'disable_gutenberg_desc' => 'Przywróć klasyczny edytor',
                'disable_gutenberg_tip' => 'Przełącza WordPress z powrotem na klasyczny edytor dla wszystkich wpisów, stron i widgetów.',
                
                'duplicate_posts' => 'Duplikacja stron i wpisów',
                'duplicate_posts_desc' => 'Włącz szybkie kopiowanie jednym kliknięciem',
                'duplicate_posts_tip' => 'Dodaje przycisk "Utwórz kopię" do akcji przy stronach, wpisach i custom post types. Kopia zawiera całą treść, metadane, taksonomie i custom fields. Duplikat zostanie zapisany jako szkic i otwarty do edycji.',
                'duplicate_action' => 'Utwórz kopię',
                'duplicate_success' => 'Kopia została utworzona i otwarta do edycji.',
                
                'enable_svg_upload' => 'Obsługa plików SVG',
                'enable_svg_upload_desc' => 'Włącz przesyłanie plików SVG',
                'enable_svg_upload_tip' => 'Umożliwia przesyłanie plików SVG do biblioteki mediów WordPress.',

                'enable_media_replace' => 'Zastępowanie plików w mediach',
                'enable_media_replace_desc' => 'Dodaj opcję zastąpienia pliku bezpośrednio w bibliotece mediów',
                'mr_row_action' => 'Zastąp plik',
                'mr_page_title' => 'Zastąp plik',
                'mr_back' => 'Wróć do biblioteki mediów',
                'mr_edit' => 'Edytuj medium',
                'mr_cancel' => 'Anuluj',
                'mr_submit' => 'Prześlij i zastąp',
                'mr_uploading' => 'Przesyłanie...',
                'mr_badge_new' => 'nowy',
                'mr_label_old' => 'Bieżący plik',
                'mr_label_new' => 'Nowy plik',
                'mr_uploaded' => 'Przesłano',
                'mr_drop_title' => 'Wybierz lub przeciągnij plik',
                'mr_drop_sub' => 'Kliknij gdziekolwiek tutaj',
                'mr_mode_keep_title' => 'Adres pozostanie taki sam',
                'mr_mode_keep_desc' => 'Plik zostanie wymieniony, ale jego adres na stronie pozostanie niezmieniony. Wszystkie miejsca gdzie jest używany będą nadal działać.',
                'mr_mode_new_title' => 'Adres zmieni się i zostanie zaktualizowany wszędzie',
                'mr_mode_new_desc' => 'Plik otrzyma nową nazwę, a jego nowy adres zostanie automatycznie uzupełniony wszędzie, gdzie jest używany na stronie.',
                'mr_mode_section_title' => 'Co stanie się z adresem pliku',
                'mr_success_keep' => 'Plik został pomyślnie zastąpiony',
                'mr_success_new' => 'Plik zastąpiony i adresy zaktualizowane',
                'mr_desc_keep' => 'Nowy plik jest aktywny. Adres pozostał taki sam — nie były potrzebne żadne inne zmiany.',
                'mr_desc_new' => 'Nowy plik jest aktywny. Jego adres został automatycznie zaktualizowany wszędzie na stronie.',
                'mr_error_no_file' => 'Żaden plik nie został przesłany lub wystąpił błąd przesyłania.',
                'mr_error_write' => 'Nie udało się nadpisać pliku. Sprawdź uprawnienia folderu uploads.',
                'mr_error_perm' => 'Nie masz uprawnień do wykonania tej czynności.',
                'mr_error_id' => 'Nieprawidłowe ID załącznika.',
                'mr_error_not_found' => 'Załącznik nie został znaleziony.',
                'mr_js_no_file' => 'Proszę wybrać plik do przesłania.',
                
                'wp_emails' => 'E-maile WordPress',
                'wp_emails_desc' => 'Zmień nadawcę e-maili',
                'wp_emails_tip' => 'Zmień nazwę nadawcy i adres e-mail dla wszystkich wiadomości wysyłanych przez WordPress (rejestracja, resetowanie hasła, powiadomienia itp.). Dotyczy również wtyczek, które nie określają własnego nadawcy.',
                'wp_email_from_name' => 'Nazwa nadawcy:',
                'wp_email_from_name_placeholder' => 'np. KACER STUDIO',
                'wp_email_from_name_default' => 'Domyślnie jest',
                'wp_email_from_email' => 'Adres e-mail:',
                'wp_email_from_email_placeholder' => 'np. info@kacer.studio',
                'wp_email_from_email_default' => 'Domyślnie jest',
                'wp_email_domain_warning' => 'Adres e-mail musi pochodzić z tej samej domeny co strona (np.',
                'wp_email_domain_warning_2' => '). Hosting wymaga, aby nadawca pochodził z domeny strony.',
                
                'post_colors' => 'Kolorowe oznaczenia wpisów według statusu',
                'post_colors_desc' => 'Włącz kolorowe rozróżnienie w administracji',
                'post_colors_tip' => 'Na liście wpisów w administracji kolorowo rozróżnia poszczególne statusy dla lepszej przejrzystości.',
                
                'custom_colors' => 'Własne kolory dla poszczególnych statusów',
                'color_draft' => 'Szkic',
                'color_pending' => 'Oczekuje na przegląd',
                'color_publish' => 'Opublikowano',
                'color_future' => 'Zaplanowano',
                'color_private' => 'Prywatne',
                'color_note' => 'Pozostaw puste aby zachować domyślny kolor WordPress',
                
                'edit_link' => 'Przycisk "Edytuj" na frontendzie',
                'edit_link_desc' => 'Wyświetl stały przycisk w lewym dolnym rogu',
                'edit_link_tip' => 'Dodaje stały przycisk "Edytuj" w lewym dolnym rogu witryny dla zalogowanych użytkowników z uprawnieniami do edycji wpisów. Umożliwia szybki dostęp do edycji bieżącej strony.',
                
                'archive_titles' => 'Nagłówki kategorii i tagów',
                'archive_titles_desc' => 'Dostosuj nagłówki',
                'archive_titles_tip' => 'Na stronach kategorii zamiast "Kategoria: Aktualności" pokazuje tylko "Aktualności".

Działa na adresach jak /category/XXX/ lub /tag/XXX/',
                'category_prefix' => 'Tekst przed kategorią:',
                'category_prefix_placeholder' => 'Tematy:',
                'tag_prefix' => 'Tekst przed tagiem:',
                'tag_prefix_placeholder' => 'Słowa kluczowe:',
                
                'year_shortcode' => 'Automatyczny shortcode [rok]',
                'year_shortcode_desc' => 'Wyświetla bieżący rok',
                'year_shortcode_tip' => 'Aktywuje shortcode [rok], który działa WSZĘDZIE: w treści wpisów/stron, widgetach, menu, fragmentach, ustawieniach motywu (footer copyright text dla wszystkich popularnych motywów) i innych miejscach. Kod PHP działa tylko w plikach .php.',
                'year_example' => 'Użycie: [rok] wyświetla bieżący rok (' . gmdate('Y') . ')',
                
                'responsive_images' => 'Responsywne obrazy',
                'responsive_images_desc' => 'Dodaj atrybuty srcset i sizes',
                'responsive_images_tip' => 'Automatycznie dodaje atrybuty srcset i sizes do obrazów dla lepszej responsywności i szybszego ładowania na urządzeniach mobilnych.',
                'responsive_images_note' => 'WordPress automatycznie generuje wiele rozmiarów obrazów podczas przesyłania.',
                'disable_big_image_threshold' => 'Wyłącz automatyczne pomniejszanie dużych obrazów',
                'disable_big_image_threshold_desc' => 'Wyłącz automatyczne pomniejszanie obrazów powyżej 2560px',
                'disable_big_image_threshold_tip' => 'Od WordPress 5.3, duże obrazy (powyżej 2560px) są automatycznie pomniejszane podczas przesyłania. Ta opcja wyłącza to - przydatne, gdy trzeba zachować oryginalne wymiary obrazów.',
                'disable_big_image_threshold_note' => 'Zachowuje oryginalne wymiary przesłanych obrazów.',
                
                'remove_comment_url' => 'Usuń pole URL z formularza komentarzy',
                'remove_comment_url_desc' => 'Ukryj pole "Strona internetowa"',
                'remove_comment_url_tip' => 'Usuwa pole do wprowadzania strony internetowej z formularza komentarzy. Zmniejsza spam i upraszcza formularz.',
                
                'comment_url' => 'Usuń pole "Strona internetowa" z komentarzy',
                'comment_url_desc' => 'Ukryj opcjonalne pole URL',
                'comment_url_tip' => 'Usuwa opcjonalne pole "Strona internetowa" (URL) z formularza dodawania komentarza. Zmniejsza spam i upraszcza formularz.',
                
                'disable_comments_completely' => 'Kompletnie wyłącz komentarze',
                'disable_comments_completely_desc' => 'Zamknij komentarze + ukryj z menu',
                'disable_comments_completely_tip' => 'Zamyka komentarze dla wszystkich postów i stron oraz ukrywa "Komentarze" z menu admina.',
                
                'disable_user_enumeration' => 'Ochrona kont użytkowników',
                'disable_user_enumeration_desc' => 'Zablokuj wyliczanie użytkowników',
                'disable_user_enumeration_tip' => 'Blokuje endpointy REST API do listowania użytkowników (wp/v2/users) i usuwa strony archiwum autora, aby uniemożliwić wyliczanie użytkowników przez ?author=1.',
                
                'auto_delete_files' => 'Automatyczne usuwanie niepotrzebnych plików',
                'auto_delete_files_desc' => 'Automatycznie usuwaj niepotrzebne pliki',
                'auto_delete_files_tip' => 'Po każdej aktualizacji WordPressa automatycznie usuwa pliki license.txt, readme.html i wp-config-sample.php z serwera FTP.',
                
                'change_login_url' => 'Zmiana adresu URL logowania',
                'change_login_url_desc' => 'Ustaw niestandardowy URL logowania',
                'change_login_url_tip' => 'Zmienia standardowy wp-login.php na niestandardowy adres URL w celu zwiększenia bezpieczeństwa. Atakujący nie będą mogli znaleźć strony logowania przy użyciu standardowych adresów URL.',
                'custom_login_slug' => 'URL logowania',
                'login_url_invalid_slug' => 'Slug może zawierać tylko małe litery, cyfry i myślniki.',
                'login_url_reserved_slug' => 'Ten slug jest zarezerwowany i nie może być użyty.',
                'login_url_current' => 'Twój aktualny URL logowania:',
                
                'login_page' => 'Strona logowania',
                'login_customize' => 'Własna strona logowania',
                'login_customize_desc' => 'Dostosuj wygląd strony logowania',
                'login_customize_tip' => 'Dostosuj logo, tło, kolory i linki strony logowania dla profesjonalnego wyglądu.',
                'login_logo' => 'Logo',
                'login_logo_upload' => 'Prześlij logo',
                'login_logo_remove' => 'Usuń logo',
                'login_logo_height' => 'Wysokość loga',
                'login_logo_url' => 'Link loga',
                'login_logo_url_placeholder' => 'https://twoja-strona.pl',
                'login_logo_url_desc' => 'URL, do którego prowadzi kliknięcie loga (domyślnie: strona główna)',
                'login_bg_color' => 'Kolor tła',
                'login_bg_image' => 'Obraz tła',
                'login_bg_image_upload' => 'Prześlij obraz',
                'login_bg_image_remove' => 'Usuń obraz',
                'login_bg_size' => 'Pokrycie tła',
                'login_bg_size_cover' => 'Pokryj cały obszar (cover)',
                'login_bg_size_contain' => 'Pokaż cały obraz (contain)',
                'login_bg_size_repeat' => 'Powtórz (repeat)',
                'login_primary_color' => 'Kolor podstawowy',
                'login_primary_color_desc' => 'Kolor dla linków i fokusa',
                'login_form_radius' => 'Zaokrąglenie rogów formularza',
                'login_form_bg_color' => 'Kolor tła formularza',
                'login_form_text_color' => 'Kolor tekstu formularza',
                'login_button_bg' => 'Kolor przycisku',
                'login_button_text_color' => 'Kolor tekstu przycisku',
                'login_button_radius' => 'Zaokrąglenie rogów przycisku',
                'login_links_color' => 'Kolor linków',
                'login_hide_lostpassword' => 'Ukryj link "Zapomniałeś hasła?"',
                'login_hide_backtoblog' => 'Ukryj link "Wróć do..."',
                'login_hide_rememberme' => 'Ukryj pole "Zapamiętaj mnie"',
                'login_hide_privacy' => 'Ukryj link Polityki prywatności',
                'login_custom_css' => 'Własny CSS',
                'login_custom_css_desc' => 'Zaawansowane dostosowania CSS',
                
                'restrict_wpforms' => 'Ogranicz WPForms do krajów',
                'restrict_wpforms_desc' => 'Pokaż tylko opcje CZ i SK',
                'restrict_wpforms_tip' => 'W menu wyboru kraju w WPForms wyświetla tylko Czechy i Słowację. Upraszcza wybór dla lokalnych stron.',
                
                'wpforms_countries' => 'Ogranicz prefiksy w polach telefonu',
                'wpforms_countries_tip' => 'Ogranicza wybór prefiksu do wybranej grupy krajów. Działa automatycznie z WPForms, SureForms, Fluent Forms i innymi wtyczkami używającymi biblioteki intl-tel-input.',
                'no_form_plugin' => 'Nie znaleziono żadnej aktywnej wtyczki formularzy',
                'phone_restrict_off' => 'Wyłączone',
                'phone_restrict_czsk' => 'CZ + SK',
                'phone_restrict_europe' => 'Europa',
                'phone_restrict_us' => 'USA + Kanada',
                
                'enable_trans' => 'Włącz własne tłumaczenia',
                'enable_trans_desc' => 'Aktywuj zastępowanie tekstów',
                'enable_trans_tip' => 'Umożliwia zastąpienie dowolnych tekstów w administracji i na frontendzie. Przydatne do dostosowywania terminów lub naprawy niepoprawnych tłumaczeń.',
                
                'trans_defs' => 'Definicje tłumaczeń',
                'trans_note' => 'ℹ️ Każdy wiersz zastąpi wszystkie wystąpienia tekstu w lewym polu tekstem w prawym polu. Możesz używać tagów HTML w polu tłumaczenia.',
                'trans_html_allowed' => 'Możesz użyć HTML (np. <a href="">link</a>)',
                'from' => 'Zastąp tekst',
                'to' => 'Nowym tekstem',
                'add_trans' => 'Dodaj tłumaczenie',
                'export_trans' => 'Eksport tłumaczeń',
                'import_trans' => 'Import tłumaczeń',
                'import_trans_confirm' => 'Import zastąpi wszystkie istniejące tłumaczenia. Kontynuować?',
                'site_name' => 'Nazwa witryny',
                'site_url' => 'Adres URL',
                'protocol' => 'Protokół',
                'protocol_https' => 'HTTPS',
                'protocol_http' => 'HTTP',
                'wp_version' => 'Wersja WordPress',
                'php_version' => 'Wersja PHP',
                'php_modern' => 'Nowoczesna',
                'php_outdated' => 'Przestarzała',
                'mysql_version' => 'Wersja MySQL',
                'server' => 'Serwer WWW',
                'php_memory' => 'Limit pamięci PHP',
                'wp_memory' => 'Limit pamięci WP',
                'max_upload' => 'Maks. rozmiar przesyłania',

                'maintenance_mode' => 'Tryb konserwacji',
                'maintenance_enable' => 'Aktywuj tryb konserwacji',
                'maintenance_enable_desc' => 'Wyświetl własną stronę konserwacji dla odwiedzających',
                'maintenance_enable_tip' => 'Gdy aktywny, odwiedzający witrynę zobaczą stronę konserwacji zamiast zwykłej treści. Zalogowani administratorzy (z uprawnieniem manage_options) widzą normalną stronę.',
                'maintenance_mode_type' => 'Tryb edycji',
                'maintenance_mode_simple' => 'Tryb prosty',
                'maintenance_mode_advanced' => 'Tryb zaawansowany (HTML)',
                'maintenance_heading' => 'Nagłówek',
                'maintenance_heading_placeholder' => 'Strona jest obecnie w konserwacji',
                'maintenance_message' => 'Tekst',
                'maintenance_message_placeholder' => 'Pracujemy nad ulepszeniem naszej strony. Przepraszamy za tymczasowe niedogodności i wkrótce wrócimy online!',
                'maintenance_button_text' => 'Tekst przycisku',
                'maintenance_button_text_placeholder' => 'Skontaktuj się z nami',
                'maintenance_button_url' => 'Link przycisku',
                'maintenance_button_url_placeholder' => 'https://example.com/kontakt',
                'maintenance_button_show' => 'Pokaż przycisk',
                'maintenance_image' => 'Logo / Obraz',
                'maintenance_image_desc' => 'Prześlij obraz, który będzie wyświetlany nad nagłówkiem (opcjonalnie)',
                'maintenance_image_upload' => 'Prześlij obraz',
                'maintenance_image_remove' => 'Usuń obraz',
                'maintenance_image_max_width' => 'Maksymalna szerokość obrazu',
                'maintenance_bg_color' => 'Kolor tła',
                'maintenance_text_color' => 'Kolor tekstu',
                'maintenance_button_bg_color' => 'Kolor przycisku',
                'maintenance_button_text_color' => 'Kolor tekstu przycisku',
                'maintenance_button_radius' => 'Zaokrąglenie rogów przycisku',
                'maintenance_html_code' => 'Kod HTML strony konserwacji',
                'maintenance_html_tip' => 'Wprowadź kompletny dokument HTML zawierający &lt;!DOCTYPE&gt;, &lt;html&gt;, &lt;head&gt; i &lt;body&gt; tagi.',
                'maintenance_show_logged' => 'Pokaż również zalogowanym użytkownikom',
                'maintenance_show_logged_desc' => 'Zalogowani użytkownicy (z wyjątkiem administratorów) również zobaczą tryb konserwacji',

                'custom_scripts' => 'Własne skrypty i kody',
                'google_maps_api_key' => 'Klucz API Google Maps',
                'google_maps_api_key_desc' => 'Aby korzystać z Google Maps, musisz wygenerować klucz API i wprowadzić go tutaj. Więcej informacji można znaleźć w',
                'google_maps_api_key_link' => 'oficjalnej dokumentacji',
                'custom_functions' => 'Własny kod PHP (functions.php)',
                'custom_functions_desc' => 'Aktywować własne funkcje PHP',
                'custom_functions_tip' => 'Dodaje własny kod PHP, który zostanie wykonany podczas ładowania WordPress. Odpowiednik dodania kodu do functions.php twojego motywu. UWAGA: Błędny kod może zepsuć stronę!',
                'custom_functions_info' => 'ℹ️ Wprowadź kod PHP <strong>BEZ</strong> otwierających/zamykających tagów &lt;?php ?&gt;.',
                'custom_functions_placeholder' => '

add_filter(\'wp_footer\', function() {
    echo \'<p>Własny tekst</p>\';
});',
                'custom_css' => 'Własne CSS',
                'custom_css_desc' => 'Aktywować własne CSS',
                'custom_css_tip' => 'Dodaje własne style CSS do &lt;head&gt;. BEZ tagów &lt;style&gt;.',
                'custom_css_active_warning' => 'Własny CSS jest aktywny i stosowany na całej stronie. Dezaktywuj, jeśli strona wyświetla nieoczekiwane style.',
                'custom_css_theme' => 'Motyw:',
                'custom_css_info' => 'ℹ️ Wprowadź CSS <strong>BEZ</strong> tagów &lt;style&gt; - zostaną dodane automatycznie.',
                'custom_css_placeholder' => 'body {
  background: #fff;
}',
                'script_head' => 'Własny JavaScript w &lt;head&gt;',
                'script_head_desc' => 'Aktywować własny JavaScript',
                'script_head_tip' => 'Dodaje JavaScript do &lt;head&gt;.',
                'script_body_start' => 'Wstaw kod na początku &lt;body&gt;',
                'script_body_start_desc' => 'Aktywować kody śledzenia',
                'script_body_start_tip' => 'Dodaje kod zaraz za otwierającym tagiem &lt;body&gt;. Idealne dla: Google Tag Manager, Facebook Pixel.',
                'script_body_end' => 'Wstaw kod przed &lt;/body&gt;',
                'script_body_end_desc' => 'Kod zostanie wstawiony przed zamykającym tagiem &lt;/body&gt;',
                'script_body_end_tip' => 'Dodaje kod przed zamykającym tagiem &lt;/body&gt;. Zalecane dla: optymalizacji szybkości ładowania.',
                'script_placeholder_head' => '<script>
  console.log("Hello");
</script>',
                'script_placeholder_body' => '<!-- Google Tag Manager -->
<script>
  (function(w,d,s,l,i){...})();
</script>',

                'robots_editor' => 'Edytor robots.txt',
                'robots_enable' => 'Włącz własny robots.txt',
                'robots_enable_desc' => 'Nadpisz domyślny WordPress robots.txt',
                'robots_enable_tip' => 'Aktywuje własny plik robots.txt.',
                'robots_content' => 'Zawartość robots.txt',
                'robots_template' => 'Szablon',
                'robots_template_default' => 'Domyślny (WordPress)',
                'robots_template_allow' => 'Pozwól wszystko',
                'robots_template_disallow' => 'Zabroń wszystko',
                'robots_template_custom' => 'Własny',
                'robots_apply' => 'Zastosuj szablon',
                'robots_tip' => 'Edytuj reguły dla wyszukiwarek.',
                'robots_info' => 'ℹ️ Po zapisaniu zawartość <strong>zostanie zapisana do fizycznego pliku</strong> <code>robots.txt</code> w katalogu głównym witryny. Jeśli plik istnieje, zostanie nadpisany.',

                'htaccess_editor' => 'Edytor .htaccess',
                'htaccess_enable' => 'Włącz własne reguły .htaccess',
                'htaccess_enable_desc' => 'Dodaj własne reguły do .htaccess',
                'htaccess_enable_tip' => 'Dodaje własne reguły Apache do pliku .htaccess. UWAGA: Nieprawidłowa konfiguracja może zepsuć stronę!',
                'htaccess_content' => 'Własne reguły .htaccess',
                'htaccess_warning' => 'OSTRZEŻENIE: Nieprawidłowe reguły mogą zepsuć stronę! Wtyczka automatycznie tworzy kopię zapasową jako <code>.htaccess.wp-admin-studio-backup</code>.',
                'htaccess_info' => 'ℹ️ Reguły zostaną <strong>dodane NA POCZĄTKU</strong> pliku .htaccess (nie nadpisując całego pliku). Zostaną otoczone komentarzami <code># BEGIN WP WP Admin Studio</code> i <code># END WP WP Admin Studio</code>.',
                'htaccess_template' => 'Szablon',
                'htaccess_template_security' => 'Reguły bezpieczeństwa',
                'htaccess_template_cache' => 'Nagłówki cache',
                'htaccess_template_redirect' => 'Przekierowanie 301',
                'htaccess_template_custom' => 'Własny',
                'htaccess_apply' => 'Zastosuj szablon',
                'htaccess_tip' => 'Dodaj własne dyrektywy Apache.',
                'htaccess_backup_success' => 'Kopia zapasowa zapisana jako .htaccess.wp-admin-studio-backup',
                'htaccess_backup_restore' => 'W przypadku problemów przywróć plik .htaccess.wp-admin-studio-backup',
                'htaccess_restore_button' => 'Przywróć z kopii zapasowej',
                'htaccess_restore_confirm' => 'Czy na pewno chcesz przywrócić .htaccess z kopii zapasowej? Obecny .htaccess zostanie nadpisany, a Twoje reguły WP Admin Studio zostaną usunięte.',
                'htaccess_restore_success' => '.htaccess został pomyślnie przywrócony z kopii zapasowej!',
                'htaccess_restore_error' => 'Błąd przywracania: plik kopii zapasowej nie istnieje lub nie jest czytelny.',
                'htaccess_no_backup' => 'Nie znaleziono pliku kopii zapasowej.',

                'feedback_bug' => 'Zgłoś błąd',

                'bug_report_title' => 'Zgłoś błąd',
                'bug_report_email' => 'Twój email',
                'bug_report_message' => 'Opis błędu',
                'bug_report_message_placeholder' => 'Opisz napotkany błąd...',
                'bug_report_screenshot' => 'Screenshot (opcjonalnie)',
                'bug_report_screenshot_desc' => 'PNG, JPG lub GIF, max 5 MB',
                'bug_report_system_info' => 'Informacje systemowe',
                'bug_report_url' => 'URL strony',
                'bug_report_consent' => 'Wysyłając ten raport, zgadzasz się, że powyższe informacje systemowe zostaną przesłane autorowi wtyczki (KACER STUDIO s.r.o.) wyłącznie w celu diagnozy i rozwiązania zgłoszonego problemu.',
                'bug_report_send' => 'Wyślij',
                'bug_report_success' => 'Dziękujemy! Twój raport został pomyślnie wysłany.',
                'bug_report_error_empty' => 'Proszę wypełnić opis błędu.',
                'bug_report_error_send' => 'Błąd wysyłania. Spróbuj ponownie.',
                'bug_report_error_security' => 'Sprawdzenie bezpieczeństwa nie powiodło się.',
                'cancel' => 'Anuluj',
                'sending' => 'Wysyłanie',
                'search_placeholder' => 'Szukaj...',
            ),
        );
        return isset($translations[$this->get_lang()][$key]) ? $translations[$this->get_lang()][$key] : $key;
    }
    
    public function ajax_change_language() {
        check_ajax_referer('wpc_nonce', 'nonce');
        $lang = isset($_POST['lang']) ? sanitize_text_field(wp_unslash($_POST['lang'])) : 'cs';
        $allowed = array('cs', 'de', 'sk', 'pl', 'en');
        if (in_array($lang, $allowed)) {
            update_option($this->lang_option, $lang);
        }
        wp_send_json_success();
    }
    
    public function ajax_submit_bug_report() {
        
        if (!check_ajax_referer('wpc_bug_report', 'nonce', false)) {
            wp_send_json_error(array('message' => $this->t('bug_report_error_security')));
        }

        $transient_key = 'wpc_bug_report_' . md5(isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
        if (get_transient($transient_key)) {
            wp_send_json_error(array('message' => 'Please wait 5 minutes before sending another report.'));
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';
        $system_info_json = isset($_POST['system_info']) ? sanitize_textarea_field(wp_unslash($_POST['system_info'])) : '{}';
        $system_info = json_decode($system_info_json, true);

        if (empty($email) || empty($message)) {
            wp_send_json_error(array('message' => $this->t('bug_report_error_empty')));
        }
        
        if (!is_email($email)) {
            wp_send_json_error(array('message' => 'Invalid email address.'));
        }

        $attachments = array();
        if (isset($_FILES['screenshot']) && !empty($_FILES['screenshot']['tmp_name'])) {
            $file = $_FILES['screenshot']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated via finfo MIME check below

            if ($file['error'] !== UPLOAD_ERR_OK) {
                wp_send_json_error(array('message' => 'File upload error. Please try again.'));
            }

            if ($file['size'] > self::MAX_UPLOAD_SIZE) {
                wp_send_json_error(array('message' => 'File too large. Maximum size is 5 MB.'));
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            $allowed_mimes = array('image/png', 'image/jpeg', 'image/jpg');
            if (!in_array($detected_type, $allowed_mimes)) {
                wp_send_json_error(array('message' => 'Invalid file type. Only PNG and JPG images are allowed.'));
            }

            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed_ext = array('png', 'jpg', 'jpeg');
            if (!in_array($file_ext, $allowed_ext)) {
                wp_send_json_error(array('message' => 'Invalid file extension.'));
            }

            $safe_filename = sanitize_file_name(basename($file['name']));

            $upload_dir = wp_upload_dir();
            $temp_file = $upload_dir['basedir'] . '/wpc-bug-' . time() . '-' . $safe_filename;
            
            if (move_uploaded_file($file['tmp_name'], $temp_file)) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
                $attachments[] = $temp_file;
            } else {
                error_log('WP Admin Studio: Failed to move uploaded file');
            }
        }

        $to = apply_filters('wpc_bug_report_email', 'info@kacer.studio');
        $subject = apply_filters('wpc_bug_report_subject', 'WP Admin Studio - Hlášení chyby');
        
        $email_body = "Hlášení chyby z WP Admin Studio\n\n";
        $email_body .= "---\n";
        $email_body .= "Od: " . $email . "\n";
        $email_body .= "\nPopis chyby:\n" . $message . "\n\n";
        $email_body .= "---\n";
        $email_body .= "Systémové informace:\n";
        $email_body .= "WordPress: " . get_bloginfo('version') . "\n";
        $email_body .= "PHP: " . PHP_VERSION . "\n";
        $email_body .= "Plugin: " . self::VERSION . "\n";
        $email_body .= "URL webu: " . home_url() . "\n";
        
        if (!empty($attachments)) {
            $email_body .= "\nScreenshot je přiložen.\n";
        }
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'Reply-To: ' . $email
        );

        $sent = wp_mail($to, $subject, $email_body, $headers, $attachments);

        if (!empty($attachments)) {
            foreach ($attachments as $attachment) {
                if (file_exists($attachment)) {
                    if (!wp_delete_file($attachment)) {
                        error_log('WP Admin Studio: Failed to delete temp file: ' . $attachment);
                    }
                }
            }
        }
        
        if ($sent) {
            
            set_transient($transient_key, true, 5 * MINUTE_IN_SECONDS);
            wp_send_json_success(array('message' => $this->t('bug_report_success')));
        } else {
            error_log('WP Admin Studio: Failed to send bug report email to ' . $to);
            wp_send_json_error(array('message' => $this->t('bug_report_error_send')));
        }
    }
    
    public function ajax_save_settings() {
        
        check_ajax_referer($this->option_name . '_group-options', '_wpnonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in sanitize_settings()
        $settings = isset($_POST[$this->option_name]) ? wp_unslash($_POST[$this->option_name]) : array();

        $sanitized = $this->sanitize_settings($settings);

        update_option($this->option_name, $sanitized);

        wp_send_json_success(array(
            'message' => $this->t('settings_saved')
        ));
    }
    
    public function ajax_restore_htaccess() {
        check_ajax_referer('wpc_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
            return;
        }
        
        $htaccess_file = ABSPATH . '.htaccess';
        $backup_file = $htaccess_file . '.wp-admin-studio-backup';
        
        if (!file_exists($backup_file)) {
            wp_send_json_error(array('message' => $this->t('htaccess_no_backup')));
            return;
        }
        
        if (!is_readable($backup_file)) {
            wp_send_json_error(array('message' => $this->t('htaccess_restore_error')));
            return;
        }
        
        if (!is_writable(ABSPATH)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
            wp_send_json_error(array('message' => 'Root adresář není zapisovatelný - zkontrolujte oprávnění.'));
            return;
        }
        
        $backup_content = @file_get_contents($backup_file);
        if ($backup_content === false) {
            wp_send_json_error(array('message' => $this->t('htaccess_restore_error')));
            return;
        }
        
        $result = @file_put_contents($htaccess_file, $backup_content);
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Nepodařilo se obnovit .htaccess - zkontrolujte oprávnění.'));
            return;
        }
        
        $options = get_option($this->option_name, array());
        $options['htaccess_content'] = '';
        $options['htaccess_enable'] = '';
        update_option($this->option_name, $options);
        
        wp_send_json_success(array('message' => $this->t('htaccess_restore_success')));
    }
    
    private function init_features() {
        $o = get_option($this->option_name, array());

        add_filter('redux/options/salient_redux/options', array($this, 'preserve_google_maps_key'), 999);

        add_action('update_option_salient_redux', array($this, 'restore_google_maps_key_after_save'), 10, 2);
        
        if (!empty($o['admin_bar_items'])) add_action('admin_bar_menu', array($this, 'remove_admin_bar_links'), 999);
        if (!empty($o['disable_login_switcher'])) add_filter('login_display_language_dropdown', '__return_false');
        if (!empty($o['hide_updates_non_admin'])) add_action('admin_head', array($this, 'hide_updates_non_admin'));
        if (!empty($o['disable_auto_update_emails'])) {
            add_filter('auto_core_update_send_email', '__return_false');
            add_filter('auto_plugin_update_send_email', '__return_false');
            add_filter('auto_theme_update_send_email', '__return_false');
        }
        if (!empty($o['hide_admin_notices'])) add_action('admin_head', array($this, 'hide_admin_notices'));
        if (!empty($o['hide_howdy'])) add_filter('gettext', array($this, 'hide_howdy_text'), 10, 3);
        if (!empty($o['hide_wp_version'])) {
            add_filter('admin_footer_text', '__return_empty_string', 11);
            add_filter('update_footer', '__return_empty_string', 11);
        }
        if (!empty($o['remove_wp_news_widget']) && empty($o['hidden_dashboard_widgets'])) {
            $o['hidden_dashboard_widgets'] = array('dashboard_primary');
            update_option($this->option_name, $o);
        }
        add_action('wp_dashboard_setup', array($this, 'detect_dashboard_widgets'), 99);
        if (!empty($o['hidden_dashboard_widgets'])) {
            add_action('wp_dashboard_setup', array($this, 'remove_dashboard_widgets'), 100);
            $detected = get_transient('wpc_detected_widgets');
            if (!empty($detected) && count((array) $o['hidden_dashboard_widgets']) >= count($detected)) {
                add_action('admin_head', array($this, 'hide_empty_dashboard_containers'));
            }
        }
        if (!empty($o['admin_page_titles_enable'])) add_filter('admin_title', array($this, 'custom_admin_title'), 10, 2);
        if (!empty($o['wp_emails_enable'])) {
            add_filter('wp_mail_from_name', array($this, 'custom_email_from_name'), 9999);
            add_filter('wp_mail_from', array($this, 'custom_email_from'), 9999);
            add_action('phpmailer_init', array($this, 'custom_phpmailer_from'), 9999);
            add_filter('wp_mail', array($this, 'force_email_from'), 9999);
        }
        if (!empty($o['disable_gutenberg'])) {
            add_filter('use_block_editor_for_post', '__return_false', 10);
            add_filter('use_widgets_block_editor', '__return_false');
        }
        if (!empty($o['duplicate_posts'])) {
            add_filter('post_row_actions', array($this, 'add_duplicate_post_action'), 10, 2);
            add_filter('page_row_actions', array($this, 'add_duplicate_post_action'), 10, 2);
            add_action('admin_action_duplicate_post', array($this, 'duplicate_post_handler'));
        }
        if (!empty($o['enable_media_replace'])) {
            add_filter('media_row_actions', array($this, 'media_replace_row_action'), 10, 2);
            add_action('admin_action_wpc_replace_media', array($this, 'media_replace_page'));
        }
        if (!empty($o['enable_svg_upload'])) {
            add_filter('upload_mimes', array($this, 'enable_svg_mime_type'), 99);
            add_filter('mime_types', array($this, 'enable_svg_mime_type'), 99);
            add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_mime_type'), 99, 5);
            add_filter('wp_handle_upload_prefilter', array($this, 'sanitize_svg_upload'), 1);
            add_action('admin_head', array($this, 'fix_svg_display'));
            add_filter('map_meta_cap', array($this, 'grant_svg_upload_capability'), 10, 4);
            add_filter('wp_calculate_image_srcset', array($this, 'fix_svg_srcset'), 10, 5);
            add_filter('wp_calculate_image_sizes', array($this, 'fix_svg_sizes'), 10, 5);
            
            if (is_multisite()) {
                add_filter('upload_mimes', array($this, 'enable_svg_mime_type_multisite'), 999);
            }
        }
        if (!empty($o['post_colors'])) add_action('admin_head', array($this, 'posts_status_color'));
        if (!empty($o['edit_link'])) {
            add_action('wp_footer', array($this, 'custom_edit_link'));
            add_action('wp_head', array($this, 'custom_edit_link_styles'));
        }
        if (!empty($o['archive_titles'])) add_filter('get_the_archive_title', array($this, 'custom_archive_title'));
        if (!empty($o['year_shortcode'])) {
            add_shortcode('year', array($this, 'year_shortcode'));

            $safe_do_shortcode = array($this, 'safe_do_shortcode_wrapper');

            add_filter('redux/options/salient_redux/options', array($this, 'process_salient_shortcodes'), 1000);
            add_filter('option_salient_redux', array($this, 'process_salient_shortcodes'), 1000);
            add_filter('option_salient', array($this, 'process_salient_shortcodes'), 1000);

            add_filter('the_content', 'do_shortcode', 11);
            add_filter('widget_text', 'do_shortcode');
            add_filter('widget_title', 'do_shortcode');
            add_filter('widget_block_content', 'do_shortcode');
            add_filter('the_excerpt', 'do_shortcode');
            add_filter('term_description', 'do_shortcode');
            add_filter('nav_menu_item_title', 'do_shortcode');
            add_filter('nav_menu_description', 'do_shortcode');

            add_filter('nectar_footer_copyright_text', $safe_do_shortcode);
            add_filter('nectar_footer_custom_html', $safe_do_shortcode);

            add_filter('astra_footer_copyright_text', $safe_do_shortcode);
            add_filter('astra_theme_footer_text', $safe_do_shortcode);
            add_filter('astra_addon_get_footer_html', $safe_do_shortcode);

            add_filter('ocean_footer_copyright_text', $safe_do_shortcode);

            add_filter('generate_copyright', $safe_do_shortcode);

            add_filter('kadence_footer_html', $safe_do_shortcode);

            add_filter('neve_footer_copyright', $safe_do_shortcode);

            add_filter('et_html_footer_content', $safe_do_shortcode);

            add_filter('avada_footer_content', $safe_do_shortcode);

            add_filter('flatsome_footer_text', $safe_do_shortcode);

            add_filter('widget_custom_html_content', $safe_do_shortcode);

        }
        if (!empty($o['responsive_images'])) {
            add_filter('wp_calculate_image_srcset', '__return_false');
            add_filter('wp_calculate_image_sizes', '__return_false');
        }
        if (!empty($o['disable_big_image_threshold'])) {
            add_filter('big_image_size_threshold', '__return_false');
        }
        if (!empty($o['disable_comments_completely'])) {
            
            add_filter('comments_open', '__return_false', 20, 2);
            add_filter('pings_open', '__return_false', 20, 2);
            add_filter('comments_array', '__return_empty_array', 10, 2);
            
            add_action('admin_menu', array($this, 'remove_comments_menu'));
            add_action('admin_bar_menu', array($this, 'remove_comments_admin_bar'), 999);
        }
        if (!empty($o['disable_user_enumeration'])) {
            
            add_filter('rest_endpoints', array($this, 'disable_user_endpoints'));
            add_action('template_redirect', array($this, 'disable_author_archives'));
        }
        if (!empty($o['auto_delete_files'])) {
            add_action('_core_updated_successfully', array($this, 'delete_unnecessary_files'));
        }
        if (!empty($o['login_customize'])) {
            add_action('login_enqueue_scripts', array($this, 'customize_login_page'));
            add_filter('login_headerurl', array($this, 'custom_login_logo_url'));
            add_filter('login_headertext', array($this, 'custom_login_logo_title'));
        }
        if (!empty($o['remove_comment_url'])) add_filter('comment_form_default_fields', array($this, 'unset_url_field'));
        if (!empty($o['restrict_wpforms_countries'])) add_action('wp_footer', array($this, 'wpforms_restrict_countries'), 99);
        if (!empty($o['enable_translations']) && !is_admin()) {
            
            add_filter('gettext', array($this, 'translate_gettext'), 999, 3);
            add_filter('gettext_with_context', array($this, 'translate_gettext'), 999, 4);
            add_filter('ngettext', array($this, 'translate_ngettext'), 999, 5);

            add_filter('widget_title', array($this, 'translate_text_only'), 999);

            add_filter('term_name', array($this, 'translate_text_only'), 999, 2);
            add_filter('single_term_title', array($this, 'translate_text_only'), 999);

            add_filter('the_title', array($this, 'translate_text_only'), 999);
        }

        if (!empty($o['maintenance_enable'])) add_action('template_redirect', array($this, 'show_maintenance_mode'));

        if (!empty($o['custom_css_enable'])) add_action('wp_head', array($this, 'insert_custom_css'), 10);
        if (!empty($o['script_head_enable'])) add_action('wp_head', array($this, 'insert_head_code'), 10);
        if (!empty($o['script_body_start_enable'])) {
            
            if (function_exists('wp_body_open')) {
                add_action('wp_body_open', array($this, 'insert_body_start_code'), 5);
            } else {
                
                add_action('wp_footer', array($this, 'insert_body_start_code'), 5);
            }
        }
        if (isset($o['google_maps_api_key']) && $o['google_maps_api_key'] !== '') add_action('wp_head', array($this, 'insert_google_maps_api'), 5);
        if (!empty($o['custom_functions_enable'])) add_action('init', array($this, 'execute_custom_functions'), 1);
        
        if (!empty($o['change_login_url']) && !empty($o['custom_login_slug'])) {
            add_action('template_redirect', array($this, 'custom_login_url_intercept'), -1);
            add_action('login_init', array($this, 'custom_login_url_block_direct_access'), 1);
            add_filter('site_url', array($this, 'custom_login_url_site_url'), 10, 4);
            add_filter('network_site_url', array($this, 'custom_login_url_site_url'), 10, 3);
            add_filter('wp_redirect', array($this, 'custom_login_url_wp_redirect'), 10, 2);
        }
    }
    
    public function add_admin_menu() {
        add_options_page('WP WP Admin Studio', 'WP WP Admin Studio', 'manage_options', 'wp-admin-studio', array($this, 'settings_page'));
    }
    
    public function register_settings() {
        register_setting($this->option_name . '_group', $this->option_name, array($this, 'sanitize_settings'));
    }
    
    public function sanitize_settings($input) {
        if (!is_array($input)) return array();
        $output = array();
        $fields = array('admin_bar_items', 'disable_login_switcher', 'hide_updates_non_admin', 'hide_howdy', 
                       'hide_wp_version', 'remove_wp_news_widget', 'hidden_dashboard_widgets', 'admin_page_titles_enable', 'admin_page_title_format',
                       'wp_emails_enable', 'wp_email_from_name', 'wp_email_from_email', 'auto_delete_files', 'disable_user_enumeration',
                       'disable_auto_update_emails', 'hide_admin_notices', 'show_notices_current_user', 'notices_user_id',
                       'change_login_url', 'custom_login_slug',
                       'login_customize', 'login_logo_height',
                       'login_bg_size',
                       'login_primary_color', 'login_form_radius',
                       'login_form_bg_color', 'login_form_text_color',
                       'login_button_bg', 'login_button_text_color', 'login_button_radius',
                       'login_links_color',
                       'login_hide_lostpassword', 'login_hide_backtoblog', 'login_hide_rememberme', 'login_hide_privacy',
                       'disable_gutenberg', 'duplicate_posts', 'enable_svg_upload', 'enable_media_replace', 'post_colors', 'edit_link', 'archive_titles', 
                       'year_shortcode', 'responsive_images', 'disable_big_image_threshold', 'disable_comments_completely', 'remove_comment_url',
                       'enable_translations', 'archive_category_prefix', 'archive_tag_prefix', 'css_editor_theme', 'php_editor_theme',
                       'custom_css_enable', 'script_head_enable', 'script_body_start_enable', 'robots_enable', 'htaccess_enable',
                       'google_maps_api_key', 'custom_functions_enable',
                       'maintenance_enable', 'maintenance_mode_type', 'maintenance_heading', 'maintenance_message',
                       'maintenance_button_text', 'maintenance_button_url', 'maintenance_button_show',
                       'maintenance_image_max_width', 'maintenance_bg_color', 'maintenance_text_color', 'maintenance_show_logged',
                       'maintenance_button_bg_color', 'maintenance_button_text_color', 'maintenance_button_radius', 'maintenance_html_editor_theme');
        
        foreach ($fields as $f) {
            if (isset($input[$f])) {
                if (is_array($input[$f])) $output[$f] = array_map('sanitize_text_field', $input[$f]);
                else $output[$f] = sanitize_text_field($input[$f]);
            }
        }

        $allowed_phone_restrict = array('', 'czsk', 'europe', 'us');
        $output['restrict_wpforms_countries'] = in_array($input['restrict_wpforms_countries'] ?? '', $allowed_phone_restrict, true) ? $input['restrict_wpforms_countries'] : '';

        $url_fields = array('maintenance_image', 'login_logo', 'login_bg_image', 'login_logo_url');
        foreach ($url_fields as $f) {
            if (isset($input[$f])) {
                $output[$f] = esc_url_raw($input[$f]);
            }
        }

        $bool_fields = array(
            'disable_login_switcher', 'hide_updates_non_admin', 'hide_howdy', 'hide_wp_version',
            'admin_page_titles_enable', 'wp_emails_enable', 'auto_delete_files',
            'disable_user_enumeration', 'change_login_url',
            'disable_auto_update_emails', 'hide_admin_notices', 'show_notices_current_user',
            'login_customize', 'login_hide_lostpassword', 'login_hide_backtoblog', 'login_hide_rememberme', 'login_hide_privacy',
            'disable_gutenberg', 'duplicate_posts', 'enable_svg_upload', 'enable_media_replace', 'post_colors', 'edit_link',
            'archive_titles', 'year_shortcode', 'responsive_images', 'disable_big_image_threshold',
            'disable_comments_completely', 'remove_comment_url',
            'enable_translations', 'custom_css_enable', 'script_head_enable', 'script_body_start_enable',
            'robots_enable', 'htaccess_enable', 'custom_functions_enable',
            'maintenance_enable', 'maintenance_show_logged', 'maintenance_button_show',
        );
        foreach ($bool_fields as $f) {
            if (!isset($output[$f])) {
                $output[$f] = '';
            }
        }

        if (isset($input['notices_user_id'])) {
            $output['notices_user_id'] = absint($input['notices_user_id']);
        } else {
            $output['notices_user_id'] = get_current_user_id();
        }

        $color_fields = array(
            'maintenance_bg_color', 'maintenance_text_color',
            'maintenance_button_bg_color', 'maintenance_button_text_color',
            'login_bg_color', 'login_primary_color',
            'login_form_bg_color', 'login_form_text_color',
            'login_button_bg', 'login_button_text_color', 'login_links_color',
            'color_draft', 'color_pending', 'color_publish', 'color_future', 'color_private',
        );
        foreach ($color_fields as $f) {
            if (isset($input[$f])) {
                $val = sanitize_hex_color($input[$f]);
                if ($val) $output[$f] = $val;
                else $output[$f] = sanitize_text_field($input[$f]);
            }
        }

        if (isset($input['custom_css_code'])) {
            $output['custom_css_code'] = $this->safe_unslash_code($input['custom_css_code']);
        }

        if (isset($input['login_custom_css'])) {
            $output['login_custom_css'] = $this->safe_unslash_code($input['login_custom_css']);
        }

        if (isset($input['script_head_code'])) {
            $output['script_head_code'] = $this->safe_unslash_code($input['script_head_code']);
        }
        if (isset($input['script_body_start_code'])) {
            $output['script_body_start_code'] = $this->safe_unslash_code($input['script_body_start_code']);
        }

        if (isset($input['custom_functions_code'])) {
            $output['custom_functions_code'] = $this->safe_unslash_code($input['custom_functions_code']);
        }

        if (isset($input['maintenance_html_code'])) {
            $output['maintenance_html_code'] = $this->safe_unslash_code($input['maintenance_html_code']);
        }

        if (isset($input['robots_content'])) {
            $output['robots_content'] = $input['robots_content'];

            if (!empty($input['robots_enable']) && !empty($input['robots_content'])) {
                $robots_file = ABSPATH . 'robots.txt';

                if (!is_writable(ABSPATH)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
                    add_settings_error('wpc_robots', 'wpc_robots_error', 'Root adresář není zapisovatelný - zkontrolujte oprávnění.', 'error');
                    error_log('WP Admin Studio: ABSPATH is not writable for robots.txt');
                } else if (file_exists($robots_file) && !is_writable($robots_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
                    add_settings_error('wpc_robots', 'wpc_robots_error', 'Soubor robots.txt není zapisovatelný - zkontrolujte oprávnění.', 'error');
                    error_log('WP Admin Studio: robots.txt is not writable');
                } else {
                    
                    $content = wp_unslash($input['robots_content']);
                    if (strlen($content) > self::MAX_FILE_SIZE) {
                        add_settings_error('wpc_robots', 'wpc_robots_error', 'Obsah robots.txt je příliš velký (max 5MB).', 'error');
                    } else {
                        $result = @file_put_contents($robots_file, $content);
                        
                        if ($result === false) {
                            $error = error_get_last();
                            error_log('WP Admin Studio: Failed to write robots.txt - ' . ($error['message'] ?? 'Unknown error'));
                            add_settings_error('wpc_robots', 'wpc_robots_error', 'Nepodařilo se zapsat robots.txt - zkontrolujte oprávnění souboru.', 'error');
                        }
                    }
                }
            }
        }

        if (isset($input['htaccess_content'])) {
            $output['htaccess_content'] = $input['htaccess_content'];
            
            $htaccess_file = ABSPATH . '.htaccess';

            if (!empty($input['htaccess_enable']) && !empty($input['htaccess_content'])) {

                if (!is_writable(ABSPATH)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
                    add_settings_error('wpc_htaccess', 'wpc_htaccess_error', 'Root adresář není zapisovatelný - zkontrolujte oprávnění.', 'error');
                    error_log('WP Admin Studio: ABSPATH is not writable for .htaccess');
                } else if (file_exists($htaccess_file) && !is_writable($htaccess_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
                    add_settings_error('wpc_htaccess', 'wpc_htaccess_error', 'Soubor .htaccess není zapisovatelný - zkontrolujte oprávnění.', 'error');
                    error_log('WP Admin Studio: .htaccess is not writable');
                } else {
                    
                    $content = wp_unslash($input['htaccess_content']);
                    if (strlen($content) > self::MAX_FILE_SIZE) {
                        add_settings_error('wpc_htaccess', 'wpc_htaccess_error', 'Obsah .htaccess je příliš velký (max 5MB).', 'error');
                    } else {
                        
                        $current_content = file_exists($htaccess_file) ? @file_get_contents($htaccess_file) : '';
                        
                        if ($current_content === false && file_exists($htaccess_file)) {
                            error_log('WP Admin Studio: Failed to read .htaccess');
                            add_settings_error('wpc_htaccess', 'wpc_htaccess_error', 'Nepodařilo se přečíst .htaccess.', 'error');
                        } else {
                            
                            $clean_content = preg_replace('/# BEGIN WP WP Admin Studio.*?# END WP WP Admin Studio\n?/s', '', $current_content);

                            $backup_file = $htaccess_file . '.wp-admin-studio-backup';
                            if (strlen($clean_content) > 0 || file_exists($htaccess_file)) {
                                @file_put_contents($backup_file, $clean_content);
                            }

                            $custom_rules = "# BEGIN WP WP Admin Studio\n" . $content . "\n# END WP WP Admin Studio\n\n";
                            $new_content = $custom_rules . $clean_content;
                            
                            $result = @file_put_contents($htaccess_file, $new_content);
                            
                            if ($result === false) {
                                $error = error_get_last();
                                error_log('WP Admin Studio: Failed to write .htaccess - ' . ($error['message'] ?? 'Unknown error'));

                                if (file_exists($backup_file)) {
                                    @copy($backup_file, $htaccess_file);
                                }
                                
                                add_settings_error('wpc_htaccess', 'wpc_htaccess_error', 'Nepodařilo se zapsat .htaccess - zkontrolujte oprávnění souboru.', 'error');
                            } else {
                                add_settings_error('wpc_htaccess', 'wpc_htaccess_success', $this->t('htaccess_backup_success'), 'success');
                            }
                        }
                    }
                }
            } else {
                
                if (file_exists($htaccess_file) && is_writable($htaccess_file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
                    
                    $current_content = @file_get_contents($htaccess_file);
                    if ($current_content !== false) {
                        $clean_content = preg_replace('/# BEGIN WP WP Admin Studio.*?# END WP WP Admin Studio\n?/s', '', $current_content);
                        
                        $backup_file = $htaccess_file . '.wp-admin-studio-backup';
                        @file_put_contents($backup_file, $clean_content);
                        
                        @file_put_contents($htaccess_file, $clean_content);
                    }
                }
            }
        }
        
        if (isset($input['translations']) && is_array($input['translations'])) {
            $output['translations'] = array();
            foreach ($input['translations'] as $t) {
                if (!empty($t['from'])) {
                    $output['translations'][] = array(
                        'from' => sanitize_text_field($t['from']),
                        'to' => wp_kses_post($t['to']) 
                    );
                }
            }
        }

        if (isset($input['google_maps_api_key']) && $input['google_maps_api_key'] !== '') {
            $api_key = sanitize_text_field($input['google_maps_api_key']);
            $current_theme = wp_get_theme();
            $theme_name = $current_theme->get('Name');
            $parent_theme = $current_theme->parent();

            if ($theme_name === 'Salient' || ($parent_theme && $parent_theme->get('Name') === 'Salient')) {
                
                $possible_options = array('salient_redux', 'salient', 'nectar_options', 'salient_options');
                $found_option = false;
                
                foreach ($possible_options as $option_name) {
                    $option_data = get_option($option_name);
                    if ($option_data !== false && is_array($option_data)) {
                        $found_option = $option_name;

                        $option_data['google-maps-api-key'] = $api_key;
                        $option_data['google_maps_api_key'] = $api_key;
                        $option_data['gmaps-api-key'] = $api_key;
                        
                        update_option($option_name, $option_data);

                        add_filter('redux/options/salient_redux/options', array($this, 'preserve_google_maps_key'));

                        add_settings_error(
                            'wpc_google_maps',
                            'wpc_google_maps_debug',
                            'DEBUG: Google Maps API key saved to option "' . $option_name . '"',
                            'updated'
                        );
                        break;
                    }
                }
                
                if (!$found_option) {
                    add_settings_error(
                        'wpc_google_maps',
                        'wpc_google_maps_error',
                        'DEBUG: Could not find Salient Redux options in database. Checked: ' . implode(', ', $possible_options),
                        'error'
                    );
                }
            }
        }

        if (isset($input['custom_login_slug']) && !empty($input['custom_login_slug'])) {
            $slug = sanitize_title($input['custom_login_slug']);
            
            $reserved_slugs = array(
                'wp-admin', 'wp-login', 'login', 'admin', 'wp-content', 'wp-includes',
                'dashboard', 'wp-json', 'feed', 'rss', 'rdf', 'atom', 'comments', 'trackback'
            );
            
            if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
                add_settings_error('wpc_custom_login', 'invalid_slug', $this->t('login_url_invalid_slug'), 'error');
                $slug = '';
            }
            else if (in_array($slug, $reserved_slugs)) {
                add_settings_error('wpc_custom_login', 'reserved_slug', $this->t('login_url_reserved_slug'), 'error');
                $slug = '';
            }
            
            $output['custom_login_slug'] = $slug;
        }

        $old_options = get_option($this->option_name, array());
        $old_svg_enabled = !empty($old_options['enable_svg_upload']);
        $new_svg_enabled = !empty($output['enable_svg_upload']);
        
        if ($old_svg_enabled && !$new_svg_enabled) {
            $administrator_role = get_role('administrator');
            if ($administrator_role) {
                $administrator_role->remove_cap('unfiltered_upload');
            }
            $editor_role = get_role('editor');
            if ($editor_role) {
                $editor_role->remove_cap('unfiltered_upload');
            }
        }

        set_transient('wpc_settings_saved', true, 30);
        return $output;
    }
    
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'settings_page_wp-admin-studio') return;

        wp_enqueue_media();
        
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_script('jquery-ui-sortable');

        wp_enqueue_code_editor(array('type' => 'text/css'));
        wp_enqueue_script('wp-theme-plugin-editor');
        wp_enqueue_style('wp-codemirror');

        $assets_url = plugin_dir_url(__FILE__) . 'assets/';
        $assets_ver = self::VERSION;

        wp_enqueue_script(
            'codemirror-addon-lint',
            $assets_url . 'js/codemirror/addon/lint/lint.min.js',
            array('wp-codemirror'),
            $assets_ver,
            true
        );
        wp_enqueue_style(
            'codemirror-addon-lint-css',
            $assets_url . 'css/codemirror/addon/lint/lint.min.css',
            array('wp-codemirror'),
            $assets_ver
        );
        wp_enqueue_script(
            'codemirror-addon-css-lint',
            $assets_url . 'js/codemirror/addon/lint/css-lint.min.js',
            array('codemirror-addon-lint'),
            $assets_ver,
            true
        );

        wp_enqueue_script(
            'csslint',
            $assets_url . 'js/csslint.min.js',
            array(),
            '1.0.5',
            true
        );

        wp_enqueue_script(
            'codemirror-mode-clike',
            $assets_url . 'js/codemirror/mode/clike.min.js',
            array('wp-codemirror'),
            $assets_ver,
            true
        );
        wp_enqueue_script(
            'codemirror-mode-php',
            $assets_url . 'js/codemirror/mode/php.min.js',
            array('wp-codemirror', 'codemirror-mode-clike'),
            $assets_ver,
            true
        );

        wp_enqueue_script(
            'codemirror-mode-xml',
            $assets_url . 'js/codemirror/mode/xml.min.js',
            array('wp-codemirror'),
            $assets_ver,
            true
        );
        wp_enqueue_script(
            'codemirror-mode-javascript',
            $assets_url . 'js/codemirror/mode/javascript.min.js',
            array('wp-codemirror'),
            $assets_ver,
            true
        );
        wp_enqueue_script(
            'codemirror-mode-css-js',
            $assets_url . 'js/codemirror/mode/css.min.js',
            array('wp-codemirror'),
            $assets_ver,
            true
        );
        wp_enqueue_script(
            'codemirror-mode-htmlmixed',
            $assets_url . 'js/codemirror/mode/htmlmixed.min.js',
            array('wp-codemirror', 'codemirror-mode-xml', 'codemirror-mode-javascript', 'codemirror-mode-css-js'),
            $assets_ver,
            true
        );

        $themes = array('material-darker', 'material', 'dracula', 'monokai', 'nord');
        foreach ($themes as $theme) {
            wp_enqueue_style(
                'codemirror-theme-' . $theme,
                $assets_url . 'css/codemirror/theme/' . $theme . '.min.css',
                array('wp-codemirror'),
                $assets_ver
            );
        }
    }
    
    public function export_settings() {
        check_admin_referer('wpc_export');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'wp-admin-studio'), 403);
        }
        $data = array('settings' => get_option($this->option_name, array()), 'version' => self::VERSION, 'export_date' => gmdate('Y-m-d H:i:s'));
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="wpc-settings-' . gmdate('Y-m-d') . '.json"');
        echo wp_json_encode($data, JSON_PRETTY_PRINT); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download response
        exit;
    }
    
    public function import_settings() {
        check_admin_referer('wpc_import');
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'wp-admin-studio'), 403);
        }
        $status = 'error';
        if (!empty($_FILES['import_file']['tmp_name'])) {
            $data = json_decode(file_get_contents($_FILES['import_file']['tmp_name']), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if ($data && isset($data['settings'])) {
                
                $current = get_option($this->option_name, array());

                if (isset($data['settings']['translations']) && is_array($data['settings']['translations'])) {
                    $current_translations = isset($current['translations']) ? $current['translations'] : array();
                    $import_translations = $data['settings']['translations'];

                    $current_lookup = array();
                    foreach ($current_translations as $index => $trans) {
                        if (isset($trans['from'])) {
                            
                            $key = strtolower(trim($trans['from']));
                            $current_lookup[$key] = $index;
                        }
                    }

                    foreach ($import_translations as $import_trans) {
                        if (isset($import_trans['from'])) {
                            $import_key = strtolower(trim($import_trans['from']));
                            
                            if (isset($current_lookup[$import_key])) {
                                
                                $current_translations[$current_lookup[$import_key]] = $import_trans;
                            } else {
                                
                                $current_translations[] = $import_trans;
                                
                                $current_lookup[$import_key] = count($current_translations) - 1;
                            }
                        }
                    }

                    $data['settings']['translations'] = $current_translations;
                }

                $sanitized = $this->sanitize_settings($data['settings']);
                $merged = array_merge($current, $sanitized);
                update_option($this->option_name, $merged);
                $status = 'success';
            } else {
                $status = 'invalid';
            }
        }
        wp_safe_redirect(add_query_arg(array('page' => 'wp-admin-studio', 'import' => $status), admin_url('options-general.php')));
        exit;
        exit;
    }
    
    public function import_translations() {
        check_admin_referer('wpc_import_translations');
        $status = 'error';
        if (!empty($_FILES['import_translations_file']['tmp_name'])) {
            $data = json_decode(file_get_contents($_FILES['import_translations_file']['tmp_name']), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            if ($data && isset($data['translations']) && is_array($data['translations'])) {
                $current = get_option($this->option_name, array());
                $new_translations = array();
                foreach ($data['translations'] as $t) {
                    if (isset($t['from'])) {
                        $new_translations[] = array(
                            'from' => sanitize_text_field($t['from']),
                            'to' => isset($t['to']) ? wp_kses_post($t['to']) : '',
                        );
                    }
                }
                $current['translations'] = $new_translations;
                update_option($this->option_name, $current);
                $status = 'success';
            } else {
                $status = 'invalid';
            }
        }
        wp_safe_redirect(add_query_arg(array('page' => 'wp-admin-studio', 'import' => $status), admin_url('options-general.php')));
        exit;
        exit;
    }

    private function safe_unslash_code($value) {
        if (!is_string($value)) return $value;
        return preg_replace('/\\\\([\\\\\'\"0])/', '$1', $value);
    }

    private function tip($txt) {
        return '<span class="wpc-tip"><span class="dashicons dashicons-editor-help"></span><span class="wpc-tooltip">' . esc_html($txt) . '</span></span>';
    }
    
    private function get_default_translations() {
        return array(
            array('from' => 'Name', 'to' => 'Jméno'),
            array('from' => 'Next', 'to' => 'Další'),
            array('from' => 'Previous', 'to' => 'Předchozí'),
            array('from' => 'Read More', 'to' => 'Přečíst'),
            array('from' => 'Previous Post', 'to' => 'Předchozí'),
            array('from' => 'Next Post', 'to' => 'Pokračovat'),
            array('from' => 'Category', 'to' => 'Téma'),
            array('from' => 'Tag', 'to' => 'Téma'),
            array('from' => 'All', 'to' => 'Vše'),
            array('from' => 'Load More', 'to' => 'Zobrazit další'),
            array('from' => 'Filter', 'to' => 'Vybrat kategorii'),
            array('from' => 'read', 'to' => 'Přečíst'),
            array('from' => 'Vše items loaded', 'to' => 'Víc už toho není'),
            array('from' => 'Back', 'to' => 'zpět'),
            array('from' => 'View', 'to' => 'Zobrazit'),
            array('from' => 'Related Posts', 'to' => 'Stojí za přečtení'),
            array('from' => 'Results For', 'to' => 'Vyhledávání'),
            array('from' => 'Sorry, no results were found.', 'to' => 'Nic nenalezeno'),
            array('from' => 'Please try again with different keywords.', 'to' => 'Zkuste zadat podobná nebo jiná klíčová slova vyhledávání.'),
            array('from' => 'Search for:', 'to' => 'Vyhledat…'),
            array('from' => 'results found', 'to' => 'výsledků'),
        );
    }
    
    public function settings_page() {
        $o = get_option($this->option_name, array());
        $defs = array('color_draft' => '#FCE3F2', 'color_pending' => '#fff4d1', 'color_publish' => '',
                     'color_future' => '#ebffd3', 'color_private' => '#f9eab5', 'archive_category_prefix' => 'Téma: ',
                     'archive_tag_prefix' => 'Téma: ', 'translations' => $this->get_default_translations(), 'admin_bar_items' => array());
        $o = wp_parse_args($o, $defs);
        
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param for import result notice
        if (isset($_GET['import'])) { $import_status = sanitize_key(wp_unslash($_GET['import'])); echo '<div class="notice notice-' . ($import_status === 'success' ? 'success' : 'error') . ' is-dismissible"><p>' . esc_html($this->t('import_' . $import_status)) . '</p></div>'; }
        
        $lang = $this->get_lang();
        ?>
        <div class="wrap wpc-wrap">
            <div class="wpc-header">
                <h1><?php echo esc_html($this->t('page_title')); ?></h1>
                <div class="wpc-header-meta">
                    <div class="wpc-header-info">
                        <span><?php echo esc_html($this->t('version')); ?></span>
                        <span class="wpc-separator">|</span>
                        <span><?php echo wp_kses_post($this->t('author')); ?></span>
                    </div>
                    <div class="wpc-header-lang">
                        <label for="wpc-lang-select">Jazyk / Language / Sprache:</label>
                        <select class="wpc-lang-select" id="wpc-lang-select">
                            <option value="cs" <?php selected($lang, 'cs'); ?>>Čeština</option>
                            <option value="de" <?php selected($lang, 'de'); ?>>Deutsch</option>
                            <option value="en" <?php selected($lang, 'en'); ?>>English</option>
                            <option value="sk" <?php selected($lang, 'sk'); ?>>Slovenčina</option>
                            <option value="pl" <?php selected($lang, 'pl'); ?>>Polski</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <form method="post" action="options.php" id="wpc-main-form">
                <?php settings_fields($this->option_name . '_group'); ?>
                
                <!-- STICKY SECTION NAVIGATION -->
                <div class="wpc-sticky-nav">
                    <div class="wpc-sticky-nav-content" id="wpc-sticky-nav-content">
                        <a href="#section-admin" class="wpc-nav-item"><?php echo esc_html($this->t('nav_admin')); ?></a>
                        <a href="#section-scripts" class="wpc-nav-item"><?php echo esc_html($this->t('nav_scripts')); ?></a>
                        <a href="#section-maintenance" class="wpc-nav-item"><?php echo esc_html($this->t('nav_maintenance')); ?></a>
                        <a href="#section-login" class="wpc-nav-item"><?php echo esc_html($this->t('nav_login')); ?></a>
                        <a href="#section-robots" class="wpc-nav-item">robots.txt</a>
                        <a href="#section-htaccess" class="wpc-nav-item">.htaccess</a>
                        <a href="#section-editor" class="wpc-nav-item"><?php echo esc_html($this->t('nav_editor')); ?></a>
                        <a href="#section-frontend" class="wpc-nav-item"><?php echo esc_html($this->t('nav_frontend')); ?></a>
                        <a href="#section-comments" class="wpc-nav-item"><?php echo esc_html($this->t('nav_comments')); ?></a>
                        <a href="#section-forms" class="wpc-nav-item"><?php echo esc_html($this->t('nav_forms')); ?></a>
                        <a href="#section-translations" class="wpc-nav-item"><?php echo esc_html($this->t('nav_translations')); ?></a>
                        <a href="#section-system" class="wpc-nav-item"><?php echo esc_html($this->t('nav_system')); ?></a>
                        <a href="#section-backup" class="wpc-nav-item"><?php echo esc_html($this->t('nav_backup')); ?></a>
                    </div>
                </div>
                
                <!-- ADMINISTRATION -->
                <div class="wpc-section" id="section-admin">
                    <h2><span class="dashicons dashicons-admin-settings"></span> <?php echo esc_html($this->t('admin_section')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label><?php echo esc_html($this->t('admin_bar')); ?> <?php echo $this->tip($this->t('admin_bar_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td>
                                <?php $items = isset($o['admin_bar_items']) ? $o['admin_bar_items'] : array(); ?>
                                <label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_items][]" value="wp-logo" <?php checked(in_array('wp-logo', $items)); ?>> <?php echo esc_html($this->t('admin_bar_logo')); ?></label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_items][]" value="updates" <?php checked(in_array('updates', $items)); ?>> <?php echo esc_html($this->t('admin_bar_updates')); ?></label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_items][]" value="new-content" <?php checked(in_array('new-content', $items)); ?>> <?php echo esc_html($this->t('admin_bar_new')); ?></label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_items][]" value="view-site" <?php checked(in_array('view-site', $items)); ?>> <?php echo esc_html($this->t('admin_bar_view')); ?></label><br>
                                <label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[admin_bar_items][]" value="my-account-avatar" <?php checked(in_array('my-account-avatar', $items)); ?>> <?php echo esc_html($this->t('admin_bar_avatar')); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('hide_updates')); ?> <?php echo $this->tip($this->t('hide_updates_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_updates_non_admin]" value="1" <?php checked(1, !empty($o['hide_updates_non_admin'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('hide_updates_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('disable_auto_update_emails')); ?> <?php echo $this->tip($this->t('disable_auto_update_emails_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_auto_update_emails]" value="1" <?php checked(1, !empty($o['disable_auto_update_emails'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('disable_auto_update_emails_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('hide_admin_notices')); ?> <?php echo $this->tip($this->t('hide_admin_notices_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_admin_notices]" value="1" <?php checked(1, !empty($o['hide_admin_notices'])); ?>><span class="wpc-toggle-slider"></span></span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('hide_admin_notices_desc')); ?></span>
                                </label>
                                <div style="margin-top:8px;">
                                    <label class="wpc-toggle-label">
                                        <span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[show_notices_current_user]" value="1" <?php checked(1, !empty($o['show_notices_current_user'])); ?>><span class="wpc-toggle-slider"></span></span>
                                        <span class="wpc-toggle-text"><?php
                                            $current_user = wp_get_current_user();
                                            $display_name = !empty($current_user->display_name) ? $current_user->display_name : $current_user->user_login;
                                            echo esc_html($this->t('show_notices_current_user_desc')) . esc_html($display_name);
                                        ?></span>
                                    </label>
                                    <input type="hidden" name="<?php echo esc_attr($this->option_name); ?>[notices_user_id]" value="<?php echo esc_attr(get_current_user_id()); ?>">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('hide_howdy')); ?>
                                    <?php echo $this->tip($this->t('hide_howdy_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_howdy]" value="1" <?php checked(1, !empty($o['hide_howdy'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('hide_howdy_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('hide_wp_version')); ?>
                                    <?php echo $this->tip($this->t('hide_wp_version_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hide_wp_version]" value="1" <?php checked(1, !empty($o['hide_wp_version'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('hide_wp_version_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('hide_dashboard_widgets')); ?> <?php echo $this->tip($this->t('hide_dashboard_widgets_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td>
                                <?php
                                $detected = get_transient('wpc_detected_widgets');
                                $default_widgets = array(
                                    'welcome_panel'       => 'Vítejte ve WordPressu',
                                    'dashboard_right_now' => 'At a Glance',
                                    'dashboard_activity'  => 'Activity',
                                    'dashboard_quick_press' => 'Quick Draft',
                                    'dashboard_primary'   => 'Events and News',
                                );
                                $widgets_list = !empty($detected) ? $detected : $default_widgets;
                                $hidden_widgets = isset($o['hidden_dashboard_widgets']) ? (array) $o['hidden_dashboard_widgets'] : array();
                                foreach ($widgets_list as $widget_id => $widget_title):
                                ?>
                                <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[hidden_dashboard_widgets][]" value="<?php echo esc_attr($widget_id); ?>" <?php checked(in_array($widget_id, $hidden_widgets)); ?>>
                                    <?php echo esc_html($widget_title); ?>
                                </label>
                                <?php endforeach; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('admin_page_titles')); ?>
                                    <?php echo $this->tip($this->t('admin_page_titles_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[admin_page_titles_enable]" value="1" <?php checked(1, !empty($o['admin_page_titles_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('admin_page_titles_desc')); ?></span>
                                    </label>
                                <div style="margin-top: 12px;">
                                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[admin_page_title_format]" value="<?php echo isset($o['admin_page_title_format']) ? esc_attr($o['admin_page_title_format']) : '%page% - %site_title%'; ?>" style="width: 100%; max-width: 400px; padding: 3px 5px; font-size: 14px; display: block;">
                                    <p class="description" style="margin-top: 8px; clear: both;">
                                        <strong>%page%</strong> = <?php echo esc_html($this->t('admin_page_title_page_tag')); ?> &nbsp;|&nbsp; <strong>%site_title%</strong> = <?php echo esc_html($this->t('admin_page_title_site_tag')); ?>
                                    </p>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('wp_emails')); ?>
                                    <?php echo $this->tip($this->t('wp_emails_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[wp_emails_enable]" value="1" <?php checked(1, !empty($o['wp_emails_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('wp_emails_desc')); ?></span>
                                    </label>
                                <div style="margin-top: 12px;">
                                    <label style="display: block; margin-bottom: 6px; font-weight: 500;"><?php echo esc_html($this->t('wp_email_from_name')); ?></label>
                                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[wp_email_from_name]" value="<?php echo isset($o['wp_email_from_name']) ? esc_attr($o['wp_email_from_name']) : ''; ?>" style="width: 100%; max-width: 400px; padding: 3px 5px; font-size: 14px; display: block;">
                                </div>
                                <div style="margin-top: 12px;">
                                    <label style="display: block; margin-bottom: 6px; font-weight: 500;"><?php echo esc_html($this->t('wp_email_from_email')); ?></label>
                                    <input type="email" name="<?php echo esc_attr($this->option_name); ?>[wp_email_from_email]" value="<?php echo isset($o['wp_email_from_email']) ? esc_attr($o['wp_email_from_email']) : ''; ?>" placeholder="<?php 
                                        $site_domain = wp_parse_url(get_site_url(), PHP_URL_HOST);
                                        $site_domain = preg_replace('/^www\./', '', $site_domain);
                                        $current_lang = $this->get_lang();
                                        $placeholder_prefix = $current_lang === 'cs' ? 'např.' : ($current_lang === 'de' ? 'z.B.' : ($current_lang === 'sk' ? 'napr.' : ($current_lang === 'pl' ? 'np.' : 'e.g.')));
                                        echo esc_attr($placeholder_prefix . ' info@' . $site_domain);
                                    ?>" style="width: 100%; max-width: 400px; padding: 3px 5px; font-size: 14px; display: block;">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('auto_delete_files')); ?>
                                    <?php echo $this->tip($this->t('auto_delete_files_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[auto_delete_files]" value="1" <?php checked(1, !empty($o['auto_delete_files'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('auto_delete_files_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('disable_user_enumeration')); ?> 
                                    <?php echo $this->tip($this->t('disable_user_enumeration_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_user_enumeration]" value="1" <?php checked(1, !empty($o['disable_user_enumeration'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('disable_user_enumeration_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('change_login_url')); ?> 
                                    <?php echo $this->tip($this->t('change_login_url_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[change_login_url]" value="1" <?php checked(1, !empty($o['change_login_url'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('change_login_url_desc')); ?></span>
                                    </label>
                                
                                <div id="wpc-custom-login-url-wrapper" style="margin-top: 12px;">
                                    <code id="wpc-login-url-display" style="background: #f0f0f1; padding: 2px 6px; border-radius: 3px; font-family: Consolas, Monaco, monospace; font-size: 14px; color: #50575e; border: 1px solid #dcdcde; white-space: nowrap;"><?php echo esc_html(home_url('/')); ?></code>
                                    <input type="text" 
                                           name="<?php echo esc_attr($this->option_name); ?>[custom_login_slug]" 
                                           id="wpc-custom-login-slug"
                                           value="<?php echo isset($o['custom_login_slug']) ? esc_attr($o['custom_login_slug']) : ''; ?>" 
                                           style="width: 300px; padding: 3px 5px; font-size: 14px; margin-left: 5px;">
                                </div>
                                
                                <style>
                                    @media screen and (max-width: 768px) {
                                        #wpc-custom-login-url-wrapper {
                                            margin-top: 22px !important;
                                        }
                                        #wpc-login-url-display {
                                            display: block !important;
                                            margin-bottom: 10px !important;
                                        }
                                        #wpc-custom-login-slug {
                                            width: 100% !important;
                                            padding: 8px 10px !important;
                                            margin: 15px 0 0 0 !important;
                                            display: block !important;
                                        }
                                    }
                                </style>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- CUSTOM SCRIPTS & CODES -->
                <div class="wpc-section" id="section-scripts">
                    <h2><span class="dashicons dashicons-editor-code"></span> <?php echo esc_html($this->t('custom_scripts')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('custom_css')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('custom_css_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label" id="custom_css_toggle_label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[custom_css_enable]" value="1" <?php checked(!empty($o['custom_css_enable'])); ?> id="custom_css_enable_checkbox">
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('custom_css_desc')); ?></span>
                                </label>
                                <br>
                                <div class="wpc-toolbar">
                                    <div class="wpc-search-wrapper">
                                        <button type="button" class="wpc-search-toggle" id="css-search-toggle">
                                            <svg class="wpc-icon-search" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                                <circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.5"/>
                                                <path d="M12 12L16 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <svg class="wpc-icon-close" width="14" height="14" viewBox="0 0 14 14" fill="none" style="display:none;">
                                                <path d="M2 2L12 12M12 2L2 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                        <div class="wpc-search-panel" id="css-search-panel" style="display:none;">
                                            <input type="text" class="wpc-search-field" id="css-search-field" placeholder="<?php echo esc_attr($this->t('search_placeholder')); ?>">
                                            <span class="wpc-search-count" id="css-search-count">0/0</span>
                                            <button type="button" class="wpc-search-up" id="css-search-up">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                    <path d="M7 11V3M7 3L3 7M7 3L11 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                            <button type="button" class="wpc-search-down" id="css-search-down">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                    <path d="M7 3V11M7 11L11 7M7 11L3 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <select id="css_editor_theme" name="<?php echo esc_attr($this->option_name); ?>[css_editor_theme]" class="wpc-theme-select">
                                        <?php $current_theme = isset($o['css_editor_theme']) && !empty($o['css_editor_theme']) ? $o['css_editor_theme'] : 'material-darker'; ?>
                                        <option value="material-darker" <?php selected($current_theme, 'material-darker'); ?>>Material Darker</option>
                                        <option value="material" <?php selected($current_theme, 'material'); ?>>Material</option>
                                        <option value="dracula" <?php selected($current_theme, 'dracula'); ?>>Dracula</option>
                                        <option value="monokai" <?php selected($current_theme, 'monokai'); ?>>Monokai</option>
                                        <option value="nord" <?php selected($current_theme, 'nord'); ?>>Nord</option>
                                        <option value="default" <?php selected($current_theme, 'default'); ?>>Světlý</option>
                                    </select>
                                </div>
                                <textarea id="custom_css_editor" name="<?php echo esc_attr($this->option_name); ?>[custom_css_code]" rows="8" class="wpc-code-textarea"><?php echo isset($o['custom_css_code']) ? esc_textarea($o['custom_css_code']) : ''; ?></textarea>
                                <p class="description" style="margin-top:8px;"><?php echo wp_kses_post($this->t('custom_css_info')); ?></p>
                                <p style="margin-top:10px;">
                                    <button type="submit" form="wpc-main-form" class="button button-primary">
                                        <?php echo esc_html($this->t('save')); ?>
                                    </button>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('script_head')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('script_head_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[script_head_enable]" value="1" <?php checked(!empty($o['script_head_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('script_head_desc')); ?></span>
                                    </label>
                                <br>
                                <textarea name="<?php echo esc_attr($this->option_name); ?>[script_head_code]" rows="8" class="wpc-code-textarea"><?php echo isset($o['script_head_code']) ? esc_textarea($o['script_head_code']) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('script_body_start')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('script_body_start_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[script_body_start_enable]" value="1" <?php checked(!empty($o['script_body_start_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('script_body_start_desc')); ?></span>
                                    </label>
                                <br>
                                <textarea name="<?php echo esc_attr($this->option_name); ?>[script_body_start_code]" rows="8" class="wpc-code-textarea"><?php echo isset($o['script_body_start_code']) ? esc_textarea($o['script_body_start_code']) : ''; ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label><?php echo esc_html($this->t('google_maps_api_key')); ?></label>
                            </th>
                            <td>
                                <input type="text" name="<?php echo esc_attr($this->option_name); ?>[google_maps_api_key]" value="<?php echo isset($o['google_maps_api_key']) ? esc_attr($o['google_maps_api_key']) : ''; ?>" style="width: 100%; max-width: 500px; padding: 6px 10px; font-size: 14px; display: block;">
                                <p class="description" style="margin-top: 8px; clear: both;"><?php echo esc_html($this->t('google_maps_api_key_desc')); ?> <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank"><?php echo esc_html($this->t('google_maps_api_key_link')); ?></a>.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('custom_functions')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('custom_functions_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[custom_functions_enable]" value="1" <?php checked(!empty($o['custom_functions_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('custom_functions_desc')); ?></span>
                                    </label>
                                <br>
                                <div class="wpc-toolbar">
                                    <div class="wpc-search-wrapper">
                                        <button type="button" class="wpc-search-toggle" id="php-search-toggle">
                                            <svg class="wpc-icon-search" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                                <circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.5"/>
                                                <path d="M12 12L16 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                            <svg class="wpc-icon-close" width="14" height="14" viewBox="0 0 14 14" fill="none" style="display:none;">
                                                <path d="M2 2L12 12M12 2L2 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                            </svg>
                                        </button>
                                        <div class="wpc-search-panel" id="php-search-panel" style="display:none;">
                                            <input type="text" class="wpc-search-field" id="php-search-field" placeholder="<?php echo esc_attr($this->t('search_placeholder')); ?>">
                                            <span class="wpc-search-count" id="php-search-count">0/0</span>
                                            <button type="button" class="wpc-search-up" id="php-search-up">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                    <path d="M7 11V3M7 3L3 7M7 3L11 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                            <button type="button" class="wpc-search-down" id="php-search-down">
                                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                    <path d="M7 3V11M7 11L11 7M7 11L3 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                    <select id="php_editor_theme" name="<?php echo esc_attr($this->option_name); ?>[php_editor_theme]" class="wpc-theme-select">
                                            <?php $php_theme = isset($o['php_editor_theme']) && !empty($o['php_editor_theme']) ? $o['php_editor_theme'] : 'material-darker'; ?>
                                            <option value="material-darker" <?php selected($php_theme, 'material-darker'); ?>>Material Darker</option>
                                            <option value="material"        <?php selected($php_theme, 'material'); ?>>Material</option>
                                            <option value="dracula"         <?php selected($php_theme, 'dracula'); ?>>Dracula</option>
                                            <option value="monokai"         <?php selected($php_theme, 'monokai'); ?>>Monokai</option>
                                            <option value="nord"            <?php selected($php_theme, 'nord'); ?>>Nord</option>
                                            <option value="default"         <?php selected($php_theme, 'default'); ?>>Světlý</option>
                                        </select>
                                </div>
                                <textarea id="custom_functions_editor" name="<?php echo esc_attr($this->option_name); ?>[custom_functions_code]" rows="12" class="wpc-code-textarea"><?php echo isset($o['custom_functions_code']) ? esc_textarea($o['custom_functions_code']) : ''; ?></textarea>
                                <p class="description" style="margin-top: 8px;"><?php echo wp_kses_post($this->t('custom_functions_info')); ?></p>
                                <p class="description" style="margin-top:6px;color:#b32d2e;">
                                    <span class="dashicons dashicons-lock" style="font-size:14px;vertical-align:middle;"></span>
                                    <?php esc_html_e('This feature executes PHP code stored in the database. Access is restricted to administrators with the manage_options capability. Never paste code from untrusted sources.', 'wp-admin-studio'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- MAINTENANCE MODE -->
                <div class="wpc-section" id="section-maintenance">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php echo esc_html($this->t('maintenance_mode')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('maintenance_enable')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('maintenance_enable_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_enable]" value="1" <?php checked(!empty($o['maintenance_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('maintenance_enable_desc')); ?></span>
                                    </label>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_show_logged]" value="1" <?php checked(!empty($o['maintenance_show_logged'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('maintenance_show_logged_desc')); ?></span>
                                    </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('maintenance_mode_type')); ?></th>
                            <td>
                                <label class="wpc-maintenance-radio">
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[maintenance_mode_type]" value="simple" <?php checked(empty($o['maintenance_mode_type']) || $o['maintenance_mode_type'] === 'simple'); ?> onclick="document.getElementById('maintenance-simple-mode').style.display='block'; document.getElementById('maintenance-advanced-mode').style.display='none';">
                                    <span><?php echo esc_html($this->t('maintenance_mode_simple')); ?></span>
                                </label>
                                <label class="wpc-maintenance-radio">
                                    <input type="radio" name="<?php echo esc_attr($this->option_name); ?>[maintenance_mode_type]" value="advanced" <?php checked(!empty($o['maintenance_mode_type']) && $o['maintenance_mode_type'] === 'advanced'); ?> onclick="document.getElementById('maintenance-simple-mode').style.display='none'; document.getElementById('maintenance-advanced-mode').style.display='block';">
                                    <span><?php echo esc_html($this->t('maintenance_mode_advanced')); ?></span>
                                </label>
                                
                                <!-- Simple Mode Fields -->
                                <div id="maintenance-simple-mode" style="margin-top: 20px; <?php echo (!empty($o['maintenance_mode_type']) && $o['maintenance_mode_type'] === 'advanced') ? 'display:none;' : ''; ?>">
                                    <!-- Logo/Image -->
                                    <div style="margin-bottom: 15px;">
                                        <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('maintenance_image')); ?></label>
                                        <div>
                                            <?php if (!empty($o['maintenance_image'])): ?>
                                                <img src="<?php echo esc_url($o['maintenance_image']); ?>" style="<?php echo !empty($o['maintenance_image_max_width']) ? 'width: 100%; max-width: ' . intval($o['maintenance_image_max_width']) . 'px;' : 'max-width: 100%;'; ?> display: block; margin-bottom: 10px; border: 1px solid #c3c4c7; padding: 5px; height: auto;" id="maintenance_image_preview">
                                            <?php endif; ?>
                                            <input type="hidden" id="maintenance_image" name="<?php echo esc_attr($this->option_name); ?>[maintenance_image]" value="<?php echo esc_attr(!empty($o['maintenance_image']) ? $o['maintenance_image'] : ''); ?>">
                                            <button type="button" class="button" id="maintenance_image_upload"><?php echo esc_html($this->t('maintenance_image_upload')); ?></button>
                                            <button type="button" class="button" id="maintenance_image_remove" style="<?php echo empty($o['maintenance_image']) ? 'display:none;' : ''; ?>"><?php echo esc_html($this->t('maintenance_image_remove')); ?></button>
                                            <div style="margin-top: 10px;">
                                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; white-space: nowrap;"><?php echo esc_html($this->t('maintenance_image_max_width')); ?></label>
                                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[maintenance_image_max_width]" value="<?php echo esc_attr(!empty($o['maintenance_image_max_width']) ? $o['maintenance_image_max_width'] : ''); ?>" style="width: 100px; padding: 4px 8px; font-size: 13px;"> px
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Heading -->
                                    <div style="margin-bottom: 15px;">
                                        <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('maintenance_heading')); ?></label>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[maintenance_heading]" value="<?php echo esc_attr(!empty($o['maintenance_heading']) ? $o['maintenance_heading'] : ''); ?>" style="width: 100%; max-width: 500px; padding: 6px 10px; font-size: 14px;">
                                    </div>
                                    
                                    <!-- Message -->
                                    <div style="margin-bottom: 15px;">
                                        <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('maintenance_message')); ?></label>
                                        <textarea name="<?php echo esc_attr($this->option_name); ?>[maintenance_message]" rows="4" style="width: 100%; max-width: 500px; padding: 6px 10px; font-size: 14px;"><?php echo esc_textarea(!empty($o['maintenance_message']) ? $o['maintenance_message'] : ''); ?></textarea>
                                    </div>
                                    
                                    <!-- Colors - Background and Text -->
                                    <div style="margin-bottom: 15px;">
                                        <div style="margin-bottom: 10px;">
                                            <label class="wpc-maintenance-field-label" style="display: block; margin-bottom: 5px; white-space: nowrap;"><?php echo esc_html($this->t('maintenance_bg_color')); ?></label>
                                            <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[maintenance_bg_color]" value="<?php echo esc_attr(!empty($o['maintenance_bg_color']) ? $o['maintenance_bg_color'] : '#ffffff'); ?>" />
                                        </div>
                                        <div>
                                            <label class="wpc-maintenance-field-label" style="display: block; margin-bottom: 5px;"><?php echo esc_html($this->t('maintenance_text_color')); ?></label>
                                            <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[maintenance_text_color]" value="<?php echo esc_attr(!empty($o['maintenance_text_color']) ? $o['maintenance_text_color'] : '#000000'); ?>" />
                                        </div>
                                    </div>
                                    
                                    <!-- Button -->
                                    <div style="margin-bottom: 15px;">
                                        <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('maintenance_button_text')); ?></label>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[maintenance_button_text]" value="<?php echo esc_attr(!empty($o['maintenance_button_text']) ? $o['maintenance_button_text'] : ''); ?>" style="width: 100%; max-width: 500px; padding: 6px 10px; font-size: 14px; display: block; margin-bottom: 8px;">
                                        <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px; margin-top: 10px;"><?php echo esc_html($this->t('maintenance_button_url')); ?></label>
                                        <input type="text" name="<?php echo esc_attr($this->option_name); ?>[maintenance_button_url]" value="<?php echo esc_attr(!empty($o['maintenance_button_url']) ? $o['maintenance_button_url'] : ''); ?>" style="width: 100%; max-width: 500px; padding: 6px 10px; font-size: 14px; display: block; margin-bottom: 10px;">
                                        <label>
                                            <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[maintenance_button_show]" value="1" <?php checked(!empty($o['maintenance_button_show'])); ?>>
                                            <span><?php echo esc_html($this->t('maintenance_button_show')); ?></span>
                                        </label>
                                        
                                        <!-- Button Colors and Radius -->
                                        <div style="margin-top: 15px;">
                                            <div style="margin-bottom: 10px;">
                                                <label class="wpc-maintenance-field-label" style="display: block; margin-bottom: 5px; white-space: nowrap;"><?php echo esc_html($this->t('maintenance_button_bg_color')); ?></label>
                                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[maintenance_button_bg_color]" value="<?php echo esc_attr(!empty($o['maintenance_button_bg_color']) ? $o['maintenance_button_bg_color'] : '#000000'); ?>" />
                                            </div>
                                            <div style="margin-bottom: 10px;">
                                                <label class="wpc-maintenance-field-label" style="display: block; margin-bottom: 5px; white-space: nowrap;"><?php echo esc_html($this->t('maintenance_button_text_color')); ?></label>
                                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[maintenance_button_text_color]" value="<?php echo esc_attr(!empty($o['maintenance_button_text_color']) ? $o['maintenance_button_text_color'] : '#ffffff'); ?>" />
                                            </div>
                                            <div>
                                                <label class="wpc-maintenance-field-label" style="display: block; margin-bottom: 5px; white-space: nowrap;"><?php echo esc_html($this->t('maintenance_button_radius')); ?></label>
                                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[maintenance_button_radius]" value="<?php echo esc_attr(!empty($o['maintenance_button_radius']) ? $o['maintenance_button_radius'] : '12'); ?>" min="0" max="50" style="width: 80px; padding: 6px 10px; font-size: 14px;"> px
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Advanced Mode Fields -->
                                <div id="maintenance-advanced-mode" style="margin-top: 20px; <?php echo (!empty($o['maintenance_mode_type']) && $o['maintenance_mode_type'] === 'advanced') ? '' : 'display:none;'; ?>">
                                    <p style="background:#f0f6fc;border-left:4px solid #72aee6;padding:12px 15px;margin:0 0 15px 0;">
                                        <?php echo wp_kses_post($this->t('maintenance_html_tip')); ?>
                                    </p>
                                    <div class="wpc-toolbar">
                                        <div class="wpc-search-wrapper">
                                            <button type="button" class="wpc-search-toggle" id="maintenance-search-toggle">
                                                <svg class="wpc-icon-search" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                                    <circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.5"/>
                                                    <path d="M12 12L16 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                                <svg class="wpc-icon-close" width="14" height="14" viewBox="0 0 14 14" fill="none" style="display:none;">
                                                    <path d="M2 2L12 12M12 2L2 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                </svg>
                                            </button>
                                            <div class="wpc-search-panel" id="maintenance-search-panel" style="display:none;">
                                                <input type="text" class="wpc-search-field" id="maintenance-search-field" placeholder="<?php echo esc_attr($this->t('search_placeholder')); ?>">
                                                <span class="wpc-search-count" id="maintenance-search-count">0/0</span>
                                                <button type="button" class="wpc-search-up" id="maintenance-search-up">
                                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                        <path d="M7 11V3M7 3L3 7M7 3L11 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </button>
                                                <button type="button" class="wpc-search-down" id="maintenance-search-down">
                                                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                        <path d="M7 3V11M7 11L11 7M7 11L3 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                        <select id="maintenance_html_editor_theme" name="<?php echo esc_attr($this->option_name); ?>[maintenance_html_editor_theme]" class="wpc-theme-select">
                                            <?php $current_theme = isset($o['maintenance_html_editor_theme']) && !empty($o['maintenance_html_editor_theme']) ? $o['maintenance_html_editor_theme'] : 'material-darker'; ?>
                                            <option value="material-darker" <?php selected($current_theme, 'material-darker'); ?>>Material Darker</option>
                                            <option value="material" <?php selected($current_theme, 'material'); ?>>Material</option>
                                            <option value="dracula" <?php selected($current_theme, 'dracula'); ?>>Dracula</option>
                                            <option value="monokai" <?php selected($current_theme, 'monokai'); ?>>Monokai</option>
                                            <option value="nord" <?php selected($current_theme, 'nord'); ?>>Nord</option>
                                            <option value="default" <?php selected($current_theme, 'default'); ?>>Světlý</option>
                                        </select>
                                    </div>
                                    <textarea id="maintenance_html_editor" name="<?php echo esc_attr($this->option_name); ?>[maintenance_html_code]" rows="20" class="wpc-code-textarea"><?php echo isset($o['maintenance_html_code']) ? esc_textarea($o['maintenance_html_code']) : esc_textarea($this->get_default_maintenance_html()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></textarea>
                                    <p style="margin-top:10px;">
                                        <button type="submit" form="wpc-main-form" class="button button-primary">
                                            <?php echo esc_html($this->t('save')); ?>
                                        </button>
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- LOGIN PAGE -->
                <div class="wpc-section" id="section-login">
                    <h2><span class="dashicons dashicons-lock"></span> <?php echo esc_html($this->t('login_page')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('login_customize')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('login_customize_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[login_customize]" value="1" <?php checked(!empty($o['login_customize'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('login_customize_desc')); ?></span>
                                    </label>
                                
                                <div style="margin-top: 20px;">
                                    <!-- Logo -->
                                    <div style="margin-bottom: 20px;">
                                        <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('login_logo')); ?></label>
                                        <div>
                                            <?php if (!empty($o['login_logo'])): ?>
                                                <img src="<?php echo esc_url($o['login_logo']); ?>" style="<?php echo !empty($o['login_logo_height']) ? 'height: ' . intval($o['login_logo_height']) . 'px; width: auto;' : 'max-height: 100px; width: auto;'; ?> display: block; margin-bottom: 10px; border: 1px solid #c3c4c7; padding: 5px;" id="login_logo_preview">
                                            <?php endif; ?>
                                            <input type="hidden" id="login_logo" name="<?php echo esc_attr($this->option_name); ?>[login_logo]" value="<?php echo esc_attr(!empty($o['login_logo']) ? $o['login_logo'] : ''); ?>">
                                            <button type="button" class="button" id="login_logo_upload"><?php echo esc_html($this->t('login_logo_upload')); ?></button>
                                            <button type="button" class="button" id="login_logo_remove" style="<?php echo empty($o['login_logo']) ? 'display:none;' : ''; ?>"><?php echo esc_html($this->t('login_logo_remove')); ?></button>
                                            <div style="margin-top: 10px;">
                                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; white-space: nowrap;"><?php echo esc_html($this->t('login_logo_height')); ?></label>
                                                <input type="number" name="<?php echo esc_attr($this->option_name); ?>[login_logo_height]" value="<?php echo esc_attr(!empty($o['login_logo_height']) ? $o['login_logo_height'] : ''); ?>" min="50" style="width: 100px; padding: 4px 8px; font-size: 13px;"> px
                                            </div>
                                            <div style="margin-top: 10px;">
                                                <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; white-space: nowrap;"><?php echo esc_html($this->t('login_logo_url')); ?></label>
                                                <input type="text" name="<?php echo esc_attr($this->option_name); ?>[login_logo_url]" value="<?php echo esc_attr(!empty($o['login_logo_url']) ? $o['login_logo_url'] : ''); ?>" style="width: 100%; max-width: 500px; padding: 6px 10px; font-size: 14px;">
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Background -->
                                    <div style="margin-bottom: 20px;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_bg_color')); ?></h4>
                                        <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_bg_color]" value="<?php echo esc_attr(!empty($o['login_bg_color']) ? $o['login_bg_color'] : '#eef2f0'); ?>" />
                                        
                                        <div style="margin-top: 15px;">
                                            <label class="wpc-maintenance-field-label" style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('login_bg_image')); ?></label>
                                            <?php if (!empty($o['login_bg_image'])): ?>
                                                <img src="<?php echo esc_url($o['login_bg_image']); ?>" style="max-width: 200px; display: block; margin-bottom: 10px; border: 1px solid #c3c4c7; padding: 5px; height: auto;" id="login_bg_image_preview">
                                            <?php endif; ?>
                                            <input type="hidden" id="login_bg_image" name="<?php echo esc_attr($this->option_name); ?>[login_bg_image]" value="<?php echo esc_attr(!empty($o['login_bg_image']) ? $o['login_bg_image'] : ''); ?>">
                                            <button type="button" class="button" id="login_bg_image_upload"><?php echo esc_html($this->t('login_bg_image_upload')); ?></button>
                                            <button type="button" class="button" id="login_bg_image_remove" style="<?php echo empty($o['login_bg_image']) ? 'display:none;' : ''; ?>"><?php echo esc_html($this->t('login_bg_image_remove')); ?></button>
                                        </div>
                                        
                                        <div style="margin-top: 10px;">
                                            <label style="display: block; font-weight: 600; margin-bottom: 5px; font-size: 13px; white-space: nowrap;"><?php echo esc_html($this->t('login_bg_size')); ?></label>
                                            <select name="<?php echo esc_attr($this->option_name); ?>[login_bg_size]" style="width: 250px;">
                                                <option value="cover" <?php selected(!empty($o['login_bg_size']) ? $o['login_bg_size'] : 'cover', 'cover'); ?>><?php echo esc_html($this->t('login_bg_size_cover')); ?></option>
                                                <option value="contain" <?php selected(!empty($o['login_bg_size']) ? $o['login_bg_size'] : 'cover', 'contain'); ?>><?php echo esc_html($this->t('login_bg_size_contain')); ?></option>
                                                <option value="repeat" <?php selected(!empty($o['login_bg_size']) ? $o['login_bg_size'] : 'cover', 'repeat'); ?>><?php echo esc_html($this->t('login_bg_size_repeat')); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <!-- Colors -->
                                    <div style="margin-bottom: 20px;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_primary_color')); ?></h4>
                                        <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_primary_color]" value="<?php echo esc_attr(!empty($o['login_primary_color']) ? $o['login_primary_color'] : '#0000ff'); ?>" />
                                    </div>
                                    
                                    <!-- Form Radius -->
                                    <div style="margin-bottom: 20px;">
                                        <label style="display: block; font-weight: 600; margin-bottom: 5px; white-space: nowrap;"><?php echo esc_html($this->t('login_form_radius')); ?></label>
                                        <input type="number" name="<?php echo esc_attr($this->option_name); ?>[login_form_radius]" value="<?php echo esc_attr(isset($o['login_form_radius']) ? $o['login_form_radius'] : '8'); ?>" min="0" max="50" style="width: 80px; padding: 6px 10px; font-size: 14px;"> px
                                    </div>
                                    
                                    <!-- Form colors -->
                                    <div style="margin-bottom: 20px;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_form_bg_color')); ?></h4>
                                        <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_form_bg_color]" value="<?php echo esc_attr(!empty($o['login_form_bg_color']) ? $o['login_form_bg_color'] : '#ffffff'); ?>" />
                                        
                                        <div style="margin-top: 10px;">
                                            <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_form_text_color')); ?></h4>
                                            <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_form_text_color]" value="<?php echo esc_attr(!empty($o['login_form_text_color']) ? $o['login_form_text_color'] : '#3c434a'); ?>" />
                                        </div>
                                    </div>
                                    
                                    <!-- Button -->
                                    <div style="margin-bottom: 20px;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_button_bg')); ?></h4>
                                        <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_button_bg]" value="<?php echo esc_attr(!empty($o['login_button_bg']) ? $o['login_button_bg'] : '#0000ff'); ?>" />
                                        
                                        <div style="margin-top: 10px;">
                                            <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_button_text_color')); ?></h4>
                                            <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_button_text_color]" value="<?php echo esc_attr(!empty($o['login_button_text_color']) ? $o['login_button_text_color'] : '#ffffff'); ?>" />
                                        </div>
                                        
                                        <div style="margin-top: 10px;">
                                            <label style="display: block; font-weight: 600; margin-bottom: 5px; white-space: nowrap;"><?php echo esc_html($this->t('login_button_radius')); ?></label>
                                            <input type="number" name="<?php echo esc_attr($this->option_name); ?>[login_button_radius]" value="<?php echo esc_attr(isset($o['login_button_radius']) ? $o['login_button_radius'] : '4'); ?>" min="0" max="50" style="width: 80px; padding: 6px 10px; font-size: 14px;"> px
                                        </div>
                                    </div>
                                    
                                    <!-- Links -->
                                    <div style="margin-bottom: 20px;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 14px; white-space: nowrap;"><?php echo esc_html($this->t('login_links_color')); ?></h4>
                                        <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[login_links_color]" value="<?php echo esc_attr(!empty($o['login_links_color']) ? $o['login_links_color'] : '#0000ff'); ?>" />
                                        
                                        <div style="margin-top: 15px;">
                                            <label class="wpc-toggle-label">
                                                <span class="wpc-toggle-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_login_switcher]" value="1" <?php checked(!empty($o['disable_login_switcher'])); ?>>
                                                    <span class="wpc-toggle-slider"></span>
                                                </span>
                                                <span class="wpc-toggle-text"><?php echo esc_html($this->t('login_lang_desc')); ?></span>
                                                </label>
                                            <br>
                                            <label class="wpc-toggle-label">
                                                <span class="wpc-toggle-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[login_hide_lostpassword]" value="1" <?php checked(!empty($o['login_hide_lostpassword'])); ?>>
                                                    <span class="wpc-toggle-slider"></span>
                                                </span>
                                                <span class="wpc-toggle-text"><?php echo esc_html($this->t('login_hide_lostpassword')); ?></span>
                                                </label>
                                            <br>
                                            <label class="wpc-toggle-label">
                                                <span class="wpc-toggle-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[login_hide_backtoblog]" value="1" <?php checked(!empty($o['login_hide_backtoblog'])); ?>>
                                                    <span class="wpc-toggle-slider"></span>
                                                </span>
                                                <span class="wpc-toggle-text"><?php echo esc_html($this->t('login_hide_backtoblog')); ?></span>
                                                </label>
                                            <br>
                                            <label class="wpc-toggle-label">
                                                <span class="wpc-toggle-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[login_hide_rememberme]" value="1" <?php checked(!empty($o['login_hide_rememberme'])); ?>>
                                                    <span class="wpc-toggle-slider"></span>
                                                </span>
                                                <span class="wpc-toggle-text"><?php echo esc_html($this->t('login_hide_rememberme')); ?></span>
                                                </label>
                                            <br>
                                            <label class="wpc-toggle-label">
                                                <span class="wpc-toggle-switch">
                                                    <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[login_hide_privacy]" value="1" <?php checked(!empty($o['login_hide_privacy'])); ?>>
                                                    <span class="wpc-toggle-slider"></span>
                                                </span>
                                                <span class="wpc-toggle-text"><?php echo esc_html($this->t('login_hide_privacy')); ?></span>
                                                </label>
                                        </div>
                                    </div>
                                    
                                    <!-- Custom CSS -->
                                    <div style="margin-bottom: 20px;">
                                        <label style="display: block; font-weight: 600; margin-bottom: 5px;"><?php echo esc_html($this->t('login_custom_css')); ?></label>
                                        <div class="wpc-toolbar">
                                            <div class="wpc-search-wrapper">
                                                <button type="button" class="wpc-search-toggle" id="login-css-search-toggle">
                                                    <svg class="wpc-icon-search" width="18" height="18" viewBox="0 0 18 18" fill="none">
                                                        <circle cx="7.5" cy="7.5" r="6" stroke="currentColor" stroke-width="1.5"/>
                                                        <path d="M12 12L16 16" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                    <svg class="wpc-icon-close" width="14" height="14" viewBox="0 0 14 14" fill="none" style="display:none;">
                                                        <path d="M2 2L12 12M12 2L2 12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                                    </svg>
                                                </button>
                                                <div class="wpc-search-panel" id="login-css-search-panel" style="display:none;">
                                                    <input type="text" class="wpc-search-field" id="login-css-search-field" placeholder="<?php echo esc_attr($this->t('search_placeholder')); ?>">
                                                    <span class="wpc-search-count" id="login-css-search-count">0/0</span>
                                                    <button type="button" class="wpc-search-up" id="login-css-search-up">
                                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                            <path d="M7 11V3M7 3L3 7M7 3L11 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                    <button type="button" class="wpc-search-down" id="login-css-search-down">
                                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                                            <path d="M7 3V11M7 11L11 7M7 11L3 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                                        </svg>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <textarea id="login_custom_css_editor" name="<?php echo esc_attr($this->option_name); ?>[login_custom_css]" rows="8" class="wpc-code-textarea"><?php echo isset($o['login_custom_css']) ? esc_textarea($o['login_custom_css']) : ''; ?></textarea>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- ROBOTS.TXT EDITOR -->
                <div class="wpc-section" id="section-robots">
                    <h2><span class="dashicons dashicons-text-page"></span> <?php echo esc_html($this->t('robots_editor')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('robots_enable')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('robots_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[robots_enable]" value="1" <?php checked(!empty($o['robots_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('robots_enable_desc')); ?></span>
                                    </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('robots_content')); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr($this->option_name); ?>[robots_content]" rows="12" class="wpc-code-textarea"><?php echo isset($o['robots_content']) ? esc_textarea($o['robots_content']) : ''; ?></textarea>
                                <p class="description" style="margin-top:8px;"><?php echo wp_kses_post($this->t('robots_info')); ?></p>
                                <p style="margin-top:10px;">
                                    <button type="submit" form="wpc-main-form" class="button button-primary">
                                        <?php echo esc_html($this->t('save')); ?>
                                    </button>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- .HTACCESS EDITOR -->
                <div class="wpc-section" id="section-htaccess">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php echo esc_html($this->t('htaccess_editor')); ?></h2>
                    <p style="background:#fff3cd;border-left:4px solid #ffc107;padding:12px 15px;margin:0 0 20px 0;">
                        <strong><?php echo wp_kses_post($this->t('htaccess_warning')); ?></strong>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th>
                                <?php echo esc_html($this->t('htaccess_enable')); ?>
                                <span class="wpc-tip">
                                    <span class="dashicons dashicons-editor-help"></span>
                                    <div class="wpc-tooltip">
                                        <button type="button" class="wpc-tooltip-close"></button>
                                        <?php echo esc_html($this->t('htaccess_tip')); ?>
                                    </div>
                                </span>
                            </th>
                            <td>
                                <label class="wpc-toggle-label">
                                    <span class="wpc-toggle-switch">
                                        <input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[htaccess_enable]" value="1" <?php checked(!empty($o['htaccess_enable'])); ?>>
                                        <span class="wpc-toggle-slider"></span>
                                    </span>
                                    <span class="wpc-toggle-text"><?php echo esc_html($this->t('htaccess_enable_desc')); ?></span>
                                    </label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('htaccess_content')); ?></th>
                            <td>
                                <textarea name="<?php echo esc_attr($this->option_name); ?>[htaccess_content]" rows="12" class="wpc-code-textarea"><?php echo isset($o['htaccess_content']) ? esc_textarea($o['htaccess_content']) : ''; ?></textarea>
                                <p class="description" style="margin-top:8px;"><?php echo wp_kses_post($this->t('htaccess_info')); ?></p>
                                <p style="margin-top:10px;">
                                    <button type="submit" form="wpc-main-form" class="button button-primary">
                                        <?php echo esc_html($this->t('save')); ?>
                                    </button>
                                    <button type="button" id="wpc-restore-htaccess" class="button" style="margin-left:10px;">
                                        <span class="dashicons dashicons-backup" style="vertical-align:middle;margin-top:-2px;"></span>
                                        <?php echo esc_html($this->t('htaccess_restore_button')); ?>
                                    </button>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- EDITOR & POSTS -->
                <div class="wpc-section" id="section-editor">
                    <h2><span class="dashicons dashicons-edit"></span> <?php echo esc_html($this->t('editor_section')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label><?php echo esc_html($this->t('disable_gutenberg')); ?> <?php echo $this->tip($this->t('disable_gutenberg_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_gutenberg]" value="1" <?php checked(1, !empty($o['disable_gutenberg'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('disable_gutenberg_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('duplicate_posts')); ?> <?php echo $this->tip($this->t('duplicate_posts_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[duplicate_posts]" value="1" <?php checked(1, !empty($o['duplicate_posts'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('duplicate_posts_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('enable_svg_upload')); ?> <?php echo $this->tip($this->t('enable_svg_upload_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_svg_upload]" value="1" <?php checked(1, !empty($o['enable_svg_upload'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('enable_svg_upload_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('enable_media_replace')); ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_media_replace]" value="1" <?php checked(1, !empty($o['enable_media_replace'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('enable_media_replace_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th><label><?php echo esc_html($this->t('post_colors')); ?> <?php echo $this->tip($this->t('post_colors_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[post_colors]" value="1" <?php checked(1, !empty($o['post_colors'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('post_colors_desc')); ?></span></label></td>
                        </tr>
                    </table>
                    
                    <h3><?php echo esc_html($this->t('custom_colors')); ?></h3>
                    <p class="description"><?php echo esc_html($this->t('color_note')); ?></p>
                    <table class="wpc-color-table">
                        <tr>
                            <th><?php echo esc_html($this->t('color_draft')); ?></th>
                            <td>
                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[color_draft]" value="<?php echo esc_attr($o['color_draft']); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('color_pending')); ?></th>
                            <td>
                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[color_pending]" value="<?php echo esc_attr($o['color_pending']); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('color_publish')); ?></th>
                            <td>
                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[color_publish]" value="<?php echo esc_attr($o['color_publish']); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('color_future')); ?></th>
                            <td>
                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[color_future]" value="<?php echo esc_attr($o['color_future']); ?>" />
                            </td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('color_private')); ?></th>
                            <td>
                                <input type="text" class="wpc-color-picker" name="<?php echo esc_attr($this->option_name); ?>[color_private]" value="<?php echo esc_attr($o['color_private']); ?>" />
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- FRONTEND & PERFORMANCE -->
                <div class="wpc-section" id="section-frontend">
                    <h2><span class="dashicons dashicons-admin-appearance"></span> <?php echo esc_html($this->t('frontend')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label><?php echo esc_html($this->t('edit_link')); ?> <?php echo $this->tip($this->t('edit_link_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[edit_link]" value="1" <?php checked(1, !empty($o['edit_link'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('edit_link_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('archive_titles')); ?>
                                    <?php echo $this->tip($this->t('archive_titles_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[archive_titles]" value="1" <?php checked(1, !empty($o['archive_titles'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('archive_titles_desc')); ?></span></label>
                                <p><small>
                                    <span class="wpc-prefix-label"><?php echo esc_html($this->t('category_prefix')); ?></span><br>
                                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[archive_category_prefix]" value="<?php echo esc_attr($o['archive_category_prefix']); ?>" class="regular-text" placeholder="<?php echo esc_attr($this->t('category_prefix_placeholder')); ?>">
                                </small></p>
                                <p><small>
                                    <span class="wpc-prefix-label"><?php echo esc_html($this->t('tag_prefix')); ?></span><br>
                                    <input type="text" name="<?php echo esc_attr($this->option_name); ?>[archive_tag_prefix]" value="<?php echo esc_attr($o['archive_tag_prefix']); ?>" class="regular-text" placeholder="<?php echo esc_attr($this->t('tag_prefix_placeholder')); ?>">
                                </small></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('year_shortcode')); ?>
                                    <?php echo $this->tip($this->t('year_shortcode_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[year_shortcode]" value="1" <?php checked(1, !empty($o['year_shortcode'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('year_shortcode_desc')); ?></span></label>
                                <p class="description"><?php echo esc_html($this->t('year_example')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('responsive_images')); ?>
                                    <?php echo $this->tip($this->t('responsive_images_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[responsive_images]" value="1" <?php checked(1, !empty($o['responsive_images'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('responsive_images_desc')); ?></span></label>
                                <p class="description"><?php echo esc_html($this->t('responsive_images_note')); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('disable_big_image_threshold')); ?>
                                    <?php echo $this->tip($this->t('disable_big_image_threshold_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_big_image_threshold]" value="1" <?php checked(1, !empty($o['disable_big_image_threshold'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('disable_big_image_threshold_desc')); ?></span></label>
                                <p class="description"><?php echo esc_html($this->t('disable_big_image_threshold_note')); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- COMMENTS -->
                <div class="wpc-section" id="section-comments">
                    <h2><span class="dashicons dashicons-admin-comments"></span> <?php echo esc_html($this->t('comments')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('disable_comments_completely')); ?> 
                                    <?php echo $this->tip($this->t('disable_comments_completely_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[disable_comments_completely]" value="1" <?php checked(1, !empty($o['disable_comments_completely'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('disable_comments_completely_desc')); ?></span></label></td>
                        </tr>
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('comment_url')); ?> 
                                    <?php echo $this->tip($this->t('comment_url_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td><label class="wpc-toggle-label"><span class="wpc-toggle-switch"><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[remove_comment_url]" value="1" <?php checked(1, !empty($o['remove_comment_url'])); ?>><span class="wpc-toggle-slider"></span></span><span class="wpc-toggle-text"><?php echo esc_html($this->t('comment_url_desc')); ?></span></label></td>
                        </tr>
                    </table>
                </div>
                
                <!-- FORMS -->
                <div class="wpc-section" id="section-forms">
                    <h2><span class="dashicons dashicons-feedback"></span> <?php echo esc_html($this->t('forms')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th>
                                <label>
                                    <?php echo esc_html($this->t('wpforms_countries')); ?>
                                    <?php echo $this->tip($this->t('wpforms_countries_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                $detected_plugins = array();
                                if (function_exists('wpforms') || class_exists('WPForms') || class_exists('WPForms_Lite')) $detected_plugins[] = 'WPForms';
                                if (defined('SRFM_VER') || class_exists('SureForms\Inc\SureForms') || function_exists('sureforms_render_form')) $detected_plugins[] = 'SureForms';
                                if (function_exists('wpFluentForm') || class_exists('FluentForm\App\Http\Controllers\FormController')) $detected_plugins[] = 'Fluent Forms';
                                if (class_exists('GFForms')) $detected_plugins[] = 'Gravity Forms';
                                if (class_exists('Forminator')) $detected_plugins[] = 'Forminator';
                                $current_restrict = isset($o['restrict_wpforms_countries']) ? $o['restrict_wpforms_countries'] : '';
                                $no_plugin = empty($detected_plugins);
                                ?>
                                <div class="wpc-wpforms-label">
                                    <select name="<?php echo esc_attr($this->option_name); ?>[restrict_wpforms_countries]" style="min-width:160px;<?php echo $no_plugin ? ' opacity:.45; pointer-events:none;' : ''; ?>"<?php echo $no_plugin ? ' disabled' : ''; ?>>
                                        <option value="" <?php selected($current_restrict, ''); ?>><?php echo esc_html($this->t('phone_restrict_off')); ?></option>
                                        <option value="czsk" <?php selected($current_restrict, 'czsk'); ?>><?php echo esc_html($this->t('phone_restrict_czsk')); ?></option>
                                        <option value="europe" <?php selected($current_restrict, 'europe'); ?>><?php echo esc_html($this->t('phone_restrict_europe')); ?></option>
                                        <option value="us" <?php selected($current_restrict, 'us'); ?>><?php echo esc_html($this->t('phone_restrict_us')); ?></option>
                                    </select>
                                    <?php if (!$no_plugin): ?>
                                        <?php foreach ($detected_plugins as $plugin_name): ?>
                                            <span class="wpc-badge wpc-badge-active">
                                                <svg viewBox="0 0 20 20" fill="currentColor" xmlns="http://www.w3.org/2000/svg"><path d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0z"/></svg>
                                                <?php echo esc_html($plugin_name); ?>
                                            </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="wpc-badge wpc-badge-notice"><?php echo esc_html($this->t('no_form_plugin')); ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- TRANSLATIONS -->
                <div class="wpc-section" id="section-translations">
                    <h2><span class="dashicons dashicons-translation"></span> <?php echo esc_html($this->t('translations')); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th><label><?php echo esc_html($this->t('enable_trans')); ?> <?php echo $this->tip($this->t('enable_trans_tip')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr($this->option_name); ?>[enable_translations]" value="1" <?php checked(1, !empty($o['enable_translations'])); ?>> <?php echo esc_html($this->t('enable_trans_desc')); ?></label></td>
                        </tr>
                    </table>
                    <h3><?php echo esc_html($this->t('trans_defs')); ?></h3>
                    <p style="background:#f0f6fc;border-left:4px solid #72aee6;padding:12px 15px;margin:0 0 15px 0;"><?php echo wp_kses_post($this->t('trans_note')); ?></p>
                    <div id="translation-rows" class="wpc-translations">
                        <?php foreach ($o['translations'] as $i => $t): ?>
                        <div class="translation-row">
                            <span class="translation-number translation-drag-handle" title="Přetáhněte pro změnu pořadí"><span class="dashicons dashicons-menu"></span><?php echo absint($i + 1); ?>.</span>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[translations][<?php echo absint($i); ?>][from]" placeholder="<?php echo esc_attr($this->t('from')); ?>" rows="1" class="translation-from"><?php echo esc_attr($t['from']); ?></textarea>
                            <textarea name="<?php echo esc_attr($this->option_name); ?>[translations][<?php echo absint($i); ?>][to]" placeholder="" title="<?php echo esc_attr($this->t('trans_html_allowed')); ?>" rows="1"><?php echo isset($t['to']) ? esc_textarea($t['to']) : ''; ?></textarea>
                            <button type="button" class="button remove-translation" title="<?php echo esc_attr($this->t('remove')); ?>"><span class="dashicons dashicons-no"></span></button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="wpc-translation-buttons" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <button type="submit" form="wpc-main-form" class="button button-primary">
                            <?php echo esc_html($this->t('save')); ?>
                        </button>
                        <button type="button" id="add-translation" class="button"><?php echo esc_html($this->t('add_trans')); ?></button>
                        <button type="button" id="export-translations" class="button"><?php echo esc_html($this->t('export_trans')); ?></button>
                        <label class="button" for="import-translations-file" style="cursor:pointer;text-align:center;"><?php echo esc_html($this->t('import_trans')); ?></label>
                        <button type="button" id="delete-all-translations" class="button" style="margin-left:auto;">Smazat všechny překlady</button>
                    </p>
                </div>
                
                <!-- SYSTEM INFO -->
                <div class="wpc-section" id="section-system">
                    <h2><span class="dashicons dashicons-admin-tools"></span> <?php echo esc_html($this->t('system_info')); ?></h2>
                    <table class="wpc-system-info">
                        <tbody>
                        <tr>
                            <th><?php echo esc_html($this->t('site_name')); ?></th>
                            <td><strong><?php echo esc_html(get_bloginfo('name')); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('site_url')); ?></th>
                            <td><a href="<?php echo esc_url(home_url()); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: inherit;"><?php echo esc_url(home_url()); ?></a></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('protocol')); ?></th>
                            <td><?php echo is_ssl() ? '🔒 ' . esc_html($this->t('protocol_https')) : '⚠️ ' . esc_html($this->t('protocol_http')); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('wp_version')); ?></th>
                            <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('php_version')); ?></th>
                            <td><?php echo esc_html(phpversion()); ?> <?php echo version_compare(phpversion(), '8.0', '>=') ? '<span class="wpc-badge wpc-badge-success">✓ ' . esc_html($this->t('php_modern')) . '</span>' : '<span class="wpc-badge wpc-badge-warning">⚠ ' . esc_html($this->t('php_outdated')) . '</span>'; ?></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('mysql_version')); ?></th>
                            <td><?php global $wpdb; echo esc_html($wpdb->db_version()); ?></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('server')); ?></th>
                            <td><code><?php echo esc_html(isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : ''); ?></code></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('php_memory')); ?></th>
                            <td><strong><?php echo esc_html(ini_get('memory_limit')); ?></strong></td>
                        </tr>
                        <tr>
                            <th><?php echo esc_html($this->t('max_upload')); ?></th>
                            <td><strong><?php echo esc_html(size_format(wp_max_upload_size())); ?></strong></td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                
                <?php submit_button($this->t('save'), 'primary large', 'submit', false, array('style' => 'display:none;')); ?>
            </form>
            
            <!-- BACKUP SETTINGS -->
            <div class="wpc-section" id="section-backup">
                <h2><span class="dashicons dashicons-database-export"></span> <?php echo esc_html($this->t('import_export')); ?></h2>
                <div class="wpc-backup-grid">
                    <div class="wpc-backup-item">
                        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>">
                            <input type="hidden" name="action" value="wpc_export_settings">
                            <?php wp_nonce_field('wpc_export'); ?>
                            <button type="submit" class="button"><span class="dashicons dashicons-download"></span> <?php echo esc_html($this->t('export')); ?></button>
                        </form>
                        <p class="wpc-backup-desc"><?php echo esc_html($this->t('export_desc')); ?></p>
                    </div>
                    
                    <div class="wpc-backup-item">
                        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" id="import-form">
                            <input type="hidden" name="action" value="wpc_import_settings">
                            <?php wp_nonce_field('wpc_import'); ?>
                            <label class="button" for="import-file"><span class="dashicons dashicons-upload"></span> <?php echo esc_html($this->t('import')); ?></label>
                            <input type="file" name="import_file" id="import-file" accept=".json" style="display:none;">
                        </form>
                        <p class="wpc-backup-desc"><?php echo esc_html($this->t('import_desc')); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="wpc-sticky-footer">
                <div class="wpc-sticky-content">
                    <button type="submit" form="wpc-main-form" class="button button-primary button-large">
                        <?php echo esc_html($this->t('save')); ?>
                    </button>
                </div>
            </div>
            
            <style>
                <?php
                
                global $_wp_admin_css_colors;
                $admin_color = get_user_option('admin_color');
                $color_scheme = isset($_wp_admin_css_colors[$admin_color]) ? $_wp_admin_css_colors[$admin_color] : $_wp_admin_css_colors['fresh'];
                $primary_color = $color_scheme->colors[2]; 
                ?>
                
                html {
                    scroll-behavior: smooth;
                    scroll-padding-top: 120px;
                }
                
                .wpc-wrap { max-width: 100%; padding-bottom: 20px; }
                .wpc-header { padding: 20px 0; margin-bottom: 20px; border-bottom: 1px solid #ccd0d4; }
                .wpc-header h1 { margin: 0 0 10px 0; font-size: 23px; }
                .wpc-header-meta { display: flex; justify-content: space-between; align-items: center; }
                .wpc-header-info { display: flex; align-items: center; gap: 8px; color: #666; font-size: 13px; }
                .wpc-header-info .wpc-separator { color: #ddd; }
                .wpc-header-info a { color: <?php echo esc_attr($primary_color); ?>; text-decoration: none; }
                .wpc-header-info a:hover { text-decoration: underline; }
                .wpc-header-lang { display: flex; align-items: center; gap: 8px; }
                .wpc-header-lang label { font-weight: 600; font-size: 13px; margin: 0; }
                .wpc-lang-select { height: 32px; font-size: 13px; padding: 4px 8px; }

                .wpc-sticky-nav {
                    position: sticky;
                    top: 32px;
                    z-index: 100;
                    background: #fff;
                    border: 1px solid #dcdcde;
                    box-shadow: 0 1px 3px rgba(0,0,0,.06);
                    margin: 20px 0;
                }
                
                .wpc-sticky-nav-content {
                    display: flex;
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    scrollbar-width: none;
                    -webkit-overflow-scrolling: touch;
                    cursor: grab;
                    user-select: none;
                }
                
                .wpc-sticky-nav-content::-webkit-scrollbar {
                    display: none;
                }
                
                .wpc-sticky-nav-content.is-dragging {
                    cursor: grabbing;
                }
                
                .wpc-nav-item {
                    display: inline-flex;
                    align-items: center;
                    padding: 11px 14px;
                    color: #50575e;
                    text-decoration: none;
                    font-size: 13px;
                    font-weight: 400;
                    white-space: nowrap;
                    flex-shrink: 0;
                    transition: color 0.15s;
                }
                
                .wpc-nav-item:hover {
                    color: <?php echo esc_attr($primary_color); ?>;
                }
                
                .wpc-backup-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
                .wpc-backup-item { display: flex; flex-direction: column; }
                .wpc-backup-item form { margin-bottom: 0; }
                .wpc-backup-item .button { width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; height: auto; }
                .wpc-backup-item .button .dashicons { font-size: 18px; width: 18px; height: 18px; }
                .wpc-backup-desc { margin: 12px 0 0 0; padding: 10px 0 0 0; color: #666; font-size: 13px; line-height: 1.6; border-top: 1px solid #f0f0f0; }
                
                .wpc-section { background: #fff; border: 1px solid #ccd0d4; padding: 20px; margin: 20px 0; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
                .wpc-section h2 { margin: -20px -20px 20px -20px; padding: 20px; border: 0px solid <?php echo esc_attr($primary_color); ?>; display: flex; align-items: center; gap: 10px; color: #ffffff; font-size: 18px; line-height: 1.4; background: <?php echo esc_attr($primary_color); ?>; }
                .wpc-section h2 .dashicons { font-size: 20px; width: 20px; height: 20px; }
                .wpc-section h3 { margin: 25px 0 10px 0; font-size: 14px; font-weight: 600; }
                
                .form-table th { width: 360px; padding: 15px 10px 15px 0; vertical-align: top; }
                .form-table td { padding: 15px 10px; vertical-align: top; }
                .form-table th label { display: flex; align-items: flex-start; gap: 8px; font-weight: 600; }
                .form-table td label:not(.wpc-maintenance-radio) { display: grid; grid-template-columns: 20px 1fr; gap: 10px; align-items: start; }
                .form-table td label input[type="checkbox"] { margin: 0 0 0 0 !important; grid-column: 1; }

                .wpc-maintenance-radio {
                    display: inline-flex !important;
                    align-items: center !important;
                    gap: 10px !important;
                    margin: 0 20px 0 0 !important;
                    padding: 0 !important;
                    vertical-align: middle !important;
                    line-height: normal !important;
                }
                
                .wpc-maintenance-radio:last-of-type {
                    margin-right: 0 !important;
                }
                
                .wpc-maintenance-radio input[type="radio"] {
                    margin: 0 !important;
                    padding: 0 !important;
                    vertical-align: middle !important;
                    position: relative !important;
                    top: 0 !important;
                }
                
                .wpc-maintenance-radio span {
                    line-height: 1.4 !important;
                    display: inline-block !important;
                    vertical-align: middle !important;
                }

                #maintenance-simple-mode label:has(input[type="checkbox"]) {
                    display: grid !important;
                    grid-template-columns: 20px 1fr !important;
                    gap: 10px !important;
                    align-items: start !important;
                    margin-bottom: 15px !important;
                }
                
                #maintenance-simple-mode label:has(input[type="checkbox"]) input[type="checkbox"] {
                    margin: 0 !important;
                    grid-column: 1 !important;
                }
                
                #maintenance-simple-mode label:has(input[type="checkbox"]) span {
                    grid-column: 2 !important;
                }
                
                input[type="checkbox"]:disabled { opacity: 0.8; background-color: #f7f5f5; cursor: not-allowed; filter: grayscale(100%); }
                .form-table td label br { display: block; content: ""; margin: 4px 0; }

                .wpc-section:has(h2 .dashicons-admin-tools) .form-table td label:first-child {
                    margin-bottom: 12px;
                }
                .form-table p small { display: block; margin-top: 18px; }
                .form-table p .description { margin-top: 7px; display: inline-block; }
                .form-table td .description { margin-top: 10px; display: inline-block; }
                
                .wpc-prefix-label { display: inline-block; padding-bottom: 12px; font-size: 14px; }
                
                table.wpc-color-table { margin-top: 20px; }
                .wpc-color-table th { width: 360px; padding: 11px 10px 5px 0; vertical-align: top; font-weight: 600; text-align: left; }
                .wpc-color-table td { padding: 5px 10px; vertical-align: top; }

                .wpc-color-picker { max-width: 100px; }
                
                .wpc-translations { margin: 15px 0; }
                .wpc-translations .translation-row { display: flex; gap: 10px; margin-bottom: 10px; align-items: flex-start; }
                .wpc-translations .translation-number { min-width: 30px; padding-top: 8px; font-weight: 600; color: #666; font-size: 13px; }
                .wpc-translations .translation-row textarea { flex: 1; min-height: 36px; resize: vertical; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif; font-size: 13px; line-height: 1.4; padding: 6px 10px; }
                .wpc-translations .remove-translation { min-width: 40px; padding: 0 8px; display: flex; align-items: center; justify-content: center; margin-top: 3px; }
                .wpc-translations .remove-translation .dashicons { font-size: 14px; width: 14px; height: 14px; }

                .wpc-translations .translation-drag-handle { cursor: move; cursor: grab; display: flex; align-items: center; gap: 4px; }
                .wpc-translations .translation-drag-handle:active { cursor: grabbing; }
                .wpc-translations .translation-drag-handle .dashicons { font-size: 16px; width: 16px; height: 16px; color: #999; }
                .wpc-translations .translation-row.ui-sortable-helper { opacity: 0.8; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.15); }
                .wpc-translations .translation-row.ui-sortable-placeholder { visibility: visible !important; background: #f0f6fc; border: 2px dashed #72aee6; height: 46px; }

                .wpc-translations .translation-row.has-duplicate .translation-from { 
                    border: 1px solid #ffa5c3 !important; 
                    background-color: #fff5f8 !important; 
                    color: #E91E63 !important; 
                }
                
                .wpc-tip { position: relative; display: inline-flex; align-items: center; margin-left: 0; cursor: help; color: <?php echo esc_attr($primary_color); ?>; flex-shrink: 0; }
                .wpc-tooltip { display: none; position: absolute; left: 30px; top: 50%; transform: translateY(-50%); background: #1d2327; color: #fff; padding: 12px 16px; border-radius: 4px; font-size: 13px; line-height: 1.5; width: 320px; z-index: 1000; box-shadow: 0 3px 12px rgba(0,0,0,0.3); white-space: normal; font-weight: normal; }
                .wpc-tooltip:before { content: ''; position: absolute; left: -6px; top: 50%; transform: translateY(-50%); border: 6px solid transparent; border-right-color: #1d2327; }
                .wpc-tip:hover .wpc-tooltip { display: block; }
                .wpc-tooltip-close { display: none; }
                .wpc-tooltip-overlay { display: none; }
                
                .wpc-system-info { width: 100%; border-collapse: collapse; margin-top: 5px; }
                .wpc-system-info th { width: 240px; padding: 15px 20px; text-align: left; font-weight: 600; background: #f8f9fa; border-bottom: 1px solid #ddd; font-size: 14px; color: #1d2327; }
                .wpc-system-info td { padding: 15px 20px; border-bottom: 1px solid #ddd; font-size: 14px; }
                .wpc-system-info tr:last-child th, .wpc-system-info tr:last-child td { border-bottom: none; }
                .wpc-system-info code { font-family: inherit !important; background: none !important; padding: 0 !important; border: none !important; font-size: inherit !important; }
                .wpc-system-info td a { text-decoration: underline !important; text-underline-offset: 2px; color: inherit; transition: all 0.2s; }
                .wpc-system-info td a:hover { color: #2271b1; text-decoration: none !important; }

                .wpc-section code { 
                    background: #f0f0f1; 
                    padding: 2px 6px; 
                    border-radius: 3px; 
                    font-family: Consolas, Monaco, monospace; 
                    font-size: 14px; 
                    color: #1e1e1e; 
                    border: 1px solid #dcdcde;
                    white-space: nowrap;
                }

                .wpc-code-textarea {
                    width: 100%;
                    font-family: Consolas, Monaco, monospace;
                    font-size: 13px;
                    line-height: 1.6;
                    border-radius: 4px;
                    border: 1px solid #8c8f94;
                    padding: 8px 12px;
                }

                .CodeMirror {
                    width: 100% !important;
                    min-height: 200px;
                    max-height: 1000px;
                    position: relative;
                    touch-action: auto;
                    -webkit-user-select: text;
                    user-select: text;
                }
                
                .CodeMirror textarea {
                    -webkit-user-select: text !important;
                    user-select: text !important;
                    -webkit-touch-callout: default !important;
                    pointer-events: auto !important;
                }

                .cm-resize-handle {
                    position: absolute;
                    bottom: 0;
                    right: 0;
                    width: 100%;
                    height: 18px;
                    background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.18));
                    cursor: ns-resize;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    user-select: none;
                    z-index: 10;
                    transition: background 0.15s;
                }
                .cm-resize-handle::after {
                    content: '';
                    display: block;
                    width: 36px;
                    height: 4px;
                    border-radius: 2px;
                    background: rgba(255,255,255,0.55);
                    box-shadow: 0 1px 3px rgba(0,0,0,0.35);
                }
                
                .cm-resize-handle:hover {
                    background: linear-gradient(to bottom, transparent, rgba(0,0,0,0.32));
                }
                .cm-resize-handle:hover::after {
                    background: rgba(255,255,255,0.9);
                    width: 48px;
                }
                
                .cm-resizing {
                    opacity: 0.8;
                }

                .CodeMirror-scroll {
                    min-height: 200px;
                }

                .wpc-section p[style*="background:#e7f3ff"] code,
                .wpc-section p[style*="background:#fff3cd"] code {
                    background: rgba(0, 0, 0, 0.06);
                    border: 1px solid rgba(0, 0, 0, 0.12);
                    font-weight: 600;
                    padding: 2px 5px;
                    white-space: nowrap;
                }

                }

                .wpc-toolbar {
                    display: flex;
                    align-items: center;
                    background: #f6f7f7;
                    border: 1px solid #dcdcde;
                    border-bottom: none;
                    border-radius: 4px 4px 0 0;
                }

                @media (min-width: 783px) {
                    .wpc-toolbar {
                        display: flex !important;
                        align-items: center !important;
                        justify-content: space-between !important;
                        flex-wrap: nowrap !important;
                        gap: 8px !important;
                        background: #f6f7f7;
                        border: 1px solid #dcdcde;
                        border-bottom: none;
                        padding: 8px 12px !important;
                        border-radius: 4px 4px 0 0;
                    }

                    .wpc-search-wrapper {
                        display: flex !important;
                        align-items: center !important;
                        gap: 8px !important;
                        flex-shrink: 0 !important;
                        flex: 0 !important;
                    }

                    .wpc-search-toggle {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 32px !important;
                        height: 30px !important;
                        padding: 0;
                        border: 1px solid #8c8f94;
                        background: #fff;
                        border-radius: 4px;
                        cursor: pointer;
                        color: #50575e;
                        flex-shrink: 0;
                    }
                    .wpc-search-toggle:hover {
                        background: #f6f7f7;
                        border-color: #2271b1;
                        color: #2271b1;
                    }
                    .wpc-search-toggle svg {
                        display: block;
                    }

                    .wpc-search-panel {
                        display: flex;
                        align-items: center !important;
                        gap: 6px !important;
                        flex-shrink: 0 !important;
                        flex: 0 !important;
                    }

                    .wpc-search-field {
                        width: 200px !important;
                        height: 30px !important;
                        padding: 0 10px;
                        border: 1px solid #8c8f94;
                        border-radius: 4px;
                        font-size: 13px !important;
                        flex-shrink: 0;
                        flex: 0 !important;
                    }
                    .wpc-search-field:focus {
                        outline: none;
                        border-color: #2271b1;
                        box-shadow: 0 0 0 1px #2271b1;
                    }

                    .wpc-search-count {
                        font-size: 12px !important;
                        color: #646970;
                        min-width: 35px !important;
                        text-align: center;
                        white-space: nowrap;
                        flex-shrink: 0;
                    }

                    .wpc-search-up,
                    .wpc-search-down {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 28px !important;
                        height: 28px !important;
                        padding: 0;
                        border: 1px solid #8c8f94;
                        background: #fff;
                        border-radius: 4px;
                        cursor: pointer;
                        color: #50575e;
                        flex-shrink: 0;
                    }
                    .wpc-search-up:hover:not(:disabled),
                    .wpc-search-down:hover:not(:disabled) {
                        background: #f6f7f7;
                        border-color: #2271b1;
                        color: #2271b1;
                    }
                    .wpc-search-up:disabled,
                    .wpc-search-down:disabled {
                        opacity: 0.4;
                        cursor: not-allowed;
                    }
                    .wpc-search-up svg,
                    .wpc-search-down svg {
                        display: block;
                    }

                    .wpc-theme-select {
                        height: 30px !important;
                        padding: 0 8px;
                        border: 1px solid #8c8f94;
                        border-radius: 4px;
                        font-size: 13px;
                        background: #fff;
                        flex-shrink: 0 !important;
                        min-width: 140px;
                        width: auto !important;
                        order: 0 !important;
                    }
                }

                .CodeMirror-search-match {
                    background: #f0b429 !important;
                    color: #000 !important;
                    border-radius: 5px;
                    box-sizing: border-box;
                    opacity: 1;
                }

                .CodeMirror span.cm-error {
                    background: transparent !important;
                    color: #f92672 !important;
                    text-decoration: underline wavy #f92672;
                }

                .wpc-toolbar + textarea,
                .wpc-toolbar + .CodeMirror {
                    border-top-left-radius: 0 !important;
                    border-top-right-radius: 0 !important;
                    margin-top: 0 !important;
                }
                
                .wpc-badge { display: inline-flex; align-items: center; padding: 4px 15px; border-radius: 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
                .wpc-badge-success { margin-left: 5px; background: #f5f5f5; color: #666; border: 1px solid #ddd; }
                .wpc-badge-warning { background: #f0ad4e; color: #ffffff; border: none; margin-left: 5px; }
                .wpc-badge-css-inactive { background: #f5f5f5; color: #666; border: 1px solid #ddd; margin-left: 8px; font-size: 11px; padding: 4px 10px; vertical-align: middle; border-radius: 16px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; }
                
                .wpc-toggle-label { display: inline-flex; align-items: center; cursor: pointer; user-select: none; }
                .wpc-toggle-switch { position: relative; display: inline-block; width: 40px; height: 22px; flex-shrink: 0; top: -2px; }
                .wpc-toggle-switch input { opacity: 0; width: 0; height: 0; position: absolute; }
                .wpc-toggle-slider { position: absolute; cursor: pointer; inset: 0; background: #ccc; border-radius: 22px; transition: background 0.2s; }
                .wpc-toggle-slider:before { content: ""; position: absolute; height: 16px; width: 16px; left: 3px; top: 3px; background: #fff; border-radius: 50%; transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.3); }
                .wpc-toggle-switch input:checked + .wpc-toggle-slider { background: var(--wp-admin-theme-color, #2271b1); }
                .wpc-toggle-switch input:checked + .wpc-toggle-slider:before { transform: translateX(18px); }
                .wpc-toggle-text { font-weight: normal; padding-left: 20px; }
                .wpc-badge-disabled { background: #f5f5f5; color: #666; border: 1px solid #ddd; margin-left: 8px; margin-top: -4px; display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px; font-size: 12px; }
                .wpc-badge-disabled .dashicons { font-size: 14px; width: 14px; height: 14px; }
                .wpc-badge-active { margin-left: 5px; background: #f5f5f5; color: #666; border: 1px solid #ddd; gap: 5px; }
                .wpc-badge-active svg { width: 11px; height: 11px; flex-shrink: 0; }
                .wpc-badge-notice { margin-left: 5px; background: #f5f5f5; color: #666; border: 1px solid #ddd; gap: 5px; }
                .wpc-badge-notice svg { width: 11px; height: 11px; flex-shrink: 0; }
                
                .wpc-wpforms-label { display: flex !important; flex-wrap: wrap; align-items: center; gap: 8px !important; grid-template-columns: none !important; }
                .wpc-checkbox-wrapper { display: inline-flex; align-items: center; gap: 10px; }

                .wpc-section:has(h2 .dashicons-admin-settings) .form-table td label { margin-bottom: 4px; }
                .wpc-section:has(h2 .dashicons-admin-settings) .form-table td label br { margin: 2px 0; }
                
                .wpc-sticky-footer { 
                    position: fixed; 
                    bottom: 0; 
                    left: 160px; 
                    right: 0; 
                    background: #fff; 
                    border-top: 0px solid #ccd0d4; 
                    box-shadow: 0 -2px 8px rgba(0,0,0,0.1); 
                    z-index: 1000; 
                    min-height: 60px;
                }
                body.folded .wpc-sticky-footer,
                body.auto-fold .wpc-sticky-footer { left: 36px; }
                .wpc-sticky-content { max-width: calc(100% - 40px); margin: 0 auto; padding: 15px 10px; display: flex; justify-content: flex-start; align-items: center; gap: 15px; min-height: 60px; box-sizing: border-box; }
                
                .wpc-wrap {
                    padding-bottom: 80px;
                }
                
                .wpc-saved-notice { display: inline-flex; align-items: center; padding: 0 24px; height: 32px; background: #d4edda; color: #155724; border: 1px solid #c3e6cb; border-radius: 3px; font-size: 13px; font-weight: 600; animation: wpc-fade-in 0.3s ease-in-out; }
                .wpc-saved-notice.wpc-fade-out { animation: wpc-fade-out 0.5s ease-in-out forwards; }
                @keyframes wpc-fade-in { from { opacity: 0; transform: translateX(-10px); } to { opacity: 1; transform: translateX(0); } }
                @keyframes wpc-fade-out { from { opacity: 1; transform: translateX(0); } to { opacity: 0; transform: translateX(-10px); } }

                .wp-core-ui .button.button-large { padding: 0 24px !important; }

                .settings-error.notice { display: none !important; }
                
                .wpc-footer-info { text-align: right; padding: 10px 0 40px 0; margin-top: 20px; color: #666; font-size: 13px; }
                .wpc-footer-info a { color: <?php echo esc_attr($primary_color); ?>; text-decoration: none; }
                .wpc-footer-info a:hover { text-decoration: underline; }
                
                @media (max-width: 960px) { 
                    .wpc-backup-grid { grid-template-columns: 1fr; gap: 20px; }
                }
                @media (max-width: 690px) {
                    .wpc-backup-item .button { width: 100%; justify-content: center; display: inline-flex; align-items: center; gap: 8px; padding: 4px 20px; height: auto; }
                }
                @media (max-width: 782px) { 
                    input, textarea { font-size: 14px; }
                    
                    .wpc-wrap {
                        padding-bottom: 100px;
                    }
                    
                    .wpc-sticky-footer { 
                        left: 0 !important; 
                        right: 0 !important;
                        position: fixed !important;
                        bottom: 0 !important;
                    }
                    
                    .wpc-sticky-content {
                        padding: 12px 15px;
                    }
                    
                    .wpc-header-meta { flex-direction: column; align-items: flex-start; gap: 15px; }
                    .wpc-header-lang { width: 100%; flex-direction: column; align-items: flex-start; }
                    .wpc-header-lang label { width: 100%; margin-bottom: 8px; }
                    .wpc-lang-select { width: 100%; }

                    .form-table td > div > label:not(.wpc-maintenance-field-label) { 
                        font-size: 13px !important;
                        white-space: nowrap !important;
                    }
                    
                    .form-table td > div > h4 {
                        white-space: nowrap !important;
                    }

                    .wpc-toolbar {
                        flex-wrap: wrap !important;
                        padding: 8px 12px;
                        gap: 8px;
                        background: #f6f7f7;
                        border: 1px solid #dcdcde;
                        border-bottom: none;
                        border-radius: 4px 4px 0 0;
                    }

                    .wpc-search-wrapper {
                        display: flex;
                        align-items: center;
                        gap: 6px;
                        width: 100%;
                        order: 1;
                    }

                    .wpc-search-toggle {
                        display: none !important;
                    }

                    .wpc-search-panel {
                        display: flex !important;
                        align-items: center;
                        gap: 6px;
                        flex: 1;
                        min-width: 0;
                        margin: 10px 0;
                    }

                    .wpc-search-field {
                        flex: 1;
                        min-width: 0;
                        height: 40px;
                        font-size: 16px;
                        line-height: 1.625;
                        padding: 5px 8px;
                    }

                    .wpc-search-count {
                        font-size: 12px;
                        min-width: 35px;
                    }

                    .wpc-search-up,
                    .wpc-search-down {
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        width: 28px !important;
                        height: 28px !important;
                        padding: 0;
                        border: 1px solid #8c8f94;
                        background: #fff;
                        border-radius: 4px;
                        cursor: pointer;
                        color: #50575e;
                        flex-shrink: 0;
                    }

                    .wpc-theme-select {
                        width: 100%;
                        min-height: 40px;
                        height: 40px;
                        font-size: 16px;
                        line-height: 1.625;
                        padding: 5px 24px 5px 8px;
                        margin: 10px 0;
                        order: 2;
                    }
                    
                    .CodeMirror {
                        font-size: 12px !important;
                    }
                    
                    .wpc-editor-toolbar + textarea,
                    .wpc-editor-toolbar + .CodeMirror {
                        -webkit-user-select: text !important;
                        user-select: text !important;
                    }
                    .wpc-sticky-footer { left: 0; }
                    
                    .wpc-backup-grid { grid-template-columns: 1fr; gap: 20px; }
                    
                    .form-table th { width: 100%; display: block; padding: 10px 0 5px 0; }
                    .form-table td { display: block; padding: 0 0 20px 0; }

                    .wpc-section:has(h2 .dashicons-admin-tools) .form-table tr:nth-child(2) th {
                        padding-bottom: 20px !important;
                    }
                    
                    .form-table td label:has(input[type="checkbox"]):not(.wpc-wpforms-label) { display: grid !important; grid-template-columns: 16px 1fr !important; gap: 18px !important; align-items: flex-start !important; padding: 8px 0; line-height: 1.5; }
                    .form-table td label:last-of-type { padding-bottom: 0; }
                    .form-table td label:not(.wpc-wpforms-label) input[type="checkbox"] { margin: 0 !important; grid-column: 1 !important; transform: scale(0.85); }
                    .form-table td label br { display: block; content: ""; margin: 2px 0; }
                    .form-table p small { margin-top: 5px; padding-bottom: 10px; }
                    .form-table td .description { padding-top: 8px; display: block; }

                    .wpc-maintenance-field-label {
                        font-weight: 400 !important;
                        font-size: 13px !important;
                    }

                    #maintenance-simple-mode label:has(input[type="checkbox"]) {
                        display: grid !important;
                        grid-template-columns: 16px 1fr !important;
                        gap: 18px !important;
                        align-items: flex-start !important;
                        padding: 8px 0 !important;
                        line-height: 1.5 !important;
                        margin-bottom: 15px !important;
                    }
                    
                    #maintenance-simple-mode label:has(input[type="checkbox"]) input[type="checkbox"] {
                        margin: 0 !important;
                        grid-column: 1 !important;
                        transform: scale(0.85);
                    }
                    
                    #maintenance-simple-mode label:has(input[type="checkbox"]) span {
                        grid-column: 2 !important;
                        white-space: nowrap !important;
                    }

                    .wpc-maintenance-radio {
                        display: inline-grid !important;
                        grid-template-columns: 16px auto !important;
                        gap: 18px !important;
                        align-items: center !important;
                        margin-right: 20px !important;
                        margin-bottom: 12px !important;
                    }
                    
                    .wpc-maintenance-radio input[type="radio"] {
                        margin: 0 !important;
                        transform: scale(0.85);
                    }
                    
                    .wpc-maintenance-radio span {
                        white-space: nowrap !important;
                    }

                    .wpc-section:has(h2 .dashicons-admin-settings) .form-table td { line-height: 0.9; }
                    .wpc-section:has(h2 .dashicons-admin-settings) .form-table td label { padding: 4px 0 !important; margin-bottom: -8px; }

                    .wpc-wpforms-label { 
                        display: block !important; 
                        width: 100%; 
                        padding: 8px 0; 
                    }
                    
                    .wpc-wpforms-label .wpc-checkbox-wrapper { 
                        display: flex !important;
                        align-items: center !important;
                        gap: 10px !important;
                        width: 100% !important;
                        padding: 0 !important;
                        position: static !important;
                        margin-bottom: 10px;
                    }
                    
                    .wpc-wpforms-label .wpc-checkbox-wrapper input[type="checkbox"] { 
                        position: static !important;
                        margin: 0 !important;
                        flex-shrink: 0;
                    }
                    
                    .wpc-wpforms-label .wpc-checkbox-wrapper > span { 
                        display: block !important;
                        line-height: 1.5 !important;
                        flex: 1;
                    }
                    
                    .wpc-wpforms-label .wpc-badge-disabled,
                    .wpc-wpforms-label .wpc-badge-active,
                    .wpc-wpforms-label .wpc-badge-notice { 
                        display: block !important; 
                        width: auto !important;
                        max-width: calc(100% - 20px) !important; 
                        margin: 0 auto !important;
                        text-align: center;
                        padding: 10px 12px;
                    }
                    
                    .wpc-tip { position: relative; }
                    .wpc-tooltip { position: fixed !important; left: 20px !important; right: 20px !important; top: 50% !important; transform: translateY(-50%) !important; margin: 0 !important; padding: 40px 20px 20px 20px !important; width: auto !important; max-width: none !important; z-index: 10000 !important; text-align: left !important; }
                    .wpc-tooltip:before { display: none !important; }
                    .wpc-tooltip-close { display: block !important; position: absolute; top: 10px; right: 10px; width: 28px; height: 28px; background: transparent; border: none; border-radius: 3px; cursor: pointer; padding: 0; line-height: 26px; color: rgba(255,255,255,0.5); font-size: 24px; font-weight: 300; transition: all 0.2s; text-align: center; z-index: 10002; }
                    .wpc-tooltip-close:hover, .wpc-tooltip-close:active { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.8); border: none; }
                    .wpc-tooltip-close:before { content: '×'; display: block; pointer-events: none; }
                    .wpc-tooltip-overlay { display: block !important; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; }
                    body.wpc-tooltip-open { overflow: hidden; }
                    
                    .wpc-color-table { width: 100%; }
                    .wpc-color-table tbody { display: block; }
                    .wpc-color-table tr { display: block; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 1px solid #ddd; }
                    .wpc-color-table tr:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
                    .wpc-color-table th { display: block; width: 100%; padding: 0 0 10px 0; text-align: left; font-size: 14px; font-weight: 600; }
                    .wpc-color-table td { display: block; width: 100%; padding: 0; }
                    .wpc-color-table td .wp-picker-container { width: 100%; }
                    .wpc-color-table td .wp-picker-input-wrap { width: 100%; }
                    .wpc-color-table td input.wpc-color-picker { width: 100% !important; }
                    
                    .wpc-translations .translation-row { flex-direction: column; align-items: stretch; gap: 8px; padding-bottom: 0px; }
                    .wpc-translations .translation-number { padding-top: 0; text-align: center; }
                    .wpc-translations .translation-drag-handle { justify-content: center; padding: 8px; background: #f0f0f1; border-radius: 4px; }
                    .wpc-translations .translation-row textarea { width: 100%; }
                    .wpc-translations .translation-row .button { width: 100%; margin: 0; }
                    .wpc-translations .remove-translation .dashicons { display: none; }
                    .wpc-translations .remove-translation:after { content: '<?php echo esc_js($this->t('remove')); ?>'; }
                    
                    .wpc-system-info { display: block; }
                    .wpc-system-info tbody { display: block; }
                    .wpc-system-info tr { display: block; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #ddd !important; }
                    .wpc-system-info tr:last-child { border-bottom: none !important; margin-bottom: 0; padding-bottom: 0; }
                    .wpc-system-info th { display: block; width: 100%; padding: 0 0 8px 0 !important; background: transparent !important; border: none !important; font-size: 14px; color: #000000; text-transform: none; letter-spacing: 0; font-weight: 500; }
                    .wpc-system-info td { display: block; width: 100%; padding: 0 !important; border: none !important; font-size: 14px; }
                }

                .wpc-footer-feedback {
                    margin-top: 40px;
                    padding: 10px 0;
                    border-top: 0px solid #dcdcde;
                    text-align: right;
                    position: relative;
                    z-index: 1;
                }
                .wpc-feedback-btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 8px 16px;
                    border: 1px solid #dcdcde;
                    border-radius: 4px;
                    background: #fff;
                    color: #2c3338;
                    text-decoration: none;
                    font-size: 13px;
                    transition: all 0.2s;
                    cursor: pointer;
                }
                .wpc-feedback-btn:hover {
                    background: #f6f7f7;
                    border-color: #8c8f94;
                    color: #2c3338;
                }
                .wpc-feedback-btn .dashicons {
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                }

                .wpc-modal-overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: 999999;
                    align-items: center;
                    justify-content: center;
                }
                .wpc-modal-overlay.active {
                    display: flex;
                }
                .wpc-modal {
                    background: #fff;
                    border-radius: 8px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 90vh;
                    overflow-y: auto;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                    animation: wpcModalSlideIn 0.3s ease-out;
                }
                @keyframes wpcModalSlideIn {
                    from {
                        opacity: 0;
                        transform: translateY(-50px);
                    }
                    to {
                        opacity: 1;
                        transform: translateY(0);
                    }
                }
                .wpc-modal-header {
                    padding: 20px 24px;
                    border-bottom: 1px solid #dcdcde;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                }
                .wpc-modal-header h2 {
                    margin: 0;
                    font-size: 18px;
                    font-weight: 600;
                    color: #1d2327;
                    display: flex;
                    align-items: center;
                    gap: 8px;
                }
                .wpc-modal-close {
                    background: none;
                    border: none;
                    font-size: 24px;
                    color: #8c8f94;
                    cursor: pointer;
                    padding: 0;
                    width: 32px;
                    height: 32px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 4px;
                }
                .wpc-modal-body {
                    padding: 32px;
                }
                .wpc-form-row {
                    margin-bottom: 20px;
                }
                .wpc-form-row label {
                    display: block;
                    margin-bottom: 6px;
                    font-weight: 600;
                    font-size: 13px;
                    color: #1d2327;
                }
                .wpc-form-row input[type="text"],
                .wpc-form-row input[type="email"],
                .wpc-form-row textarea {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #8c8f94;
                    border-radius: 4px;
                    font-size: 14px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
                }
                .wpc-form-row input[type="file"] {
                    width: 100%;
                    padding: 8px 0;
                    font-size: 14px;
                }
                .wpc-form-row textarea {
                    min-height: 120px;
                    resize: vertical;
                }
                .wpc-form-row .description {
                    margin-top: 6px;
                    font-size: 12px;
                    color: #646970;
                }
                .wpc-system-info-box {
                    display: none;
                    background: #f6f7f7;
                    border: 1px solid #dcdcde;
                    border-radius: 4px;
                    padding: 12px;
                    font-size: 12px;
                    font-family: monospace;
                    color: #50575e;
                    margin-bottom: 20px;
                }
                .wpc-system-info-box strong {
                    display: block;
                    margin-bottom: 8px;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
                    color: #1d2327;
                }
                .wpc-modal-footer {
                    padding: 16px 24px;
                    border-top: 1px solid #dcdcde;
                    display: flex;
                    gap: 12px;
                    justify-content: flex-end;
                    background: #f6f7f7;
                }
                .wpc-modal-footer .button {
                    min-width: 100px;
                }
                .wpc-bug-report-status {
                    padding: 16px;
                    margin-bottom: 20px;
                    border-radius: 4px;
                    display: none;
                }
                .wpc-bug-report-status.success {
                    background: #d5f4e6;
                    border: 1px solid #00a32a;
                    color: #00a32a;
                    display: block;
                    padding: 16px;
                }
                .wpc-bug-report-status.error {
                    background: #fcf0f1;
                    border: 1px solid #d63638;
                    color: #d63638;
                    display: block;
                    padding: 16px;
                }
                
                @media (max-width: 782px) {
                    .wpc-footer-feedback {
                        text-align: center;
                    }
                    .wpc-modal {
                        width: 95%;
                    }
                    .wpc-modal-body {
                        padding: 16px;
                    }
                }

                .settings-error,
                .updated,
                .update-nag,
                div.notice {
                    display: none !important;
                }

                .wpc-notify {
                    position: fixed;
                    top: 46px;
                    right: 20px;
                    padding: 18px 32px;
                    background: #fff;
                    border-left: 5px solid #00a32a;
                    font-size: 16px;
                    font-weight: 600;
                    z-index: 999999;
                    opacity: 0;
                    transform: translateX(400px);
                    transition: all .4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
                    border-radius: 4px;
                }
                .wpc-notify.show {
                    opacity: 1;
                    transform: translateX(0) scale(1.02);
                }
                .wpc-notify.success {
                    border-left-color: #00a32a;
                    background: #00a32a;
                    color: #ffffff;
                }
                .wpc-notify.success::before {
                    content: '✓';
                    display: inline-block;
                    margin-right: 10px;
                    color: #ffffff;
                    font-size: 20px;
                    font-weight: 700;
                }
                .wpc-notify.error {
                    border-left-color: #d63638;
                    background: linear-gradient(135deg, #fff0f0 0%, #ffffff 100%);
                }
                .wpc-notify.error::before {
                    content: '✕';
                    display: inline-block;
                    margin-right: 8px;
                    color: #d63638;
                    font-size: 18px;
                    font-weight: 700;
                }
                @media (max-width: 782px) {
                    .wpc-notify {
                        top: 0;
                        right: 0;
                        left: 0;
                        border-radius: 0;
                    }
                }
                
                @media (max-width: 767px) {
                    /* Responzivní tlačítka pro překlady */
                    .wpc-translation-buttons {
                        flex-direction: column !important;
                        align-items: stretch !important;
                    }
                    
                    .wpc-translation-buttons button {
                        width: 100% !important;
                        margin-left: 0 !important;
                        margin-right: 0 !important;
                    }
                }
            </style>
            
            <!-- User Feedback Footer -->
            <div class="wpc-footer-feedback">
                <button type="button" class="wpc-feedback-btn" id="wpc-open-bug-report" onclick="console.log('Inline onclick works!'); document.getElementById('wpc-bug-report-modal').classList.add('active');">
                    <span class="dashicons dashicons-warning"></span>
                    <?php echo esc_html($this->t('feedback_bug')); ?>
                </button>
            </div>
            
            <!-- Bug Report Modal -->
            <div class="wpc-modal-overlay" id="wpc-bug-report-modal">
                <div class="wpc-modal">
                    <div class="wpc-modal-header">
                        <h2>
                            <span class="dashicons dashicons-warning"></span>
                            <?php echo esc_html($this->t('bug_report_title')); ?>
                        </h2>
                        <button type="button" class="wpc-modal-close" onclick="document.getElementById('wpc-bug-report-modal').classList.remove('active');">×</button>
                    </div>
                    <div class="wpc-modal-body">
                        <div class="wpc-bug-report-status" id="wpc-bug-report-status"></div>
                        
                        <form id="wpc-bug-report-form">
                            <div class="wpc-form-row">
                                <label for="wpc-bug-email"><?php echo esc_html($this->t('bug_report_email')); ?> *</label>
                                <input type="email" id="wpc-bug-email" name="email" required>
                            </div>
                            
                            <div class="wpc-form-row">
                                <label for="wpc-bug-message"><?php echo esc_html($this->t('bug_report_message')); ?> *</label>
                                <textarea id="wpc-bug-message" name="message" rows="6" required placeholder="<?php echo esc_attr($this->t('bug_report_message_placeholder')); ?>"></textarea>
                            </div>
                            
                            <div class="wpc-form-row">
                                <label for="wpc-bug-screenshot"><?php echo esc_html($this->t('bug_report_screenshot')); ?></label>
                                <input type="file" id="wpc-bug-screenshot" name="screenshot" accept="image/png,image/jpeg">
                                <p class="description"><?php echo esc_html($this->t('bug_report_screenshot_desc')); ?></p>
                            </div>
                            
                            <div class="wpc-system-info-box">
                                <strong><?php echo esc_html($this->t('bug_report_system_info')); ?>:</strong>
                                WordPress: <?php echo esc_html(get_bloginfo('version')); ?><br>
                                PHP: <?php echo esc_html(PHP_VERSION); ?><br>
                                Plugin: <?php echo esc_html(self::VERSION); ?><br>
                                <?php echo esc_html($this->t('bug_report_url')); ?>: <?php echo esc_html(home_url()); ?>
                            </div>
                            <p class="description" style="margin-top:8px;font-style:italic;">
                                    <?php echo esc_html($this->t('bug_report_consent')); ?>
                                </p>
                        </form>
                    </div>
                    <div class="wpc-modal-footer">
                        <button type="button" class="button button-primary" id="wpc-submit-bug-report" onclick="wpcSubmitBugReport();"><?php echo esc_html($this->t('bug_report_send')); ?></button>
                    </div>
                </div>
            </div>
            
        </div> <!-- End .wrap -->

        <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" enctype="multipart/form-data" id="import-translations-form" style="display:none;">
            <input type="hidden" name="action" value="wpc_import_translations">
            <?php wp_nonce_field('wpc_import_translations'); ?>
            <input type="file" name="import_translations_file" id="import-translations-file" accept=".json">
        </form>
        
        <script>
            jQuery(document).ready(function($){
                
                function notify(msg, type) {
                    $('.wpc-notify').remove();
                    var $n = $('<div class="wpc-notify ' + type + '">' + msg + '</div>');
                    $('body').append($n);
                    setTimeout(function() { $n.addClass('show'); }, 10);
                    setTimeout(function() { 
                        $n.removeClass('show'); 
                        setTimeout(function() { $n.remove(); }, 300);
                    }, 3000);
                }
                
                $('#import-file').on('change', function(){
                    if(this.files.length > 0) $('#import-form').submit();
                });

                $('#import-translations-file').on('change', function(){
                    if (this.files.length > 0) {
                        if (confirm('<?php echo esc_js($this->t('import_trans_confirm')); ?>')) {
                            $('#import-translations-form').submit();
                        } else {
                            $(this).val('');
                        }
                    }
                });

                $('label[for="import-translations-file"]').on('click', function(e){
                    e.preventDefault();
                    $('#import-translations-file').trigger('click');
                });

                $('#export-translations').on('click', function(){
                    var rows = [];
                    $('#translation-rows .translation-row').each(function(){
                        var from = $(this).find('textarea').eq(0).val();
                        var to = $(this).find('textarea').eq(1).val();
                        rows.push({from: from, to: to});
                    });
                    var data = JSON.stringify({translations: rows}, null, 2);
                    var blob = new Blob([data], {type: 'application/json'});
                    var url = URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = 'wpc-translations-<?php echo esc_attr(gmdate('Y-m-d')); ?>.json';
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                });

                $('#wpc-lang-select').on('change', function(){
                    var lang = $(this).val();
                    var nonce = '<?php echo esc_attr( wp_create_nonce('wpc_nonce') ); ?>';
                    $.post(ajaxurl, {action: 'wpc_change_language', lang: lang, nonce: nonce}, function(){ location.reload(); });
                });
                
                $('#wpc-restore-htaccess').on('click', function(){
                    if (!confirm('<?php echo esc_js($this->t('htaccess_restore_confirm')); ?>')) {
                        return;
                    }
                    
                    var $button = $(this);
                    var originalText = $button.html();
                    $button.prop('disabled', true).html('<span class="dashicons dashicons-update dashicons-spin" style="vertical-align:middle;"></span> <?php echo esc_js($this->t('saving')); ?>...');
                    
                    var nonce = '<?php echo esc_attr( wp_create_nonce('wpc_nonce') ); ?>';
                    
                    $.post(ajaxurl, {
                        action: 'wpc_restore_htaccess',
                        nonce: nonce
                    }, function(response) {
                        $button.prop('disabled', false).html(originalText);
                        
                        if (response.success) {
                            $('textarea[name="<?php echo esc_attr($this->option_name); ?>[htaccess_content]"]').val('');
                            $('input[name="<?php echo esc_attr($this->option_name); ?>[htaccess_enable]"]').prop('checked', false);
                            
                            notify(response.data.message, 'success');
                            
                            setTimeout(function() {
                                location.reload();
                            }, 2500);
                        } else {
                            notify(response.data.message, 'error');
                        }
                    }).fail(function() {
                        $button.prop('disabled', false).html(originalText);
                        notify('Chyba při komunikaci se serverem.', 'error');
                    });
                });

                $('.wpc-color-picker').wpColorPicker({
                    change: function(event, ui) {
                        $(this).val(ui.color.toString());
                    },
                    clear: function() {
                        $(this).val('');
                    }
                });



                var translationIndex = <?php echo count($o['translations']); ?>;
                $('#add-translation').on('click', function(){
                    var rowNumber = $('#translation-rows .translation-row').length + 1;
                    var row = '<div class="translation-row">' +
                        '<span class="translation-number translation-drag-handle" title="Přetáhněte pro změnu pořadí"><span class="dashicons dashicons-menu"></span>' + rowNumber + '.</span>' +
                        '<textarea name="<?php echo esc_attr($this->option_name); ?>[translations]['+translationIndex+'][from]" placeholder="<?php echo esc_attr($this->t('from')); ?>" rows="1" class="translation-from"></textarea>' +
                        '<textarea name="<?php echo esc_attr($this->option_name); ?>[translations]['+translationIndex+'][to]" placeholder="" title="<?php echo esc_attr($this->t('trans_html_allowed')); ?>" rows="1"></textarea>' +
                        '<button type="button" class="button remove-translation" title="<?php echo esc_attr($this->t('remove')); ?>"><span class="dashicons dashicons-no"></span></button>' +
                        '</div>';
                    $('#translation-rows').append(row);
                    translationIndex++;
                    updateTranslationNumbers();
                    checkDuplicates();
                });
                
                $('#delete-all-translations').on('click', function(){
                    if (confirm('Opravdu chcete smazat všechny překlady? Tato akce nelze vrátit zpět.')) {
                        $('#translation-rows').empty();
                        translationIndex = 0;
                        var row = '<div class="translation-row">' +
                            '<span class="translation-number translation-drag-handle" title="Přetáhněte pro změnu pořadí"><span class="dashicons dashicons-menu"></span>1.</span>' +
                            '<textarea name="<?php echo esc_attr($this->option_name); ?>[translations][0][from]" placeholder="<?php echo esc_attr($this->t('from')); ?>" rows="1" class="translation-from"></textarea>' +
                            '<textarea name="<?php echo esc_attr($this->option_name); ?>[translations][0][to]" placeholder="" title="<?php echo esc_attr($this->t('trans_html_allowed')); ?>" rows="1"></textarea>' +
                            '<button type="button" class="button remove-translation" title="<?php echo esc_attr($this->t('remove')); ?>"><span class="dashicons dashicons-no"></span></button>' +
                            '</div>';
                        $('#translation-rows').append(row);
                        translationIndex = 1;
                    }
                });
                
                $(document).on('click', '.remove-translation', function(){
                    $(this).closest('.translation-row').remove();
                    updateTranslationNumbers();
                    checkDuplicates();
                });

                function updateTranslationNumbers() {
                    $('#translation-rows .translation-row').each(function(index) {
                        var $number = $(this).find('.translation-number');
                        
                        var hasIcon = $number.find('.dashicons').length > 0;
                        if (hasIcon) {
                            $number.html('<span class="dashicons dashicons-menu"></span>' + (index + 1) + '.');
                        } else {
                            $number.text((index + 1) + '.');
                        }
                    });
                }

                function checkDuplicates() {
                    var values = {};
                    var hasDuplicates = false;

                    $('.translation-row').removeClass('has-duplicate');

                    $('.translation-from').each(function() {
                        var val = $(this).val().trim();
                        if (val === '') return; 
                        
                        if (values[val]) {
                            
                            hasDuplicates = true;
                            $(this).closest('.translation-row').addClass('has-duplicate');
                            values[val].closest('.translation-row').addClass('has-duplicate');
                        } else {
                            values[val] = $(this);
                        }
                    });
                }

                $(document).on('input', '.translation-from', function() {
                    checkDuplicates();
                });

                $('#translation-rows').sortable({
                    handle: '.translation-drag-handle',
                    placeholder: 'ui-sortable-placeholder',
                    cursor: 'move',
                    opacity: 0.8,
                    update: function(event, ui) {
                        updateTranslationNumbers();
                    }
                });

                checkDuplicates();

                if (window.innerWidth <= 782) {
                    var overlay = null;

                    $('.wpc-tooltip').each(function() {
                        if ($(this).find('.wpc-tooltip-close').length === 0) {
                            $(this).prepend('<button type="button" class="wpc-tooltip-close"></button>');
                        }
                    });

                    $(document).on('click touchstart', '.wpc-tooltip-close', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        e.stopImmediatePropagation();
                        
                        $('.wpc-tooltip').hide();
                        if (overlay) {
                            overlay.remove();
                            overlay = null;
                        }
                        $('body').removeClass('wpc-tooltip-open');
                        
                        return false;
                    });
                    
                    $('.wpc-tip').on('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        
                        var $tooltip = $(this).find('.wpc-tooltip');
                        var isVisible = $tooltip.is(':visible');

                        $('.wpc-tooltip').hide();
                        if (overlay) {
                            overlay.remove();
                            overlay = null;
                        }
                        $('body').removeClass('wpc-tooltip-open');
                        
                        if (!isVisible) {
                            
                            overlay = $('<div class="wpc-tooltip-overlay"></div>');
                            $('body').append(overlay).addClass('wpc-tooltip-open');

                            $tooltip.show();

                            overlay.on('click touchstart', function(e) {
                                e.preventDefault();
                                $tooltip.hide();
                                overlay.remove();
                                overlay = null;
                                $('body').removeClass('wpc-tooltip-open');
                            });
                        }
                    });

                    $('.wpc-tooltip').on('click', function(e) {
                        e.stopPropagation();
                    });
                }

                if (typeof wp !== 'undefined' && typeof wp.codeEditor !== 'undefined') {
                    
                    var selectedTheme = '<?php echo isset($o["css_editor_theme"]) && !empty($o["css_editor_theme"]) ? esc_js($o["css_editor_theme"]) : "material-darker"; ?>';
                    
                    var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
                    editorSettings.codemirror = _.extend(
                        {},
                        editorSettings.codemirror,
                        {
                            mode: 'css',
                            theme: selectedTheme,
                            lineNumbers: true,
                            lineWrapping: true,
                            styleActiveLine: true,
                            matchBrackets: true,
                            autoCloseBrackets: true,
                            continueComments: true,
                            indentUnit: 2,
                            tabSize: 2,
                            indentWithTabs: false,
                            lint: true,
                            gutters: ["CodeMirror-lint-markers", "CodeMirror-linenumbers"],
                            extraKeys: {
                                "Ctrl-Space": "autocomplete",
                                "Ctrl-/": "toggleComment",
                                "Cmd-/": "toggleComment"
                            }
                        }
                    );

                    var cssEditor = $('#custom_css_editor');
                    if (cssEditor.length) {
                        var cmInstance = wp.codeEditor.initialize(cssEditor, editorSettings);

                        $('#css_editor_theme').on('change', function() {
                            var newTheme = $(this).val();
                            if (cmInstance && cmInstance.codemirror) {
                                cmInstance.codemirror.setOption('theme', newTheme);
                            }
                        });
                        
                        if (cmInstance && cmInstance.codemirror) {
                            enableMobilePaste(cmInstance.codemirror);
                            cmInstance.codemirror.on('change', function(cm) { cm.save(); });
                        }
                    }

                    var phpEditor = $('#custom_functions_editor');
                    if (phpEditor.length) {
                        
                        var phpEditorSettings = {
                            codemirror: {
                                mode: 'text/x-php',
                                lineNumbers: true,
                                lineWrapping: true,
                                indentUnit: 4,
                                indentWithTabs: false,
                                matchBrackets: true,
                                autoCloseBrackets: true,
                                theme: '<?php echo isset($o["php_editor_theme"]) && !empty($o["php_editor_theme"]) ? esc_js($o["php_editor_theme"]) : "material-darker"; ?>',
                                extraKeys: {
                                    "Ctrl-Space": "autocomplete"
                                }
                            }
                        };
                        
                        var phpCmInstance = wp.codeEditor.initialize(phpEditor, phpEditorSettings);

                        $('#php_editor_theme').on('change', function() {
                            var newTheme = $(this).val();
                            if (phpCmInstance && phpCmInstance.codemirror) {
                                phpCmInstance.codemirror.setOption('theme', newTheme);
                            }
                        });
                        
                        if (phpCmInstance && phpCmInstance.codemirror) {
                            enableMobilePaste(phpCmInstance.codemirror);
                            phpCmInstance.codemirror.on('change', function(cm) { cm.save(); });
                        }
                    }

                    var maintenanceHtmlEditor = $('#maintenance_html_editor');
                    if (maintenanceHtmlEditor.length) {
                        var maintenanceEditorSettings = {
                            codemirror: {
                                mode: 'htmlmixed',
                                lineNumbers: true,
                                lineWrapping: true,
                                indentUnit: 2,
                                indentWithTabs: false,
                                matchBrackets: true,
                                autoCloseBrackets: true,
                                theme: $('#maintenance_html_editor_theme').val() || 'material-darker',
                                extraKeys: {
                                    "Ctrl-Space": "autocomplete",
                                    "Ctrl-/": "toggleComment",
                                    "Cmd-/": "toggleComment"
                                }
                            }
                        };
                        
                        var maintenanceCmInstance = wp.codeEditor.initialize(maintenanceHtmlEditor, maintenanceEditorSettings);

                        $('#maintenance_html_editor_theme').on('change', function() {
                            var newTheme = $(this).val();
                            if (maintenanceCmInstance && maintenanceCmInstance.codemirror) {
                                maintenanceCmInstance.codemirror.setOption('theme', newTheme);
                            }
                        });

                        $('input[name="<?php echo esc_attr($this->option_name); ?>[maintenance_mode_type]"]').on('change', function() {
                            if ($(this).val() === 'advanced' && maintenanceCmInstance && maintenanceCmInstance.codemirror) {
                                
                                setTimeout(function() {
                                    maintenanceCmInstance.codemirror.refresh();
                                }, 10);
                            }
                        });
                        
                        if (maintenanceCmInstance && maintenanceCmInstance.codemirror) {
                            enableMobilePaste(maintenanceCmInstance.codemirror);
                            maintenanceCmInstance.codemirror.on('change', function(cm) { cm.save(); });
                        }
                    }

                    var loginCssEditor = $('#login_custom_css_editor');
                    if (loginCssEditor.length) {
                        var loginCssEditorSettings = {
                            codemirror: {
                                mode: 'css',
                                lineNumbers: true,
                                lineWrapping: true,
                                indentUnit: 2,
                                indentWithTabs: false,
                                matchBrackets: true,
                                autoCloseBrackets: true,
                                theme: $('#css_editor_theme').val() || 'material-darker',
                                extraKeys: {
                                    "Ctrl-Space": "autocomplete",
                                    "Ctrl-/": "toggleComment",
                                    "Cmd-/": "toggleComment"
                                }
                            }
                        };
                        
                        var loginCssCmInstance = wp.codeEditor.initialize(loginCssEditor, loginCssEditorSettings);

                        $('#css_editor_theme').on('change', function() {
                            var newTheme = $(this).val();
                            if (loginCssCmInstance && loginCssCmInstance.codemirror) {
                                loginCssCmInstance.codemirror.setOption('theme', newTheme);
                            }
                        });
                        
                        if (loginCssCmInstance && loginCssCmInstance.codemirror) {
                            enableMobilePaste(loginCssCmInstance.codemirror);
                            loginCssCmInstance.codemirror.on('change', function(cm) { cm.save(); });
                        }
                    }

                    function enableMobilePaste(cm) {
                        if (!cm) return;
                        
                        var wrapper = cm.getWrapperElement();
                        var textarea = wrapper.querySelector('textarea');
                        
                        if (textarea) {
                            textarea.setAttribute('contenteditable', 'true');
                            textarea.style.webkitUserSelect = 'text';
                            textarea.style.userSelect = 'text';
                            
                            if (!textarea._wpcPasteAttached) {
                                textarea._wpcPasteAttached = true;
                                
                                textarea.addEventListener('paste', function(e) {
                                    if (!('ontouchstart' in window) && !navigator.maxTouchPoints) return;
                                    
                                    e.preventDefault();
                                    e.stopImmediatePropagation();
                                    
                                    var text = '';
                                    if (e.clipboardData && e.clipboardData.getData) {
                                        text = e.clipboardData.getData('text/plain');
                                    } else if (window.clipboardData && window.clipboardData.getData) {
                                        text = window.clipboardData.getData('Text');
                                    }
                                    
                                    if (text) {
                                        var cursor = cm.getCursor();
                                        cm.replaceRange(text, cursor);
                                        cm.focus();
                                    }
                                });
                            }
                        }
                        
                        cm.on('focus', function() {
                            if (textarea) {
                                textarea.setAttribute('contenteditable', 'true');
                            }
                        });
                        
                        wrapper.addEventListener('touchstart', function(e) {
                            setTimeout(function() {
                                cm.focus();
                            }, 10);
                        }, {passive: true});
                    }

                    function makeEditorResizable(cmInstance) {
                        if (!cmInstance || !cmInstance.codemirror) return;
                        
                        var cm = cmInstance.codemirror;
                        var wrapper = cm.getWrapperElement();

                        var resizeHandle = document.createElement('div');
                        resizeHandle.className = 'cm-resize-handle';
                        resizeHandle.innerHTML = '';
                        wrapper.appendChild(resizeHandle);
                        
                        var startY, startHeight;
                        
                        resizeHandle.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            startY = e.clientY;
                            startHeight = wrapper.offsetHeight;
                            document.addEventListener('mousemove', onMouseMove);
                            document.addEventListener('mouseup', onMouseUp);
                            wrapper.classList.add('cm-resizing');
                        });
                        
                        function onMouseMove(e) {
                            var height = startHeight + (e.clientY - startY);
                            if (height >= 200 && height <= 1000) {
                                wrapper.style.height = height + 'px';
                                cm.setSize(null, height);
                                cm.refresh();
                            }
                        }
                        
                        function onMouseUp() {
                            document.removeEventListener('mousemove', onMouseMove);
                            document.removeEventListener('mouseup', onMouseUp);
                            wrapper.classList.remove('cm-resizing');
                        }
                    }

                    if (cmInstance) makeEditorResizable(cmInstance);
                    if (phpCmInstance) makeEditorResizable(phpCmInstance);
                    if (maintenanceCmInstance) makeEditorResizable(maintenanceCmInstance);
                    if (loginCssCmInstance) makeEditorResizable(loginCssCmInstance);
                    
                    function initSearch(cm, id) {
                        var state = { query: '', matches: [], idx: -1, marks: [] };
                        
                        function clear() {
                            state.marks.forEach(m => m.clear());
                            state.marks = [];
                        }
                        
                        function search(q) {
                            clear();
                            state.matches = [];
                            state.idx = -1;
                            
                            if (!q) {
                                $('#' + id + '-search-count').text('0/0');
                                $('#' + id + '-search-up, #' + id + '-search-down').prop('disabled', true);
                                return;
                            }
                            
                            var cursor = cm.getSearchCursor(q, null, true);
                            while (cursor.findNext()) {
                                var from = cursor.from();
                                var to = cursor.to();
                                state.matches.push({from, to});
                                state.marks.push(cm.markText(from, to, {className: 'CodeMirror-search-match'}));
                            }
                            
                            if (state.matches.length > 0) {
                                state.idx = 0;
                                highlight();
                            }
                            
                            update();
                        }
                        
                        function highlight() {
                            clear();
                            state.matches.forEach((m, i) => {
                                state.marks.push(cm.markText(m.from, m.to, {
                                    className: i === state.idx ? 'CodeMirror-search-match CodeMirror-selectedtext' : 'CodeMirror-search-match'
                                }));
                            });
                            if (state.matches[state.idx]) {
                                cm.scrollIntoView(state.matches[state.idx].from, 80);
                            }
                        }
                        
                        function update() {
                            var total = state.matches.length;
                            var curr = state.idx + 1;
                            $('#' + id + '-search-count').text(total > 0 ? curr + '/' + total : '0/0');
                            $('#' + id + '-search-up, #' + id + '-search-down').prop('disabled', total === 0);
                        }
                        
                        $('#' + id + '-search-toggle').click(function() {
                            var $toggle = $(this);
                            var $panel = $('#' + id + '-search-panel');
                            var $search = $toggle.find('.wpc-icon-search');
                            var $close = $toggle.find('.wpc-icon-close');
                            
                            if ($panel.is(':visible')) {
                                $panel.hide();
                                $search.show();
                                $close.hide();
                                $('#' + id + '-search-field').val('');
                                clear();
                                state = { query: '', matches: [], idx: -1, marks: [] };
                                update();
                            } else {
                                $panel.show();
                                $search.hide();
                                $close.show();
                                $('#' + id + '-search-field').focus();
                            }
                        });
                        
                        $('#' + id + '-search-field').on('input', function() {
                            search($(this).val());
                        }).on('keydown', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                if (state.matches.length === 0) return;
                                state.idx = e.shiftKey ? 
                                    (state.idx - 1 + state.matches.length) % state.matches.length : 
                                    (state.idx + 1) % state.matches.length;
                                highlight();
                                update();
                            } else if (e.key === 'Escape') {
                                $('#' + id + '-search-toggle').click();
                            }
                        });
                        
                        $('#' + id + '-search-down').click(function() {
                            if (state.matches.length === 0) return;
                            state.idx = (state.idx + 1) % state.matches.length;
                            highlight();
                            update();
                        });
                        
                        $('#' + id + '-search-up').click(function() {
                            if (state.matches.length === 0) return;
                            state.idx = (state.idx - 1 + state.matches.length) % state.matches.length;
                            highlight();
                            update();
                        });
                        
                        cm.addKeyMap({
                            'Ctrl-F': () => $('#' + id + '-search-toggle').click(),
                            'Cmd-F': () => $('#' + id + '-search-toggle').click()
                        });
                    }
                    
                    if (cmInstance && cmInstance.codemirror) initSearch(cmInstance.codemirror, 'css');
                    if (phpCmInstance && phpCmInstance.codemirror) initSearch(phpCmInstance.codemirror, 'php');
                    if (maintenanceCmInstance && maintenanceCmInstance.codemirror) initSearch(maintenanceCmInstance.codemirror, 'maintenance');
                    if (loginCssCmInstance && loginCssCmInstance.codemirror) initSearch(loginCssCmInstance.codemirror, 'login-css');
                }
            });

            function wpcSubmitBugReport() {
                var statusEl = document.getElementById('wpc-bug-report-status');
                var btnEl = document.getElementById('wpc-submit-bug-report');
                var emailEl = document.getElementById('wpc-bug-email');
                var messageEl = document.getElementById('wpc-bug-message');
                var screenshotEl = document.getElementById('wpc-bug-screenshot');
                
                var email = emailEl.value.trim();
                var message = messageEl.value.trim();

                if (!message) {
                    statusEl.className = 'wpc-bug-report-status error';
                    statusEl.textContent = '<?php echo esc_js($this->t('bug_report_error_empty')); ?>';
                    statusEl.style.display = 'block';
                    return;
                }

                if (!email) {
                    statusEl.className = 'wpc-bug-report-status error';
                    statusEl.textContent = '<?php echo esc_js($this->t('bug_report_error_email')); ?>';
                    statusEl.style.display = 'block';
                    return;
                }

                var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    statusEl.className = 'wpc-bug-report-status error';
                    statusEl.textContent = '<?php echo esc_js($this->t('bug_report_error_email')); ?>';
                    statusEl.style.display = 'block';
                    return;
                }

                if (screenshotEl.files.length > 0) {
                    var file = screenshotEl.files[0];
                    if (file.size > 5242880) { 
                        statusEl.className = 'wpc-bug-report-status error';
                        statusEl.textContent = 'Screenshot is too large. Max 5 MB.';
                        statusEl.style.display = 'block';
                        return;
                    }
                }

                btnEl.disabled = true;
                btnEl.textContent = '<?php echo esc_js($this->t('sending')); ?>...';

                var formData = new FormData();
                formData.append('action', 'wpc_submit_bug_report');
                formData.append('nonce', '<?php echo esc_attr( wp_create_nonce('wpc_bug_report') ); ?>');
                formData.append('email', email);
                formData.append('message', message);
                formData.append('system_info', JSON.stringify({
                    wp: '<?php echo esc_js(get_bloginfo('version')); ?>',
                    php: '<?php echo esc_js(PHP_VERSION); ?>',
                    plugin: '<?php echo esc_js(self::VERSION); ?>',
                    url: '<?php echo esc_js(home_url()); ?>'
                }));

                if (screenshotEl.files.length > 0) {
                    formData.append('screenshot', screenshotEl.files[0]);
                }

                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            statusEl.className = 'wpc-bug-report-status success';
                            statusEl.textContent = response.data.message;
                            statusEl.style.display = 'block';
                            document.getElementById('wpc-bug-report-form').reset();
                            setTimeout(function() {
                                document.getElementById('wpc-bug-report-modal').classList.remove('active');
                                statusEl.style.display = 'none';
                            }, 2000);
                        } else {
                            statusEl.className = 'wpc-bug-report-status error';
                            statusEl.textContent = response.data.message;
                            statusEl.style.display = 'block';
                        }
                    },
                    error: function() {
                        statusEl.className = 'wpc-bug-report-status error';
                        statusEl.textContent = '<?php echo esc_js($this->t('bug_report_error_send')); ?>';
                        statusEl.style.display = 'block';
                    },
                    complete: function() {
                        btnEl.disabled = false;
                        btnEl.textContent = '<?php echo esc_js($this->t('bug_report_send')); ?>';
                    }
                });
            }

            jQuery(document).ready(function($) {
                $('#wpc-bug-report-modal').on('click', function(e) {
                    if ($(e.target).is('#wpc-bug-report-modal')) {
                        $(this).removeClass('active');
                    }
                });

                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape' && $('#wpc-bug-report-modal').hasClass('active')) {
                        $('#wpc-bug-report-modal').removeClass('active');
                    }
                });

                var maintenanceFrame;
                $('#maintenance_image_upload').on('click', function(e) {
                    e.preventDefault();
                    
                    if (maintenanceFrame) {
                        maintenanceFrame.open();
                        return;
                    }
                    
                    maintenanceFrame = wp.media({
                        title: '<?php echo esc_js($this->t('maintenance_image_upload')); ?>',
                        button: {
                            text: '<?php echo esc_js($this->t('maintenance_image_upload')); ?>'
                        },
                        multiple: false
                    });
                    
                    maintenanceFrame.on('select', function() {
                        var attachment = maintenanceFrame.state().get('selection').first().toJSON();
                        $('#maintenance_image').val(attachment.url);
                        
                        var maxWidth = $('input[name="<?php echo esc_attr($this->option_name); ?>[maintenance_image_max_width]"]').val();
                        var styleAttr = maxWidth ? 'width: 100%; max-width: ' + parseInt(maxWidth) + 'px; height: auto;' : 'max-width: 100%; height: auto;';
                        styleAttr += ' display: block; margin-bottom: 10px; border: 1px solid #c3c4c7; padding: 5px;';

                        if ($('#maintenance_image_preview').length) {
                            $('#maintenance_image_preview').attr('src', attachment.url).attr('style', styleAttr);
                        } else {
                            $('<img id="maintenance_image_preview" src="' + attachment.url + '" style="' + styleAttr + '">').insertBefore('#maintenance_image_upload');
                        }
                        $('#maintenance_image_remove').show();
                    });
                    
                    maintenanceFrame.open();
                });
                
                $('#maintenance_image_remove').on('click', function(e) {
                    e.preventDefault();
                    $('#maintenance_image').val('');
                    $('#maintenance_image_preview').remove();
                    $(this).hide();
                });

                var loginLogoFrame;
                $('#login_logo_upload').on('click', function(e) {
                    e.preventDefault();
                    
                    if (loginLogoFrame) {
                        loginLogoFrame.open();
                        return;
                    }
                    
                    loginLogoFrame = wp.media({
                        title: '<?php echo esc_js($this->t('login_logo_upload')); ?>',
                        button: {
                            text: '<?php echo esc_js($this->t('login_logo_upload')); ?>'
                        },
                        multiple: false
                    });
                    
                    loginLogoFrame.on('select', function() {
                        var attachment = loginLogoFrame.state().get('selection').first().toJSON();
                        $('#login_logo').val(attachment.url);
                        
                        var logoHeight = $('input[name="<?php echo esc_attr($this->option_name); ?>[login_logo_height]"]').val() || 100;
                        var styleAttr = 'height: ' + parseInt(logoHeight) + 'px; width: auto;';
                        styleAttr += ' display: block; margin-bottom: 10px; border: 1px solid #c3c4c7; padding: 5px;';

                        if ($('#login_logo_preview').length) {
                            $('#login_logo_preview').attr('src', attachment.url).attr('style', styleAttr);
                        } else {
                            $('<img id="login_logo_preview" src="' + attachment.url + '" style="' + styleAttr + '">').insertBefore('#login_logo_upload');
                        }
                        $('#login_logo_remove').show();
                    });
                    
                    loginLogoFrame.open();
                });
                
                $('#login_logo_remove').on('click', function(e) {
                    e.preventDefault();
                    $('#login_logo').val('');
                    $('#login_logo_preview').remove();
                    $(this).hide();
                });

                var loginBgFrame;
                $('#login_bg_image_upload').on('click', function(e) {
                    e.preventDefault();
                    
                    if (loginBgFrame) {
                        loginBgFrame.open();
                        return;
                    }
                    
                    loginBgFrame = wp.media({
                        title: '<?php echo esc_js($this->t('login_bg_image_upload')); ?>',
                        button: {
                            text: '<?php echo esc_js($this->t('login_bg_image_upload')); ?>'
                        },
                        multiple: false
                    });
                    
                    loginBgFrame.on('select', function() {
                        var attachment = loginBgFrame.state().get('selection').first().toJSON();
                        $('#login_bg_image').val(attachment.url);
                        
                        var styleAttr = 'max-width: 200px; display: block; margin-bottom: 10px; border: 1px solid #c3c4c7; padding: 5px; height: auto;';

                        if ($('#login_bg_image_preview').length) {
                            $('#login_bg_image_preview').attr('src', attachment.url).attr('style', styleAttr);
                        } else {
                            $('<img id="login_bg_image_preview" src="' + attachment.url + '" style="' + styleAttr + '">').insertBefore('#login_bg_image_upload');
                        }
                        $('#login_bg_image_remove').show();
                    });
                    
                    loginBgFrame.open();
                });
                
                $('#login_bg_image_remove').on('click', function(e) {
                    e.preventDefault();
                    $('#login_bg_image').val('');
                    $('#login_bg_image_preview').remove();
                    $(this).hide();
                });

                $('#wpc-main-form').on('submit', function(e) {
                    e.preventDefault();
                    var pos = $(window).scrollTop();
                    var $btn = $('#submit');
                    var txt = $btn.val();

                    if (typeof cmInstance !== 'undefined' && cmInstance && cmInstance.codemirror) cmInstance.codemirror.save();
                    if (typeof phpCmInstance !== 'undefined' && phpCmInstance && phpCmInstance.codemirror) phpCmInstance.codemirror.save();
                    if (typeof loginCssCmInstance !== 'undefined' && loginCssCmInstance && loginCssCmInstance.codemirror) loginCssCmInstance.codemirror.save();
                    if (typeof maintenanceCmInstance !== 'undefined' && maintenanceCmInstance && maintenanceCmInstance.codemirror) maintenanceCmInstance.codemirror.save();

                    $btn.prop('disabled', true).val('<?php echo esc_js($this->t("saving")); ?>...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: $(this).serialize() + '&action=wpc_save_settings',
                        success: function(r) {
                            $btn.prop('disabled', false).val(txt);
                            if (r.success) {
                                notify(r.data.message, 'success');
                                setTimeout(function() { $(window).scrollTop(pos); }, 50);
                            } else {
                                notify(r.data.message || 'Error', 'error');
                            }
                        },
                        error: function() {
                            $btn.prop('disabled', false).val(txt);
                            notify('Network error', 'error');
                        }
                    });
                });
                
                function notify(msg, type) {
                    $('.wpc-notify').remove();
                    var $n = $('<div class="wpc-notify ' + type + '">' + msg + '</div>');
                    $('body').append($n);
                    setTimeout(function() { $n.addClass('show'); }, 10);
                    setTimeout(function() { 
                        $n.removeClass('show'); 
                        setTimeout(function() { $n.remove(); }, 300);
                    }, 3000);
                }
            });
        </script>
        <?php
    }

    public function remove_admin_bar_links() {
        global $wp_admin_bar;
        $o = get_option($this->option_name, array());
        $items = isset($o['admin_bar_items']) ? $o['admin_bar_items'] : array();

        if (!is_array($items)) {
            $items = array();
        }
        
        $map = array('wp-logo' => array('wp-logo', 'about', 'wporg', 'documentation', 'support-forums', 'feedback'),
                    'updates' => array('updates'), 'comments' => array('comments'), 
                    'new-content' => array('new-content'), 'view-site' => array('view-site'));
        
        foreach ($items as $item) {
            if (isset($map[$item])) {
                foreach ($map[$item] as $menu) $wp_admin_bar->remove_menu($menu);
            }
        }

        if (in_array('my-account-avatar', $items)) {
            echo '<style>
                @media screen and (min-width: 783px) {
                    #wp-admin-bar-my-account > .ab-item:before {
                        display: none !important;
                    }
                    #wpadminbar #wp-admin-bar-my-account.with-avatar>.ab-empty-item img,
                    #wpadminbar #wp-admin-bar-my-account.with-avatar>a img {
                        display: none !important;
                    }
                }
            </style>';
        }

        $this->add_sticky_nav_script();
    }
    
    public function unset_url_field($fields) {
        if (isset($fields['url'])) unset($fields['url']);
        return $fields;
    }

    public function remove_comments_menu() {
        remove_menu_page('edit-comments.php');
    }

    public function remove_comments_admin_bar() {
        global $wp_admin_bar;
        $wp_admin_bar->remove_menu('comments');
    }
    
    public function disable_user_endpoints($endpoints) {
        
        if (isset($endpoints['/wp/v2/users'])) {
            unset($endpoints['/wp/v2/users']);
        }
        if (isset($endpoints['/wp/v2/users/(?P<id>[\d]+)'])) {
            unset($endpoints['/wp/v2/users/(?P<id>[\d]+)']);
        }
        return $endpoints;
    }
    
    public function disable_author_archives() {
        
        if (is_author()) {
            wp_safe_redirect(home_url(), 301);
            exit;
            exit;
        }
    }
    
    public function delete_unnecessary_files() {
        $files_to_delete = array(
            ABSPATH . 'license.txt',
            ABSPATH . 'readme.html',
            ABSPATH . 'wp-config-sample.php'
        );
        
        foreach ($files_to_delete as $file) {
            if (file_exists($file) && is_writable($file)) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
                @wp_delete_file($file);
            }
        }
    }
    
    public function wpforms_restrict_countries() {
        $o = get_option($this->option_name, array());
        $mode = isset($o['restrict_wpforms_countries']) ? $o['restrict_wpforms_countries'] : 'czsk';
        $mode = in_array($mode, array('czsk', 'europe', 'us'), true) ? $mode : 'czsk';
        ?>
        <script type="text/javascript">
        (function($) {
            var GROUPS = {
                czsk:   { countries: ['cz','sk'], initial: 'cz' },
                europe: { countries: ['al','ad','at','by','be','ba','bg','hr','cy','cz','dk','ee','fi','fr','de','gi','gr','va','hu','is','ie','it','xk','lv','li','lt','lu','mt','md','mc','me','nl','mk','no','pl','pt','ro','sm','rs','sk','si','es','se','ch','ua','gb'], initial: 'cz' },
                us:     { countries: ['us','ca'], initial: 'us' }
            };
            var mode  = <?php echo json_encode($mode); ?>;
            var group = GROUPS[mode] || GROUPS.czsk;

            var restricted = typeof WeakSet !== 'undefined' ? new WeakSet() : null;
            var restrictedArr = [];
            function isRestricted(el) { return restricted ? restricted.has(el) : restrictedArr.indexOf(el) !== -1; }
            function markRestricted(el) { if (restricted) restricted.add(el); else restrictedArr.push(el); }

            function injectOpts(opts) {
                opts = $.extend({}, opts || {});
                opts.onlyCountries = group.countries;
                var ic = (opts.initialCountry || '').toLowerCase();
                if (!ic || group.countries.indexOf(ic) === -1) opts.initialCountry = group.initial;
                return opts;
            }

            // --- Intercept window.intlTelInput (modern / SureForms) ---
            if (window.intlTelInput) {
                var _origFn = window.intlTelInput;
                window.intlTelInput = function(input, opts) {
                    var instance = _origFn.call(this, input, injectOpts(opts));
                    if (input) markRestricted(input);
                    return instance;
                };
                try { Object.keys(_origFn).forEach(function(k) { window.intlTelInput[k] = _origFn[k]; }); } catch(e) {}
            }

            // --- Intercept $.fn.intlTelInput (jQuery plugin / WPForms, Fluent Forms) ---
            if ($.fn && typeof $.fn.intlTelInput === 'function') {
                var _origJq = $.fn.intlTelInput;
                $.fn.intlTelInput = function(opts) {
                    if (typeof opts === 'object' || opts === undefined || opts === null) {
                        opts = injectOpts(opts);
                        var self = this;
                        setTimeout(function() { self.each(function() { markRestricted(this); }); }, 0);
                    }
                    return _origJq.apply(this, arguments);
                };
            }

            // --- Fallback: fix already-initialized instances (WPForms fires wpformsReady after init) ---
            function applyToInstance(input) {
                if (isRestricted(input)) return;

                var iti = null;
                if (window.intlTelInputGlobals && intlTelInputGlobals.getInstance) {
                    iti = intlTelInputGlobals.getInstance(input);
                }
                if (!iti && $.fn && $.fn.intlTelInput) {
                    iti = $(input).data('plugin_intlTelInput');
                }
                if (!iti) return;

                markRestricted(input);

                if (typeof iti.setOptions === 'function') {
                    iti.setOptions({ onlyCountries: group.countries });
                    var cur = iti.getSelectedCountryData ? iti.getSelectedCountryData() : null;
                    if (!cur || !cur.iso2 || group.countries.indexOf(cur.iso2) === -1) {
                        if (typeof iti.setCountry === 'function') iti.setCountry(group.initial);
                    }
                } else {
                    var options = {};
                    if (iti.options) options = $.extend({}, iti.options);
                    else if (iti.s) options = $.extend({}, iti.s);
                    options.onlyCountries = group.countries;
                    if (!options.initialCountry || group.countries.indexOf(options.initialCountry) === -1) {
                        options.initialCountry = group.initial;
                    }
                    var isJq = !!$(input).data('plugin_intlTelInput');
                    if (isJq) {
                        $(input).intlTelInput('destroy');
                        $(input).intlTelInput(options);
                        if ($(input).hasClass('wpforms-smart-phone-field') && options.hiddenInput) {
                            $(input).siblings('input[type="hidden"]').attr('name', 'wpforms[fields][' + options.hiddenInput + ']');
                        }
                    } else {
                        if (typeof iti.destroy === 'function') iti.destroy();
                        if (window.intlTelInput) window.intlTelInput(input, options);
                    }
                }
            }

            function scanAll() {
                document.querySelectorAll('input[type="tel"]').forEach(function(input) {
                    applyToInstance(input);
                });
            }

            // Retries — pro async init (SureForms geoIP, Fluent Forms lazy load)
            [0, 150, 400, 900, 2000].forEach(function(d) { setTimeout(scanAll, d); });

            $(document).on('wpformsReady', scanAll);
            $(document).on('fluentform_init ff:formInit', scanAll);

            // MutationObserver — dynamické formuláře (modaly, AJAX)
            if (window.MutationObserver && document.body) {
                new MutationObserver(function(mutations) {
                    var hasTel = mutations.some(function(m) {
                        return Array.prototype.some.call(m.addedNodes, function(node) {
                            return node.nodeType === 1 && (
                                (node.querySelector && node.querySelector('input[type="tel"]')) ||
                                (node.nodeName === 'INPUT' && node.type === 'tel')
                            );
                        });
                    });
                    if (hasTel) { [150, 400, 900].forEach(function(d) { setTimeout(scanAll, d); }); }
                }).observe(document.body, { childList: true, subtree: true });
            }

        })(jQuery);
        </script>
        <?php
    }

    private function add_sticky_nav_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            var sections = $('.wpc-section[id^="section-"]');
            var navItems = $('.wpc-nav-item');
            var navEl = document.getElementById('wpc-sticky-nav-content');
            var dragMoved = false;
            
            if (navEl) {
                var isDown = false;
                var startX = 0;
                var startScrollLeft = 0;

                navEl.addEventListener('mousedown', function(e) {
                    if (e.button !== 0) return;
                    isDown = true;
                    dragMoved = false;
                    startX = e.clientX;
                    startScrollLeft = navEl.scrollLeft;
                    navEl.classList.add('is-dragging');
                    e.preventDefault();
                });

                document.addEventListener('mousemove', function(e) {
                    if (!isDown) return;
                    var delta = startX - e.clientX;
                    if (Math.abs(delta) > 4) dragMoved = true;
                    navEl.scrollLeft = startScrollLeft + delta;
                });

                document.addEventListener('mouseup', function() {
                    if (!isDown) return;
                    isDown = false;
                    navEl.classList.remove('is-dragging');
                    setTimeout(function() { dragMoved = false; }, 0);
                });
            }

            $(window).on('scroll', function() {
                var scrollPos = $(window).scrollTop() + 150;
                
                sections.each(function() {
                    var section = $(this);
                    var sectionTop = section.offset().top;
                    var sectionBottom = sectionTop + section.outerHeight();
                    var sectionId = section.attr('id');
                    
                    if (scrollPos >= sectionTop && scrollPos < sectionBottom) {
                        navItems.removeClass('active');
                        var activeItem = $('.wpc-nav-item[href="#' + sectionId + '"]');
                        activeItem.addClass('active');
                        if (navEl && activeItem.length) {
                            var itemEl = activeItem[0];
                            var itemLeft = itemEl.offsetLeft;
                            var itemRight = itemLeft + itemEl.offsetWidth;
                            var visLeft = navEl.scrollLeft;
                            var visRight = visLeft + navEl.clientWidth;
                            if (itemLeft < visLeft) {
                                navEl.scrollLeft = itemLeft - 16;
                            } else if (itemRight > visRight) {
                                navEl.scrollLeft = itemRight - navEl.clientWidth + 16;
                            }
                        }
                    }
                });
            });

            navEl && navEl.querySelectorAll('.wpc-nav-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (dragMoved) return;
                    var target = document.querySelector(item.getAttribute('href'));
                    if (target) {
                        var top = target.getBoundingClientRect().top + window.pageYOffset - 100;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                    }
                });
            });
        });
        </script>
        <style>
        .wpc-nav-item.active {
            color: <?php echo esc_attr($this->get_primary_color()); ?>;
            background: #f6f7f7;
        }
        .wpc-nav-item:focus {
            box-shadow: none;
            outline: none;
        }
        </style>
        <?php
    }
    
    private function get_primary_color() {
        global $_wp_admin_css_colors;
        $admin_color = get_user_option('admin_color');
        $color_scheme = isset($_wp_admin_css_colors[$admin_color]) ? $_wp_admin_css_colors[$admin_color] : $_wp_admin_css_colors['fresh'];
        return $color_scheme->colors[2];
    }

    public function posts_status_color() {
        $o = get_option($this->option_name, array());
        echo '<style>';
        if (!empty($o['color_draft'])) echo '.status-draft{background:' . esc_attr($o['color_draft']) . '!important;}';
        if (!empty($o['color_pending'])) echo '.status-pending{background:' . esc_attr($o['color_pending']) . '!important;}';
        if (!empty($o['color_publish'])) echo '.status-publish{background:' . esc_attr($o['color_publish']) . '!important;}';
        if (!empty($o['color_future'])) echo '.status-future{background:' . esc_attr($o['color_future']) . '!important;}';
        if (!empty($o['color_private'])) echo '.status-private{background:' . esc_attr($o['color_private']) . '!important;}';
        echo '</style>';
    }
    
    public function custom_edit_link() {
        if (is_user_logged_in() && current_user_can('edit_post', get_the_ID())) {
            $link = get_edit_post_link(get_the_ID());

            $user_locale = get_user_locale();

            $button_texts = array(
                'de' => 'Bearbeiten',
                'cs' => 'Upravit',
                'sk' => 'Upraviť',
                'pl' => 'Edytuj',
                'en' => 'Edit',
            );

            $lang_code = substr($user_locale, 0, 2);
            $button_text = isset($button_texts[$lang_code]) ? $button_texts[$lang_code] : 'Edit';
            
            if ($link) echo '<a href="' . esc_url($link) . '" class="custom-edit-link">' . esc_html($button_text) . '</a>';
        }
    }
    
    public function custom_edit_link_styles() {
        echo '<style>.custom-edit-link{position:fixed!important;bottom:0!important;left:0!important;background:#eef2f0;color:#000;padding:8px 15px;font-size:0.7em;font-weight:500;text-decoration:none;z-index:99999;}.custom-edit-link:hover{text-decoration:underline;}</style>';
    }
    
    public function custom_archive_title($title) {
        $o = get_option($this->option_name, array());
        if (is_category()) {
            $prefix = isset($o['archive_category_prefix']) ? $o['archive_category_prefix'] : '';
            $title = $prefix . single_cat_title('', false);
        } elseif (is_tag()) {
            $prefix = isset($o['archive_tag_prefix']) ? $o['archive_tag_prefix'] : '';
            $title = $prefix . single_tag_title('', false);
        } elseif (is_author()) {
            $title = get_the_author();
        }
        return $title;
    }
    
    public function year_shortcode() {
        if (is_admin()) {
            return '[year]';
        }
        
        if (is_customize_preview()) {
            return '[year]';
        }
        
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return '[year]';
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            if (is_user_logged_in() && !empty($_SERVER['HTTP_REFERER']) && 
                strpos(sanitize_text_field(wp_unslash($_SERVER['HTTP_REFERER'])), '/wp-admin/') !== false) {
                return '[year]';
            }
        }
        
        if (!empty($_SERVER['REQUEST_URI'])) {
            $uri = sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI']));
            if (strpos($uri, '/wp-admin/') !== false || 
                strpos($uri, 'customize.php') !== false ||
                strpos($uri, 'admin-ajax.php') !== false && is_user_logged_in()) {
                return '[year]';
            }
        }
        
        return gmdate('Y');
    }
    
    public function safe_do_shortcode_wrapper($content) {
        if (is_admin() || 
            is_customize_preview() || 
            (defined('REST_REQUEST') && REST_REQUEST) ||
            (defined('DOING_AJAX') && DOING_AJAX && is_user_logged_in())) {
            return $content;
        }
        
        return do_shortcode($content);
    }
    
    public function start_output_buffer() {

        if (!is_admin()) {
            ob_start();
        }
    }
    
    public function end_output_buffer() {
        
        if (!is_admin() && ob_get_level() > 0) {
            $buffer = ob_get_clean();
            
            if (strpos($buffer, '[year]') !== false) {
                $buffer = do_shortcode($buffer);
            }
            echo $buffer; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- buffer contains full page HTML
        }
    }
    
    public function translate_gettext($translated, $text = '', $domain = '', $context = '') {
        
        static $cache = array();
        static $translations_loaded = false;
        static $translations_data = null;

        if (!$translations_loaded) {
            $o = get_option($this->option_name, array());
            $translations_data = (!empty($o['translations']) && is_array($o['translations'])) ? $o['translations'] : array();
            $translations_loaded = true;
        }
        
        if (empty($translations_data)) {
            return $translated;
        }

        $cache_key = md5($translated . '|' . $text);
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        foreach ($translations_data as $t) {
            if (!empty($t['from']) && !empty($t['to'])) {
                
                if (strcasecmp($text, $t['from']) === 0) {
                    $cache[$cache_key] = $t['to'];
                    return $t['to'];
                }
                
                if (strcasecmp($translated, $t['from']) === 0) {
                    $cache[$cache_key] = $t['to'];
                    return $t['to'];
                }
            }
        }

        $cache[$cache_key] = $translated;
        return $translated;
    }
    
    public function translate_ngettext($translated, $single, $plural, $number, $domain) {
        return $this->translate_gettext($translated, $single, $domain);
    }

    public function translate_text_only($text, $term = null) {
        
        if (empty($text) || !is_string($text)) {
            return $text;
        }

        if (wp_strip_all_tags($text) !== $text) {
            return $text;
        }
        
        $o = get_option($this->option_name, array());
        if (empty($o['translations']) || !is_array($o['translations'])) {
            return $text;
        }

        foreach ($o['translations'] as $t) {
            if (!empty($t['from']) && !empty($t['to'])) {
                $text = str_ireplace($t['from'], $t['to'], $text);
            }
        }
        
        return $text;
    }

    public function apply_custom_translations($content) {
        $o = get_option($this->option_name, array());
        if (empty($o['translations']) || !is_array($o['translations'])) {
            return $content;
        }

        $style_blocks = array();
        $script_blocks = array();

        $content = preg_replace_callback('/<style[^>]*>.*?<\/style>/is', function($matches) use (&$style_blocks) {
            $placeholder = '___STYLE_BLOCK_' . count($style_blocks) . '___';
            $style_blocks[$placeholder] = $matches[0];
            return $placeholder;
        }, $content);

        $content = preg_replace_callback('/<script[^>]*>.*?<\/script>/is', function($matches) use (&$script_blocks) {
            $placeholder = '___SCRIPT_BLOCK_' . count($script_blocks) . '___';
            $script_blocks[$placeholder] = $matches[0];
            return $placeholder;
        }, $content);

        foreach ($o['translations'] as $t) {
            if (!empty($t['from']) && !empty($t['to'])) {
                $content = str_ireplace($t['from'], $t['to'], $content);
            }
        }

        foreach ($style_blocks as $placeholder => $original) {
            $content = str_replace($placeholder, $original, $content);
        }

        foreach ($script_blocks as $placeholder => $original) {
            $content = str_replace($placeholder, $original, $content);
        }
        
        return $content;
    }

    public function hide_updates_non_admin() {
        if (!current_user_can('administrator')) {
            remove_action('admin_notices', 'update_nag', 3);
            echo '<style>#update-nag, .update-nag, .notice.is-dismissible { display: none !important; }</style>';
        }
    }
    
    public function hide_admin_notices() {
        $o = get_option($this->option_name, array());
        $current_uid = get_current_user_id();
        $exempt_uid  = !empty($o['notices_user_id']) ? intval($o['notices_user_id']) : 0;
        if (!empty($o['show_notices_current_user']) && $exempt_uid && $current_uid === $exempt_uid) {
            return;
        }
        echo '<style>.notice,.notice-info,.notice-success,.notice-warning,.notice-error,div.updated,div.error{display:none!important;}</style>';
    }

    public function hide_empty_dashboard_containers() {
        echo '<style>#dashboard-widgets .postbox-container .empty-container{display:none;}</style>';
    }

    public function detect_dashboard_widgets() {
        global $wp_meta_boxes;
        $widgets = array();
        if (is_callable('wp_welcome_panel') || has_action('welcome_panel')) {
            $widgets['welcome_panel'] = 'Vítejte ve WordPressu';
        }
        if (!empty($wp_meta_boxes['dashboard'])) {
            foreach ($wp_meta_boxes['dashboard'] as $context => $priorities) {
                foreach ($priorities as $priority => $boxes) {
                    foreach ($boxes as $id => $box) {
                        if (empty($box)) continue;
                        $title = isset($box['title']) ? wp_strip_all_tags($box['title']) : $id;
                        $widgets[$id] = trim($title);
                    }
                }
            }
        }
        if (!empty($widgets)) {
            set_transient('wpc_detected_widgets', $widgets, WEEK_IN_SECONDS);
        }
    }

    public function remove_dashboard_widgets() {
        global $wp_meta_boxes;
        $o = get_option($this->option_name, array());
        $hidden = isset($o['hidden_dashboard_widgets']) ? (array) $o['hidden_dashboard_widgets'] : array();
        if (in_array('welcome_panel', $hidden)) {
            remove_action('welcome_panel', 'wp_welcome_panel');
        }
        if (empty($wp_meta_boxes['dashboard'])) return;
        foreach ($hidden as $widget_id) {
            if ($widget_id === 'welcome_panel') continue;
            foreach (array('normal', 'side', 'column3', 'column4') as $context) {
                foreach (array('high', 'core', 'default', 'low') as $priority) {
                    if (!empty($wp_meta_boxes['dashboard'][$context][$priority][$widget_id])) {
                        remove_meta_box($widget_id, 'dashboard', $context);
                    }
                }
            }
        }
    }
    
    public function custom_admin_title($admin_title, $title) {
        $o = get_option($this->option_name, array());
        $format = isset($o['admin_page_title_format']) && !empty($o['admin_page_title_format']) 
                  ? $o['admin_page_title_format'] 
                  : '%page% - %site_title%';
        
        $site_title = get_bloginfo('name');
        $page_title = $title;

        $custom_title = str_replace('%site_title%', $site_title, $format);
        $custom_title = str_replace('%page%', $page_title, $custom_title);

        return esc_html($custom_title);
    }
    
    public function custom_email_from_name($name) {
        $o = get_option($this->option_name, array());
        if (isset($o['wp_email_from_name']) && $o['wp_email_from_name'] !== '') {
            return sanitize_text_field($o['wp_email_from_name']);
        }
        return $name;
    }
    
    public function custom_email_from($email) {
        $o = get_option($this->option_name, array());
        if (isset($o['wp_email_from_email']) && $o['wp_email_from_email'] !== '') {
            return sanitize_email($o['wp_email_from_email']);
        }
        return $email;
    }
    
    public function custom_phpmailer_from($phpmailer) {
        $o = get_option($this->option_name, array());

        $phpmailer->clearReplyTos();
        
        if (isset($o['wp_email_from_email']) && $o['wp_email_from_email'] !== '') {
            $from_email = sanitize_email($o['wp_email_from_email']);
            $from_name = isset($o['wp_email_from_name']) && $o['wp_email_from_name'] !== '' 
                ? sanitize_text_field($o['wp_email_from_name']) 
                : 'WordPress';

            $phpmailer->From = $from_email;
            $phpmailer->FromName = $from_name;

            $phpmailer->Sender = $from_email;

            try {
                $phpmailer->setFrom($from_email, $from_name, false);
            } catch (Exception $e) {
                
            }
        }
    }
    
    public function force_email_from($args) {
        $o = get_option($this->option_name, array());

        if (!empty($args['headers'])) {
            if (is_string($args['headers'])) {
                $args['headers'] = explode("\n", $args['headers']);
            }

            $args['headers'] = array_filter($args['headers'], function($header) {
                return stripos($header, 'From:') !== 0;
            });

            if (isset($o['wp_email_from_email']) && $o['wp_email_from_email'] !== '') {
                $from_name = isset($o['wp_email_from_name']) && $o['wp_email_from_name'] !== '' 
                    ? sanitize_text_field($o['wp_email_from_name']) 
                    : 'WordPress';
                $from_email = sanitize_email($o['wp_email_from_email']);
                $args['headers'][] = 'From: ' . $from_name . ' <' . $from_email . '>';
            }
        } else {
            
            if (isset($o['wp_email_from_email']) && $o['wp_email_from_email'] !== '') {
                $from_name = isset($o['wp_email_from_name']) && $o['wp_email_from_name'] !== '' 
                    ? sanitize_text_field($o['wp_email_from_name']) 
                    : 'WordPress';
                $from_email = sanitize_email($o['wp_email_from_email']);
                $args['headers'] = array('From: ' . $from_name . ' <' . $from_email . '>');
            }
        }
        
        return $args;
    }
    
    public function hide_howdy_text($translated, $text, $domain) {
        if (is_admin() && $text === 'Howdy, %s') {
            return '%s';
        }
        if (is_admin() && strpos($text, 'Přihlášený uživatel') !== false) {
            return str_replace('Přihlášený uživatel, ', '', $text);
        }
        return $translated;
    }
    
    public function modify_plugin_links($links, $file) {
        if ($file === plugin_basename(__FILE__)) {
            
            $custom_url = 'https://kacer.studio/wpadminstudio';

            foreach ($links as $key => $link) {
                if (strpos($link, 'thickbox') !== false || 
                    strpos($link, 'plugin-information') !== false ||
                    strpos($link, 'Version') !== false ||
                    strpos($link, 'Verze') !== false ||
                    strpos($link, 'Wersja') !== false ||
                    strpos($link, 'Verzia') !== false) {
                    unset($links[$key]);
                }
            }

            array_unshift($links, 'Verze ' . self::VERSION);
            array_unshift($links, '<a href="' . esc_url($custom_url) . '" target="_blank">' . __('View details', 'wp-admin-studio') . '</a>');
        }
        return $links;
    }

    public function preserve_google_maps_key($options) {
        $our_options = get_option($this->option_name, array());
        if (isset($our_options['google_maps_api_key']) && $our_options['google_maps_api_key'] !== '') {
            $options['google-maps-api-key'] = $our_options['google_maps_api_key'];
        }
        return $options;
    }

    public function process_salient_shortcodes($options) {
        if (!is_array($options)) {
            return $options;
        }
        
        if (is_admin() || is_customize_preview() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return $options;
        }

        $keys_to_process = array(
            'footer-copyright-text',
            'footer_copyright_text',
            'copyright-text',
            'cta-text',
            'footer-copyright',
            'copyright'
        );
        
        foreach ($keys_to_process as $key) {
            if (isset($options[$key]) && is_string($options[$key]) && !empty($options[$key])) {
                
                $options[$key] = do_shortcode($options[$key]);
            }
        }
        
        return $options;
    }

    public function restore_google_maps_key_after_save($old_value, $new_value) {
        
        if ($this->restoring_maps_key) {
            return;
        }
        
        $this->restoring_maps_key = true;
        $our_options = get_option($this->option_name, array());

        if (isset($our_options['google_maps_api_key']) && $our_options['google_maps_api_key'] !== '') {
            if (!isset($new_value['google-maps-api-key']) || $new_value['google-maps-api-key'] === '') {
                
                $new_value['google-maps-api-key'] = $our_options['google_maps_api_key'];
                update_option('salient_redux', $new_value);
            }
        }
        
        $this->restoring_maps_key = false;
    }
    
    public function insert_custom_css() {
        $o = get_option($this->option_name, array());
        if (!empty($o['custom_css_code'])) {
            echo "\n<!-- WP Admin Studio: Custom CSS -->\n";
            echo "<style>\n";
            echo $o['custom_css_code']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw CSS in style tag
            echo "\n</style>\n";
            echo "<!-- /WP Admin Studio -->\n";
        }
    }
    
    public function insert_head_code() {
        $o = get_option($this->option_name, array());
        if (!empty($o['script_head_code'])) {
            echo "\n<!-- WP Admin Studio: Custom Head Code -->\n";
            echo wp_unslash($o['script_head_code']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional raw code output
            echo "\n<!-- /WP Admin Studio -->\n";
        }
    }
    
    public function insert_google_maps_api() {
        $o = get_option($this->option_name, array());
        if (isset($o['google_maps_api_key']) && $o['google_maps_api_key'] !== '') {
            $api_key = sanitize_text_field($o['google_maps_api_key']);

            $current_theme = wp_get_theme();
            $theme_name = $current_theme->get('Name');
            $parent_theme = $current_theme->parent();
            
            if ($theme_name === 'Salient' || ($parent_theme && $parent_theme->get('Name') === 'Salient')) {

                echo "\n<!-- WP Admin Studio: Google Maps API Key configured for Salient theme in Redux options -->\n";
            } else {
                
                echo "\n<!-- WP Admin Studio: Google Maps API -->\n";
                echo '<script src="https://maps.googleapis.com/maps/api/js?key=' . esc_attr($api_key) . '" async defer></script>' . "\n"; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
                echo "<!-- /WP Admin Studio -->\n";
            }
        }
    }
    
    public function insert_body_start_code() {
        $o = get_option($this->option_name, array());
        if (!empty($o['script_body_start_code'])) {
            echo "\n<!-- WP Admin Studio: Custom Body Start Code -->\n";
            echo wp_unslash($o['script_body_start_code']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional raw code output
            echo "\n<!-- /WP Admin Studio -->\n";
        }
    }
    
    public function execute_custom_functions() {
        $o = get_option($this->option_name, array());
        if (empty($o['custom_functions_code'])) {
            return;
        }

        // Custom PHP code is only settable by administrators with manage_options.
        // We verify the option was saved by a trusted source (WordPress options API)
        // and is not user-supplied input at runtime.
        $code = wp_unslash($o['custom_functions_code']);

        try {
            eval($code); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found,Squiz.PHP.Eval.Discouraged -- Code stored by admin via settings page, equivalent to functions.php
        } catch (ParseError $e) {
            error_log('WP Admin Studio Custom Functions Parse Error: ' . $e->getMessage() . ' on line ' . $e->getLine());
        } catch (Error $e) {
            error_log('WP Admin Studio Custom Functions Fatal Error: ' . $e->getMessage());
        } catch (Exception $e) {
            error_log('WP Admin Studio Custom Functions Error: ' . $e->getMessage());
        }
    }

    public function get_default_maintenance_html() {
        return '';
    }
    
    public function show_maintenance_mode() {
        $o = get_option($this->option_name, array());

        if (current_user_can('manage_options')) {
            return;
        }

        if (!empty($o['maintenance_show_logged']) && is_user_logged_in()) {
            
        } else if (is_user_logged_in()) {
            
            return;
        }

        $mode_type = !empty($o['maintenance_mode_type']) ? $o['maintenance_mode_type'] : 'simple';
        
        if ($mode_type === 'advanced' && !empty($o['maintenance_html_code'])) {
            
            status_header(503);
            header('Retry-After: 3600');
            echo wp_unslash($o['maintenance_html_code']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- intentional raw code output
            exit;
        } else {
            
            $heading = !empty($o['maintenance_heading']) ? $o['maintenance_heading'] : '';
            $message = !empty($o['maintenance_message']) ? $o['maintenance_message'] : '';
            $button_text = !empty($o['maintenance_button_text']) ? $o['maintenance_button_text'] : '';
            $button_url = !empty($o['maintenance_button_url']) ? $o['maintenance_button_url'] : '';
            $button_show = !empty($o['maintenance_button_show']);
            $image_url = !empty($o['maintenance_image']) ? $o['maintenance_image'] : '';
            $image_max_width = !empty($o['maintenance_image_max_width']) ? intval($o['maintenance_image_max_width']) : 0;
            $bg_color = !empty($o['maintenance_bg_color']) ? $o['maintenance_bg_color'] : '#ffffff';
            $text_color = !empty($o['maintenance_text_color']) ? $o['maintenance_text_color'] : '#000000';
            $button_bg_color = !empty($o['maintenance_button_bg_color']) ? $o['maintenance_button_bg_color'] : '#000000';
            $button_text_color = !empty($o['maintenance_button_text_color']) ? $o['maintenance_button_text_color'] : '#ffffff';
            $button_radius = isset($o['maintenance_button_radius']) ? intval($o['maintenance_button_radius']) : 12;
            
            status_header(503);
            header('Retry-After: 3600');
            
            ?>
            <!DOCTYPE html>
            <html lang="<?php echo esc_attr(get_locale()); ?>">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title><?php echo esc_html($heading ? $heading : get_bloginfo('name')); ?></title>
                <style>
                    *, *::before, *::after { box-sizing: border-box; }
                    body {
                        margin: 0;
                        padding: 0;
                        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                        background-color: <?php echo esc_html($bg_color); ?>;
                        color: <?php echo esc_html($text_color); ?>;
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        min-height: 100vh;
                        text-align: center;
                    }
                    .container {
                        max-width: 600px;
                        padding: 40px 20px;
                    }
                    h1 {
                        font-size: 2.5em;
                        margin-bottom: 40px;
                        font-weight: 700;
                    }
                    p {
                        font-size: 1.1em;
                        line-height: 1.6;
                        margin-bottom: 50px;
                    }
                    a.button {
                        display: inline-block;
                        padding: 20px 30px;
                        background-color: <?php echo esc_html($button_bg_color); ?> !important;
                        color: <?php echo esc_html($button_text_color); ?> !important;
                        text-decoration: none !important;
                        border-radius: <?php echo intval($button_radius); ?>px;
                        font-weight: 600;
                        font-family: inherit;
                    }
                </style>
            </head>
            <body>
                <div class="container">
                    <?php if ($image_url): ?>
                        <img src="<?php echo esc_url($image_url); ?>" alt="Logo" style="display: block; margin: 0 auto 50px auto; width: 100%; max-width: <?php echo $image_max_width > 0 ? esc_attr($image_max_width) . 'px' : '100%'; ?>; height: auto;">
                    <?php endif; ?>
                    <?php if ($heading): ?>
                        <h1><?php echo esc_html($heading); ?></h1>
                    <?php endif; ?>
                    <?php if ($message): ?>
                        <p><?php echo esc_html($message); ?></p>
                    <?php endif; ?>
                    <?php if ($button_show && $button_text && $button_url): ?>
                        <a href="<?php echo esc_url($button_url); ?>" class="button"><?php echo esc_html($button_text); ?></a>
                    <?php endif; ?>
                </div>
            </body>
            </html>
            <?php
            exit;
        }
    }
    
    public function customize_login_page() {
        $o = get_option($this->option_name, array());
        
        $logo = !empty($o['login_logo']) ? $o['login_logo'] : '';
        $logo_height = isset($o['login_logo_height']) && $o['login_logo_height'] !== '' ? intval($o['login_logo_height']) : 0;
        
        // DEBUG: Uncomment to see actual value
        // error_log('Logo height from DB: ' . print_r($o['login_logo_height'], true));
        // error_log('Logo height after intval: ' . $logo_height);
        
        $bg_color = !empty($o['login_bg_color']) ? $o['login_bg_color'] : '#eef2f0';
        $bg_image = !empty($o['login_bg_image']) ? $o['login_bg_image'] : '';
        $bg_size = !empty($o['login_bg_size']) ? $o['login_bg_size'] : 'cover';
        $primary_color = !empty($o['login_primary_color']) ? $o['login_primary_color'] : '#0000ff';
        $form_radius = isset($o['login_form_radius']) ? intval($o['login_form_radius']) : 8;
        $form_bg_color = !empty($o['login_form_bg_color']) ? $o['login_form_bg_color'] : '#ffffff';
        $form_text_color = !empty($o['login_form_text_color']) ? $o['login_form_text_color'] : '#3c434a';
        $button_bg = !empty($o['login_button_bg']) ? $o['login_button_bg'] : '#0000ff';
        $button_text_color = !empty($o['login_button_text_color']) ? $o['login_button_text_color'] : '#ffffff';
        $button_radius = isset($o['login_button_radius']) ? intval($o['login_button_radius']) : 4;
        $links_color = !empty($o['login_links_color']) ? $o['login_links_color'] : '#0000ff';
        $hide_lostpassword = !empty($o['login_hide_lostpassword']);
        $hide_backtoblog = !empty($o['login_hide_backtoblog']);
        $hide_rememberme = !empty($o['login_hide_rememberme']);
        $hide_privacy = !empty($o['login_hide_privacy']);
        $custom_css = !empty($o['login_custom_css']) ? $o['login_custom_css'] : '';
        
        ?>
        <style>
            body.login {
                background-color: <?php echo esc_attr($bg_color); ?>;
                <?php if ($bg_image): ?>
                background-image: url('<?php echo esc_url($bg_image); ?>');
                background-size: <?php echo esc_attr($bg_size); ?>;
                background-position: center center;
                background-repeat: <?php echo $bg_size === 'repeat' ? 'repeat' : 'no-repeat'; ?>;
                <?php endif; ?>
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                min-height: 100vh !important;
                padding: 20px !important;
            }
            
            /* Hide default WordPress logo if no custom logo is set */
            <?php if (!$logo): ?>
            #login h1 a {
                display: none !important;
            }
            #login h1 {
                margin-bottom: 0 !important;
            }
            <?php endif; ?>
            
            /* Custom logo styling */
            <?php if ($logo): ?>
            #login h1 {
                margin-bottom: 2em !important;
                display: flex !important;
                justify-content: center !important;
                align-items: center !important;
            }
            
            #login h1 a {
                display: block !important;
                background-image: url('<?php echo esc_url($logo); ?>') !important;
                background-position: center center !important;
                background-repeat: no-repeat !important;
                <?php if ($logo_height > 0): ?>
                background-size: auto <?php echo esc_attr($logo_height); ?>px !important;
                height: <?php echo esc_attr($logo_height); ?>px !important;
                <?php else: ?>
                background-size: auto 84px !important;
                height: 84px !important;
                <?php endif; ?>
                width: 100% !important;
                max-width: 400px !important;
                margin: 0 !important;
                padding: 0 !important;
                text-indent: -9999px !important;
                overflow: visible !important;
                flex-shrink: 0 !important;
            }
            <?php endif; ?>
            
            /* Form styling */
            #login {
                padding: 0 !important;
                margin: 0 auto !important;
                width: 320px !important;
            }
            
            #loginform,
            #lostpasswordform,
            #resetpassform {
                margin-top: 20px !important;
                padding: 30px 24px 50px !important;
                background: <?php echo esc_attr($form_bg_color); ?> !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: <?php echo esc_attr($form_radius); ?>px !important;
            }
            
            .login label {
                font-size: 14px !important;
                font-weight: 400 !important;
                color: <?php echo esc_attr($form_text_color); ?> !important;
                margin-bottom: 8px !important;
                display: block !important;
            }
            
            /* Form field groups spacing */
            .login form .user-pass-wrap,
            .login form p:not(.forgetmenot):not(.submit) {
                margin-bottom: 20px !important;
            }
            
            .login form p.submit {
                margin-bottom: 0 !important;
            }
            
            /* Input fields */
            .login form .input,
            .login input[type="text"],
            .login input[type="password"],
            .login input[type="email"],
            .login form input[type="text"],
            .login form input[type="password"],
            .login form input[type="email"] {
                padding: 12px 14px !important;
                font-size: 16px !important;
                line-height: 1.5 !important;
                border: 1px solid #dcdcde !important;
                border-radius: <?php echo esc_attr($form_radius); ?>px !important;
                background: #fff !important;
                box-shadow: none !important;
                width: 100% !important;
                margin: 0 !important;
            }
            
            .login form .input:focus,
            .login input[type="text"]:focus,
            .login input[type="password"]:focus,
            .login input[type="email"]:focus,
            .login form input[type="text"]:focus,
            .login form input[type="password"]:focus,
            .login form input[type="email"]:focus {
                border-color: <?php echo esc_attr($primary_color); ?> !important;
                box-shadow: none !important;
                outline: none !important;
            }
            
            /* Password field wrapper */
            .login .user-pass-wrap {
                position: relative !important;
            }
            
            /* Password visibility icons */
            .login .wp-pwd {
                position: relative !important;
                display: block !important;
            }
            
            .login .wp-pwd input {
                padding-right: 50px !important;
            }
            
            .login .wp-pwd button.button,
            .login .wp-pwd .wp-hide-pw {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                cursor: pointer !important;
                padding: 0 !important;
                position: absolute !important;
                right: 8px !important;
                top: 40% !important;
                transform: translateY(-50%) !important;
                width: 40px !important;
                height: 40px !important;
                min-width: 40px !important;
                min-height: 40px !important;
                z-index: 10 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            /* Reset password form - different position */
            #resetpassform .wp-pwd button.button,
            #resetpassform .wp-pwd .wp-hide-pw {
                top: 25% !important;
            }
            
            .login .wp-pwd button.button:hover,
            .login .wp-pwd button.button:focus,
            .login .wp-pwd button.button:active,
            .login .wp-pwd .wp-hide-pw:hover,
            .login .wp-pwd .wp-hide-pw:focus,
            .login .wp-pwd .wp-hide-pw:active {
                background: transparent !important;
                border: none !important;
                box-shadow: none !important;
                opacity: 1 !important;
            }
            
            .login .wp-pwd .dashicons {
                width: 20px !important;
                height: 20px !important;
                font-size: 20px !important;
                line-height: 1 !important;
            }
            
            /* Submit button */
            .wp-core-ui .button-primary {
                width: 100% !important;
                padding: 14px 24px !important;
                font-size: 16px !important;
                font-weight: 600 !important;
                background: <?php echo esc_attr($button_bg); ?> !important;
                border-color: <?php echo esc_attr($button_bg); ?> !important;
                color: <?php echo esc_attr($button_text_color); ?> !important;
                border-radius: <?php echo esc_attr($button_radius); ?>px !important;
                text-shadow: none !important;
                box-shadow: none !important;
                border: none !important;
                cursor: pointer !important;
                margin-top: 10px !important;
            }
            
            .wp-core-ui .button-primary:hover,
            .wp-core-ui .button-primary:focus,
            .wp-core-ui .button-primary:active {
                background: <?php echo esc_attr($button_bg); ?> !important;
                border-color: <?php echo esc_attr($button_bg); ?> !important;
                color: <?php echo esc_attr($button_text_color); ?> !important;
                opacity: 0.9 !important;
            }
            
            /* Secondary buttons (Generate Password) - NOT primary buttons */
            .wp-core-ui .button:not(.button-primary),
            .wp-core-ui .button-secondary,
            button.button.wp-generate-pw {
                color: <?php echo esc_attr($primary_color); ?> !important;
                border-color: <?php echo esc_attr($primary_color); ?> !important;
                background: #ffffff !important;
                border-radius: <?php echo esc_attr($button_radius); ?>px !important;
                padding: 8px 16px !important;
                font-size: 14px !important;
            }
            
            .wp-core-ui .button:not(.button-primary):hover,
            .wp-core-ui .button-secondary:hover,
            button.button.wp-generate-pw:hover {
                color: <?php echo esc_attr($primary_color); ?> !important;
                border-color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Password strength indicator */
            .login #pass-strength-result {
                border-radius: 0 0 <?php echo esc_attr($form_radius); ?>px <?php echo esc_attr($form_radius); ?>px !important;
                margin-top: -2px !important;
            }
            
            /* Password field with strength indicator */
            input#pass1,
            input#pass1-text {
                border-radius: <?php echo esc_attr($form_radius); ?>px <?php echo esc_attr($form_radius); ?>px 0 0 !important;
            }
            
            /* Password strength border colors */
            #pass1-text.strong, #pass1.strong {
                border-color: #68de7c !important;
            }
            
            #pass1-text.good, #pass1.good {
                border-color: #f0c33c !important;
            }
            
            #pass1-text.short, #pass1.short {
                border-color: #e65054 !important;
            }
            
            #pass1-text.bad, #pass1.bad {
                border-color: #f86368 !important;
            }
            
            /* Navigation links - centered */
            .login #nav,
            .login #backtoblog {
                text-align: center !important;
                padding: 0 !important;
                margin: 16px 0 0 0 !important;
            }
            
            .login #nav a,
            .login #backtoblog a {
                color: <?php echo esc_attr($links_color); ?> !important;
                font-size: 14px !important;
                text-decoration: none !important;
                border-bottom: 1px solid !important;
            }
            
            .login #nav a:hover,
            .login #backtoblog a:hover {
                color: <?php echo esc_attr($links_color); ?> !important;
                opacity: 1 !important;
                text-decoration: none !important;
            }
            
            /* Global link color for all links on login page */
            .login a,
            .login .message a,
            .login .notice a,
            .login .success a,
            .login .error a {
                color: <?php echo esc_attr($links_color); ?> !important;
            }
            
            /* Messages and notices */
            .login .message,
            .login .notice,
            .login .success {
                border-left: 0 !important;
                border-radius: <?php echo esc_attr($form_radius); ?>px !important;
                padding: 12px !important;
                margin-left: 0 !important;
                margin-bottom: 20px !important;
                background-color: <?php echo esc_attr($form_bg_color); ?> !important;
                color: <?php echo esc_attr($form_text_color); ?> !important;
                box-shadow: none !important;
                word-wrap: break-word !important;
            }
            
            .login .error {
                border-left: 0 !important;
                border-radius: <?php echo esc_attr($form_radius); ?>px !important;
                padding: 12px !important;
                margin-left: 0 !important;
                margin-bottom: 20px !important;
                background-color: #fff !important;
                box-shadow: none !important;
            }
            
            .login .message p,
            .login .notice p,
            .login .success p,
            .login .error p {
                margin: 15px !important;
                padding: 0 !important;
            }
            }
            
            /* Password visibility toggle icons - FORCED */
            .dashicons-visibility:before {
                content: "\f160" !important;
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            .dashicons-hidden:before {
                content: "\f528" !important;
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Extra specificity to override WordPress */
            body.login .dashicons-visibility:before,
            .login .dashicons-visibility:before,
            .wp-pwd .dashicons-visibility:before {
                content: "\f160" !important;
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            body.login .dashicons-hidden:before,
            .login .dashicons-hidden:before,
            .wp-pwd .dashicons-hidden:before {
                content: "\f528" !important;
                color: <?php echo esc_attr($primary_color); ?> !important;
            }
            
            /* Remember me checkbox */
            .login .forgetmenot {
                margin-bottom: 16px !important;
            }
            
            .login .forgetmenot label {
                font-size: 14px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
                margin-left: 8px !important;
            }
            
            .login .forgetmenot input[type="checkbox"] {
                margin: 0 !important;
            }
            
            <?php if ($hide_lostpassword): ?>
            .login #nav {
                display: none !important;
            }
            <?php endif; ?>
            
            <?php if ($hide_backtoblog): ?>
            .login #backtoblog {
                display: none !important;
            }
            <?php endif; ?>
            
            <?php if ($hide_rememberme): ?>
            .login .forgetmenot {
                display: none !important;
            }
            <?php endif; ?>
            
            <?php if ($hide_privacy): ?>
            .login .privacy-policy-page-link {
                display: none !important;
            }
            <?php endif; ?>
            
            <?php if ($custom_css): ?>
            <?php echo wp_strip_all_tags($custom_css); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </style>
        <script>
        (function() {
            function removeBackToText() {
                var backLink = document.querySelector('#backtoblog a');
                if (backLink) {
                    var text = backLink.textContent || backLink.innerText;
                    // Remove all variations of arrow, "Back to", and "Zpět:" text
                    var siteName = text
                        .replace(/^[←↩︎\s]+/, '')  // Remove arrows at start
                        .replace(/^(Zpět na|Zpět:|Back to|Zurück zu|Späť na|Wróć do)\s+/i, '')  // Remove "Back to" phrases including "Zpět:"
                        .trim();
                    backLink.textContent = siteName;
                }
            }
            
            // Try multiple times to ensure it catches
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', removeBackToText);
            } else {
                removeBackToText();
            }
            window.addEventListener('load', removeBackToText);
            setTimeout(removeBackToText, 100);
        })();
        </script>
        <?php
    }
    
    public function custom_login_logo_url() {
        $o = get_option($this->option_name, array());
        $logo_url = !empty($o['login_logo_url']) ? $o['login_logo_url'] : home_url();
        return esc_url($logo_url);
    }
    
    public function custom_login_logo_title() {
        return get_bloginfo('name');
    }
    
    public function custom_login_url_intercept() {
        $o = get_option($this->option_name, array());
        $custom_slug = !empty($o['custom_login_slug']) ? $o['custom_login_slug'] : '';
        
        if (empty($custom_slug)) {
            return;
        }
        
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '/';
        $parsed = wp_parse_url($request_uri);
        $path = isset($parsed['path']) ? trim($parsed['path'], '/') : '';
        
        $path_parts = array_filter(explode('/', $path));
        $last_part = end($path_parts);
        
        if ($last_part === $custom_slug) {
            global $wpc_custom_login_valid, $error, $wp_query;
            $wpc_custom_login_valid = true;
            
            if (isset($error)) {
                $error = '';
            }
            
            if (isset($wp_query) && is_object($wp_query)) {
                $wp_query->is_404 = false;
                $wp_query->is_page = false;
            }
            status_header(200);
            
            $_SERVER['SCRIPT_NAME'] = '/wp-login.php';
            
            // Initialize $user_login to prevent "Undefined variable" warning in wp-login.php
            // when the file is loaded via require_once instead of as a direct entry point.
            if ( ! isset( $user_login ) ) {
                $user_login = '';
            }
            
            require_once ABSPATH . 'wp-login.php';
            exit;
        }
    }
    
    public function custom_login_url_block_direct_access() {
        global $wpc_custom_login_valid, $error;
        
        if (!empty($wpc_custom_login_valid)) {
            return;
        }
        
        $o = get_option($this->option_name, array());
        $custom_slug = !empty($o['custom_login_slug']) ? $o['custom_login_slug'] : '';
        
        $query_string = isset($_SERVER['QUERY_STRING']) ? sanitize_text_field(wp_unslash($_SERVER['QUERY_STRING'])) : '';
        if (!empty($query_string)) {
            $error = '';
            status_header(200);
            wp_safe_redirect(home_url('/' . $custom_slug . '/?' . $query_string));
            exit;
        }
        
        wp_safe_redirect(home_url('/404-page-not-found'));
        exit;
    }
    
    public function custom_login_url_site_url($url, $path = '', $scheme = null, $blog_id = null) {
        $o = get_option($this->option_name, array());
        $custom_slug = !empty($o['custom_login_slug']) ? $o['custom_login_slug'] : '';
        
        if (empty($custom_slug)) {
            return $url;
        }
        
        $current_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($url, 'wp-login.php') !== false && strpos($current_uri, $custom_slug) !== false) {
            $url = str_replace('wp-login.php', $custom_slug . '/', $url);
        }
        
        return $url;
    }
    
    public function custom_login_url_wp_redirect($location, $status) {
        $o = get_option($this->option_name, array());
        $custom_slug = !empty($o['custom_login_slug']) ? $o['custom_login_slug'] : '';
        
        if (empty($custom_slug)) {
            return $location;
        }
        
        if (strpos($location, 'wp-login.php') !== false) {
            if (!is_user_logged_in() && is_admin()) {
                return home_url('/404-page-not-found');
            }
            
            $location = str_replace('wp-login.php', $custom_slug . '/', $location);
        }
        
        return $location;
    }
    
    public function add_duplicate_post_action($actions, $post) {
        if (!current_user_can('edit_post', $post->ID)) {
            return $actions;
        }
        
        $duplicate_url = wp_nonce_url(
            add_query_arg(
                array(
                    'action' => 'duplicate_post',
                    'post' => $post->ID
                ),
                admin_url('admin.php')
            ),
            'duplicate_post_' . $post->ID
        );
        
        $actions['duplicate'] = '<a href="' . esc_url($duplicate_url) . '">' . esc_html($this->get_user_translation('duplicate_action')) . '</a>';
        
        return $actions;
    }
    
    public function show_duplicate_notice() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only GET param for success notice
        if (isset($_GET['duplicated']) && $_GET['duplicated'] === '1') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($this->get_user_translation('duplicate_success')) . '</p></div>';
        }
    }
    
    public function duplicate_post_handler() {
        if (empty($_GET['post'])) {
            wp_die(esc_html__('No post to duplicate.', 'wp-admin-studio'));
        }
        
        $post_id = absint($_GET['post']);
        
        check_admin_referer('duplicate_post_' . $post_id);
        
        if (!current_user_can('edit_post', $post_id)) {
            wp_die(esc_html__('You do not have permission to duplicate this post.', 'wp-admin-studio'));
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_die(esc_html__('Post not found.', 'wp-admin-studio'));
        }
        
        $current_user = wp_get_current_user();
        
        $new_post_args = array(
            'post_author' => $current_user->ID,
            'post_content' => $post->post_content,
            'post_content_filtered' => $post->post_content_filtered,
            'post_title' => $post->post_title,
            'post_excerpt' => $post->post_excerpt,
            'post_status' => 'draft',
            'post_type' => $post->post_type,
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_password' => $post->post_password,
            'to_ping' => $post->to_ping,
            'menu_order' => $post->menu_order,
            'post_mime_type' => $post->post_mime_type,
            'post_parent' => $post->post_parent
        );
        
        $new_post_id = wp_insert_post($new_post_args);
        
        if (is_wp_error($new_post_id)) {
            wp_die(esc_html($new_post_id->get_error_message()));
        }
        
        $post_meta = get_post_meta($post_id);
        
        $excluded_meta = array('_edit_lock', '_edit_last', '_encloseme');
        
        foreach ($post_meta as $meta_key => $meta_values) {
            if (in_array($meta_key, $excluded_meta)) {
                continue;
            }
            
            foreach ($meta_values as $meta_value) {
                add_post_meta($new_post_id, $meta_key, maybe_unserialize($meta_value));
            }
        }
        
        $taxonomies = get_object_taxonomies($post->post_type);
        
        foreach ($taxonomies as $taxonomy) {
            $terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'ids'));
            
            if (!is_wp_error($terms) && !empty($terms)) {
                wp_set_object_terms($new_post_id, $terms, $taxonomy);
            }
        }
        
        $edit_url = add_query_arg(
            array(
                'post' => $new_post_id,
                'action' => 'edit',
                'duplicated' => '1'
            ),
            admin_url('post.php')
        );
        
        wp_safe_redirect($edit_url);
        exit;
        exit;
    }
    
    public function enable_svg_mime_type($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }
    
    public function enable_svg_mime_type_multisite($mimes) {
        if (!is_array($mimes)) {
            $mimes = array();
        }
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }
    
    public function fix_svg_mime_type($data, $file, $filename, $mimes, $real_mime = null) {
        if (!is_array($data)) {
            $data = array();
        }
        
        $ext = isset($data['ext']) ? $data['ext'] : '';
        
        if (empty($ext)) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        }
        
        if ($ext === 'svg' || $ext === 'svgz') {
            $data['ext'] = $ext;
            $data['type'] = 'image/svg+xml';
            $data['proper_filename'] = $filename;
        }
        
        return $data;
    }
    
    
    public function sanitize_svg_upload($file) {
        if (!isset($file['name'])) {
            return $file;
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'svg' && $ext !== 'svgz') {
            return $file;
        }
        
        $file['type'] = 'image/svg+xml';
        
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return $file;
        }
        
        $svg_content = file_get_contents($file['tmp_name']);
        
        if ($svg_content === false) {
            $file['error'] = 'Could not read SVG file.';
            return $file;
        }
        
        $sanitized = $this->sanitize_svg_content($svg_content);
        
        if ($sanitized === false) {
            $file['error'] = 'Invalid SVG file or potentially dangerous content detected.';
            return $file;
        }
        
        file_put_contents($file['tmp_name'], $sanitized);
        
        return $file;
    }
    
    private function sanitize_svg_content($content) {
        $content = trim($content);
        
        if (empty($content)) {
            return false;
        }
        
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;
        $dom->strictErrorChecking = false;
        
        $loaded = @$dom->loadXML($content, LIBXML_NONET);
        
        if (!$loaded) {
            libxml_clear_errors();
            return $content;
        }
        
        libxml_clear_errors();
        
        $xpath = new DOMXPath($dom);
        
        $dangerous_elements = $xpath->query('//*[local-name()="script" or local-name()="iframe" or local-name()="object" or local-name()="embed" or local-name()="foreignObject"]');
        
        foreach ($dangerous_elements as $element) {
            if ($element->parentNode) {
                $element->parentNode->removeChild($element);
            }
        }
        
        $all_elements = $xpath->query('//*');
        foreach ($all_elements as $element) {
            $attributes_to_remove = array();
            
            if ($element->hasAttributes()) {
                foreach ($element->attributes as $attr) {
                    $attr_name = strtolower($attr->name);
                    $attr_value = $attr->value;
                    
                    if (substr($attr_name, 0, 2) === 'on') {
                        $attributes_to_remove[] = $attr->name;
                        continue;
                    }
                    
                    if (preg_match('/javascript:|data:text\/html/i', $attr_value)) {
                        $attributes_to_remove[] = $attr->name;
                        continue;
                    }
                }
            }
            
            foreach ($attributes_to_remove as $attr_name) {
                $element->removeAttribute($attr_name);
            }
        }
        
        $clean_svg = $dom->saveXML($dom->documentElement);
        
        if ($clean_svg === false) {
            return $content;
        }
        
        return $clean_svg;
    }
    
    public function grant_svg_upload_capability($caps, $cap, $user_id, $args) {
        if ($cap !== 'unfiltered_upload') {
            return $caps;
        }
        
        $user = get_userdata($user_id);
        if (!$user) {
            return $caps;
        }
        
        if (in_array('administrator', $user->roles) || in_array('editor', $user->roles)) {
            return array('exist');
        }
        
        return $caps;
    }
    
    public function fix_svg_display() {
        echo '<style>
            .attachment-266x266, .thumbnail img {
                width: 100% !important;
                height: auto !important;
            }
        </style>';
    }
    
    public function fix_svg_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        $mime_type = get_post_mime_type($attachment_id);
        if ($mime_type === 'image/svg+xml') {
            return false;
        }
        return $sources;
    }
    
    public function fix_svg_sizes($sizes, $size, $image_src, $image_meta, $attachment_id) {
        $mime_type = get_post_mime_type($attachment_id);
        if ($mime_type === 'image/svg+xml') {
            return false;
        }
        return $sizes;
    }
    
    // ============================================================
    // MEDIA REPLACE
    // ============================================================

    public function media_replace_row_action($actions, $post) {
        if (!current_user_can('upload_files')) {
            return $actions;
        }
        $url = wp_nonce_url(
            admin_url('admin.php?action=wpc_replace_media&attachment_id=' . $post->ID),
            'wpc_replace_media_' . $post->ID
        );
        $actions['wpc_replace'] = '<a href="' . esc_url($url) . '">' . esc_html($this->t('mr_row_action')) . '</a>';
        return $actions;
    }

    public function media_replace_page() {
        if (!current_user_can('upload_files')) {
            wp_die(esc_html($this->t('mr_error_perm')));
        }

        $attachment_id = isset($_REQUEST['attachment_id']) ? absint($_REQUEST['attachment_id']) : 0;
        if (!$attachment_id) {
            wp_die(esc_html($this->t('mr_error_id')));
        }

        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            wp_die(esc_html($this->t('mr_error_not_found')));
        }

        $result = null;

        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            check_admin_referer('wpc_do_replace_' . $attachment_id, 'wpc_nonce');
            $result = $this->process_media_replace($attachment_id);
        } else {
            check_admin_referer('wpc_replace_media_' . $attachment_id);
        }

        $this->render_media_replace_page($attachment_id, $attachment, $result);
        exit;
    }

    private function process_media_replace($attachment_id) {
        // Ověřit přítomnost souboru a získat detailní chybu uploadu
        // phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in media_replace_page() via check_admin_referer()
        if (empty($_FILES['wpc_new_file'])) {
            return array('error' => $this->t('mr_error_no_file'));
        }

        $file_error = (int) $_FILES['wpc_new_file']['error'];
        if ($file_error !== UPLOAD_ERR_OK) {
            $php_errors = array(
                UPLOAD_ERR_INI_SIZE   => 'Soubor překračuje limit upload_max_filesize v php.ini.',
                UPLOAD_ERR_FORM_SIZE  => 'Soubor překračuje limit MAX_FILE_SIZE formuláře.',
                UPLOAD_ERR_PARTIAL    => 'Soubor byl nahrán jen částečně.',
                UPLOAD_ERR_NO_FILE    => $this->t('mr_error_no_file'),
                UPLOAD_ERR_NO_TMP_DIR => 'Chybí dočasná složka pro upload.',
                UPLOAD_ERR_CANT_WRITE => 'Nepodařilo se zapsat soubor na disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload byl zastaven PHP rozšířením.',
            );
            return array('error' => isset($php_errors[$file_error]) ? $php_errors[$file_error] : $this->t('mr_error_no_file'));
        }

        $tmp_name  = $_FILES['wpc_new_file']['tmp_name'];
        $orig_name = $_FILES['wpc_new_file']['name'];

        if (!is_uploaded_file($tmp_name)) {
            return array('error' => $this->t('mr_error_no_file'));
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $replace_mode = isset($_POST['replace_mode']) ? sanitize_key($_POST['replace_mode']) : 'keep_url';

        $old_file = get_attached_file($attachment_id);
        $old_url  = wp_get_attachment_url($attachment_id);
        $old_meta = wp_get_attachment_metadata($attachment_id);

        // Detekovat MIME typ ze jména souboru (spolehlivější než $_FILES['type'])
        $filetype = wp_check_filetype($orig_name);
        $new_mime = !empty($filetype['type']) ? $filetype['type'] : $_FILES['wpc_new_file']['type'];

        // Smazat staré náhledy (thumbnails)
        if (!empty($old_meta['sizes']) && !empty($old_file)) {
            $old_dir = dirname($old_file);
            foreach ($old_meta['sizes'] as $size_data) {
                if (!empty($size_data['file'])) {
                    $size_path = $old_dir . '/' . $size_data['file'];
                    if (file_exists($size_path)) {
                        @wp_delete_file($size_path);
                    }
                }
            }
        }

        if ($replace_mode === 'keep_url') {
            // ── Přepsat soubor na původní URL ───────────────────────────────
            if (empty($old_file)) {
                return array('error' => $this->t('mr_error_write'));
            }
            wp_mkdir_p(dirname($old_file));

            // Primárně move_uploaded_file (funguje i na localhostu)
            $moved = @move_uploaded_file($tmp_name, $old_file); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found

            if (!$moved || !file_exists($old_file)) {
                // Fallback přes wp_handle_upload + rename/copy
                $upload = wp_handle_upload($_FILES['wpc_new_file'], array('test_form' => false));
                if (isset($upload['error'])) {
                    return array('error' => $upload['error']);
                }
                if (empty($upload['file']) || !file_exists($upload['file'])) {
                    return array('error' => $this->t('mr_error_write'));
                }
                if (!WP_Filesystem() || !$wp_filesystem->move($upload['file'], $old_file)) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride
                    if (@copy($upload['file'], $old_file)) {
                        @wp_delete_file($upload['file']);
                    } else {
                        @wp_delete_file($upload['file']);
                        return array('error' => $this->t('mr_error_write'));
                    }
                }
            }

            if (!file_exists($old_file)) {
                return array('error' => $this->t('mr_error_write'));
            }

            $final_file     = $old_file;
            $final_url      = $old_url;
            $replaced_count = 0;

        } else {
            // ── Nová URL: nahrát do standardní WP upload složky ─────────────
            $upload_dir = wp_upload_dir();
            if (!empty($upload_dir['error'])) {
                return array('error' => $upload_dir['error']);
            }

            $target_dir = $upload_dir['path'];
            wp_mkdir_p($target_dir);
            $safe_name   = sanitize_file_name($orig_name);
            $unique_name = wp_unique_filename($target_dir, $safe_name);
            $target_path = $target_dir . '/' . $unique_name;

            // Primárně move_uploaded_file
            $moved = @move_uploaded_file($tmp_name, $target_path); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found

            if (!$moved || !file_exists($target_path)) {
                // Fallback přes wp_handle_upload
                $upload = wp_handle_upload($_FILES['wpc_new_file'], array('test_form' => false));
                if (isset($upload['error'])) {
                    return array('error' => $upload['error']);
                }
                if (empty($upload['file']) || !file_exists($upload['file'])) {
                    return array('error' => $this->t('mr_error_write'));
                }
                $target_path = $upload['file'];
                $unique_name = basename($target_path);
            }

            // Sestavit URL a relativní cestu (bezpečné pro Windows i Unix)
            $base_normalized   = wp_normalize_path($upload_dir['basedir']);
            $target_normalized = wp_normalize_path($target_path);
            $new_relative      = ltrim(str_replace($base_normalized, '', $target_normalized), '/');
            $new_url           = $upload_dir['url'] . '/' . $unique_name;

            // Smazat starý soubor
            if (!empty($old_file) && file_exists($old_file) && realpath($old_file) !== realpath($target_path)) {
                @wp_delete_file($old_file);
            }

            $final_file = $target_path;
            $final_url  = $new_url;

            // Aktualizovat DB
            update_post_meta($attachment_id, '_wp_attached_file', $new_relative);
            wp_update_post(array(
                'ID'   => $attachment_id,
                'guid' => $new_url,
            ));

            $replaced_count = $this->media_replace_update_links($old_url, $new_url);
        }

        // Aktualizovat MIME typ
        wp_update_post(array(
            'ID'             => $attachment_id,
            'post_mime_type' => $new_mime,
        ));

        // Přegenerovat metadata a náhledy
        $new_meta = wp_generate_attachment_metadata($attachment_id, $final_file);

        // wp_generate_attachment_metadata nezvládá SVG (ani jiné ne-rastrové soubory)
        // — vrátí prázdné pole, čímž by se smazaly rozměry. Doplníme je ručně.
        if (empty($new_meta) || (empty($new_meta['width']) && empty($new_meta['height']))) {
            $new_meta = $this->media_replace_build_meta($final_file, $new_mime, $new_meta);
        }

        wp_update_attachment_metadata($attachment_id, $new_meta);
        clean_attachment_cache($attachment_id);

        return array(
            'success'        => true,
            'new_url'        => $final_url,
            'new_mime'       => $new_mime,
            'replaced_count' => $replaced_count,
            'mode'           => $replace_mode,
        );
    }

    private function media_replace_build_meta($file_path, $mime_type, $existing_meta = array()) {
        if (!is_array($existing_meta)) {
            $existing_meta = array();
        }

        if ($mime_type === 'image/svg+xml' && file_exists($file_path)) {
            // Zkusit načíst rozměry ze SVG
            $svg_content = @file_get_contents($file_path);
            if ($svg_content) {
                libxml_use_internal_errors(true);
                $dom = new DOMDocument();
                if (@$dom->loadXML($svg_content, LIBXML_NONET)) {
                    $svg = $dom->getElementsByTagName('svg')->item(0);
                    if ($svg) {
                        $width  = 0;
                        $height = 0;

                        // Nejprve viewBox
                        $viewbox = $svg->getAttribute('viewBox');
                        if ($viewbox) {
                            $parts = preg_split('/[\s,]+/', trim($viewbox));
                            if (count($parts) === 4) {
                                $width  = (int) round((float) $parts[2]);
                                $height = (int) round((float) $parts[3]);
                            }
                        }

                        // Fallback na width/height atributy
                        if (!$width && $svg->hasAttribute('width')) {
                            $width = (int) round((float) $svg->getAttribute('width'));
                        }
                        if (!$height && $svg->hasAttribute('height')) {
                            $height = (int) round((float) $svg->getAttribute('height'));
                        }

                        if ($width && $height) {
                            $existing_meta['width']  = $width;
                            $existing_meta['height'] = $height;
                        }
                    }
                }
                libxml_clear_errors();
            }
        }

        return $existing_meta;
    }

    private function media_replace_update_links($old_url, $new_url) {
        global $wpdb;
        $count = 0;

        // Direct queries are required here for bulk URL replacement across tables.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery

        // Obsah příspěvků
        $count += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
            $old_url, $new_url, '%' . $wpdb->esc_like($old_url) . '%'
        ));

        // Postmeta — vynecháme serializované attachment klíče, ty se přepíší samostatně
        $count += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->postmeta} SET meta_value = REPLACE(meta_value, %s, %s)
             WHERE meta_value LIKE %s
             AND meta_key NOT IN ('_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_backup_sizes')",
            $old_url, $new_url, '%' . $wpdb->esc_like($old_url) . '%'
        ));

        // Options (widgety, nastavení témat) — jen autoload, vynecháme attachment_data
        $count += (int) $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->options} SET option_value = REPLACE(option_value, %s, %s)
             WHERE option_value LIKE %s
             AND autoload = 'yes'
             AND option_name NOT LIKE '%transient%'",
            $old_url, $new_url, '%' . $wpdb->esc_like($old_url) . '%'
        ));

        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery

        return $count;
    }

    // phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotValidated,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    private function render_media_replace_page($attachment_id, $attachment, $result = null) {
        $mime_type   = get_post_mime_type($attachment_id);
        $file_path   = get_attached_file($attachment_id);
        $file_url    = wp_get_attachment_url($attachment_id);
        $is_image    = strpos($mime_type, 'image/') === 0;
        $file_size   = ($file_path && file_exists($file_path)) ? size_format(filesize($file_path)) : '—';
        $upload_date = get_the_date('j. n. Y', $attachment);
        $icon_url    = wp_mime_type_icon($attachment_id);
        $meta        = wp_get_attachment_metadata($attachment_id);
        $dimensions  = '';
        if (!empty($meta['width']) && !empty($meta['height'])) {
            $dimensions = $meta['width'] . ' × ' . $meta['height'] . ' px';
        } elseif ($is_image && $file_path && file_exists($file_path)) {
            $size = @getimagesize($file_path);
            if ($size) {
                $dimensions = $size[0] . ' × ' . $size[1] . ' px';
            }
        }

        $form_action = admin_url('admin.php?action=wpc_replace_media&attachment_id=' . $attachment_id);
        $back_url    = admin_url('upload.php');

        require_once ABSPATH . 'wp-admin/admin-header.php';
        ?>
        <div class="wrap wpc-replace-wrap">
            <h1 class="wp-heading-inline"><?php echo esc_html($this->t('mr_page_title')); ?></h1>
            <a href="<?php echo esc_url($back_url); ?>" class="page-title-action">← <?php echo esc_html($this->t('mr_back')); ?></a>
            <hr class="wp-header-end">

            <?php if (!empty($result['error'])): ?>
                <div class="notice notice-error is-dismissible"><p><?php echo esc_html($result['error']); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($result['success'])): ?>

                <div class="wpc-replace-success">
                    <div class="wpc-success-icon dashicons dashicons-yes-alt"></div>
                    <h2><?php echo esc_html($result['mode'] === 'new_url' ? $this->t('mr_success_new') : $this->t('mr_success_keep')); ?></h2>
                    <p class="description">
                        <?php echo esc_html($result['mode'] === 'new_url' ? $this->t('mr_desc_new') : $this->t('mr_desc_keep')); ?>
                    </p>
                    <div class="wpc-success-actions">
                        <a href="<?php echo esc_url($back_url); ?>" class="button button-primary"><?php echo esc_html($this->t('mr_back')); ?></a>
                        <a href="<?php echo esc_url(get_edit_post_link($attachment_id)); ?>" class="button"><?php echo esc_html($this->t('mr_edit')); ?></a>
                    </div>
                </div>

            <?php else: ?>

                <div class="wpc-replace-layout">

                    <!-- NÁHLED + FORM -->
                    <form method="post" action="<?php echo esc_url($form_action); ?>" enctype="multipart/form-data" id="wpc-replace-form">
                        <?php wp_nonce_field('wpc_do_replace_' . $attachment_id, 'wpc_nonce'); ?>
                        <input type="hidden" name="attachment_id" value="<?php echo esc_attr($attachment_id); ?>">

                        <div class="wpc-replace-card">
                            <div class="wpc-preview-grid">

                                <!-- Levá strana: aktuální soubor -->
                                <div class="wpc-preview-col">
                                    <div class="wpc-preview-label"><?php echo esc_html($this->t('mr_label_old')); ?></div>
                                    <div class="wpc-preview-box">
                                        <?php if ($is_image): ?>
                                            <img src="<?php echo esc_url($file_url); ?>" alt="">
                                        <?php else: ?>
                                            <div class="wpc-file-icon-wrap">
                                                <img src="<?php echo esc_url($icon_url); ?>" alt="" class="wpc-file-type-icon">
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="wpc-preview-meta">
                                        <strong><?php echo esc_html(basename($file_path ?: get_the_title($attachment_id))); ?></strong>
                                        <?php if ($dimensions): ?><span><?php echo esc_html($dimensions); ?></span><?php endif; ?>
                                        <span><?php echo esc_html($file_size); ?></span>
                                        <span><?php echo esc_html($this->t('mr_uploaded')); ?> <?php echo esc_html($upload_date); ?></span>
                                    </div>
                                </div>

                                <div class="wpc-preview-arrow">→</div>

                                <!-- Pravá strana: dropzone / nový soubor -->
                                <div class="wpc-preview-col">
                                    <div class="wpc-preview-label"><?php echo esc_html($this->t('mr_label_new')); ?></div>
                                    <div class="wpc-preview-box wpc-dropzone" id="wpc-new-preview">
                                        <!-- Stav: prázdný (dropzone) -->
                                        <div id="wpc-drop-inner">
                                            <span class="dashicons dashicons-upload wpc-drop-dashicon"></span>
                                            <strong><?php echo esc_html($this->t('mr_drop_title')); ?></strong>
                                            <span><?php echo esc_html($this->t('mr_drop_sub')); ?></span>
                                            <input type="file" name="wpc_new_file" id="wpc-file-input" accept="*/*">
                                        </div>
                                        <!-- Stav: soubor vybrán (preview) -->
                                        <div id="wpc-filled-inner" style="display:none;width:100%;height:100%;position:relative;">
                                            <img id="wpc-new-img" src="" alt="" style="display:none;width:100%;height:100%;object-fit:contain;">
                                            <div class="wpc-file-icon-wrap" id="wpc-new-icon-wrap" style="display:none;">
                                                <img src="<?php echo esc_url($icon_url); ?>" alt="" class="wpc-file-type-icon" id="wpc-new-icon">
                                            </div>
                                            <button type="button" id="wpc-clear-btn" title="Odebrat soubor">✕</button>
                                        </div>
                                    </div>
                                    <div class="wpc-preview-meta" id="wpc-new-meta">
                                        <span class="wpc-meta-dash">—</span>
                                    </div>
                                </div>

                            </div>
                        </div>

                        <!-- ZPŮSOB NAHRAZENÍ -->
                        <div class="wpc-replace-card">
                            <h2 class="wpc-card-title"><?php echo esc_html($this->t('mr_mode_section_title')); ?></h2>
                            <div class="wpc-mode-grid">
                                <label class="wpc-mode-card wpc-mode-selected" id="wpc-mode-keep">
                                    <input type="radio" name="replace_mode" value="keep_url" checked>
                                    <div class="wpc-mode-body">
                                        <strong><?php echo esc_html($this->t('mr_mode_keep_title')); ?></strong>
                                        <span><?php echo esc_html($this->t('mr_mode_keep_desc')); ?></span>
                                    </div>
                                </label>
                                <label class="wpc-mode-card" id="wpc-mode-new">
                                    <input type="radio" name="replace_mode" value="new_url">
                                    <div class="wpc-mode-body">
                                        <strong><?php echo esc_html($this->t('mr_mode_new_title')); ?></strong>
                                        <span><?php echo esc_html($this->t('mr_mode_new_desc')); ?></span>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="wpc-replace-actions">
                            <div class="wpc-replace-submit">
                                <a href="<?php echo esc_url($back_url); ?>" class="button"><?php echo esc_html($this->t('mr_cancel')); ?></a>
                                <button type="submit" class="button button-primary" id="wpc-submit-btn">
                                    <?php echo esc_html($this->t('mr_submit')); ?>
                                </button>
                            </div>
                        </div>
                    </form>

                </div>

            <?php endif; ?>
        </div>

        <style>
        .wpc-replace-wrap { max-width: 860px; }
        .wpc-replace-layout { margin-top: 20px; }
        .wpc-replace-card { background: #fff; border: 1px solid #c3c4c7; border-radius: 3px; margin-bottom: 16px; }
        .wpc-card-title { font-size: 12px; font-weight: 600; color: #646970; text-transform: uppercase; letter-spacing: .05em; margin: 0; padding: 10px 16px; border-bottom: 1px solid #c3c4c7; }
        /* Grid: šipka vycentrovaná, menší boxy */
        .wpc-preview-grid { display: grid; grid-template-columns: 1fr 36px 1fr; gap: 12px; align-items: start; padding: 16px; }
        .wpc-preview-label { font-size: 11px; font-weight: 600; color: #8c8f94; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
        .wpc-preview-box { border: 1px solid #c3c4c7; border-radius: 3px; background: #f6f7f7; height: 160px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; }
        .wpc-preview-box > img { width: 100%; height: 100%; object-fit: contain; }
        .wpc-preview-arrow { display: flex; align-items: center; justify-content: center; font-size: 22px; color: #8c8f94; padding-top: 100px; }
        /* Dropzone integrovana do prave preview box */
        .wpc-dropzone { border: 2px dashed #c3c4c7; cursor: pointer; transition: border-color .15s, background .15s; }
        .wpc-dropzone:hover, .wpc-dropzone.wpc-drag-over { border-color: #2271b1; background: #f0f6fc; }
        .wpc-dropzone.wpc-has-file { border: 1px solid #c3c4c7; cursor: default; }
        #wpc-drop-inner { display: flex; flex-direction: column; align-items: center; gap: 4px; text-align: center; padding: 12px; pointer-events: none; position: relative; }
        #wpc-drop-inner input[type=file] { position: absolute; inset: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; font-size: 0; pointer-events: all; }
        .wpc-drop-dashicon { font-size: 28px !important; width: 28px !important; height: 28px !important; color: #8c8f94; margin-bottom: 2px; }
        .wpc-dropzone:hover .wpc-drop-dashicon, .wpc-dropzone.wpc-drag-over .wpc-drop-dashicon { color: #2271b1; }
        #wpc-drop-inner strong { font-size: 12px; color: #1d2327; }
        #wpc-drop-inner span { font-size: 11px; color: #8c8f94; }
        .wpc-dropzone:hover #wpc-drop-inner strong,
        .wpc-dropzone:hover #wpc-drop-inner span { color: #2271b1; }
        /* Clear button */
        #wpc-clear-btn { position: absolute; top: 5px; right: 5px; width: 22px; height: 22px; border-radius: 50%; border: none; background: rgba(0,0,0,.45); color: #fff; font-size: 12px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; z-index: 2; padding: 0; }
        #wpc-clear-btn:hover { background: rgba(0,0,0,.7); }
        .wpc-file-icon-wrap { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 12px; width: 100%; }
        .wpc-file-type-icon { width: 40px; height: 54px; object-fit: contain; }
        .wpc-preview-meta { margin-top: 8px; font-size: 12px; line-height: 1.8; color: #646970; min-height: 54px; }
        .wpc-preview-meta strong { display: block; color: #1d2327; font-size: 13px; }
        .wpc-preview-meta span { display: block; }
        .wpc-meta-dash { color: #c3c4c7; }
        .wpc-new-badge { position: absolute; top: 5px; right: 30px; background: #00a32a; color: #fff; font-size: 11px; padding: 1px 7px; border-radius: 10px; font-weight: 600; }
        .wpc-mode-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 16px; }
        .wpc-mode-card { display: flex; align-items: flex-start; gap: 10px; border: 1px solid #c3c4c7; border-radius: 3px; padding: 14px; cursor: pointer; transition: border-color .15s, background .15s; }
        .wpc-mode-card input[type=radio] { margin-top: 3px; flex-shrink: 0; }
        .wpc-mode-selected { border-color: #2271b1; background: #f0f6fc; }
        .wpc-mode-body strong { display: block; font-size: 13px; color: #1d2327; margin-bottom: 4px; }
        .wpc-mode-body span { font-size: 12px; color: #646970; line-height: 1.5; }
        .wpc-replace-actions { display: flex; align-items: center; justify-content: flex-end; margin-bottom: 24px; }
        .wpc-replace-submit { display: flex; gap: 8px; }
        @media (max-width: 600px) {
            .wpc-preview-grid { grid-template-columns: 1fr; gap: 16px; }
            .wpc-preview-arrow { display: none; }
            .wpc-mode-grid { grid-template-columns: 1fr; }
            .wpc-replace-actions { justify-content: stretch; }
            .wpc-replace-submit { flex-direction: column; width: 100%; }
            .wpc-replace-submit .button,
            .wpc-replace-submit button { width: 100%; text-align: center; box-sizing: border-box; }
            .wpc-success-actions { flex-direction: column; align-items: center; }
        }
        .wpc-replace-success { background: #fff; border: 1px solid #c3c4c7; border-radius: 3px; padding: 48px 20px; text-align: center; margin-top: 20px; }
        .wpc-replace-success .wpc-success-icon { font-size: 48px; width: 48px; height: 48px; color: #00a32a; margin: 0 auto 16px; }
        .wpc-replace-success h2 { font-size: 20px; font-weight: 400; margin-bottom: 10px; }
        .wpc-replace-success .description { font-size: 14px; color: #646970; margin-bottom: 24px; }
        .wpc-success-actions { display: flex; gap: 10px; justify-content: center; }
        </style>

        <script>
        (function($) {
            var currentFile = null;
            var i18n = {
                badge:   '<?php echo esc_js($this->t('mr_badge_new')); ?>',
                noFile:  '<?php echo esc_js($this->t('mr_js_no_file')); ?>',
                loading: '<?php echo esc_js($this->t('mr_uploading')); ?>'
            };

            function formatBytes(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1048576) return Math.round(bytes / 1024) + ' KB';
                return (bytes / 1048576).toFixed(1) + ' MB';
            }

            function escHtml(str) {
                return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }

            function setFile(file) {
                if (!file) return;
                currentFile = file;

                var dropInner   = document.getElementById('wpc-drop-inner');
                var filledInner = document.getElementById('wpc-filled-inner');
                var newPreview  = document.getElementById('wpc-new-preview');
                var newMeta     = document.getElementById('wpc-new-meta');

                dropInner.style.display   = 'none';
                filledInner.style.display = 'block';
                newPreview.classList.add('wpc-has-file');
                newPreview.classList.remove('wpc-drag-over');

                // Badge "nový"
                if (!newPreview.querySelector('.wpc-new-badge')) {
                    var badge = document.createElement('span');
                    badge.className = 'wpc-new-badge';
                    badge.textContent = i18n.badge;
                    newPreview.appendChild(badge);
                }

                var sizeStr  = formatBytes(file.size);
                var isImgType = file.type.indexOf('image/') === 0;

                if (isImgType) {
                    // Obrázek včetně SVG — ukázat přes FileReader
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        if (file.type === 'image/svg+xml') {
                            // SVG: zobrazit jako <img> s data URL
                            var img = document.getElementById('wpc-new-img');
                            img.src = e.target.result;
                            img.style.display = 'block';
                            document.getElementById('wpc-new-icon-wrap').style.display = 'none';
                            newMeta.innerHTML =
                                '<strong>' + escHtml(file.name) + '</strong>' +
                                '<span>' + sizeStr + '</span>';
                        } else {
                            var tmpImg = new Image();
                            tmpImg.onload = function() {
                                var img = document.getElementById('wpc-new-img');
                                img.src = e.target.result;
                                img.style.display = 'block';
                                document.getElementById('wpc-new-icon-wrap').style.display = 'none';
                                newMeta.innerHTML =
                                    '<strong>' + escHtml(file.name) + '</strong>' +
                                    '<span>' + tmpImg.naturalWidth + ' × ' + tmpImg.naturalHeight + ' px</span>' +
                                    '<span>' + sizeStr + '</span>';
                            };
                            tmpImg.src = e.target.result;
                        }
                    };
                    reader.readAsDataURL(file);
                } else {
                    // Neobrázek — ikona souboru
                    document.getElementById('wpc-new-img').style.display = 'none';
                    document.getElementById('wpc-new-icon-wrap').style.display = 'flex';
                    newMeta.innerHTML =
                        '<strong>' + escHtml(file.name) + '</strong>' +
                        '<span>' + sizeStr + '</span>';
                }
            }

            function clearFile() {
                currentFile = null;
                document.getElementById('wpc-file-input').value = '';

                var dropInner   = document.getElementById('wpc-drop-inner');
                var filledInner = document.getElementById('wpc-filled-inner');
                var newPreview  = document.getElementById('wpc-new-preview');
                var newMeta     = document.getElementById('wpc-new-meta');

                filledInner.style.display = 'none';
                dropInner.style.display   = '';
                newPreview.classList.remove('wpc-has-file');

                var badge = newPreview.querySelector('.wpc-new-badge');
                if (badge) badge.remove();

                document.getElementById('wpc-new-img').src = '';
                document.getElementById('wpc-new-img').style.display = 'none';
                document.getElementById('wpc-new-icon-wrap').style.display = 'none';

                newMeta.innerHTML = '<span class="wpc-meta-dash">—</span>';
            }

            // File input change
            $('#wpc-file-input').on('change', function() {
                if (this.files && this.files[0]) setFile(this.files[0]);
            });

            // Drag & drop na preview boxu
            var dropZone = document.getElementById('wpc-new-preview');
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                if (!this.classList.contains('wpc-has-file')) {
                    this.classList.add('wpc-drag-over');
                }
            });
            dropZone.addEventListener('dragleave', function() {
                this.classList.remove('wpc-drag-over');
            });
            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('wpc-drag-over');
                var file = e.dataTransfer.files[0];
                if (!file) return;
                try {
                    var dt = new DataTransfer();
                    dt.items.add(file);
                    document.getElementById('wpc-file-input').files = dt.files;
                } catch(err) {}
                setFile(file);
            });

            // X tlačítko — odebrat soubor
            document.getElementById('wpc-clear-btn').addEventListener('click', function(e) {
                e.stopPropagation();
                clearFile();
            });

            // Výběr režimu
            $('input[name="replace_mode"]').on('change', function() {
                $('.wpc-mode-card').removeClass('wpc-mode-selected');
                $(this).closest('.wpc-mode-card').addClass('wpc-mode-selected');
            });

            // Submit validace + loading state
            $('#wpc-replace-form').on('submit', function() {
                var fileInput = document.getElementById('wpc-file-input');
                if (!fileInput.files || !fileInput.files.length) {
                    alert(i18n.noFile);
                    return false;
                }
                var btn = document.getElementById('wpc-submit-btn');
                btn.textContent = i18n.loading;
                btn.disabled = true;
            });

        })(jQuery);
        </script>
        <?php
        require_once ABSPATH . 'wp-admin/admin-footer.php';
    }

    public static function on_activation() {
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('WP Admin Studio requires PHP 7.4 or higher.', 'wp-admin-studio'));
        }
        if (version_compare(get_bloginfo('version'), '5.8', '<')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(esc_html__('WP Admin Studio requires WordPress 5.8 or higher.', 'wp-admin-studio'));
        }
    }

    public static function on_deactivation() {
        $administrator_role = get_role('administrator');
        if ($administrator_role && $administrator_role->has_cap('unfiltered_upload')) {
            $administrator_role->remove_cap('unfiltered_upload');
        }
        
        $editor_role = get_role('editor');
        if ($editor_role && $editor_role->has_cap('unfiltered_upload')) {
            $editor_role->remove_cap('unfiltered_upload');
        }
    }
    
}

register_deactivation_hook(__FILE__, array('WPAdminStudio', 'on_deactivation'));
register_activation_hook(__FILE__, array('WPAdminStudio', 'on_activation'));

add_action('plugins_loaded', function () {
    new WPAdminStudio();
});
