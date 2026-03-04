<?php
if (!defined('ABSPATH')) exit;

class LW_Helper_GitHub_Updater {

    private $file;
    private $plugin;
    private $basename;
    private $github_response;

    public function __construct($file) {
        $this->file = $file;
        $this->basename = plugin_basename($file);

        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_filter('upgrader_post_install', [$this, 'after_install'], 10, 3);
    }

    private function get_plugin_data() {
        if (!$this->plugin) {
            $this->plugin = get_plugin_data($this->file);
        }
        return $this->plugin;
    }

    private function get_github_release() {
        if ($this->github_response !== null) {
            return $this->github_response;
        }

        $url = 'https://api.github.com/repos/' . LW_HELPER_GITHUB_REPO . '/releases/latest';
        $response = wp_remote_get($url, [
            'headers' => ['Accept' => 'application/vnd.github.v3+json'],
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            $this->github_response = false;
            return false;
        }

        $this->github_response = json_decode(wp_remote_retrieve_body($response));
        return $this->github_response;
    }

    public function check_update($transient) {
        if (empty($transient->checked)) return $transient;

        $plugin_data = $this->get_plugin_data();
        $release = $this->get_github_release();

        if (!$release || empty($release->tag_name)) return $transient;

        $remote_version = ltrim($release->tag_name, 'v');
        $current_version = $plugin_data['Version'];

        if (version_compare($remote_version, $current_version, '>')) {
            $download_url = $release->zipball_url ?? '';
            if (!empty($release->assets) && !empty($release->assets[0]->browser_download_url)) {
                $download_url = $release->assets[0]->browser_download_url;
            }

            $transient->response[$this->basename] = (object) [
                'slug'        => dirname($this->basename),
                'plugin'      => $this->basename,
                'new_version' => $remote_version,
                'url'         => $release->html_url ?? '',
                'package'     => $download_url,
            ];
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if (!isset($args->slug) || $args->slug !== dirname($this->basename)) return $result;

        $plugin_data = $this->get_plugin_data();
        $release = $this->get_github_release();

        if (!$release) return $result;

        return (object) [
            'name'          => $plugin_data['Name'],
            'slug'          => dirname($this->basename),
            'version'       => ltrim($release->tag_name, 'v'),
            'author'        => $plugin_data['Author'],
            'homepage'      => $plugin_data['PluginURI'] ?? '',
            'sections'      => [
                'description' => $plugin_data['Description'],
                'changelog'   => $release->body ?? '',
            ],
            'download_link' => $release->zipball_url ?? '',
        ];
    }

    public function after_install($response, $hook_extra, $result) {
        if (!isset($hook_extra['plugin']) || $hook_extra['plugin'] !== $this->basename) {
            return $result;
        }

        global $wp_filesystem;
        $plugin_dir = WP_PLUGIN_DIR . '/' . dirname($this->basename);
        $wp_filesystem->move($result['destination'], $plugin_dir);
        $result['destination'] = $plugin_dir;

        activate_plugin($this->basename);

        return $result;
    }
}
