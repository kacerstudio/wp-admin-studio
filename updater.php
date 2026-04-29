<?php
/**
 * WP Admin Studio — GitHub Update Checker
 * Checks for new releases on GitHub and integrates with the WordPress update system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WPAdminStudioUpdater {

    private $plugin_file;
    private $plugin_slug;
    private $github_user  = 'kacerstudio';
    private $github_repo  = 'wp-admin-studio';
    private $version;
    private $cache_key    = 'wpas_github_release';
    private $cache_expiry = 12 * HOUR_IN_SECONDS;

    public function __construct($plugin_file, $version) {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version     = $version;

        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 20, 3);
        add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
    }

    private function get_release_info() {
        $cached = get_transient($this->cache_key);
        if ($cached !== false) {
            return $cached;
        }

        $url      = 'https://api.github.com/repos/' . $this->github_user . '/' . $this->github_repo . '/releases/latest';
        $response = wp_remote_get($url, array(
            'headers'   => array('User-Agent' => 'WP-Admin-Studio/' . $this->version),
            'timeout'   => 10,
            'sslverify' => true,
        ));

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_transient($this->cache_key, null, $this->cache_expiry);
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response));
        if (empty($release->tag_name)) {
            return null;
        }

        set_transient($this->cache_key, $release, $this->cache_expiry);
        return $release;
    }

    public function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $release = $this->get_release_info();
        if (!$release) {
            return $transient;
        }

        $remote_version = ltrim($release->tag_name, 'v');

        if (version_compare($this->version, $remote_version, '<')) {
            $download_url = '';
            if (!empty($release->assets)) {
                foreach ($release->assets as $asset) {
                    if (str_ends_with($asset->name, '.zip')) {
                        $download_url = $asset->browser_download_url;
                        break;
                    }
                }
            }
            if (empty($download_url)) {
                $download_url = $release->zipball_url;
            }

            $transient->response[$this->plugin_slug] = (object) array(
                'slug'        => dirname($this->plugin_slug),
                'plugin'      => $this->plugin_slug,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
                'package'     => $download_url,
                'icons'       => array(),
                'banners'     => array(),
                'tested'      => '6.9',
                'requires'    => '5.8',
                'requires_php'=> '7.4',
            );
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }

        $release = $this->get_release_info();
        if (!$release) {
            return $result;
        }

        $remote_version = ltrim($release->tag_name, 'v');
        $download_url   = $release->zipball_url;

        if (!empty($release->assets)) {
            foreach ($release->assets as $asset) {
                if (str_ends_with($asset->name, '.zip')) {
                    $download_url = $asset->browser_download_url;
                    break;
                }
            }
        }

        return (object) array(
            'name'          => 'WP Admin Studio',
            'slug'          => dirname($this->plugin_slug),
            'version'       => $remote_version,
            'author'        => '<a href="https://kacer.studio">KACER STUDIO s.r.o.</a>',
            'homepage'      => 'https://github.com/' . $this->github_user . '/' . $this->github_repo,
            'download_link' => $download_url,
            'requires'      => '5.8',
            'requires_php'  => '7.4',
            'tested'        => '6.9',
            'last_updated'  => gmdate('Y-m-d', strtotime($release->published_at)),
            'sections'      => array(
                'description' => isset($release->body) ? wp_kses_post($release->body) : 'WP Admin Studio — Professional WordPress admin toolkit.',
                'changelog'   => isset($release->body) ? wp_kses_post($release->body) : '',
            ),
        );
    }

    public function after_install($response, $hook_extra, $result) {
        global $wp_filesystem;

        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->plugin_slug) {
            return $response;
        }

        // Přejmenovat složku na správný slug
        $install_dir   = dirname(WP_PLUGIN_DIR . '/' . $this->plugin_slug);
        $wp_filesystem->move($result['destination'], $install_dir);
        $result['destination'] = $install_dir;

        return $result;
    }
}
