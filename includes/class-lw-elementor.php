<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-lw-price-visibility.php';

/**
 * Drops the single-lokal price table from the frontend HTML when the
 * queried lokal has a hidden-price status.
 *
 * Elementor builds widget markup in a buffer, then
 * elementor/frontend/widget/should_render decides whether that markup is printed.
 * Returning false leaves no wrapper and no cell HTML in the response.
 */
class LW_Helper_Elementor {

    private static $booted = false;

    public static function boot() {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        if (did_action('elementor/loaded')) {
            self::register_hook();
            return;
        }

        add_action('elementor/loaded', array(__CLASS__, 'register_hook'));
    }

    public static function register_hook() {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        add_filter('elementor/frontend/widget/should_render', array(__CLASS__, 'should_render'), 10, 2);
    }

    /**
     * @param bool  $should_render
     * @param mixed $widget Elementor widget instance.
     * @return bool
     */
    public static function should_render($should_render, $widget) {
        if (!$should_render) {
            return false;
        }
        if (is_admin()) {
            return $should_render;
        }
        if (self::in_elementor_editor_or_preview()) {
            return $should_render;
        }
        if (!self::widget_has_hidden_price_class($widget)) {
            return $should_render;
        }

        $post_id = self::current_lokal_id();
        if (!$post_id) {
            return $should_render;
        }
        if (!LW_Helper_Price_Visibility::should_hide_post($post_id)) {
            return $should_render;
        }

        return false;
    }

    /**
     * Queried lokal on a single template, with the rendered post as a fallback
     * when the main query object is not the lokal itself.
     *
     * @return int
     */
    private static function current_lokal_id() {
        if (!is_singular('lokal')) {
            return 0;
        }

        $post_id = (int) get_queried_object_id();
        if ($post_id && get_post_type($post_id) === 'lokal') {
            return $post_id;
        }

        $rendered = (int) get_the_ID();
        if ($rendered && get_post_type($rendered) === 'lokal') {
            return $rendered;
        }

        return 0;
    }

    /**
     * @param mixed $widget
     * @return bool
     */
    public static function widget_has_hidden_price_class($widget) {
        $classes = self::widget_css_classes($widget);
        if (!$classes) {
            return false;
        }

        foreach (self::hidden_css_classes() as $needle) {
            if (in_array($needle, $classes, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * CSS classes that mark a widget whose whole markup should be omitted.
     *
     * @return string[]
     */
    public static function hidden_css_classes() {
        /**
         * Elementor `_css_classes` tokens that hide the widget for a
         * hidden-price lokal. Matched as whole class names.
         *
         * @param string[] $classes
         */
        $classes = apply_filters('lw_helper_hide_price_css_classes', array('kolabo-ceny-table'));
        if (!is_array($classes)) {
            $classes = array('kolabo-ceny-table');
        }

        $clean = array();
        foreach ($classes as $class) {
            $class = trim((string) $class);
            if ($class !== '') {
                $clean[] = $class;
            }
        }
        return $clean;
    }

    /**
     * @param mixed $widget
     * @return string[]
     */
    private static function widget_css_classes($widget) {
        if (!is_object($widget)) {
            return array();
        }

        $raw = null;
        if (method_exists($widget, 'get_settings')) {
            $raw = $widget->get_settings('_css_classes');
        }
        if (($raw === null || $raw === '') && method_exists($widget, 'get_data')) {
            $data = $widget->get_data();
            if (is_array($data) && isset($data['settings']) && is_array($data['settings']) && isset($data['settings']['_css_classes'])) {
                $raw = $data['settings']['_css_classes'];
            }
        }

        return self::tokenize_classes($raw);
    }

    /**
     * @param mixed $raw
     * @return string[]
     */
    private static function tokenize_classes($raw) {
        if (is_array($raw)) {
            $parts = array();
            foreach ($raw as $value) {
                if (is_string($value) || is_numeric($value)) {
                    $parts[] = (string) $value;
                }
            }
            $raw = implode(' ', $parts);
        }

        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $split = preg_split('/\s+/', $raw);
        return is_array($split) ? $split : array();
    }

    /**
     * Editor canvas and the Elementor preview iframe must keep the table,
     * so authors can still see and edit it.
     *
     * @return bool
     */
    private static function in_elementor_editor_or_preview() {
        if (!class_exists('\Elementor\Plugin')) {
            return false;
        }

        $plugin = \Elementor\Plugin::$instance;
        if (!is_object($plugin)) {
            return false;
        }

        if (isset($plugin->editor) && is_object($plugin->editor) && method_exists($plugin->editor, 'is_edit_mode') && $plugin->editor->is_edit_mode()) {
            return true;
        }

        if (isset($plugin->preview) && is_object($plugin->preview) && method_exists($plugin->preview, 'is_preview_mode') && $plugin->preview->is_preview_mode()) {
            return true;
        }

        return false;
    }
}
